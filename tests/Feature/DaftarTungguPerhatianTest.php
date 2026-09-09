<?php

use App\Models\JejakAudit;
use App\Models\PaketWisata\DaftarTunggu;
use App\Models\PaketWisata\TravelPackage;
use Illuminate\Support\Facades\Schema;

/**
 * Hitungan daftar tunggu untuk penanda di bilah samping lemon.
 *
 * Yang dijadikan ANGKA penandanya bukan seluruh antrean, melainkan yang
 * menunggu TANPA SUREL.
 *
 * Antrean yang panjang adalah keadaan yang wajar dan tidak menuntut apa pun —
 * sistem sudah mengabari mereka sendiri begitu ada kursi terbuka. Penanda yang
 * menghitung seluruhnya akan menyala terus tanpa pernah bisa dinolkan, dan
 * penanda yang tidak pernah padam berhenti dibaca orang.
 */
/**
 * Kepala permintaan bagi berkas INI, dengan namanya sendiri.
 *
 * Sempat memakai kepalaTungguPerhatian() milik DaftarTungguTest.php, dan itu keliru
 * dua kali. Menyalin deklarasinya membuat SELURUH suite berhenti dengan galat
 * fatal — Pest memuat semua berkas uji ke ruang nama yang sama. Tetapi
 * membuangnya begitu saja dan menumpang deklarasi berkas lain membuat berkas
 * ini tidak bisa dijalankan sendirian: `php artisan test tests/Feature/
 * DaftarTungguPerhatianTest.php` gagal dengan "Call to undefined function",
 * dan menjalankan satu berkas uji adalah hal yang dikerjakan orang tiap hari.
 *
 * Nama sendiri menyelesaikan keduanya sekaligus.
 */
function kepalaTungguPerhatian(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-tunggu');

    return ['X-Orcha-Key' => 'kunci-uji-tunggu', 'Accept' => 'application/json'];
}

function paketTunggu(): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Uji', 'category' => 'open_trip',
        'price' => 1000000, 'status' => 'terbit',
    ]);
}

function antre(array $ubah = []): DaftarTunggu
{
    return DaftarTunggu::create(array_merge([
        'travel_package_id' => paketTunggu()->id,
        'nama' => 'Peminat',
        'whatsapp' => '0812'.rand(10000000, 99999999),
        'email' => 'peminat'.uniqid().'@contoh.test',
        'jumlah_peserta' => 2,
    ], $ubah));
}

test('yang dihitung hanya yang kursinya terbuka, tanpa surel, belum dihubungi', function () {
    /*
     | Ketiga syarat perlu sekaligus. Versi pertama menghitung yang BELUM
     | dikabari dan tanpa surel — dan itu salah orang: selama kursinya belum
     | terbuka tidak ada apa pun yang bisa dikabarkan.
     */
    antre(['email' => null]);                                        // tanpa surel, kursi BELUM terbuka
    antre(['dikabari_pada' => now()]);                               // kursi terbuka, punya surel
    antre(['email' => null, 'dikabari_pada' => now()]);              // ← inilah yang dihitung
    antre(['email' => '', 'dikabari_pada' => now()]);                // ← dan ini
    antre(['email' => null, 'dikabari_pada' => now(), 'dihubungi_pada' => now()]); // sudah ditelepon

    $data = $this->getJson('/api/v1/daftar-tunggu/perhatian', kepalaTungguPerhatian())
        ->assertOk()
        ->json('data');

    expect($data['perlu_dihubungi'])->toBe(2);
});

test('yang tanpa surel tetapi kursinya belum terbuka TIDAK dihitung', function () {
    /*
     | Bug yang ditemukan lewat satu pertanyaan: "kalau mengabari lewat WA
     | bagaimana sistem sudah tahu?".
     |
     | Orang ini cuma menunggu. Tidak ada kursi untuknya, jadi tidak ada yang
     | bisa dikabarkan — menghitungnya membuat penandanya menyala terus tanpa
     | pernah bisa dinolkan, dan penanda yang tidak pernah padam berhenti
     | dibaca orang.
     */
    antre(['email' => null]);

    expect($this->getJson('/api/v1/daftar-tunggu/perhatian', kepalaTungguPerhatian())->json('data.perlu_dihubungi'))
        ->toBe(0);
});

test('yang sudah dihubungi keluar dari hitungan', function () {
    // Inilah yang membuat angkanya bisa turun tanpa mengeluarkan orangnya dari
    // antrean — ia bisa saja menjawab "nanti saya kabari lagi".
    $satu = antre(['email' => null, 'dikabari_pada' => now()]);

    expect($this->getJson('/api/v1/daftar-tunggu/perhatian', kepalaTungguPerhatian())->json('data.perlu_dihubungi'))
        ->toBe(1);

    $this->postJson("/api/v1/daftar-tunggu/{$satu->id}/dihubungi", [], kepalaTungguPerhatian())->assertOk();

    expect($this->getJson('/api/v1/daftar-tunggu/perhatian', kepalaTungguPerhatian())->json('data.perlu_dihubungi'))
        ->toBe(0);
});

test('menandai dihubungi TIDAK mengeluarkannya dari antrean', function () {
    /*
     | Ia bisa saja menjawab "nanti saya kabari lagi". Mengeluarkannya berarti
     | kehilangan jejaknya, dan kursi yang terbuka berikutnya tidak lagi
     | menawarkannya kepada orang yang sudah menyatakan minat.
     */
    $satu = antre(['email' => null, 'dikabari_pada' => now()]);

    $this->postJson("/api/v1/daftar-tunggu/{$satu->id}/dihubungi", [], kepalaTungguPerhatian());

    expect(DaftarTunggu::find($satu->id))->not->toBeNull()
        ->and($satu->fresh()->dihubungi_pada)->not->toBeNull();
});

test('siapa yang menghubungi ikut tercatat', function () {
    // Antrean yang panjang diurus lebih dari satu orang, dan "sudah dihubungi"
    // tanpa nama membuat dua admin sama-sama mengira yang lain mengerjakannya.
    $satu = antre(['email' => null, 'dikabari_pada' => now()]);

    $this->postJson("/api/v1/daftar-tunggu/{$satu->id}/dihubungi", [], kepalaTungguPerhatian());

    expect($satu->fresh()->dihubungi_oleh)->not->toBeNull();
});

test('penandaannya masuk jejak audit', function () {
    $satu = antre(['email' => null, 'dikabari_pada' => now()]);

    $this->postJson("/api/v1/daftar-tunggu/{$satu->id}/dihubungi", [], kepalaTungguPerhatian());

    expect(JejakAudit::where('aksi', 'hubungi daftar tunggu')->exists())->toBeTrue();
});

test('antrean kosong menjawab nol, bukan galat', function () {
    // Penanda ini dipanggil di TIAP halaman admin lemon.
    $data = $this->getJson('/api/v1/daftar-tunggu/perhatian', kepalaTungguPerhatian())->json('data');

    expect($data)->toBe(['perlu_dihubungi' => 0, 'menunggu' => 0, 'dikabari' => 0]);
});

test('menandai dihubungi tertutup tanpa kunci API', function () {
    $satu = antre();

    $this->postJson("/api/v1/daftar-tunggu/{$satu->id}/dihubungi", [],
        ['Accept' => 'application/json'])->assertStatus(401);
});

test('tanpa kunci API, jalurnya tertutup', function () {
    $this->getJson('/api/v1/daftar-tunggu/perhatian', ['Accept' => 'application/json'])
        ->assertStatus(401);
});

test('"perhatian" tidak terbaca sebagai nomor antrean', function () {
    /*
     | Jebakan yang sudah pernah kena dua kali di aplikasi ini — sekali di
     | jalur pembayaran, sekali di rute halaman lemon. Rute berparameter yang
     | terdaftar lebih dulu menelan rute bernama.
     */
    antre();

    $this->getJson('/api/v1/daftar-tunggu/perhatian', kepalaTungguPerhatian())
        ->assertOk()
        // 'perlu_dihubungi', bukan 'tanpa_email': namanya sempat berubah selama
        // dikerjakan, dan baris ini tertinggal memakai nama lama. Kunci yang
        // benar-benar dibaca sidebar lemon adalah yang di bawah — kalau salah,
        // yang jatuh bukan uji ini melainkan lencana di menu admin.
        ->assertJsonStructure(['data' => ['menunggu', 'perlu_dihubungi', 'dikabari']]);
});

/* ---------------------------- PAGINASI ---------------------------- */

test('metanya berbentuk sama dengan daftar Orcha lain', function () {
    /*
     | Bentuk metanya sempat disalin tangan di controller ini — persis yang
     | dilarang komentar di halamanDipeta(). Penomoran halaman di lemon
     | membacanya apa adanya, jadi selisih sekecil apa pun antara dua bentuk
     | langsung terasa di layar admin tanpa satu pun galat yang menunjukkannya.
     */
    antre();

    $meta = $this->getJson('/api/v1/daftar-tunggu', kepalaTungguPerhatian())
        ->assertOk()
        ->json('meta');

    expect($meta)->toHaveKeys(['halaman', 'per_halaman', 'total', 'halaman_terakhir', 'paket']);
});

test('halaman kedua berisi orang yang berbeda', function () {
    $paket = paketTunggu();

    foreach (range(1, 5) as $i) {
        DaftarTunggu::create([
            'travel_package_id' => $paket->id,
            'nama' => 'Peminat '.$i,
            'whatsapp' => '08120000000'.$i,
            'jumlah_peserta' => 1,
        ]);
    }

    $satu = $this->getJson('/api/v1/daftar-tunggu?per_halaman=2', kepalaTungguPerhatian())->json();
    $dua = $this->getJson('/api/v1/daftar-tunggu?per_halaman=2&page=2', kepalaTungguPerhatian())->json();

    expect($satu['data'])->toHaveCount(2)
        ->and($satu['meta']['halaman_terakhir'])->toBe(3)
        ->and($dua['meta']['halaman'])->toBe(2)
        // Yang paling penting: isinya benar-benar berbeda, bukan halaman satu
        // yang digambar ulang.
        ->and(collect($dua['data'])->pluck('id')->intersect(collect($satu['data'])->pluck('id')))
        ->toBeEmpty();
});

test('meta daftar membawa angka yang sama dengan penanda menu', function () {
    /*
     | Supaya layar bisa menyebut keduanya berdampingan. Tanpa itu admin
     | melihat "1 menunggu kursi" di layar sementara penanda di menu kosong,
     | dan menyimpulkan keduanya tidak sinkron.
     */
    antre(['email' => null, 'dikabari_pada' => now()]);
    antre();

    $meta = $this->getJson('/api/v1/daftar-tunggu', kepalaTungguPerhatian())->json('meta');
    $penanda = $this->getJson('/api/v1/daftar-tunggu/perhatian', kepalaTungguPerhatian())->json('data');

    expect($meta['perlu_dihubungi'])->toBe(1)
        // Angkanya harus PERSIS sama; dua sumber untuk satu angka akan
        // berbeda suatu saat.
        ->and($meta['perlu_dihubungi'])->toBe($penanda['perlu_dihubungi'])
        ->and($meta['total'])->toBe(2);
});

test('saringan dan penomoran halaman tidak mengubah angka perlu dihubungi', function () {
    /*
     | Angkanya dibandingkan dengan penanda di menu, yang menghitung seluruh
     | antrean. Menghitungnya dari baris yang sedang tampil membuat keduanya
     | berbeda setiap kali admin menyaring — persis kebingungan yang hendak
     | dihilangkan.
     */
    antre(['email' => null, 'dikabari_pada' => now()]);
    antre(['email' => null, 'dikabari_pada' => now()]);

    $meta = $this->getJson('/api/v1/daftar-tunggu?per_halaman=1', kepalaTungguPerhatian())->json('meta');

    expect($meta['perlu_dihubungi'])->toBe(2)
        ->and($meta['per_halaman'])->toBe(1);
});

test('daftarnya tetap tampil walau hitungan lencananya gagal', function () {
    /*
     | Terjadi sungguhan: migrasi dihubungi_pada belum jalan di sebuah
     | lingkungan, dan SELURUH layar Daftar Tunggu mati dengan kode 500 —
     | padahal daftar pesertanya sendiri baik-baik saja. Yang gagal cuma angka
     | di pojok.
     |
     | Hitungan untuk sebuah lencana tidak pantas menjatuhkan isi halamannya.
     | Ditiru di sini dengan membuang kolomnya.
     */
    antre();

    Schema::table('tbl_daftar_tunggu',
        fn ($t) => $t->dropColumn('dihubungi_pada'));

    $jawab = $this->getJson('/api/v1/daftar-tunggu', kepalaTungguPerhatian())->assertOk();

    expect($jawab->json('data'))->toHaveCount(1)
        // Angkanya menyerah jadi nol, daftarnya tetap utuh.
        ->and($jawab->json('meta.perlu_dihubungi'))->toBe(0);
});

test('penanda menu juga tidak ikut roboh saat kolomnya belum ada', function () {
    // Penanda ini dipanggil di TIAP halaman admin lemon; robohnya berarti
    // seluruh admin ikut mati, bukan cuma satu layar.
    antre();

    Schema::table('tbl_daftar_tunggu',
        fn ($t) => $t->dropColumn('dihubungi_pada'));

    $this->getJson('/api/v1/daftar-tunggu/perhatian', kepalaTungguPerhatian())
        ->assertOk()
        ->assertJsonPath('data.perlu_dihubungi', 0);
});

test('daftar pilihan paket ikut di meta, tidak hilang oleh pembungkus bersama', function () {
    // Dipakai penyaring di layar admin. Sempat hilang saat metanya dipindahkan
    // ke pembungkus bersama yang tidak menerima keterangan tambahan.
    antre();

    $meta = $this->getJson('/api/v1/daftar-tunggu', kepalaTungguPerhatian())->json('meta');

    expect($meta['paket'])->not->toBeEmpty();
});
