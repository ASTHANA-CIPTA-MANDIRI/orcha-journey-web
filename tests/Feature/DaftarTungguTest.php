<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\DaftarTunggu;
use App\Models\PaketWisata\TravelPackage;
use App\Support\LepaskanKursiTertahan;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

/**
 * Peminat trip yang kursinya sedang penuh.
 *
 * Halaman paket yang penuh dulu cuma mengarahkan ke WhatsApp, dan jawabannya
 * tidak tersimpan di mana pun: begitu percakapannya berakhir, peminat itu
 * hilang. Padahal merekalah yang paling mungkin langsung mengambil kursi yang
 * dilepas otomatis tiap jam.
 */
beforeEach(fn () => Mail::fake());

function paketPenuh(int $kuota = 2): TravelPackage
{
    $paket = TravelPackage::create([
        'name' => 'Open Trip Penuh', 'category' => 'open_trip',
        'price' => 1430000, 'status' => 'terbit', 'kuota' => $kuota,
    ]);

    PendaftaranOpenTrip::create([
        'nama' => 'Sudah Daftar', 'whatsapp' => '081200000000',
        'travel_package_id' => $paket->id, 'jumlah_peserta' => $kuota,
    ]);

    return $paket->fresh();
}

test('peminat bisa mendaftar antre dari halaman paket yang penuh', function () {
    $paket = paketPenuh();

    Volt::test('public.paket-wisata.detail', ['paket' => $paket])
        ->set('tungguNama', 'Budi Santoso')
        ->set('tungguWa', '081234567890')
        ->set('tungguJumlah', 2)
        ->call('antre')
        ->assertHasNoErrors()
        ->assertSet('tungguTerkirim', true);

    expect(DaftarTunggu::count())->toBe(1);
});

test('nomor yang sama tidak menempati dua tempat di antrean', function () {
    /*
     | Orang yang bertanya dua kali tidak boleh menempati dua tempat — dan
     | tanpa ini ia juga menerima dua kabar saat kursinya terbuka.
     |
     | Nomornya diseragamkan dulu, sebab kunci uniknya membandingkan teks apa
     | adanya: "0812…", "+62812…", dan "0812-3456-7890" akan terbaca sebagai
     | tiga orang berbeda.
     */
    $paket = paketPenuh();

    foreach (['081234567890', '+6281234567890', '0812-3456-7890'] as $nomor) {
        Volt::test('public.paket-wisata.detail', ['paket' => $paket])
            ->set('tungguNama', 'Budi')->set('tungguWa', $nomor)->set('tungguJumlah', 1)
            ->call('antre')->assertHasNoErrors();
    }

    expect(DaftarTunggu::count())->toBe(1);
});

test('yang menunggu dikabari saat kursinya dilepas', function () {
    /*
     | Inilah yang mengubah pelepasan kursi dari sekadar pembersihan jadi
     | penjualan: kursi yang kembali tersedia tidak berguna kalau tidak ada
     | yang tahu.
     */
    $paket = paketPenuh(5);

    $lama = PendaftaranOpenTrip::first();
    $lama->forceFill(['created_at' => now()->subDays(5)])->save();

    $antre = DaftarTunggu::create([
        'travel_package_id' => $paket->id, 'nama' => 'Menunggu',
        'whatsapp' => '081234567890', 'email' => 'tunggu@contoh.test', 'jumlah_peserta' => 2,
    ]);

    LepaskanKursiTertahan::jalankan();

    expect($antre->fresh()->dikabari_pada)->not->toBeNull();
});

test('rombongan yang lebih besar daripada kursi terbuka tidak dikabari', function () {
    /*
     | Mengabarinya berarti menawarkan sesuatu yang belum tentu bisa ditepati —
     | dan kekecewaan itu lebih mahal daripada satu kabar yang tidak dikirim.
     */
    $paket = paketPenuh(3);

    PendaftaranOpenTrip::first()->forceFill(['created_at' => now()->subDays(5)])->save();

    $kebesaran = DaftarTunggu::create([
        'travel_package_id' => $paket->id, 'nama' => 'Rombongan Besar',
        'whatsapp' => '081234567890', 'email' => 'besar@contoh.test', 'jumlah_peserta' => 20,
    ]);

    LepaskanKursiTertahan::jalankan();

    expect($kebesaran->fresh()->dikabari_pada)->toBeNull();
});

test('yang dikabari hanya sebanyak kursi yang terbuka', function () {
    /*
     | Mengabari seluruh antrean untuk dua kursi membuat sebagian besar dari
     | mereka datang ke kursi yang sudah diambil orang lain.
     */
    $paket = paketPenuh(2);

    PendaftaranOpenTrip::first()->forceFill(['created_at' => now()->subDays(5)])->save();

    foreach (range(1, 5) as $i) {
        DaftarTunggu::create([
            'travel_package_id' => $paket->id, 'nama' => "Antre $i",
            'whatsapp' => '08120000000'.$i, 'email' => "a$i@contoh.test", 'jumlah_peserta' => 1,
        ]);
    }

    LepaskanKursiTertahan::jalankan();

    // Dua kursi terbuka → dua orang dikabari, tiga sisanya tetap menunggu.
    expect(DaftarTunggu::whereNotNull('dikabari_pada')->count())->toBe(2);
});

test('yang paling lama menunggu dikabari lebih dulu', function () {
    $paket = paketPenuh(1);

    PendaftaranOpenTrip::first()->forceFill(['created_at' => now()->subDays(5)])->save();

    $duluan = DaftarTunggu::create([
        'travel_package_id' => $paket->id, 'nama' => 'Duluan',
        'whatsapp' => '081200000001', 'email' => 'duluan@contoh.test', 'jumlah_peserta' => 1,
    ]);
    $duluan->forceFill(['created_at' => now()->subDays(3)])->save();

    DaftarTunggu::create([
        'travel_package_id' => $paket->id, 'nama' => 'Belakangan',
        'whatsapp' => '081200000002', 'email' => 'belakangan@contoh.test', 'jumlah_peserta' => 1,
    ]);

    LepaskanKursiTertahan::jalankan();

    expect($duluan->fresh()->dikabari_pada)->not->toBeNull()
        ->and(DaftarTunggu::where('nama', 'Belakangan')->first()->dikabari_pada)->toBeNull();
});
