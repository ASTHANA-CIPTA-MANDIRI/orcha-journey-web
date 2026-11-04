<?php

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Support\Rab\BerkasRab;

/**
 * PDF penawaran tidak boleh menyebut nama baris biaya.
 *
 * Admin menamai baris biaya untuk dirinya sendiri: "Dana Tak Terduga",
 * "Uang Rokok Supir", "Operasional Tim Internal", "Tour Leader Internal".
 * Nama-nama itu pernah ikut tercetak di bagian "Harga Sudah Termasuk" karena
 * daftarnya memungut nama mentah tiap baris. Pelanggan jadi membaca istilah
 * dapur kami — dan baris yang sebenarnya cadangan margin terbaca sebagai
 * biaya yang ia tanggung.
 *
 * Yang dijaga di sini BUKAN empat nama itu, melainkan aturannya: apa pun yang
 * diketik admin di kolom nama tidak boleh muncul di berkas pelanggan. Nama
 * vendor ikut dijaga — "Restoku Chen-Chen" adalah informasi belanja kami,
 * bukan janji ke pelanggan.
 */
function rabPenawaran(array $biaya, array $ubah = []): string
{
    $rab = Rab::create(array_merge([
        'judul' => 'Private Trip Banyuwangi',
        'nama_pelanggan' => 'Rombongan Uji',
        'provinsi' => 'Jawa Timur',
        'jumlah_hari' => 3,
        'jumlah_malam' => 2,
        'jumlah_peserta' => 20,
        'margin_jenis' => 'persen',
        'margin_nilai' => 20,
    ], $ubah));

    foreach ($biaya as $i => [$kategori, $nama, $satuan, $harga, $jumlah]) {
        RabBiaya::create([
            'rab_id' => $rab->id, 'kategori' => $kategori, 'nama' => $nama,
            'satuan' => $satuan, 'harga_satuan' => $harga, 'jumlah' => $jumlah, 'urutan' => $i,
        ]);
    }

    [$view, $data] = BerkasRab::bahan($rab->fresh(), 'penawaran');

    return view($view, $data)->render();
}

test('nama baris biaya tidak pernah sampai ke penawaran', function () {
    $html = rabPenawaran([
        ['transportasi', 'Hiace Premio 14 seat', 'unit_hari', 1400000, 2],
        ['sopir', 'Uang Rokok Supir', 'orang_hari', 150000, 6],
        ['konsumsi', 'Makan malam Restoku Chen-Chen', 'orang', 45000, 20],
        ['pemandu', 'Tour Leader Internal', 'orang_hari', 250000, 3],
        ['lainnya', 'Dana Tak Terduga', 'paket', 500000, 1],
        ['operasional', 'Operasional Tim Internal', 'paket', 750000, 1],
    ]);

    foreach ([
        'Uang Rokok Supir', 'Dana Tak Terduga', 'Operasional Tim Internal',
        'Tour Leader Internal', 'Restoku Chen-Chen', 'Hiace Premio',
    ] as $istilah) {
        expect($html)->not->toContain($istilah);
    }
});

test('baris biaya bernama apa pun tidak bocor, termasuk yang baru diketik admin', function () {
    // Bukan daftar hitam: nama yang belum pernah terpikirkan pun harus tertahan.
    $html = rabPenawaran([
        ['lainnya', 'ZXQV catatan pribadi admin 9981', 'paket', 100000, 1],
        ['operasional', 'titipan bos jangan dikasih tau', 'paket', 100000, 1],
    ]);

    expect($html)->not->toContain('ZXQV')
        ->and($html)->not->toContain('titipan bos');
});

test('yang tercetak kalimat kami sendiri, bukan kategori mentah', function () {
    $html = rabPenawaran([
        ['transportasi', 'Elf long 19 seat', 'unit_hari', 1200000, 2],
        ['akomodasi', 'Homestay Ijen', 'kamar_malam', 350000, 6],
        ['konsumsi', 'Makan siang hari 1', 'orang', 35000, 20],
        ['konsumsi', 'Makan malam hari 1', 'orang', 45000, 20],
        ['konsumsi', 'Snack perjalanan', 'orang', 15000, 20],
        ['tiket', 'Tiket Baluran', 'orang', 29000, 20],
    ]);

    expect($html)->toContain('Transportasi')
        ->and($html)->toContain('Penginapan 2 malam')   // ikut jumlah_malam
        ->and($html)->toContain('Makan 2×')             // snack tidak terhitung jatah makan
        ->and($html)->toContain('Snack &amp; air mineral')
        ->and($html)->toContain('Tiket masuk &amp; retribusi');
});

test('kategori yang murni internal tidak punya baris sendiri di penawaran', function () {
    // Operasional dan cadangan memang ikut membentuk harga, tetapi pelanggan
    // tidak perlu daftar belanjanya. Yang mewakilinya satu kalimat penutup.
    $html = rabPenawaran([
        ['transportasi', 'Hiace', 'unit_hari', 1400000, 2],
        ['operasional', 'Operasional Tim', 'paket', 750000, 1],
    ]);

    expect($html)->toContain('Transportasi')
        ->and(substr_count($html, 'class="termasuk-kotak"'))->toBe(1)
        ->and($html)->toContain('operasional tim kami selama perjalanan');
});

test('bagian termasuk tidak menyisakan kolom kosong', function () {
    // Jumlah ganjil dulu menyisakan satu sel kosong di baris terakhir. Sekarang
    // barang terakhir melebar penuh, jadi tidak ada lubang berapa pun jumlahnya.
    foreach ([1, 2, 3] as $banyak) {
        $isi = array_slice([
            ['transportasi', 'Hiace', 'unit_hari', 1400000, 2],
            ['akomodasi', 'Homestay', 'kamar_malam', 350000, 6],
            ['tiket', 'Tiket Baluran', 'orang', 29000, 20],
        ], 0, $banyak);

        $html = rabPenawaran($isi);

        // toContain() menerima BANYAK jarum, bukan pesan — pesannya di toBe.
        expect($html)->not->toContain('<td></td>')
            ->and(substr_count($html, 'class="termasuk-kotak"'))
            ->toBe($banyak, "jumlah {$banyak} menyisakan sel kosong");
    }
});

test('RAB internal tetap menyebut nama baris biayanya', function () {
    // Pengaman arah sebaliknya: yang dibersihkan HANYA berkas pelanggan.
    // Kantor justru butuh nama aslinya untuk belanja.
    $rab = Rab::create([
        'judul' => 'Private Trip Banyuwangi', 'nama_pelanggan' => 'Rombongan Uji',
        'provinsi' => 'Jawa Timur', 'jumlah_hari' => 3, 'jumlah_malam' => 2,
        'jumlah_peserta' => 20, 'margin_jenis' => 'persen', 'margin_nilai' => 20,
    ]);
    RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'sopir', 'nama' => 'Uang Rokok Supir',
        'satuan' => 'orang_hari', 'harga_satuan' => 150000, 'jumlah' => 6,
    ]);

    [$view, $data] = BerkasRab::bahan($rab->fresh(), 'internal');

    expect(view($view, $data)->render())->toContain('Uang Rokok Supir');
});

/*
|--------------------------------------------------------------------------
| Kategori yang salah arsip tidak boleh menghapus janji
|--------------------------------------------------------------------------
|
| Kategori baris biaya DIBEKUKAN saat barisnya dibuat, dan tidak ikut berubah
| kalau kategori di master harga dibetulkan belakangan. RAB produksi punya
| "Tour Leader Internal" yang masih berkategori `operasional` — kategori yang
| sengaja tidak punya kalimat — sehingga tour leader yang jelas-jelas ada
| tidak pernah tercetak sebagai yang sudah termasuk.
|
| Gagalnya diam: tidak ada galat, cuma satu janji yang hilang. Pelanggan
| membandingkan beberapa penawaran, dan yang tidak tertulis dianggap tidak
| diberikan.
*/
test('tour leader tetap tercetak walau barisnya salah kategori', function () {
    $html = rabPenawaran([
        ['operasional', 'Tour Leader Internal', 'hari', 300_000, 3],
    ]);

    expect($html)->toContain('Tour leader &amp; pemandu')
        // Namanya sendiri tetap tidak ikut.
        ->and($html)->not->toContain('Tour Leader Internal');
});

test('barang lain juga punya dua jalan muncul: kategori atau namanya', function () {
    // Semuanya diarsip di kategori yang salah; yang menolong namanya.
    $html = rabPenawaran([
        ['lainnya', 'Sewa Hiace 14 seat', 'unit_hari', 1_400_000, 2],
        ['lainnya', 'Naila Homestay Banyuwangi', 'kamar_malam', 280_000, 6],
        ['lainnya', 'Tiket Masuk Baluran', 'orang', 21_000, 20],
        ['lainnya', 'Fotografer & drone', 'paket', 1_500_000, 1],
        ['lainnya', 'Asuransi perjalanan peserta', 'orang', 15_000, 20],
    ]);

    foreach (['Transportasi', 'Penginapan', 'Tiket masuk &amp; retribusi',
        'Dokumentasi perjalanan', 'Asuransi perjalanan'] as $kalimat) {
        expect($html)->toContain($kalimat);
    }
});

test('kategori yang benar tetap cukup tanpa bantuan nama', function () {
    // Jalan pertama tidak boleh ikut hilang saat jalan kedua ditambahkan.
    $html = rabPenawaran([
        ['pemandu', 'Nala Tour', 'orang', 300_000, 20],
        ['transportasi', 'ZXQV-9981', 'unit_hari', 800_000, 3],
    ]);

    expect($html)->toContain('Tour leader &amp; pemandu')
        ->and($html)->toContain('Transportasi')
        ->and($html)->not->toContain('ZXQV')
        ->and($html)->not->toContain('Nala Tour');
});

test('operasional murni tetap tidak punya kalimat sendiri', function () {
    // Jaring namanya tidak boleh jadi pintu belakang buat biaya dapur.
    $html = rabPenawaran([
        ['operasional', 'Operasional Tim Internal', 'paket', 750_000, 1],
        ['lainnya', 'Dana Tak Terduga', 'paket', 500_000, 1],
    ]);

    expect(substr_count($html, 'class="termasuk-kotak"'))->toBe(0)
        ->and($html)->not->toContain('Operasional Tim Internal')
        ->and($html)->not->toContain('Dana Tak Terduga');
});
