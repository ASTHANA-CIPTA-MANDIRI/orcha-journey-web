<?php

use App\Models\PaketWisata\TravelPackage;
use App\Support\PromoRombongan;
use App\Support\RincianBiaya;

/**
 * Potongan menurut jumlah peserta.
 *
 * Berlaku DI ATAS harga yang sudah berlaku — termasuk harga early bird. Paket
 * yang sudah turun dari 1.700.000 ke 1.430.000 dihitung promo rombongannya
 * dari 1.430.000.
 */
function paketPromo(): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'original_price' => 1700000,
        'price' => 1430000,          // harga early bird
        'status' => 'terbit',
        // Ditandai ikut promo. Bawaannya MATI — lihat ujinya di bawah.
        'promo_rombongan' => true,
    ]);
}

test('di bawah tingkat pertama tidak ada potongan', function () {
    $b = RincianBiaya::untuk(paketPromo(), 4);

    expect($b['total'])->toBe((float) (4 * 1430000))
        ->and($b['promo_label'])->toBeNull();
});

test('lima orang mendapat potongan persen dari harga early bird', function () {
    /*
     | Dihitung dari 1.430.000, BUKAN dari 1.700.000. Promo rombongan menumpang
     | di atas harga yang sudah berlaku — kalau dihitung dari harga normal,
     | pelanggan early bird justru dirugikan karena promonya jadi lebih kecil.
     */
    $b = RincianBiaya::untuk(paketPromo(), 5);

    $sebelum = 5 * 1430000;

    expect($b['total_sebelum_promo'])->toBe((float) $sebelum)
        ->and($b['total'])->toBe((float) round($sebelum * 0.95))
        ->and($b['promo_label'])->toContain('5 orang');
});

test('sepuluh orang membayar sembilan', function () {
    /*
     | "Gratis 1 dari 10" secara hitungan sama dengan potongan 10%, tetapi
     | disebut sebagai gratis satu orang — itu yang dipahami dan diceritakan
     | ulang orang ke temannya.
     */
    $b = RincianBiaya::untuk(paketPromo(), 10);

    expect($b['promo_gratis_orang'])->toBe(1)
        ->and($b['promo_orang_dibayar'])->toBe(9)
        ->and($b['total'])->toBe((float) (9 * 1430000))
        ->and($b['promo_potongan'])->toBe(1430000);
});

test('tingkat tidak bertumpuk — dua belas orang memakai tingkat sepuluh saja', function () {
    /*
     | Bertumpuk terdengar lebih murah hati, tetapi angkanya jadi sulit
     | dijelaskan di WhatsApp dan lebih sulit lagi diperiksa saat ada yang
     | protes.
     */
    $b = RincianBiaya::untuk(paketPromo(), 12);

    // 12 orang, 1 gratis, 11 dibayar — tanpa potongan persen tingkat 5.
    expect($b['promo_orang_dibayar'])->toBe(11)
        ->and($b['total'])->toBe((float) (11 * 1430000));
});

test('ajakan menyebut berapa orang lagi yang kurang', function () {
    /*
     | Ini yang mengubah promo dari keterangan jadi dorongan: orang yang sedang
     | mengisi 4 peserta perlu tahu bahwa satu orang lagi mengubah harganya.
     */
    expect(PromoRombongan::ajakanBerikutnya(4))->toContain('1 orang lagi')
        ->and(PromoRombongan::ajakanBerikutnya(7))->toContain('3 orang lagi')
        // Sudah di tingkat tertinggi: tidak ada yang perlu diajak lagi.
        ->and(PromoRombongan::ajakanBerikutnya(15))->toBeNull();
});

test('uang muka ikut dihitung dari total setelah promo', function () {
    // Kalau DP dihitung dari total sebelum promo, pelanggan diminta
    // mentransfer lebih banyak daripada haknya — dan itu ketahuan justru saat
    // pelunasan, ketika sisanya jadi minus.
    $b = RincianBiaya::untuk(paketPromo(), 10);

    expect($b['dp'])->toBe(round($b['total'] * $b['dp_persen'] / 100));
});

test('paket tanpa harga tidak mengarang promo', function () {
    $tanpaHarga = TravelPackage::create([
        'name' => 'Private Trip', 'category' => 'private_trip', 'status' => 'terbit',
    ]);

    expect(RincianBiaya::untuk($tanpaHarga, 10))->toBe([]);
});

test('promo tampil hidup di formulir saat peserta ditambah', function () {
    /*
     | Promo yang cuma diumumkan di halaman paket hanya menguntungkan rombongan
     | yang kebetulan sudah ramai. Yang dituju justru yang masih empat orang —
     | dan ia baru bergerak kalau tahu satu orang lagi mengubah harganya.
     */
    $paket = paketPromo();

    $halaman = Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid);

    // Empat orang: belum dapat, tetapi diberi tahu kurang berapa.
    $halaman->set('jumlahPeserta', 4)
        ->assertSee('1 orang lagi')
        ->assertDontSee('gratis');

    // Sepuluh orang: bentuknya disebut apa adanya, bukan sebagai persen.
    $halaman->set('jumlahPeserta', 10)
        ->assertSee('gratis')
        ->assertSee('dibayar 9 orang');
});

/* ------------------------ DIKELOLA DARI ADMIN ------------------------ */

use App\Models\PaketWisata\PromoRombonganTingkat;

function kepalaPromo(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-promo');

    return ['X-Orcha-Key' => 'kunci-uji-promo', 'Accept' => 'application/json'];
}

test('tingkat yang diubah admin langsung berlaku di harga', function () {
    /*
     | Inti perubahannya. Selama angkanya di berkas config, mengubah "ajak 5
     | dapat 5%" jadi 10% berarti menunggu ada yang menyunting kode dan
     | menaikkannya ke server — padahal justru angka inilah yang paling sering
     | diutak-atik.
     */
    PromoRombonganTingkat::where('min_peserta', 5)->update(['potongan_persen' => 10]);

    $b = RincianBiaya::untuk(paketPromo(), 5);

    expect($b['total'])->toBe((float) round(5 * 1430000 * 0.90));
});

test('tingkat yang dimatikan tidak lagi berlaku', function () {
    // Promo musiman sering dihidupkan lagi tahun berikutnya dengan angka yang
    // sama — jadi dimatikan, bukan dihapus.
    PromoRombonganTingkat::where('min_peserta', 10)->update(['aktif' => false]);

    $b = RincianBiaya::untuk(paketPromo(), 10);

    // Turun ke tingkat 5 yang masih hidup, bukan kehilangan promo sama sekali.
    expect($b['promo_gratis_orang'])->toBe(0)
        ->and($b['total'])->toBe((float) round(10 * 1430000 * 0.95));
});

test('tabel kosong jatuh ke tingkat bawaan, bukan kehilangan promo', function () {
    /*
     | Saat migrasinya belum jalan di sebuah lingkungan, promo yang sedang
     | berjalan tetap berlaku alih-alih mati diam-diam — dan yang menyadarinya
     | pelanggan, bukan kita.
     */
    PromoRombonganTingkat::query()->delete();

    $b = RincianBiaya::untuk(paketPromo(), 10);

    expect($b['promo_gratis_orang'])->toBe(1);
});

test('dua tingkat dengan syarat sama ditolak', function () {
    /*
     | "Tingkat terbaik" jadi tidak menentu — yang menang tergantung urutan
     | baris, dan itu berubah sendiri saat salah satunya disunting.
     */
    $this->postJson('/api/v1/promo-rombongan', [
        'min_peserta' => 5, 'potongan_persen' => 8, 'label' => 'Duplikat',
    ], kepalaPromo())
        ->assertStatus(422);
});

test('tingkat tanpa keuntungan apa pun ditolak', function () {
    /*
     | Ia tersimpan rapi dan tampil di daftar, tetapi tidak mengubah harga
     | sepeser pun — sementara ia MENGGESER tingkat di bawahnya. Rombongan yang
     | seharusnya dapat gratis 1 berhenti di tingkat kosong ini, dan tidak ada
     | yang tahu sampai ada pelanggan menghitung sendiri.
     */
    $this->postJson('/api/v1/promo-rombongan', [
        'min_peserta' => 20, 'potongan_persen' => 0, 'gratis_orang' => 0, 'label' => 'Kosong',
    ], kepalaPromo())
        ->assertStatus(422);
});

test('tingkat baru dari admin ikut dihitung', function () {
    $this->postJson('/api/v1/promo-rombongan', [
        'min_peserta' => 20, 'gratis_orang' => 2, 'label' => 'Ajak 20 — gratis 2 orang',
    ], kepalaPromo())
        ->assertCreated();

    $b = RincianBiaya::untuk(paketPromo(), 20);

    expect($b['promo_gratis_orang'])->toBe(2)
        ->and($b['promo_orang_dibayar'])->toBe(18);
});

/* ------------------------ PAKET MANA YANG IKUT ------------------------ */

test('paket bawaan TIDAK ikut promo', function () {
    /*
     | Bawaannya mati, dan itu disengaja.
     |
     | Kalau bawaannya hidup, seluruh paket yang sudah ada mendadak memberi
     | potongan rombongan begitu migrasinya jalan — tanpa satu pun keputusan
     | diambil, dan yang menyadarinya belakangan adalah laporan keuntungan.
     |
     | Mati lebih dulu berarti promonya tidak melakukan apa-apa sampai ada yang
     | sengaja menyalakannya: keadaan yang terlihat dan bisa diperbaiki, bukan
     | uang yang sudah telanjur keluar.
     */
    $biasa = TravelPackage::create([
        'name' => 'Trip Tanpa Promo', 'category' => 'open_trip',
        'price' => 1430000, 'status' => 'terbit',
    ]);

    expect($biasa->promo_rombongan)->toBeFalse();

    $b = RincianBiaya::untuk($biasa, 10);

    expect($b['total'])->toBe((float) (10 * 1430000))
        ->and($b['promo_gratis_orang'])->toBe(0)
        ->and($b['promo_label'])->toBeNull();
});

test('paket yang tidak ikut juga tidak menampilkan ajakan', function () {
    /*
     | Mengajak menambah teman untuk potongan yang tidak akan datang justru
     | membuat orang merasa dibohongi saat totalnya tidak berubah — lebih buruk
     | daripada tidak menawarkan apa-apa.
     */
    $biasa = TravelPackage::create([
        'name' => 'Trip Tanpa Promo', 'category' => 'open_trip',
        'price' => 1430000, 'status' => 'terbit',
    ]);

    expect(RincianBiaya::untuk($biasa, 4)['promo_ajakan'])->toBeNull();
});

test('menyalakan penanda membuat promonya langsung berlaku', function () {
    $paket = TravelPackage::create([
        'name' => 'Trip Uji', 'category' => 'open_trip',
        'price' => 1430000, 'status' => 'terbit',
    ]);

    expect(RincianBiaya::untuk($paket, 10)['promo_gratis_orang'])->toBe(0);

    $paket->update(['promo_rombongan' => true]);

    expect(RincianBiaya::untuk($paket->fresh(), 10)['promo_gratis_orang'])->toBe(1);
});

/* --------------------- TULISANNYA DIRAKIT SENDIRI --------------------- */

test('tulisan dan ajakan dirakit dari angka yang berlaku', function () {
    /*
     | Dulu keduanya diketik admin, dan angkanya bisa berbeda dari yang
     | benar-benar berlaku: mengubah potongan 5% jadi 7% lalu lupa menyunting
     | kalimat "hemat 5%" di sebelahnya adalah satu langkah yang mudah
     | terlewat. Yang dibaca pelanggan angka yang salah, yang ditagih angka
     | yang benar.
     */
    $persen = PromoRombonganTingkat::create([
        'min_peserta' => 7, 'potongan_persen' => 8,
    ]);

    $gratis = PromoRombonganTingkat::create([
        'min_peserta' => 15, 'gratis_orang' => 2,
    ]);

    expect($persen->label)->toBe('Ajak 7 orang — hemat 8%')
        ->and($persen->ajakan)->toBe('Ajak 7 orang, hemat 8% untuk seluruh rombongan.')
        ->and($gratis->label)->toBe('Ajak 15 orang — gratis 2 orang')
        ->and($gratis->ajakan)->toBe('Ajak 15 orang, 2 orang gratis.');
});

test('tulisannya ikut berubah saat angkanya disunting', function () {
    // Inti perbaikannya: kalimat dan angka tidak bisa lagi berbeda.
    $t = PromoRombonganTingkat::create(['min_peserta' => 7, 'potongan_persen' => 5]);

    $t->update(['potongan_persen' => 9]);

    expect($t->fresh()->label)->toBe('Ajak 7 orang — hemat 9%');
});

test('tulisan yang dikirim pemanggil diabaikan, bukan dipakai', function () {
    /*
     | Dibiarkan lewat validasi supaya pemanggil lama tidak mendadak gagal —
     | tetapi nilainya tidak boleh menang atas angka yang berlaku.
     */
    $t = PromoRombonganTingkat::create([
        'min_peserta' => 7, 'potongan_persen' => 5,
        'label' => 'Tulisan karangan sendiri',
        'ajakan' => 'Ajakan karangan sendiri',
    ]);

    expect($t->label)->toBe('Ajak 7 orang — hemat 5%')
        ->and($t->ajakan)->not->toContain('karangan');
});
