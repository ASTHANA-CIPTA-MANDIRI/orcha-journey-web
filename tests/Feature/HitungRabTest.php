<?php

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Support\Rab\HitungRab;

/**
 * Hitungan RAB — satu-satunya sumber angka untuk layar, dua PDF, dan
 * "Jadikan Pendaftaran". Salah di sini berarti pelanggan dan kantor membaca
 * angka yang berbeda untuk perjalanan yang sama.
 */
function rabUji(array $ubah = []): Rab
{
    return Rab::create(array_merge([
        'judul' => 'Private Trip Jogja',
        'nama_pelanggan' => 'Bu Siti',
        'provinsi' => 'DI Yogyakarta',
        'jumlah_hari' => 3,
        'jumlah_malam' => 2,
        'jumlah_peserta' => 17,
        'margin_jenis' => 'persen',
        'margin_nilai' => 20,
    ], $ubah));
}

function biayaUji(Rab $rab, string $satuan, int $harga, ?int $kapasitas = null, int $jumlah = 1): RabBiaya
{
    return RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'lainnya', 'nama' => "uji {$satuan}",
        'satuan' => $satuan, 'harga_satuan' => $harga, 'kapasitas' => $kapasitas, 'jumlah' => $jumlah,
    ]);
}

test('setiap satuan di config punya cabang hitungnya', function () {
    // Satuan baru yang lupa ditambahkan ke faktor() terhitung NOL — baris
    // biayanya ada di layar, tetapi tidak ikut total. Diam, dan mahal.
    $rab = rabUji();

    foreach (array_keys(config('orcha.rab.satuan')) as $satuan) {
        expect(HitungRab::faktor($satuan, 10, $rab))
            ->toBeGreaterThan(0, "satuan {$satuan} terhitung nol");
    }
});

test('kendaraan dan kamar dihitung per unit utuh, dibulatkan ke atas', function () {
    /*
     | 17 peserta di Hiace berkapasitas 14 butuh DUA unit. Membulatkan ke
     | bawah berarti tiga orang tanpa kursi; unit kedua memang disewa utuh
     | walau setengah kosong.
     */
    $rab = rabUji(['jumlah_peserta' => 17, 'jumlah_hari' => 3, 'jumlah_malam' => 2]);

    expect(HitungRab::faktor('unit_hari', 14, $rab))->toBe(2 * 3)
        // 17 orang, 4 per kamar → 5 kamar × 2 malam.
        ->and(HitungRab::faktor('kamar_malam', 4, $rab))->toBe(5 * 2)
        ->and(HitungRab::faktor('orang_hari', null, $rab))->toBe(17 * 3);
});

test('penjelasan pengali terbaca sebagai kalimat', function () {
    $rab = rabUji(['jumlah_peserta' => 17, 'jumlah_hari' => 3]);
    $bus = biayaUji($rab, 'unit_hari', 850000, 14);

    expect(HitungRab::penjelasan($bus, $rab))->toBe('Rp 850.000 × 2 unit × 3 hari')
        ->and(HitungRab::subtotal($bus, $rab))->toBe(5_100_000);
});

test('modal variabel dan tetap dipisah menurut satuannya', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'jumlah_hari' => 2, 'jumlah_malam' => 1]);
    biayaUji($rab, 'orang', 50_000);           // tiket: 500.000 variabel
    biayaUji($rab, 'unit_hari', 1_000_000, 14); // bus:  2.000.000 tetap

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['modal_variabel'])->toBe(500_000)
        ->and($r['modal_tetap'])->toBe(2_000_000)
        ->and($r['modal_total'])->toBe(2_500_000);
});

test('tiga jenis margin menghitung seperti yang dimaksud orang', function () {
    $rab = rabUji(['jumlah_peserta' => 10]);

    $rab->margin_jenis = 'persen';
    $rab->margin_nilai = 20;
    expect(HitungRab::margin($rab, 1_000_000))->toBe(200_000);

    $rab->margin_jenis = 'per_orang';
    $rab->margin_nilai = 150_000;
    expect(HitungRab::margin($rab, 1_000_000))->toBe(1_500_000);

    $rab->margin_jenis = 'total';
    $rab->margin_nilai = 3_000_000;
    expect(HitungRab::margin($rab, 1_000_000))->toBe(3_000_000);
});

test('harga per orang dibulatkan ke atas, margin tidak pernah menyusut', function () {
    /*
     | Pembulatan boleh menambah margin sedikit, tidak boleh menguranginya.
     | Membulatkan ke bawah diam-diam memotong untung yang sudah diputuskan
     | admin — dan pada rombongan besar potongannya tidak kecil.
     */
    $rab = rabUji(['jumlah_peserta' => 7, 'margin_jenis' => 'persen', 'margin_nilai' => 20, 'pembulatan' => 1000]);
    biayaUji($rab, 'rombongan', 1_000_001);

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['harga_per_orang'] % 1000)->toBe(0)
        ->and($r['untung'])->toBeGreaterThanOrEqual($r['margin_diminta'])
        ->and($r['harga_total'])->toBe($r['harga_per_orang'] * 7);
});

test('pemecahan untuk pendaftaran menghasilkan modal yang sama persis', function () {
    /*
     | Pendaftaran menghitung modal = harga_modal × peserta + biaya_tetap.
     | Membagi modal variabel dengan peserta hampir tidak pernah bulat; sisa
     | yang dibuang membuat laporan keuntungan berbeda dari RAB yang disetujui
     | pelanggan. Diuji pada jumlah peserta yang sengaja tidak membagi rata.
     */
    foreach ([1, 3, 7, 13, 17] as $peserta) {
        $rab = rabUji(['jumlah_peserta' => $peserta, 'jumlah_hari' => 3, 'jumlah_malam' => 2]);
        biayaUji($rab, 'orang', 33_333);
        biayaUji($rab, 'orang_hari', 47_777);
        biayaUji($rab, 'unit_hari', 1_150_000, 14);
        biayaUji($rab, 'kamar_malam', 425_000, 4);

        $r = HitungRab::ringkas($rab->fresh());
        $p = $r['untuk_pendaftaran'];

        expect($p['harga_modal'] * $peserta + $p['biaya_tetap'])
            ->toBe($r['modal_total'], "peserta {$peserta}")
            ->and($p['biaya_tetap'])->toBeGreaterThanOrEqual(0);
    }
});

test('rab tanpa biaya tidak meledak', function () {
    $r = HitungRab::ringkas(rabUji());

    expect($r['modal_total'])->toBe(0)
        ->and($r['harga_per_orang'])->toBe(0)
        ->and($r['persen_untung'])->toBeNull();
});

test('rincian dikelompokkan menurut urutan kategori, bukan urutan ditambahkan', function () {
    $rab = rabUji();
    foreach ([['pemandu', 'Tour leader'], ['tiket', 'Tiket A'], ['transportasi', 'Hiace'], ['tiket', 'Tiket B']] as $i => [$kat, $nama]) {
        RabBiaya::create(['rab_id' => $rab->id, 'kategori' => $kat, 'nama' => $nama, 'satuan' => 'rombongan', 'harga_satuan' => 1000, 'jumlah' => 1, 'urutan' => $i]);
    }

    /*
     | Urutannya mengikuti config, bukan urutan baris ditambahkan — dan
     | urutan config itu urutan BACA: transportasi dan akomodasi lebih dulu
     | karena itulah dua pos terbesar yang dicari mata pertama kali.
     | Dipatok di sini supaya perubahan urutannya jadi keputusan yang
     | disengaja, bukan efek samping yang baru ketahuan di PDF pelanggan.
     */
    expect(collect(HitungRab::ringkas($rab->fresh())['baris'])->pluck('nama')->all())
        ->toBe(['Hiace', 'Tiket A', 'Tiket B', 'Tour leader']);
});

test('titik impas memberi tahu rombongan sekecil apa yang masih menutup biaya tetap', function () {
    /*
     | Sewa bus dan honor koordinator dibayar sama saja entah yang berangkat
     | sepuluh atau lima belas orang — dan itulah yang membuat rombongan kecil
     | diam-diam merugi walau harganya terlihat untung di atas kertas.
     */
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 5, 'pembulatan' => 1000]);

    // Satu pos tetap (sewa bus) dan satu pos per orang (tiket).
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'transportasi', 'nama' => 'Bus',
        'satuan' => 'rombongan', 'harga_satuan' => 5_000_000, 'jumlah' => 1, 'urutan' => 1]);
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'tiket', 'nama' => 'Tiket',
        'satuan' => 'orang', 'harga_satuan' => 50_000, 'jumlah' => 1, 'urutan' => 2]);

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['bep_peserta'])->toBeInt()
        ->and($r['bep_peserta'])->toBeGreaterThan(0)
        // Sumbangan tiap kepala = harga jual − biaya yang menempel padanya.
        ->and($r['sumbangan_per_orang'])->toBe($r['harga_per_orang'] - $r['modal_variabel_per_orang'])
        // Biaya tetapnya tertutup tepat pada jumlah peserta itu.
        ->and($r['bep_peserta'] * $r['sumbangan_per_orang'])->toBeGreaterThanOrEqual($r['modal_tetap'])
        ->and($r['bep_tercapai'])->toBe($r['bep_peserta'] <= 10);
});

test('tanpa biaya tetap dan tanpa margin, impasnya di peserta pertama', function () {
    /*
     | Seluruh biayanya menempel pada orang, harganya persis menutup biaya
     | itu: trip tidak rugi pada jumlah berapa pun, termasuk satu orang.
     |
     | Rumus lama menjawab "tidak ada titik impas" di sini — hasil membagi
     | biaya tetap nol dengan sumbangan nol. Yang dicari sekarang jumlah
     | peserta terkecil yang sudah tidak rugi, dan jawabannya 1.
     */
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 0, 'pembulatan' => 1]);

    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'tiket', 'nama' => 'Tiket',
        'satuan' => 'orang', 'harga_satuan' => 100_000, 'jumlah' => 1, 'urutan' => 1]);

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['modal_tetap'])->toBe(0)
        ->and($r['sumbangan_per_orang'])->toBe(0)
        ->and($r['bep_peserta'])->toBe(1);
});

test('tidak ada titik impas bila tiap jumlah peserta tetap rugi', function () {
    // Tidak ada jumlah peserta yang menolong harga semacam itu; angka impas
    // di situ hanya akan menyesatkan orang yang membacanya.
    $semuaRugi = [
        ['peserta' => 1, 'laba' => -500],
        ['peserta' => 2, 'laba' => -400],
        ['peserta' => 3, 'laba' => -300],
    ];

    expect(HitungRab::impas($semuaRugi))->toBeNull();

    // Dan yang impasnya ada dikembalikan pada baris PERTAMA yang tidak rugi,
    // bukan baris terakhir yang kebetulan untung besar.
    expect(HitungRab::impas([
        ['peserta' => 1, 'laba' => -500],
        ['peserta' => 2, 'laba' => 0],
        ['peserta' => 3, 'laba' => 9000],
    ]))->toBe(2);
});

test('titik impas dicari, bukan dibagi — dan bedanya satu kursi', function () {
    /*
     | Angka ini bukan teori. Pada contoh tiga hari Banyuwangi, biaya tetap
     | dibagi sumbangan per kepala menjawab 13 peserta, padahal pada 12
     | peserta trip sudah untung — karena kamar keempat baru disewa pada
     | peserta ke-13. Selisih satu kursi itulah yang dipakai memutuskan
     | berangkat atau tidak.
     */
    $rab = rabUji(['jumlah_peserta' => 14, 'margin_jenis' => 'persen', 'margin_nilai' => 5, 'pembulatan' => 1000]);

    foreach ([
        ['transportasi', 'Hiace', 'unit_hari', 850_000, 14],
        ['akomodasi', 'Homestay', 'kamar_malam', 350_000, 4],
        ['konsumsi', 'Makan', 'orang', 170_000, null],
        ['tiket', 'Snorkeling', 'orang', 281_000, null],
        ['pemandu', 'Tour leader', 'hari', 300_000, null],
        ['dokumentasi', 'Fotografer', 'rombongan', 500_000, null],
        ['operasional', 'Koordinator', 'rombongan', 541_667, null],
        ['sopir', 'Konsumsi sopir', 'hari', 50_000, null],
        ['asuransi', 'Asuransi', 'orang', 15_000, null],
    ] as $i => [$kat, $nama, $satuan, $harga, $kap]) {
        RabBiaya::create(['rab_id' => $rab->id, 'kategori' => $kat, 'nama' => $nama, 'satuan' => $satuan,
            'harga_satuan' => $harga, 'kapasitas' => $kap, 'jumlah' => 1, 'urutan' => $i + 1]);
    }

    $r = HitungRab::ringkas($rab->fresh());

    $lurus = (int) ceil($r['modal_tetap'] / $r['sumbangan_per_orang']);

    expect($r['bep_peserta'])->toBe(12)
        ->and($lurus)->toBe(13)
        // Dan simulasinya membuktikannya: 12 untung, 11 masih rugi.
        ->and(collect($r['simulasi'])->firstWhere('peserta', 12)['laba'])->toBeGreaterThanOrEqual(0)
        ->and(collect($r['simulasi'])->firstWhere('peserta', 11)['laba'])->toBeLessThan(0);
});

test('satu peserta tambahan bisa justru menambah rugi', function () {
    /*
     | Tangga kamar dan armada membuat untung-rugi TIDAK naik mulus. Peserta
     | ke-9 memaksa kamar ketiga seharga Rp 700.000 sementara ia sendiri cuma
     | menyumbang sekian ratus ribu — jadi menambah satu orang justru
     | memperburuk.
     |
     | Penting untuk menetapkan peserta minimum: letakkan tepat DI BAWAH
     | tangganya, bukan di angka bulat yang enak diucapkan.
     */
    $rab = rabUji(['jumlah_peserta' => 14, 'margin_jenis' => 'persen', 'margin_nilai' => 5, 'pembulatan' => 1000]);

    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'akomodasi', 'nama' => 'Homestay',
        'satuan' => 'kamar_malam', 'harga_satuan' => 350_000, 'kapasitas' => 4, 'jumlah' => 1, 'urutan' => 1]);
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'konsumsi', 'nama' => 'Makan',
        'satuan' => 'orang', 'harga_satuan' => 170_000, 'jumlah' => 1, 'urutan' => 2]);

    $sim = collect(HitungRab::ringkas($rab->fresh())['simulasi'])->keyBy('peserta');

    // Kamar ketiga disewa pada peserta ke-9.
    expect($sim[9]['modal_tetap'])->toBeGreaterThan($sim[8]['modal_tetap']);
});

test('pos biaya yang mudah terlupa punya kategorinya sendiri', function () {
    /*
     | Asuransi, dokumentasi, honor koordinator, dan konsumsi sopir dulu
     | menumpuk di "Lain-lain". Ketika sebuah penawaran meleset, yang bisa
     | dibaca cuma "lain-lain terlalu kecil" — bukan pos mana yang belum
     | diisi.
     */
    $kategori = array_keys(config('orcha.rab.kategori'));

    foreach (['asuransi', 'dokumentasi', 'operasional', 'sopir'] as $pos) {
        expect(in_array($pos, $kategori, true))->toBeTrue("kategori {$pos} tidak terdaftar");
    }

    // "Lain-lain" tetap paling belakang: ia penampung, bukan pos.
    expect(array_key_last(config('orcha.rab.kategori')))->toBe('lainnya');
});

test('tanpa biaya tetap, titik impasnya satu peserta — bukan nol', function () {
    // "Balik modal mulai 0 peserta" bukan jawaban, cuma pembagian yang
    // kebetulan nol.
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);

    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'tiket', 'nama' => 'Tiket',
        'satuan' => 'orang', 'harga_satuan' => 50_000, 'jumlah' => 1, 'urutan' => 1]);

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['modal_tetap'])->toBe(0)
        ->and($r['bep_peserta'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Negosiasi
|--------------------------------------------------------------------------
|
| Harga jual biasanya DITURUNKAN dari modal + margin. Saat pelanggan menawar
| arahnya dibalik: angkanya yang diketahui, dan untungnya jadi hasil.
*/

test('harga nego dipakai apa adanya, tidak dibulatkan ulang', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'pembulatan' => 10000]);
    biayaUji($rab, 'orang', 100_000);

    // 1.234.567 bukan kelipatan 10.000. Angka hasil kesepakatan tidak boleh
    // digeser sistem — pelanggan sudah menyebut angka itu di telepon.
    $rab->update(['harga_nego' => 1_234_567]);

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['harga_per_orang'])->toBe(1_234_567)
        ->and($r['nego_aktif'])->toBeTrue()
        ->and($r['harga_total'])->toBe(12_345_670);
});

test('tanpa nego, hitungannya sama persis seperti sebelumnya', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);
    biayaUji($rab, 'orang', 100_000);

    $r = HitungRab::ringkas($rab->fresh());

    // modal 1.000.000, margin 200.000 → 120.000/orang.
    expect($r['nego_aktif'])->toBeFalse()
        ->and($r['harga_per_orang'])->toBe(120_000)
        ->and($r['nego']['sebelum']['harga_per_orang'])->toBe(120_000)
        ->and($r['nego']['selisih']['harga_per_orang'])->toBe(0);
});

test('harga asli tetap dikirim sebagai pembanding saat nego hidup', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);
    biayaUji($rab, 'orang', 100_000);
    $rab->update(['harga_nego' => 110_000]);

    $r = HitungRab::ringkas($rab->fresh());

    // Potongan tanpa pembanding bukan potongan — ia cuma angka lain.
    expect($r['nego']['sebelum']['harga_per_orang'])->toBe(120_000)
        ->and($r['nego']['selisih']['harga_per_orang'])->toBe(-10_000)
        ->and($r['untung'])->toBe(100_000);
});

test('nego di bawah modal ditandai rugi, bukan ditolak', function () {
    $rab = rabUji(['jumlah_peserta' => 10]);
    biayaUji($rab, 'orang', 100_000);

    // Kadang memang itu keputusannya. Yang wajib adalah angkanya terlihat.
    $rab->update(['harga_nego' => 90_000]);

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['nego_rugi'])->toBeTrue()
        ->and($r['untung'])->toBe(-100_000);
});

test('batas aman adalah harga terendah yang belum rugi, dibulatkan ke atas', function () {
    $rab = rabUji(['jumlah_peserta' => 3, 'pembulatan' => 1000]);
    // modal 100.000 untuk 3 orang = 33.333,3 per orang.
    biayaUji($rab, 'rombongan', 100_000);

    $r = HitungRab::ringkas($rab->fresh());

    // Dibulatkan KE ATAS: 33.000 masih rugi 1.000 untuk rombongan ini.
    expect($r['nego']['batas']['harga'])->toBe(34_000);

    $rab->update(['harga_nego' => $r['nego']['batas']['harga']]);
    expect(HitungRab::ringkas($rab->fresh())['nego_rugi'])->toBeFalse();
});

test('harga nego nol berarti tidak ada kesepakatan', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);
    biayaUji($rab, 'orang', 100_000);
    $rab->update(['harga_nego' => 0]);

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['nego_aktif'])->toBeFalse()
        ->and($r['harga_per_orang'])->toBe(120_000);
});

test('pendaftaran memakai harga yang disepakati, bukan harga hitungan', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);
    biayaUji($rab, 'orang', 100_000);
    $rab->update(['harga_nego' => 110_000]);

    $r = HitungRab::ringkas($rab->fresh());

    // Kalau tidak, pelanggan membayar harga nego tetapi laporan keuntungan
    // menghitung harga lama — dan selisihnya tidak pernah ketahuan.
    expect($r['untuk_pendaftaran']['harga_jual'])->toBe(110_000);
});

test('perbandingan nego menyebut untung sebelum, sesudah, dan yang dilepas', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);
    biayaUji($rab, 'orang', 100_000);
    // modal 1.000.000 → harga hitungan 120.000, untung 200.000.
    $rab->update(['harga_nego' => 110_000]);

    $n = HitungRab::ringkas($rab->fresh())['nego'];

    expect($n['aktif'])->toBeTrue()
        ->and($n['sebelum']['harga_per_orang'])->toBe(120_000)
        ->and($n['sebelum']['untung'])->toBe(200_000)
        ->and($n['sebelum']['persen_untung'])->toBe(20.0)
        ->and($n['sesudah']['harga_per_orang'])->toBe(110_000)
        ->and($n['sesudah']['untung'])->toBe(100_000)
        ->and($n['sesudah']['persen_untung'])->toBe(10.0)
        ->and($n['selisih']['harga_per_orang'])->toBe(-10_000)
        ->and($n['selisih']['untung'])->toBe(-100_000)
        // Poin persen, bukan persen dari persen: 20% turun ke 10%.
        ->and($n['selisih']['poin_persen'])->toBe(-10.0);
});

test('bagian untung yang dilepas jauh lebih besar daripada potongan harganya', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);
    biayaUji($rab, 'orang', 100_000);
    $rab->update(['harga_nego' => 110_000]);

    $n = HitungRab::ringkas($rab->fresh())['nego'];

    // Harga turun 8,3% — tetapi SEPARUH untung yang dilepas, karena yang
    // dipotong adalah selisihnya, bukan harganya. Inilah angka yang paling
    // sering mengejutkan saat tawaran disetujui.
    expect($n['selisih']['bagian_untung_dilepas'])->toBe(50.0);
});

test('titik impas ikut bergeser saat harganya diturunkan', function () {
    $rab = rabUji(['jumlah_peserta' => 20, 'margin_jenis' => 'persen', 'margin_nilai' => 50]);
    // Biaya TETAP: menurunkan harga menuntut lebih banyak kepala menutupinya.
    biayaUji($rab, 'rombongan', 600_000);
    biayaUji($rab, 'orang', 40_000);

    $sebelum = HitungRab::ringkas($rab->fresh())['nego']['sebelum']['bep_peserta'];
    $rab->update(['harga_nego' => 70_000]);
    $n = HitungRab::ringkas($rab->fresh())['nego'];

    expect($n['sesudah']['bep_peserta'])->toBeGreaterThan($sebelum)
        ->and($n['selisih']['bep_bergeser'])->toBe($n['sesudah']['bep_peserta'] - $sebelum);
});

test('impas yang terlempar ke luar jangkauan tidak dilaporkan sebagai tidak bergeser', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 50]);
    biayaUji($rab, 'rombongan', 600_000);
    biayaUji($rab, 'orang', 40_000);
    // Pada 70.000 impasnya butuh 20 kepala — di luar rentang simulasi.
    $rab->update(['harga_nego' => 70_000]);

    $n = HitungRab::ringkas($rab->fresh())['nego'];

    // (int) null adalah nol, dan nol di sini berbunyi "aman" padahal
    // impasnya justru tidak tercapai sama sekali.
    expect($n['sesudah']['bep_peserta'])->toBeNull()
        ->and($n['selisih']['bep_bergeser'])->toBeNull();
});

test('jarak ke batas aman negatif saat kesepakatannya sudah merugi', function () {
    $rab = rabUji(['jumlah_peserta' => 10]);
    biayaUji($rab, 'orang', 100_000);
    $rab->update(['harga_nego' => 90_000]);

    $n = HitungRab::ringkas($rab->fresh())['nego'];

    expect($n['batas']['harga'])->toBe(100_000)
        ->and($n['batas']['jarak'])->toBe(-10_000);
});

test('tanpa nego, kedua sisi perbandingannya sama persis', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);
    biayaUji($rab, 'orang', 100_000);

    $n = HitungRab::ringkas($rab->fresh())['nego'];

    // Selalu dirakit: angka yang hanya ada kadang-kadang adalah angka yang
    // lupa diuji.
    expect($n['aktif'])->toBeFalse()
        ->and($n['sebelum'])->toBe($n['sesudah'])
        ->and($n['selisih']['untung'])->toBe(0)
        ->and($n['selisih']['bagian_untung_dilepas'])->toBe(0.0);
});

test('tambahan dari pembulatan disebut angkanya, bukan dibiarkan jadi selisih misterius', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20, 'pembulatan' => 5000]);
    biayaUji($rab, 'orang', 10_000);

    $r = HitungRab::ringkas($rab->fresh());

    /*
     | Modal 100.000, margin diminta 20.000 → harga mentah 12.000/orang.
     | Dibulatkan ke kelipatan 5.000 jadi 15.000 — dan untungnya melompat
     | ke 50.000, dua setengah kali yang diminta. Selisih 30.000 itu bukan
     | salah hitung, tetapi tanpa namanya ia terbaca seperti salah hitung.
     */
    expect($r['harga_per_orang'])->toBe(15_000)
        ->and($r['margin_diminta'])->toBe(20_000)
        ->and($r['untung'])->toBe(50_000)
        ->and($r['tambahan_pembulatan'])->toBe(30_000)
        // Ketiganya harus berjumlah utuh: margin diminta + tambahan = untung.
        ->and($r['margin_diminta'] + $r['tambahan_pembulatan'])->toBe($r['untung']);
});

test('pembulatan ke rupiah terkecil tidak menambah apa-apa', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20, 'pembulatan' => 1]);
    biayaUji($rab, 'orang', 10_000);

    expect(HitungRab::ringkas($rab->fresh())['tambahan_pembulatan'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Margin dari harga jual
|--------------------------------------------------------------------------
|
| "Margin 5%" punya dua arti yang dipakai orang dengan kata yang sama, dan
| keduanya memberi harga berbeda untuk modal yang sama.
*/

test('margin dari harga jual menyisakan persennya di dalam harga', function () {
    $rab = rabUji(['jumlah_peserta' => 4, 'margin_jenis' => 'persen_harga', 'margin_nilai' => 5, 'pembulatan' => 1]);
    // Modal 1.343.551 per orang — angka dari lembar kerja tim.
    biayaUji($rab, 'orang', 1_343_551);

    $r = HitungRab::ringkas($rab->fresh());

    // 1.343.551 / 0,95 = 1.414.264,2 → dibulatkan ke atas 1.414.265.
    expect($r['harga_per_orang'])->toBe(1_414_265)
        ->and($r['untung_per_orang'])->toBe(70_714)
        ->and($r['untung'])->toBe(282_856);
});

test('persen dari modal dan persen dari harga jual memberi harga berbeda', function () {
    $buat = fn (string $jenis) => tap(rabUji([
        'jumlah_peserta' => 4, 'margin_jenis' => $jenis, 'margin_nilai' => 5, 'pembulatan' => 1,
    ]), fn ($rab) => biayaUji($rab, 'orang', 1_343_551));

    $dariModal = HitungRab::ringkas($buat('persen')->fresh());
    $dariHarga = HitungRab::ringkas($buat('persen_harga')->fresh());

    // Bedanya nyata, dan keduanya disebut "margin 5%" oleh orang berbeda.
    expect($dariModal['harga_per_orang'])->toBe(1_410_729)
        ->and($dariHarga['harga_per_orang'])->toBe(1_414_265);
});

test('untung dari margin harga jual benar-benar sebesar persen harganya', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen_harga', 'margin_nilai' => 20, 'pembulatan' => 1]);
    biayaUji($rab, 'orang', 80_000);

    $r = HitungRab::ringkas($rab->fresh());

    // 80.000 / 0,8 = 100.000 → untung 20.000, tepat 20% DARI HARGA JUAL.
    expect($r['harga_per_orang'])->toBe(100_000)
        ->and(round($r['untung'] / $r['harga_total'] * 100, 1))->toBe(20.0)
        // Diukur terhadap modal, persennya 25% — angka yang sama, dua nama.
        ->and($r['persen_untung'])->toBe(25.0);
});

test('harga sebelum dibulatkan ikut dikirim, supaya margin tidak terlihat mati', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20, 'pembulatan' => 5000]);
    biayaUji($rab, 'orang', 5_500);

    $r = HitungRab::ringkas($rab->fresh());

    // Modal 55.000 + margin 11.000 = 66.000 → 6.600/orang, dibulatkan 10.000.
    expect($r['harga_sebelum_bulat'])->toBe(6_600)
        ->and($r['harga_per_orang'])->toBe(10_000);
});

test('kelipatan terlalu kasar membuat rentang margin lebar memberi harga yang sama', function () {
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'pembulatan' => 5000]);
    biayaUji($rab, 'orang', 5_500);

    $harga = [];
    foreach ([0, 20, 50, 80] as $persen) {
        $rab->update(['margin_nilai' => $persen]);
        $harga[$persen] = HitungRab::ringkas($rab->fresh())['harga_per_orang'];
    }

    // Bukan bug — tetapi tanpa harga_sebelum_bulat, kotak marginnya
    // terlihat rusak bagi yang mengubahnya.
    expect(array_values($harga))->toBe([10_000, 10_000, 10_000, 10_000]);

    $rab->update(['margin_nilai' => 82]);
    expect(HitungRab::ringkas($rab->fresh())['harga_per_orang'])->toBe(15_000);
});

test('rincian per orang selalu berjumlah pas dengan harga jualnya', function () {
    // Angka yang sengaja tidak habis dibagi: 7 peserta, margin 17%.
    $rab = rabUji(['jumlah_peserta' => 7, 'margin_jenis' => 'persen', 'margin_nilai' => 17, 'pembulatan' => 5000]);
    biayaUji($rab, 'orang', 137_000);
    biayaUji($rab, 'rombongan', 349_000);

    $p = HitungRab::ringkas($rab->fresh())['per_orang'];

    // Membagi tiap angka totalnya sendiri lalu membulatkan masing-masing
    // menghasilkan selisih yang terlihat seperti salah hitung.
    expect($p['modal'] + $p['margin'] + $p['pembulatan'])->toBe($p['harga']);
});

test('rincian per orang memakai dasar yang sama dengan harga jualnya', function () {
    $rab = rabUji([
        'jenis' => 'open', 'harga_dasar' => 'minimum', 'peserta_minimum' => 8,
        'jumlah_peserta' => 20, 'margin_jenis' => 'persen', 'margin_nilai' => 20, 'pembulatan' => 1,
    ]);
    biayaUji($rab, 'orang', 100_000);

    $r = HitungRab::ringkas($rab->fresh());

    // Harga open trip dihitung atas peserta MINIMUM; rinciannya harus ikut,
    // kalau tidak kolomnya menjumlah ke angka yang bukan harga jualnya.
    expect($r['per_orang']['harga'])->toBe($r['harga_per_orang'])
        ->and($r['per_orang']['modal'] + $r['per_orang']['margin'] + $r['per_orang']['pembulatan'])
        ->toBe($r['harga_per_orang']);
});
