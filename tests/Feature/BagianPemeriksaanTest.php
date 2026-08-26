<?php

use App\Models\SewaKendaraan\BagianPemeriksaan;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\Pemeriksaan;

const KUNCI_BAGIAN = 'kunci-uji-bagian';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_BAGIAN);
    config()->set('orcha.api.ip_diizinkan', []);
    Pemeriksaan::lupakan();
});

function kepalaBagian(): array
{
    return [
        'X-Orcha-Key' => KUNCI_BAGIAN,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

/**
 * Unit uji sendiri, bukan buatMobil() milik SewaKendaraanTest.
 *
 * Fungsi global Pest hanya ada bila berkas yang mendefinisikannya ikut dimuat;
 * menjalankan berkas ini sendirian membuatnya tidak ditemukan.
 */
function mobilBagian(): Car
{
    return Car::create([
        'name' => 'Avanza Uji Bagian',
        'brand' => 'Toyota',
        'type' => 'mobil',
        'price_per_day' => 350000,
        'transmission' => 'Manual',
        'capacity' => 7,
        'is_available' => true,
    ]);
}

function isiBagian(array $ubah = []): array
{
    return array_merge([
        'label' => 'Pintu bagasi samping',
        'jenis' => ['bus'],
        'biaya_lecet' => 150000,
        'biaya_rusak' => 900000,
        'biaya_hilang' => 1800000,
    ], $ubah);
}

test('dua belas bagian bawaan tertanam dari config', function () {
    // Ditanam lewat migrasi, bukan seeder: seeder harus diingat untuk
    // dijalankan, dan yang lupa mendapat ceklis serah terima kosong tanpa satu
    // pun pesan yang menjelaskan sebabnya.
    expect(BagianPemeriksaan::count())->toBe(count(config('orcha.pemeriksaan_kendaraan')));

    $bodi = BagianPemeriksaan::where('kunci', 'bodi_depan')->first();

    // Kunci dan tarifnya disalin apa adanya, jadi kondisi unit yang sudah
    // tersimpan tetap menunjuk ke baris yang benar.
    expect($bodi->label)->toBe('Bodi depan & bemper')
        ->and($bodi->biaya_rusak)->toBe(config('orcha.biaya_kerusakan.bodi_depan.rusak'))
        // Bawaannya berlaku untuk semua jenis — persis seperti sebelumnya
        ->and($bodi->jenis)->toBe(array_keys(config('orcha.jenis_kendaraan')));
});

test('bagian baru hanya muncul pada jenis kendaraan yang dipilih', function () {
    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian(), kepalaBagian())
        ->assertCreated();

    // Bus tidak punya ban serep sebagaimana mobil, dan ceklis yang memuat
    // bagian tak berlaku hanya akan diisi "Baik" tanpa pernah diperiksa.
    expect(Pemeriksaan::untuk('bus'))->toHaveKey('pintu_bagasi_samping')
        ->and(Pemeriksaan::untuk('mobil'))->not->toHaveKey('pintu_bagasi_samping');
});

test('kunci dibuatkan dari labelnya dan tidak pernah bertabrakan', function () {
    // Yang mengisi formulir memikirkan "AC blower atas", bukan "ac_blower_atas".
    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian(['label' => 'AC Blower Atas']), kepalaBagian())
        ->assertCreated()->assertJsonPath('data.kunci', 'ac_blower_atas');

    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian(['label' => 'AC Blower Atas']), kepalaBagian())
        ->assertCreated()->assertJsonPath('data.kunci', 'ac_blower_atas_2');
});

test('kunci tidak ikut berubah saat labelnya diperbaiki', function () {
    $bagian = BagianPemeriksaan::where('kunci', 'kaca')->first();

    $this->patchJson("/api/v1/bagian-pemeriksaan/{$bagian->id}", isiBagian([
        'kunci' => 'kaca_dan_spion',
        'label' => 'Kaca, spion & wiper',
        'jenis' => ['mobil'],
    ]), kepalaBagian())->assertOk();

    // Ribuan baris kondisi menunjuk ke kuncinya; labelnya boleh diperbaiki
    // ejaannya kapan saja, kuncinya tidak.
    expect($bagian->fresh()->kunci)->toBe('kaca')
        ->and($bagian->fresh()->label)->toBe('Kaca, spion & wiper');
});

test('tarif wajib diisi walau nol', function () {
    // Bagian tanpa tarif membuat usulan denda diam-diam melewatinya:
    // perhitungannya tetap jalan, angkanya kurang, dan tidak ada yang
    // memberi tahu. Nol yang ditulis sadar berbeda dari nol karena lupa.
    $tanpaTarif = isiBagian();
    unset($tanpaTarif['biaya_rusak']);

    $this->postJson('/api/v1/bagian-pemeriksaan', $tanpaTarif, kepalaBagian())
        ->assertStatus(422)->assertJsonValidationErrors('biaya_rusak');

    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian(['biaya_rusak' => 0]), kepalaBagian())
        ->assertCreated();
});

test('bagian tanpa jenis mana pun ditolak', function () {
    // Tidak akan pernah muncul di formulir siapa pun, dan admin yang
    // menyimpannya akan mengira ia sudah terpasang.
    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian(['jenis' => []]), kepalaBagian())
        ->assertStatus(422)->assertJsonValidationErrors('jenis');
});

test('bagian yang sudah tercatat di serah terima tidak bisa dihapus', function () {
    $mobil = mobilBagian();

    PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Manual',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 350000, 'status' => 'selesai',
        'kondisi_akhir' => ['kaca' => 'rusak'],
    ]);

    $kaca = BagianPemeriksaan::where('kunci', 'kaca')->first();

    // Menghapusnya membuat namanya hilang dari lembar itu dan tersisa kunci
    // mentahnya — tepat pada dokumen yang dipakai berbantahan dengan penyewa.
    $this->deleteJson("/api/v1/bagian-pemeriksaan/{$kaca->id}", [], kepalaBagian())
        ->assertStatus(422);

    expect(BagianPemeriksaan::where('kunci', 'kaca')->exists())->toBeTrue();
});

test('bagian yang belum pernah dipakai boleh dihapus', function () {
    $baru = $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian(), kepalaBagian())
        ->assertCreated()->json('data');

    $this->deleteJson("/api/v1/bagian-pemeriksaan/{$baru['id']}", [], kepalaBagian())
        ->assertOk();

    expect(BagianPemeriksaan::find($baru['id']))->toBeNull();
});

test('bagian yang dinonaktifkan berhenti diisi tetapi namanya tetap terbaca', function () {
    $kaca = BagianPemeriksaan::where('kunci', 'kaca')->first();
    $kaca->update(['aktif' => false]);
    Pemeriksaan::lupakan();

    expect(Pemeriksaan::untuk('mobil'))->not->toHaveKey('kaca')
        // Lembar serah terima setahun lalu tetap harus bisa menyebut namanya,
        // bukan kunci mentahnya.
        ->and(Pemeriksaan::label())->toHaveKey('kaca')
        // Dan dendanya tetap terhitung: unit yang terlanjur diserahkan dengan
        // bagian itu masih bisa kembali dalam keadaan rusak.
        ->and(Pemeriksaan::tarif())->toHaveKey('kaca');
});

test('rujukan mengirim daftar baca dan daftar isi secara terpisah', function () {
    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian(), kepalaBagian())->assertCreated();

    $meta = $this->getJson('/api/v1/rujukan', kepalaBagian())->assertOk()->json('data');

    // Yang DIBACA: seluruhnya, apa pun jenisnya
    expect($meta['pemeriksaan_kendaraan'])->toHaveKey('pintu_bagasi_samping')
        // Yang DIISI: dipilah per jenis unit
        ->and($meta['pemeriksaan_per_jenis']['bus'])->toHaveKey('pintu_bagasi_samping')
        ->and($meta['pemeriksaan_per_jenis']['mobil'])->not->toHaveKey('pintu_bagasi_samping')
        // Tarifnya ikut dari tabel, bukan lagi dari config
        ->and($meta['biaya_kerusakan']['pintu_bagasi_samping']['rusak'])->toBe(900000);
});

test('daftar bagian dipenggal per halaman dan bisa dicari', function () {
    $data = $this->getJson('/api/v1/bagian-pemeriksaan?per_halaman=5', kepalaBagian())
        ->assertOk()->json();

    expect($data['data'])->toHaveCount(5)
        ->and($data['meta']['total'])->toBe(12)
        ->and($data['meta']['halaman_terakhir'])->toBe(3);

    // Halaman terakhir memuat sisanya, bukan lima lagi
    $akhir = $this->getJson('/api/v1/bagian-pemeriksaan?per_halaman=5&page=3', kepalaBagian())
        ->assertOk()->json();

    expect($akhir['data'])->toHaveCount(2);

    // Dua belas baris muat di layar, tetapi begitu admin menambahkan
    // bagiannya sendiri mencari satu nama jadi menggulung terus.
    $cari = $this->getJson('/api/v1/bagian-pemeriksaan?cari=kaca', kepalaBagian())
        ->assertOk()->json('data');

    expect($cari)->toHaveCount(1)->and($cari[0]['kunci'])->toBe('kaca');
});

test('daftar bagian bisa disaring per jenis unit', function () {
    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian(), kepalaBagian())->assertCreated();

    $bus = $this->getJson('/api/v1/bagian-pemeriksaan?jenis=bus', kepalaBagian())
        ->assertOk()->json('data');

    $mobil = $this->getJson('/api/v1/bagian-pemeriksaan?jenis=mobil', kepalaBagian())
        ->assertOk()->json('data');

    // Bawaan berlaku untuk semua jenis; yang baru hanya untuk bus
    expect(collect($bus)->pluck('kunci'))->toContain('pintu_bagasi_samping')
        ->and(collect($mobil)->pluck('kunci'))->not->toContain('pintu_bagasi_samping');
});

test('kunci terpakai dihitung sekali, bukan sekali per baris', function () {
    $mobil = mobilBagian();

    // Kondisi awal lebih panjang daripada kondisi akhir: bentuk yang dulu
    // memakai "+" pada array berindeks angka menghilangkan kunci pertama milik
    // kondisi_akhir diam-diam justru pada susunan seperti ini.
    PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Manual',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 350000, 'status' => 'selesai',
        'kondisi_awal' => ['bodi_depan' => 'baik', 'bodi_belakang' => 'baik', 'ban' => 'baik'],
        'kondisi_akhir' => ['interior' => 'rusak'],
    ]);

    $daftar = collect($this->getJson('/api/v1/bagian-pemeriksaan?per_halaman=100', kepalaBagian())
        ->assertOk()->json('data'))->keyBy('kunci');

    expect($daftar['interior']['pernah_dipakai'])->toBeTrue()
        ->and($daftar['bodi_depan']['pernah_dipakai'])->toBeTrue()
        ->and($daftar['kaca']['pernah_dipakai'])->toBeFalse();
});

test('bagian baru langsung ikut dijaga saat kondisi unit dan serah terima disimpan', function () {
    /*
     | Rantai penuhnya: bagian ditambahkan lewat API → ikut daftar per jenis →
     | diterima penjagaan masukan → tarifnya ikut menghitung usulan denda.
     |
     | Yang diuji di sini bukan tiap potongannya, melainkan bahwa potongan itu
     | benar-benar tersambung: bagian yang baru dibuat harus DITERIMA saat
     | dikirim, dan bagian yang tidak berlaku untuk jenis unitnya harus
     | ditolak diam-diam.
     */
    $mobil = mobilBagian();

    $baru = $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian([
        'label' => 'Kamera mundur',
        'jenis' => ['mobil'],
        'biaya_lecet' => 100000, 'biaya_rusak' => 700000, 'biaya_hilang' => 1400000,
    ]), kepalaBagian())->assertCreated()->json('data');

    // Bagian khusus bus, TIDAK berlaku untuk mobil
    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian([
        'label' => 'Pintu bagasi samping', 'jenis' => ['bus'],
    ]), kepalaBagian())->assertCreated();

    $this->patchJson("/api/v1/kendaraan/{$mobil->id}/kondisi", [
        'kondisi' => [
            'kamera_mundur' => 'rusak',
            'pintu_bagasi_samping' => 'rusak',
            'kaca' => 'baik',
        ],
    ], kepalaBagian())->assertOk();

    $tersimpan = $mobil->fresh()->kondisi_terkini;

    expect($tersimpan)->toHaveKey('kamera_mundur')
        ->and($tersimpan['kamera_mundur'])->toBe('rusak')
        // Bagian bus tidak nyangkut di catatan mobil
        ->and($tersimpan)->not->toHaveKey('pintu_bagasi_samping');

    expect($baru['kunci'])->toBe('kamera_mundur');
});

test('tarif bagian baru ikut menghitung usulan denda kerusakan', function () {
    $mobil = mobilBagian();

    $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian([
        'label' => 'Kamera mundur', 'jenis' => ['mobil'],
        'biaya_lecet' => 100000, 'biaya_rusak' => 700000, 'biaya_hilang' => 1400000,
    ]), kepalaBagian())->assertCreated();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Manual',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 350000, 'status' => 'selesai',
        'kondisi_awal' => ['kamera_mundur' => 'baik'],
        'kondisi_akhir' => ['kamera_mundur' => 'rusak'],
    ]);

    // Tanpa tarif dari tabel, usulannya akan Rp 0 tanpa satu pun tanda bahwa
    // ada bagian yang terlewat — itulah sebabnya tarif dijadikan wajib.
    expect($sewa->denda_kerusakan_usulan)->toBe(700000);

    // Dan namanya disebut, bukan kunci mentahnya
    expect(collect($sewa->kerusakan_baru)->pluck('bagian'))->toContain('Kamera mundur');
});

test('bagian yang dinonaktifkan tetap terbaca namanya di serah terima lama', function () {
    $mobil = mobilBagian();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Manual',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 350000, 'status' => 'selesai',
        'kondisi_awal' => ['kaca' => 'baik'],
        'kondisi_akhir' => ['kaca' => 'rusak'],
    ]);

    BagianPemeriksaan::where('kunci', 'kaca')->update(['aktif' => false]);
    Pemeriksaan::lupakan();

    // Lembar setahun lalu tetap menyebut namanya, bukan kunci mentahnya —
    // ini dokumen yang dipakai berbantahan dengan penyewa.
    expect(collect($sewa->fresh()->kerusakan_baru)->pluck('bagian'))->toContain('Kaca & spion')
        // Dan dendanya tetap terhitung
        ->and($sewa->fresh()->denda_kerusakan_usulan)->toBeGreaterThan(0);
});

test('daftar pengelolaan urut dari yang terbaru, ceklis serah terima tidak ikut dibalik', function () {
    $baru = $this->postJson('/api/v1/bagian-pemeriksaan', isiBagian([
        'label' => 'Kamera mundur', 'jenis' => ['mobil'],
    ]), kepalaBagian())->assertCreated()->json('data');

    // Yang dicari admin saat membuka halaman ini adalah yang baru saja ia
    // tambahkan, bukan yang sudah setahun tenang di bawah.
    $daftar = $this->getJson('/api/v1/bagian-pemeriksaan', kepalaBagian())
        ->assertOk()->json('data');

    expect($daftar[0]['kunci'])->toBe($baru['kunci']);

    /*
     | Tetapi ceklisnya TIDAK ikut dibalik: di sana urutannya mengikuti jalan
     | memeriksa unit — bodi depan, belakang, kanan, kiri, lalu kaca dan lampu
     | — dan membaliknya membuat petugas melompat-lompat mengelilingi mobil.
     */
    Pemeriksaan::lupakan();
    $ceklis = array_keys(Pemeriksaan::untuk('mobil'));

    expect($ceklis[0])->toBe('bodi_depan')
        ->and($ceklis[1])->toBe('bodi_belakang')
        // Yang baru ditambahkan masuk ke belakang, bukan menyerobot ke depan
        ->and(end($ceklis))->toBe('kamera_mundur');
});
