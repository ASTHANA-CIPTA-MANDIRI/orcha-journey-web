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

test('titik impas kosong saat harga tidak menutup biaya per orangnya sendiri', function () {
    // Tidak ada jumlah peserta yang menolong harga semacam itu; angka BEP di
    // situ hanya akan menyesatkan orang yang membacanya.
    $rab = rabUji(['jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 0, 'pembulatan' => 1]);

    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'tiket', 'nama' => 'Tiket mahal',
        'satuan' => 'orang', 'harga_satuan' => 100_000, 'jumlah' => 1, 'urutan' => 1]);

    $r = HitungRab::ringkas($rab->fresh());

    // Harga = modal per orang, jadi sumbangannya nol — tidak ada yang tersisa
    // untuk menutup biaya tetap.
    expect($r['sumbangan_per_orang'])->toBe(0)
        ->and($r['bep_peserta'])->toBeNull()
        ->and($r['bep_tercapai'])->toBeFalse();
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
