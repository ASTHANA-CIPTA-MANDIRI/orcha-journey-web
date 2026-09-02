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

test('tiap isian antrean punya label dan tanda wajib', function () {
    /*
     | Sebelumnya ketiganya hanya berisi teks bayangan, dan kotak angka di
     | sebelah nomor tidak menyebut apa-apa — yang mengisinya harus menebak
     | apakah itu jumlah orang, umur, atau nomor urut. Placeholder juga hilang
     | begitu diketik, jadi orang yang berhenti sejenak kehilangan
     | satu-satunya keterangan yang ada.
     */
    $isi = Volt::test('public.paket-wisata.detail', ['paket' => paketPenuh()])->html();

    expect($isi)
        // Keduanya DIPENDEKKAN karena kini berdampingan dalam satu baris:
        // "Nomor WhatsApp" pecah dua baris di kolom selebar ini dan membuat
        // kotaknya tidak lagi sejajar dengan kotak di sebelahnya.
        ->toContain('WhatsApp')
        ->toContain('Orang');

    /*
     | Tiga isian, tiga bintang — dan ketiganya memang "required" di validator.
     |
     | Tanda bintang yang dipasang pada isian yang sebenarnya opsional membuat
     | seluruh tanda bintang di situs ini berhenti dipercaya.
     */
    expect(substr_count($isi, '(wajib diisi)'))->toBe(3);
});

test('ketiga isiannya benar-benar ditolak kalau kosong', function () {
    // Yang menjaga tanda bintang di atas tidak berbohong.
    Volt::test('public.paket-wisata.detail', ['paket' => paketPenuh()])
        ->call('antre')
        ->assertHasErrors(['tungguNama', 'tungguWa']);
});

test('kelas tata letak kartu antrean benar-benar ada di css terbangun', function () {
    /*
     | Tailwind hanya membuat kelas yang DITEMUKANNYA di sumber saat build.
     | Kelas yang belum pernah dipakai di proyek ini — col-span-2 salah satunya
     | — tidak ada di berkas CSS, dan memakainya tidak menghasilkan galat apa
     | pun: elemennya cuma tidak tertata.
     |
     | Itu yang terjadi di kartu ini. grid-cols-3 dengan dua isian dan
     | col-span-2 yang tidak ada menyisakan SATU KOLOM KOSONG di kanan, dan
     | yang melihatnya menyangka tata letaknya yang salah.
     |
     | Kelasnya DIBACA DARI TAMPILANNYA, bukan ditulis ulang di sini. Daftar
     | yang ditulis tangan tetap hijau walau tampilannya diubah memakai kelas
     | lain — persis kelemahan versi pertama uji ini, yang lolos saat bugnya
     | sengaja ditanam kembali.
     */
    $berkas = glob(public_path('build/assets/*.css'));

    if ($berkas === []) {
        $this->markTestSkipped('Belum ada hasil build; tidak ada yang bisa diperiksa.');
    }

    $css = collect($berkas)->map(fn ($f) => file_get_contents($f))->implode("\n");

    $blade = file_get_contents(
        resource_path('views/livewire/public/paket-wisata/detail.blade.php')
    );

    $awal = strpos($blade, 'bg-white/70 rounded-2xl');
    $potongan = substr($blade, $awal, strpos($blade, '@endif', $awal) - $awal);

    preg_match_all('/class="([^"]*)"/', $potongan, $cocok);

    /*
     | Hanya utilitas TATA LETAK yang diperiksa.
     |
     | Kelas warna atau tipografi yang hilang cuma membuat tampilannya kurang
     | cantik; kelas tata letak yang hilang membuat isinya berantakan atau
     | menyisakan ruang kosong — dan itu yang tidak terlihat sebagai galat.
     */
    $pola = '/^(grid-cols-|col-span-|flex-|w-\d|items-|gap-|grid$|flex$|shrink-)/';

    $hilang = collect($cocok[1])
        ->flatMap(fn ($c) => preg_split('/\s+/', trim($c)))
        ->filter(fn ($k) => $k !== '' && preg_match($pola, $k))
        ->unique()
        ->reject(fn ($k) => str_contains($css, '.'.$k))
        ->values()
        ->all();

    expect($hilang)->toBe([], 'Kelas tata letak ini dipakai kartu daftar tunggu '
        .'tetapi TIDAK ADA di CSS terbangun, jadi ia tidak berpengaruh apa pun: '
        .implode(', ', $hilang));
});

/* ---------------------------- LEWAT API ---------------------------- */

function kepalaTunggu(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-tunggu');

    return ['X-Orcha-Key' => 'kunci-uji-tunggu', 'Accept' => 'application/json'];
}

test('daftar tunggu bisa dibaca lewat api', function () {
    /*
     | Uji ini ADA karena ketiadaannya sudah berbiaya sekali.
     |
     | Seluruh perilaku antrean diuji lewat model dan perintahnya, tetapi
     | endpoint-nya sendiri tidak pernah dipanggil satu kali pun — dan ia
     | memanggil perHalaman() yang tidak ada di kelas induknya. Ujinya hijau
     | semua, sementara halaman admin menjawab 500 pada pembukaan pertama.
     |
     | Lapisan yang tidak pernah dipanggil dalam uji adalah lapisan yang
     | ditemukan penggunanya lebih dulu.
     */
    $paket = paketPenuh();

    DaftarTunggu::create([
        'travel_package_id' => $paket->id, 'nama' => 'Budi Menunggu',
        'whatsapp' => '081234567890', 'jumlah_peserta' => 2,
    ]);

    $balasan = $this->getJson('/api/v1/daftar-tunggu', kepalaTunggu())->assertOk();

    expect($balasan->json('data.0.nama'))->toBe('Budi Menunggu')
        ->and($balasan->json('data.0.jumlah_peserta'))->toBe(2)
        ->and($balasan->json('meta.total'))->toBe(1)
        // Dipakai penyaring trip di layar admin.
        ->and($balasan->json('meta.paket'))->not->toBeEmpty();
});

test('yang belum dikabari muncul lebih dulu', function () {
    // Urutan yang dipakai admin bekerja: yang di atas masih menunggu jawaban,
    // bukan yang sudah selesai diurus.
    $paket = paketPenuh();

    DaftarTunggu::create([
        'travel_package_id' => $paket->id, 'nama' => 'Sudah Dikabari',
        'whatsapp' => '081200000001', 'jumlah_peserta' => 1, 'dikabari_pada' => now(),
    ]);

    DaftarTunggu::create([
        'travel_package_id' => $paket->id, 'nama' => 'Masih Menunggu',
        'whatsapp' => '081200000002', 'jumlah_peserta' => 1,
    ]);

    $balasan = $this->getJson('/api/v1/daftar-tunggu', kepalaTunggu())->assertOk();

    expect($balasan->json('data.0.nama'))->toBe('Masih Menunggu');
});

test('antrean bisa dikeluarkan lewat api', function () {
    $paket = paketPenuh();

    $antre = DaftarTunggu::create([
        'travel_package_id' => $paket->id, 'nama' => 'Batal Ikut',
        'whatsapp' => '081234567890', 'jumlah_peserta' => 1,
    ]);

    $this->deleteJson("/api/v1/daftar-tunggu/{$antre->id}", [], kepalaTunggu())->assertOk();

    expect(DaftarTunggu::count())->toBe(0)
        // Perubahannya tercatat seperti perubahan lain.
        ->and(App\Models\JejakAudit::where('aksi', 'keluarkan dari daftar tunggu')->exists())->toBeTrue();
});

test('tanpa kunci api ditolak', function () {
    $this->getJson('/api/v1/daftar-tunggu')->assertUnauthorized();
});
