<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use Livewire\Volt\Volt;

/**
 * Batas ATAS jumlah peserta sebuah paket.
 *
 * Selama ini paket hanya punya minimal_peserta — batas bawah, jumlah minimum
 * supaya tripnya jadi berangkat. Batas atasnya tidak ada di mana pun, sehingga
 * yang menahan pendaftaran melewati daya angkut armada cuma ingatan admin —
 * dan ingatan itu bekerja paling buruk justru saat pendaftaran ramai.
 */
function paketBerkuota(int $kuota = 10): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Bromo',
        'category' => 'open_trip',
        'price' => 750000,
        'status' => 'terbit',
        'kuota' => $kuota,
    ]);
}

function daftarkan(TravelPackage $paket, int $jumlah, string $status = 'baru'): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => 'Peserta',
        'whatsapp' => '081234567890',
        'travel_package_id' => $paket->id,
        'jumlah_peserta' => $jumlah,
        'status' => $status,
    ]);
}

test('sisa kursi dihitung dari pendaftaran yang belum batal', function () {
    $paket = paketBerkuota(10);

    daftarkan($paket, 3);
    daftarkan($paket, 2, 'lunas');

    expect($paket->fresh()->sisa_kursi)->toBe(5);
});

test('pendaftaran yang batal mengembalikan kursinya', function () {
    $paket = paketBerkuota(10);

    daftarkan($paket, 4);
    daftarkan($paket, 3, 'batal');

    expect($paket->fresh()->sisa_kursi)->toBe(6);
});

test('kursi yang menunggu pembayaran tetap dihitung terpakai', function () {
    /*
     | Termasuk yang statusnya masih "baru". Kursi yang sedang ditunggu
     | pembayarannya tetap kursi yang tidak boleh dijual dua kali — menghitung
     | hanya yang sudah lunas berarti menjual kursi yang sama kepada orang
     | kedua selama orang pertama belum sempat mentransfer.
     */
    $paket = paketBerkuota(5);

    daftarkan($paket, 5, 'baru');

    expect($paket->fresh()->kursi_habis)->toBeTrue();
});

test('paket tanpa kuota tidak pernah penuh', function () {
    /*
     | Paket lama seluruhnya belum punya angka kuota. Memperlakukan null
     | sebagai nol akan menutup pendaftaran SEMUA paket yang sudah tayang pada
     | detik migrasinya jalan.
     */
    $paket = TravelPackage::create([
        'name' => 'Open Trip Lama', 'category' => 'open_trip',
        'price' => 500000, 'status' => 'terbit',
    ]);

    daftarkan($paket, 99);

    expect($paket->fresh()->sisa_kursi)->toBeNull()
        ->and($paket->fresh()->kursi_habis)->toBeFalse();
});

test('sisa kursi tidak pernah muncul di halaman publik', function () {
    /*
     | Kuotanya dipakai, angkanya tidak diumumkan.
     |
     | Ketersediaan dibicarakan lewat WhatsApp, dan angka yang muncul di layar
     | akan dibandingkan orang dengan yang dikatakan tim di percakapan — dua
     | angka yang berbeda melemahkan keduanya.
     |
     | Diperiksa pada tiga keadaan sekaligus (lega, tinggal sedikit, penuh)
     | karena yang paling mungkin merembes justru keadaan "tinggal sedikit":
     | itu yang paling menggoda untuk ditampilkan.
     */
    foreach ([[40, 2], [5, 3], [3, 3]] as [$kuota, $terisi]) {
        $paket = paketBerkuota($kuota);
        daftarkan($paket, $terisi);

        $isi = $this->get(route('paket-detail', $paket->uuid))->assertOk()->getContent();

        expect($isi)
            ->not->toContain('Tinggal')
            ->not->toContain('kursi tersisa')
            ->not->toContain('sisa kursi')
            // Angka sisanya sendiri tidak boleh terbaca di mana pun.
            ->not->toMatch('/\b'.($kuota - $terisi).' kursi\b/');
    }
});

test('paket penuh berhenti menerima pendaftaran tanpa mengumumkan sebabnya', function () {
    /*
     | Yang tetap dikerjakan: berhenti menerima pendaftaran saat armadanya
     | memang penuh, lalu mengantar orangnya ke WhatsApp. Menerima pendaftaran
     | yang tidak muat bukan menjaga peluang — ia menunda kabar buruknya sampai
     | orang itu sudah mentransfer.
     */
    $paket = paketBerkuota(3);
    daftarkan($paket, 3);

    $isi = $this->get(route('paket-detail', $paket->uuid))->assertOk()->getContent();

    expect($isi)
        ->toContain('Pendaftaran online untuk trip ini sedang kami tutup')
        ->not->toContain('Daftar Sekarang')
        // Tidak menyebut "habis" — itu ikut mengumumkan keadaan kuotanya.
        ->not->toContain('sudah habis');
});

test('paket yang masih lega tetap menampilkan tombol daftar', function () {
    $paket = paketBerkuota(20);
    daftarkan($paket, 2);

    $this->get(route('paket-detail', $paket->uuid))
        ->assertOk()
        ->assertSee('Daftar Sekarang');
});

test('pendaftaran melebihi sisa kursi ditolak di server', function () {
    /*
     | Tulisan "sisa 2 kursi" di layar sudah basi sejak digambar: dua orang
     | bisa membuka halaman yang sama pada menit yang sama dan melihat angka
     | yang sama. Yang menentukan bukan yang dilihat, melainkan yang tersisa
     | saat tombolnya ditekan.
     */
    $paket = paketBerkuota(5);
    daftarkan($paket, 3);

    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('jumlahPeserta', 3)
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('peserta', [['nama' => 'Budi Santoso'], ['nama' => 'Siti Aminah'], ['nama' => 'Joko Susilo']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasErrors('jumlahPeserta');

    // Pesannya mengantar ke WhatsApp, TIDAK menyebut berapa yang tersisa.
    $galat = Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('jumlahPeserta', 3)
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('peserta', [['nama' => 'Budi Santoso'], ['nama' => 'Siti Aminah'], ['nama' => 'Joko Susilo']])
        ->set('setuju', true)
        ->call('daftar')
        ->errors()->get('jumlahPeserta')[0];

    expect($galat)->toContain('WhatsApp')->not->toMatch('/\d+ kursi/');

    // Yang muat tetap boleh masuk.
    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('jumlahPeserta', 2)
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('peserta', [['nama' => 'Budi Santoso'], ['nama' => 'Siti Aminah']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors('jumlahPeserta');
});
