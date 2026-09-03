<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rujukan\KodeRujukan;
use App\Support\RincianBiaya;
use App\Support\Rujukan;
use App\Support\TagihanPesanan;

/**
 * Kode rujukan: alumni trip yang membawa pendaftaran baru.
 *
 * Bedanya dengan promo rombongan tegas. Promo rombongan berlaku dalam SATU
 * pendaftaran — ramai orang berangkat bersama di tanggal yang sama. Kode
 * rujukan berlaku LINTAS pendaftaran — orang yang sudah pulang mengajak
 * temannya ikut trip berikutnya, di tanggal yang berbeda.
 */
function paketRujukan(): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Uji', 'category' => 'open_trip',
        'price' => 1000000, 'harga_modal' => 600000, 'status' => 'terbit',
    ]);
}

function kodeMilik(string $nama, string $whatsapp): KodeRujukan
{
    return KodeRujukan::create(['nama' => $nama, 'whatsapp' => $whatsapp]);
}

beforeEach(function () {
    config()->set('orcha.rujukan', ['aktif' => true, 'potongan' => 50000, 'imbalan' => 75000]);
});

/* ------------------------------ KODENYA ------------------------------ */

test('kodenya memuat nama depan pemiliknya', function () {
    /*
     | Namanya ikut karena kode ini DIUCAPKAN dan DIBANGGAKAN. Orang menyebar
     | kodenya sendiri ke grup WhatsApp temannya, dan "pakai kode BUDI-K7QM"
     | jauh lebih mungkin benar-benar diucapkan daripada deretan huruf acak.
     */
    $kode = kodeMilik('Budi Santoso', '081234567890');

    expect($kode->kode)->toStartWith('BUDI-')
        ->and(strlen($kode->kode))->toBe(9);
});

test('dua orang bernama sama tetap mendapat kode berbeda', function () {
    // Kode rujukan yang bisa ditebak berarti komisi yang bisa diakui orang
    // lain.
    $a = kodeMilik('Budi', '081234567890');
    $b = kodeMilik('Budi', '081298765432');

    expect($a->kode)->not->toBe($b->kode);
});

test('nama bertanda baca tidak menghasilkan kode yang tak bisa diketik', function () {
    $kode = kodeMilik("D'Angelo 99", '081234567890');

    expect($kode->kode)->toMatch('/^[A-Z]+-[A-Z0-9]{4}$/');
});

test('nama yang tidak berhuruf sama sekali tetap menghasilkan kode', function () {
    // Tanpa cadangan, kodenya jadi "-K7QM" — yang tidak bisa diucapkan dan
    // tidak bisa dicari.
    $kode = kodeMilik('123 456', '081234567890');

    expect($kode->kode)->toStartWith('ORCHA-');
});

/* ---------------------------- PEMERIKSAAN ---------------------------- */

test('kode yang tidak dikenal ditolak dengan sebabnya', function () {
    /*
     | Kode yang ditolak tanpa keterangan membuat orang mengetik ulang tiga
     | kali lalu menyerah — dan yang menyerah di langkah ini adalah
     | pendaftaran yang sudah hampir jadi.
     */
    $hasil = Rujukan::periksa('TIDAK-ADA');

    expect($hasil['sah'])->toBeFalse()
        ->and($hasil['sebab'])->toContain('tidak dikenali');
});

test('kode yang dimatikan ditolak, dan sebabnya berbeda', function () {
    $kode = kodeMilik('Budi', '081234567890');
    $kode->update(['aktif' => false]);

    $hasil = Rujukan::periksa($kode->kode);

    expect($hasil['sah'])->toBeFalse()
        ->and($hasil['sebab'])->toContain('tidak berlaku');
});

test('huruf kecil tetap diterima', function () {
    // Orang menyalin kodenya dari WhatsApp, dan ponsel mengecilkan hurufnya
    // sendiri.
    $kode = kodeMilik('Budi', '081234567890');

    expect(Rujukan::periksa(mb_strtolower($kode->kode))['sah'])->toBeTrue();
});

test('kode tidak bisa dipakai pemiliknya sendiri', function () {
    /*
     | Tanpa penjagaan ini, setiap orang yang punya kode mendapat potongan
     | tetap untuk dirinya sendiri selamanya — dan ikut menagih imbalan atas
     | pendaftarannya sendiri. Yang menemukan celah ini bukan orang jahat; ia
     | cuma mencoba, berhasil, lalu memberi tahu temannya.
     */
    $kode = kodeMilik('Budi', '081234567890');

    $hasil = Rujukan::periksa($kode->kode, '081234567890');

    expect($hasil['sah'])->toBeFalse()
        ->and($hasil['sebab'])->toContain('milik Anda sendiri');
});

test('nomor yang sama dengan awalan berbeda tetap dikenali sebagai pemiliknya', function () {
    // "+62812..." dan "0812..." adalah orang yang sama. Perbandingan mentah
    // menganggapnya berbeda, dan celahnya terbuka lagi.
    $kode = kodeMilik('Budi', '081234567890');

    expect(Rujukan::periksa($kode->kode, '+6281234567890')['sah'])->toBeFalse();
});

test('program yang dimatikan menolak semua kode', function () {
    config()->set('orcha.rujukan.aktif', false);
    $kode = kodeMilik('Budi', '081234567890');

    expect(Rujukan::periksa($kode->kode)['sah'])->toBeFalse();
});

/* ------------------------------- HARGA ------------------------------- */

test('potongan rujukan mengurangi total tagihan', function () {
    $kode = kodeMilik('Budi', '081234567890');

    $tanpa = RincianBiaya::untuk(paketRujukan(), 2);
    $dengan = RincianBiaya::untuk(paketRujukan(), 2, $kode->kode);

    expect($dengan['total'])->toBe($tanpa['total'] - 50000)
        ->and($dengan['rujukan_potongan'])->toBe(50000)
        ->and($dengan['rujukan_nama'])->toBe('Budi');
});

test('uang muka ikut dihitung dari total setelah rujukan', function () {
    // Kalau DP dihitung dari total sebelum potongan, pelanggan diminta
    // mentransfer lebih banyak daripada haknya.
    $kode = kodeMilik('Budi', '081234567890');
    $b = RincianBiaya::untuk(paketRujukan(), 2, $kode->kode);

    expect($b['dp'])->toBe(round($b['total'] * $b['dp_persen'] / 100));
});

test('potongannya per PENDAFTARAN, bukan per orang', function () {
    /*
     | Rujukan yang dihitung per kepala membuat satu kode yang dipakai
     | rombongan dua puluh orang memotong dua puluh kali — dan itu angka yang
     | tidak pernah dimaksudkan siapa pun saat menetapkannya.
     */
    $kode = kodeMilik('Budi', '081234567890');

    expect(RincianBiaya::untuk(paketRujukan(), 20, $kode->kode)['rujukan_potongan'])
        ->toBe(50000);
});

test('potongannya tidak pernah membuat tagihan minus', function () {
    // Tagihan minus berarti kita berutang kepada orang yang belum berangkat.
    config()->set('orcha.rujukan.potongan', 5000000);
    $kode = kodeMilik('Budi', '081234567890');

    $b = RincianBiaya::untuk(paketRujukan(), 1, $kode->kode);

    expect($b['total'])->toBe(0.0)
        ->and($b['rujukan_potongan'])->toBe(1000000);
});

test('rujukan ditumpuk di atas promo rombongan, bukan menggantinya', function () {
    /*
     | Keduanya menjawab hal yang berbeda: promo rombongan menghargai banyaknya
     | orang dalam satu pendaftaran, rujukan menghargai siapa yang membawa
     | pendaftaran itu ke sini. Menolak salah satunya berarti menghukum justru
     | pendaftaran yang paling kita inginkan.
     */
    $paket = paketRujukan();
    $paket->update(['promo_rombongan' => true]);
    $kode = kodeMilik('Budi', '081234567890');

    $hanyaPromo = RincianBiaya::untuk($paket->fresh(), 11);
    $keduanya = RincianBiaya::untuk($paket->fresh(), 11, $kode->kode);

    expect($hanyaPromo['promo_gratis_orang'])->toBe(1)
        ->and($keduanya['promo_gratis_orang'])->toBe(1)
        ->and($keduanya['total'])->toBe($hanyaPromo['total'] - 50000);
});

/* ---------------------------- PENDAFTARAN ---------------------------- */

function daftarDenganRujukan(?string $kode, string $whatsapp = '081200000000'): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => 'Pendaftar', 'whatsapp' => $whatsapp, 'jumlah_peserta' => 2,
        'travel_package_id' => paketRujukan()->id, 'nama_paket' => 'Open Trip Uji',
        'kode_rujukan' => $kode,
    ])->fresh();
}

test('angkanya dibekukan saat mendaftar', function () {
    /*
     | Imbalan rujukan berubah sepanjang tahun. Tanpa dibekukan, komisi yang
     | BELUM DIBAYARKAN ikut berubah setiap kali angkanya disunting hari ini —
     | dan yang menagih nanti orang yang mengingat angka lain daripada yang
     | tertulis di layar kita.
     */
    $kode = kodeMilik('Budi', '081234567890');
    $daftar = daftarDenganRujukan($kode->kode);

    config()->set('orcha.rujukan.imbalan', 500000);

    expect($daftar->fresh()->imbalan_rujukan)->toBe(75000)
        ->and($daftar->fresh()->potongan_rujukan)->toBe(50000);
});

test('kode yang ditolak dibuang, bukan disimpan dengan potongan nol', function () {
    /*
     | Menyimpannya berarti daftar rujukan memuat kode-kode yang tidak pernah
     | sah, dan yang membaca laporan komisi nanti menghitungnya.
     */
    $daftar = daftarDenganRujukan('NGARANG-1234');

    expect($daftar->kode_rujukan)->toBeNull()
        ->and($daftar->potongan_rujukan)->toBe(0)
        ->and($daftar->imbalan_rujukan)->toBe(0);
});

test('pemakaian kode sendiri ditolak saat mendaftar, bukan cuma di layar', function () {
    $kode = kodeMilik('Budi', '081234567890');

    $daftar = daftarDenganRujukan($kode->kode, '081234567890');

    expect($daftar->kode_rujukan)->toBeNull();
});

test('ejaan kodenya diseragamkan supaya laporan komisi tidak terpecah', function () {
    $kode = kodeMilik('Budi', '081234567890');

    $daftar = daftarDenganRujukan(mb_strtolower($kode->kode));

    expect($daftar->kode_rujukan)->toBe($kode->kode);
});

test('tagihannya memakai angka yang dibekukan, bukan config hari ini', function () {
    $kode = kodeMilik('Budi', '081234567890');
    $daftar = daftarDenganRujukan($kode->kode);

    $sebelum = TagihanPesanan::untuk($daftar)['total'];

    config()->set('orcha.rujukan.potongan', 900000);

    expect(TagihanPesanan::untuk($daftar->fresh())['total'])->toBe($sebelum);
});

test('omzet tidak menghitung uang yang tidak pernah masuk', function () {
    /*
     | Kesalahan yang sama pada promo rombongan dulu melaporkan keuntungan
     | lima puluh persen lebih besar daripada kenyataannya — dan angka itu
     | dipakai memutuskan paket mana yang layak dijalankan lagi.
     */
    $kode = kodeMilik('Budi', '081234567890');
    $daftar = daftarDenganRujukan($kode->kode);

    expect($daftar->omzet)->toBe(2 * 1000000 - 50000);
});

test('omzet TIDAK dikurangi imbalan pemilik kode', function () {
    /*
     | Imbalan itu biaya, dan biaya masuk di sisi lain laporan. Menguranginya
     | dari omzet membuat omzet tidak lagi sama dengan yang ditagihkan ke
     | pelanggan — dan dua angka untuk satu hal yang sama adalah asal
     | perselisihan yang paling sulit diselesaikan.
     */
    $kode = kodeMilik('Budi', '081234567890');
    $daftar = daftarDenganRujukan($kode->kode);

    expect($daftar->omzet)->toBe(TagihanPesanan::untuk($daftar)['total']);
});

/* ----------------------------- ALUMNI ----------------------------- */

test('alumni mendapat kode setelah pulang', function () {
    $daftar = daftarDenganRujukan(null, '081211112222');

    $kode = Rujukan::untukAlumni($daftar);

    // Nama dipotong delapan huruf: kode yang panjang tidak diucapkan orang,
    // dan yang tidak diucapkan tidak pernah dipakai.
    expect($kode->kode)->toStartWith('PENDAFTA-')
        ->and($kode->kode_pendaftaran_asal)->toBe($daftar->kode);
});

test('satu orang tidak pernah punya dua kode', function () {
    /*
     | Membuatkan kode kedua untuk orang yang sama memecah imbalannya jadi dua
     | catatan terpisah, dan yang menagih nanti menagih keduanya.
     */
    $a = Rujukan::untukAlumni(daftarDenganRujukan(null, '081211112222'));
    $b = Rujukan::untukAlumni(daftarDenganRujukan(null, '081211112222'));

    expect($b->id)->toBe($a->id)
        ->and(KodeRujukan::count())->toBe(1);
});

/* --------------------------- FORMULIR PUBLIK --------------------------- */

test('kode yang dipakai langsung menurunkan harga di layar', function () {
    /*
     | Potongan yang baru terlihat setelah pendaftarannya terkirim tidak
     | mendorong siapa pun mengetik kodenya. Yang membuat orang mencari kode
     | temannya adalah melihat angkanya turun sekarang juga.
     */
    $kode = kodeMilik('Budi', '081234567890');
    $paket = paketRujukan();

    $halaman = Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('jumlahPeserta', 2);

    $halaman->assertDontSee('Kode dari Budi')
        ->set('kodeRujukan', $kode->kode)
        ->assertSee('Kode dari Budi')
        ->assertSee('Rp 50.000');
});

test('kode salah ketik ditegur di layar, bukan dibuang diam-diam', function () {
    /*
     | Model memang membuang kode yang tidak sah — itu penjagaan terakhir.
     | Tetapi kalau layarnya ikut diam, orang menekan Daftar, melihat halaman
     | berhasil, dan baru menyadari potongannya hilang saat tagihannya datang.
     | Yang menanggung kekecewaannya orang yang tidak melakukan kesalahan apa
     | pun selain salah satu huruf.
     */
    $paket = paketRujukan();

    Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('nama', 'Pendaftar Uji')
        ->set('whatsapp', '081200000000')
        ->set('jumlahPeserta', 1)
        ->set('peserta', [['nama' => 'Pendaftar Uji', 'titik_jemput' => '']])
        ->set('kodeRujukan', 'NGARANG-9999')
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasErrors('kodeRujukan');
});

test('formulir tanpa kode rujukan tetap bisa dikirim', function () {
    // Sebagian besar orang datang tanpa kode. Kotak yang menahan mereka adalah
    // kotak yang menghapus pendaftaran.
    $paket = paketRujukan();

    Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('nama', 'Pendaftar Uji')
        ->set('whatsapp', '081200000000')
        ->set('jumlahPeserta', 1)
        ->set('peserta', [['nama' => 'Pendaftar Uji', 'titik_jemput' => '']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();
});
