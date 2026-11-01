<?php

use App\Models\Etalase\KatalogDestinasi;
use App\Models\Rab\HariLibur;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabItinerary;
use App\Models\Rab\RiwayatHarga;
use App\Support\Rab\HitungRab;

/**
 * Harga hari kerja vs akhir pekan / hari libur.
 *
 * Yang dijaga di sini: harga dipilih dari tanggal DIKUNJUNGI (bukan tanggal
 * berangkat), tarif akhir pekan tidak pernah bocor ke kolom hari kerja, dan
 * perjalanan yang lintas jenis hari disebut alih-alih dipilih diam-diam.
 *
 * Contoh angkanya dari Baluran: hari kerja Rp 21.000, akhir pekan Rp 31.000.
 */
function kepalaLibur(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-libur');

    return ['X-Orcha-Key' => 'kunci-uji-libur', 'Accept' => 'application/json'];
}

function baluran(array $ubah = []): MasterHarga
{
    return MasterHarga::create(array_merge([
        'kategori' => 'tiket',
        'nama' => 'Tiket Baluran',
        'provinsi' => 'Jawa Timur',
        'destinasi' => 'Taman Nasional Baluran',
        'satuan' => 'orang',
        'harga' => 21_000,
        'harga_akhir_pekan' => 31_000,
        'otomatis' => false,
        'aktif' => true,
    ], $ubah));
}

/** RAB berangkat pada $mulai, dengan satu kunjungan Baluran di hari ke-$hariKe. */
function rabBaluran(string $mulai, int $hariKe = 1, int $hari = 1): Rab
{
    $rab = Rab::create([
        'judul' => 'Private Trip Banyuwangi',
        'nama_pelanggan' => 'Pak Budi',
        'provinsi' => 'Jawa Timur',
        'tanggal_mulai' => $mulai,
        'jumlah_hari' => $hari,
        'jumlah_malam' => max(0, $hari - 1),
        'jumlah_peserta' => 10,
        'margin_jenis' => 'persen',
        'margin_nilai' => 20,
    ]);

    RabItinerary::create([
        'rab_id' => $rab->id,
        'hari_ke' => $hariKe,
        'urutan' => 1,
        'nama' => 'Safari Baluran',
        'destinasi' => 'Taman Nasional Baluran',
    ]);

    return $rab->fresh();
}

test('harga akhir pekan boleh kosong, dan kosong bukan nol', function () {
    $this->postJson('/api/v1/master-harga', [
        'kategori' => 'tiket', 'nama' => 'Tiket Ijen', 'provinsi' => 'Jawa Timur',
        'satuan' => 'orang', 'harga' => 100_000,
    ], kepalaLibur())->assertCreated()->assertJsonPath('data.harga_akhir_pekan', null);

    // Nol adalah harga yang SAH — ada tempat yang gratis di hari kerja — jadi
    // ia tidak boleh ditumpuk jadi satu arti dengan "tidak diisi".
    $this->postJson('/api/v1/master-harga', [
        'kategori' => 'tiket', 'nama' => 'Pantai Boom', 'provinsi' => 'Jawa Timur',
        'satuan' => 'orang', 'harga' => 0, 'harga_akhir_pekan' => 5_000,
    ], kepalaLibur())->assertCreated()
        ->assertJsonPath('data.harga_akhir_pekan', 5000)
        ->assertJsonPath('data.dua_harga', true);
});

test('tiket memakai tarif akhir pekan karena DIKUNJUNGI hari Sabtu, walau berangkat Jumat', function () {
    baluran();
    // Berangkat Jumat 8 Jan 2027, Baluran di hari ke-2 = Sabtu 9 Jan.
    $rab = rabBaluran('2027-01-08', hariKe: 2, hari: 3);

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    $biaya = RabBiaya::where('rab_id', $rab->id)->firstOrFail();

    expect($biaya->harga_satuan)->toBe(31_000)
        ->and($biaya->pakai_akhir_pekan)->toBeTrue()
        ->and($biaya->harga_sebab)->toContain('Sabtu');
});

test('tiket memakai tarif hari kerja dan tidak menyebut sebab apa pun', function () {
    baluran();
    $rab = rabBaluran('2027-01-04'); // Senin

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    $biaya = RabBiaya::where('rab_id', $rab->id)->firstOrFail();

    expect($biaya->harga_satuan)->toBe(21_000)
        ->and($biaya->pakai_akhir_pekan)->toBeFalse()
        ->and($biaya->harga_sebab)->toBeNull();
});

test('hari libur nasional di hari Selasa ikut memakai tarif akhir pekan', function () {
    baluran();
    HariLibur::create(['tanggal' => '2027-01-05', 'nama' => 'Cuti bersama']);

    $rab = rabBaluran('2027-01-05'); // Selasa

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    $biaya = RabBiaya::where('rab_id', $rab->id)->firstOrFail();

    expect($biaya->harga_satuan)->toBe(31_000)
        // Namanya yang disebut, bukan "akhir pekan": admin yang membaca
        // "Selasa" di sebelah tarif akhir pekan akan mengira sistem salah.
        ->and($biaya->harga_sebab)->toContain('Cuti bersama');
});

test('daftar hari libur yang kosong berarti hanya Sabtu & Minggu', function () {
    baluran();
    expect(HariLibur::count())->toBe(0);

    $rab = rabBaluran('2027-01-05'); // Selasa, tanpa daftar libur

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    expect(RabBiaya::where('rab_id', $rab->id)->value('harga_satuan'))->toBe(21_000);
});

test('tanpa tanggal berangkat, harga hari kerja yang dipakai', function () {
    baluran();
    $rab = Rab::create([
        'judul' => 'Belum ada tanggal', 'nama_pelanggan' => 'Pak Budi',
        'provinsi' => 'Jawa Timur', 'tanggal_mulai' => null,
        'jumlah_hari' => 2, 'jumlah_malam' => 1, 'jumlah_peserta' => 10,
        'margin_jenis' => 'persen', 'margin_nilai' => 20,
    ]);
    RabItinerary::create([
        'rab_id' => $rab->id, 'hari_ke' => 1, 'urutan' => 1,
        'nama' => 'Safari Baluran', 'destinasi' => 'Taman Nasional Baluran',
    ]);

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    expect(RabBiaya::where('rab_id', $rab->id)->value('harga_satuan'))->toBe(21_000);
});

test('baris akhir pekan tidak dikira "harga master berubah"', function () {
    baluran();
    $rab = rabBaluran('2027-01-09'); // Sabtu

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    $baris = collect(HitungRab::ringkas($rab->fresh())['baris'])->firstWhere('nama', 'Tiket Baluran');

    /*
     | Dibandingkan dengan sisi akhir pekan master, bukan hari kerjanya.
     | Tanpa ini baris ini selamanya berteriak "lebih mahal Rp 10.000", dan
     | admin akan menurunkannya — tepat di tanggal yang paling mahal.
     */
    expect($baris['harga_master_kini'])->toBeNull()
        ->and($baris['selisih_master'])->toBe(0)
        ->and($baris['pakai_akhir_pekan'])->toBeTrue();
});

test('simpan ke master dari baris akhir pekan menulis ke kolom akhir pekan', function () {
    $m = baluran();
    $rab = rabBaluran('2027-01-09'); // Sabtu

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    $biaya = RabBiaya::where('rab_id', $rab->id)->firstOrFail();
    // Tim menemukan tarif akhir pekan yang lebih murah.
    $biaya->update(['harga_satuan' => 28_000]);

    $this->postJson('/api/v1/rab/'.$rab->id.'/biaya/'.$biaya->id.'/ke-master', [], kepalaLibur())
        ->assertOk()->assertJsonPath('pesan', fn ($p) => str_contains($p, 'akhir pekan'));

    $m->refresh();

    expect($m->harga_akhir_pekan)->toBe(28_000)
        // Harga hari kerjanya TIDAK tersentuh. Kalau ikut berubah, seluruh
        // RAB berikutnya memakai Rp 28.000 di hari Selasa.
        ->and($m->harga)->toBe(21_000);

    $r = RiwayatHarga::where('master_harga_id', $m->id)->firstOrFail();
    expect($r->akhir_pekan_lama)->toBe(31_000)
        ->and($r->akhir_pekan_baru)->toBe(28_000)
        ->and($r->akhir_pekan_berubah)->toBeTrue();
});

test('pakai harga master pada baris akhir pekan mengambil sisi akhir pekan', function () {
    $m = baluran();
    $rab = rabBaluran('2027-01-09'); // Sabtu

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();
    $biaya = RabBiaya::where('rab_id', $rab->id)->firstOrFail();

    $m->update(['harga' => 23_000, 'harga_akhir_pekan' => 35_000]);

    $this->patchJson('/api/v1/rab/'.$rab->id.'/biaya/'.$biaya->id, [
        'pakai_harga_master' => true,
    ], kepalaLibur())->assertOk();

    expect($biaya->fresh()->harga_satuan)->toBe(35_000);
});

test('satuan per unit tidak dikali hari', function () {
    $rab = rabBaluran('2027-01-04', hari: 3);

    // Tiket masuk kendaraan roda 4: dibayar SEKALI di gerbang, bukan tiap hari.
    $gerbang = RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'tiket', 'nama' => 'Tiket masuk kendaraan',
        'satuan' => 'unit', 'harga_satuan' => 10_000, 'kapasitas' => 4,
        'jumlah' => 1, 'urutan' => 9,
    ]);

    // 10 peserta / 4 per kendaraan = 3 kendaraan, satu kali.
    expect(HitungRab::subtotal($gerbang, $rab))->toBe(30_000)
        ->and(HitungRab::faktor('unit', 4, $rab))->toBe(3)
        ->and(HitungRab::faktor('unit_hari', 4, $rab))->toBe(9)
        ->and(HitungRab::penjelasan($gerbang, $rab))->toContain('3 unit');
});

test('perjalanan lintas hari kerja & akhir pekan disebut, bukan dipilih diam-diam', function () {
    // Sewa bus per hari, dua harga — Jumat tarif biasa, Sabtu-Minggu tidak.
    MasterHarga::create([
        'kategori' => 'transportasi', 'nama' => 'Bus 45 seat', 'provinsi' => 'Jawa Timur',
        'satuan' => 'unit_hari', 'harga' => 3_000_000, 'harga_akhir_pekan' => 3_500_000,
        'kapasitas' => 45, 'otomatis' => true, 'aktif' => true,
    ]);
    baluran();

    $rab = rabBaluran('2027-01-08', hariKe: 2, hari: 3); // Jumat–Minggu

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())
        ->assertOk()
        ->assertJsonPath('harga_campuran', ['Bus 45 seat']);
});

test('perjalanan yang seluruh harinya sejenis tidak memberi peringatan campuran', function () {
    MasterHarga::create([
        'kategori' => 'transportasi', 'nama' => 'Bus 45 seat', 'provinsi' => 'Jawa Timur',
        'satuan' => 'unit_hari', 'harga' => 3_000_000, 'harga_akhir_pekan' => 3_500_000,
        'kapasitas' => 45, 'otomatis' => true, 'aktif' => true,
    ]);
    baluran();

    $rab = rabBaluran('2027-01-04', hari: 3); // Senin–Rabu

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())
        ->assertOk()->assertJsonPath('harga_campuran', []);
});

test('memindahkan kunjungan ke hari kerja menandai harga beku yang tak cocok lagi', function () {
    baluran();
    $rab = rabBaluran('2027-01-09'); // Sabtu

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();
    expect(RabBiaya::where('rab_id', $rab->id)->value('harga_satuan'))->toBe(31_000);

    // Perjalanannya digeser ke Senin. Harga TETAP beku — itu aturannya —
    // tetapi penawarannya kini kemahalan Rp 10.000 per orang.
    $rab->update(['tanggal_mulai' => '2027-01-04']);

    $ringkas = HitungRab::ringkas($rab->fresh());
    $baris = collect($ringkas['baris'])->firstWhere('nama', 'Tiket Baluran');

    expect($ringkas['ada_harga_salah_hari'])->toBeTrue()
        ->and($baris['harga_satuan'])->toBe(31_000)
        ->and($baris['harga_sebab_seharusnya'])->toContain('hari kerja');
});

test('tanggal berangkat yang dikosongkan disebut apa adanya', function () {
    baluran();
    $rab = rabBaluran('2027-01-09'); // Sabtu

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    // Tanpa penjagaan ini layar menulis "harga hari kerja ()" — tanda baca
    // tanpa isi, yang terbaca sebagai layar rusak, bukan tanggal yang hilang.
    $rab->update(['tanggal_mulai' => null]);

    $baris = collect(HitungRab::ringkas($rab->fresh())['baris'])->firstWhere('nama', 'Tiket Baluran');

    expect($baris['harga_sebab_seharusnya'])->toBe('tanggal berangkatnya dikosongkan');
});

/* -------------------- TIKET PER KENDARAAN -------------------- */

/** Tiket kendaraan Alas Purwo: dua jenis, destinasi yang sama persis. */
function tiketKendaraan(string $nama, int $harga, int $kapasitas): MasterHarga
{
    return MasterHarga::create([
        'kategori' => 'tiket',
        'nama' => $nama,
        'provinsi' => 'Jawa Timur',
        'destinasi' => 'Taman Nasional Baluran',
        'satuan' => 'unit',
        'harga' => $harga,
        'kapasitas' => $kapasitas,
        'otomatis' => false,
        'aktif' => true,
    ]);
}

test('tiket per kendaraan TIDAK ikut tertarik, melainkan ditawarkan', function () {
    baluran();
    tiketKendaraan('Tiket kendaraan roda 4', 10_000, 4);
    tiketKendaraan('Tiket kendaraan bus', 50_000, 40);

    $rab = rabBaluran('2027-01-04'); // Senin

    $balasan = $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    /*
     | Yang masuk HANYA tiket per orang. Ditarik semua, rombongan bermobil
     | ikut membayar tiket bus yang tidak pernah datang — modal naik tanpa
     | satu pun salah ketik.
     */
    $biaya = RabBiaya::where('rab_id', $rab->id)->get();

    expect($biaya)->toHaveCount(1)
        ->and($biaya->first()->nama)->toBe('Tiket Baluran');

    $balasan->assertJsonCount(2, 'data.tawaran_kendaraan')
        ->assertJsonPath('data.tawaran_kendaraan.0.nama', 'Tiket kendaraan roda 4')
        ->assertJsonPath('data.tawaran_kendaraan.1.nama', 'Tiket kendaraan bus');
});

test('tawaran kendaraan memakai tarif tanggal kunjungannya', function () {
    baluran();
    $m = tiketKendaraan('Tiket kendaraan roda 4', 10_000, 4);
    $m->update(['harga_akhir_pekan' => 15_000]);

    $rab = rabBaluran('2027-01-09'); // Sabtu

    // Tawaran yang berubah nominalnya begitu ditekan adalah tawaran yang
    // tidak dipercaya lagi.
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk()
        ->assertJsonPath('data.tawaran_kendaraan.0.harga', 15_000)
        ->assertJsonPath('data.tawaran_kendaraan.0.harga_sebab', fn ($s) => str_contains($s, 'Sabtu'));
});

test('tawaran yang sudah dipakai tidak ditawarkan lagi', function () {
    baluran();
    $m = tiketKendaraan('Tiket kendaraan bus', 50_000, 40);
    $rab = rabBaluran('2027-01-04');

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk()
        ->assertJsonCount(1, 'data.tawaran_kendaraan');

    // Admin memilihnya.
    $this->postJson('/api/v1/rab/'.$rab->id.'/biaya', [
        'master_harga_id' => $m->id,
    ], kepalaLibur())->assertOk()->assertJsonCount(0, 'data.tawaran_kendaraan');

    $baris = RabBiaya::where('rab_id', $rab->id)->where('master_harga_id', $m->id)->firstOrFail();

    // 10 peserta / 40 per bus = 1 bus, SEKALI — bukan dikali hari.
    expect($baris->satuan)->toBe('unit')
        ->and(HitungRab::subtotal($baris, $rab->fresh()))->toBe(50_000);
});

test('tawaran ikut di setiap pembacaan RAB, bukan cuma sesudah tarik biaya', function () {
    baluran();
    tiketKendaraan('Tiket kendaraan roda 4', 10_000, 4);
    $rab = rabBaluran('2027-01-04');

    // Admin yang membuka kembali RAB kemarin tidak menekan "Tarik biaya"
    // lagi; tawaran yang cuma muncul sekali adalah tawaran yang terlupakan.
    $this->getJson('/api/v1/rab/'.$rab->id, kepalaLibur())->assertOk()
        ->assertJsonCount(1, 'data.tawaran_kendaraan');
});

test('biaya wajib provinsi per kendaraan tidak ikut terisi saat RAB dibuat', function () {
    MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Parkir bus', 'provinsi' => 'Jawa Timur',
        'satuan' => 'unit', 'harga' => 25_000, 'kapasitas' => 40,
        'otomatis' => true, 'aktif' => true,
    ]);

    $rab = rabBaluran('2027-01-04');

    expect(RabBiaya::where('rab_id', $rab->id)->count())->toBe(0);
});

test('tempelan massal menolak satuan berkapasitas tanpa kapasitas', function () {
    /*
     | Lubang paling mahal kalau dibiarkan: menempel dua puluh rute carter
     | tanpa kapasitas membuat KEDUA PULUHNYA menghitung satu kendaraan
     | untuk rombongan sebesar apa pun. Formulir satu baris sudah menolaknya;
     | penolakan yang berlaku di satu pintu saja bukan penjagaan.
     */
    $this->postJson('/api/v1/master-harga/banyak', [
        'kategori' => 'transportasi',
        'satuan' => 'unit',
        'provinsi' => 'DI Yogyakarta',
        'baris' => [['nama' => 'Sewa Hiace Jogja-Banyuwangi 4D3N', 'harga' => 8_500_000]],
    ], kepalaLibur())->assertUnprocessable()->assertJsonValidationErrors('kapasitas');

    expect(MasterHarga::count())->toBe(0);
});

test('tempelan massal rute carter masuk sekali jalan', function () {
    $this->postJson('/api/v1/master-harga/banyak', [
        'kategori' => 'transportasi',
        // Carter per rute dibayar SEKALI per kendaraan, tidak dikali hari.
        'satuan' => 'unit',
        'provinsi' => 'DI Yogyakarta',
        'kapasitas' => 14,
        'baris' => [
            ['nama' => 'Sewa Hiace Jogja-Banyuwangi 4D3N', 'harga' => 8_500_000],
            ['nama' => 'Sewa Hiace Jogja-Bromo 3D2N', 'harga' => 6_500_000],
        ],
    ], kepalaLibur())->assertCreated()->assertJsonPath('masuk', 2);

    $m = MasterHarga::where('nama', 'Sewa Hiace Jogja-Banyuwangi 4D3N')->firstOrFail();

    expect($m->satuan)->toBe('unit')->and($m->kapasitas)->toBe(14);
});

test('carter per rute dihitung sekali, bukan dikali hari', function () {
    $rab = rabBaluran('2027-01-04', hari: 4);

    $carter = RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'transportasi',
        'nama' => 'Sewa Hiace Jogja-Banyuwangi 4D3N',
        'satuan' => 'unit', 'harga_satuan' => 8_500_000, 'kapasitas' => 14,
        'jumlah' => 1, 'urutan' => 1,
    ]);

    /*
     | 10 peserta / 14 per Hiace = 1 unit, SEKALI. Dengan satuan unit_hari,
     | angka yang sama jadi 8,5 jt x 4 hari = Rp 34 juta — empat kali lipat,
     | dan tidak ada yang terlihat salah karena 8.500.000 memang harganya.
     */
    expect(HitungRab::subtotal($carter, $rab))->toBe(8_500_000)
        ->and(HitungRab::faktor('unit_hari', 14, $rab))->toBe(4);
});

test('kenaikan massal menaikkan kedua harga sekaligus', function () {
    $m = baluran();

    $this->postJson('/api/v1/master-harga/massal', [
        'id' => [$m->id], 'aksi' => 'naik', 'persen' => 10,
    ], kepalaLibur())->assertOk();

    $m->refresh();

    /*
     | Keduanya naik. Menaikkan hari kerjanya saja membuat selisih akhir
     | pekan menyusut tiap kenaikan — sampai suatu hari tarif akhir pekan
     | jadi lebih murah daripada hari kerja, tanpa satu pun tanda.
     */
    expect($m->harga)->toBe(23_100)
        ->and($m->harga_akhir_pekan)->toBe(34_100);
});

test('riwayat mencatat harga akhir pekan yang berubah sendirian', function () {
    $m = baluran();

    $this->patchJson('/api/v1/master-harga/'.$m->id, [
        'kategori' => 'tiket', 'nama' => 'Tiket Baluran', 'provinsi' => 'Jawa Timur',
        'destinasi' => 'Taman Nasional Baluran', 'satuan' => 'orang',
        'harga' => 21_000, 'harga_akhir_pekan' => 36_000,
    ], kepalaLibur())->assertOk();

    $r = RiwayatHarga::where('master_harga_id', $m->id)->firstOrFail();

    // Hari kerjanya TETAP, jadi tanpa kolom akhir pekan baris ini tidak ada.
    expect($r->harga_lama)->toBe(21_000)
        ->and($r->harga_baru)->toBe(21_000)
        ->and($r->akhir_pekan_lama)->toBe(31_000)
        ->and($r->akhir_pekan_baru)->toBe(36_000);
});

test('hari libur yang jatuh di Sabtu dikatakan memang sudah akhir pekan', function () {
    $this->postJson('/api/v1/hari-libur', [
        'tanggal' => '2027-01-09', 'nama' => 'Libur contoh',
    ], kepalaLibur())->assertCreated()
        ->assertJsonPath('data.sudah_akhir_pekan', true)
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'sudah'));

    // Tanggal yang sama tidak bisa didaftarkan dua kali.
    $this->postJson('/api/v1/hari-libur', [
        'tanggal' => '2027-01-09', 'nama' => 'Libur contoh lagi',
    ], kepalaLibur())->assertUnprocessable()->assertJsonValidationErrors('tanggal');
});

test('hari libur bisa ditempel banyak sekaligus, yang sudah ada dilewati', function () {
    HariLibur::create(['tanggal' => '2027-01-01', 'nama' => 'Tahun Baru']);

    $this->postJson('/api/v1/hari-libur/banyak', ['baris' => [
        ['tanggal' => '2027-01-01', 'nama' => 'Tahun Baru'],
        ['tanggal' => '2027-08-17', 'nama' => 'HUT RI'],
        ['tanggal' => '2027-03-19', 'nama' => 'Nyepi'],
    ]], kepalaLibur())->assertCreated()
        ->assertJsonPath('masuk', 2)
        ->assertJsonPath('lewat', ['2027-01-01']);

    expect(HariLibur::count())->toBe(3);
});

test('menghapus hari libur tidak mengubah RAB yang sudah dibuat', function () {
    baluran();
    $libur = HariLibur::create(['tanggal' => '2027-01-05', 'nama' => 'Cuti bersama']);
    $rab = rabBaluran('2027-01-05'); // Selasa

    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaLibur())->assertOk();

    $this->deleteJson('/api/v1/hari-libur/'.$libur->id, [], kepalaLibur())->assertOk();

    // Harga dibekukan per baris, jadi penawaran yang sudah dikirim tidak
    // bergeser gara-gara daftar liburnya disunting.
    expect(RabBiaya::where('rab_id', $rab->id)->value('harga_satuan'))->toBe(31_000);
});

/* -------------------- CARI TEMPAT -------------------- */

test('tempat yang hanya ada di master harga tetap ketemu', function () {
    baluran();

    /*
     | Kasus yang dilaporkan: "Baluran" punya tiket di master harga tetapi
     | belum pernah masuk katalog destinasi, jadi mengetik "baluran" di
     | penyusun RAB memulangkan "tidak ada destinasi bernama baluran".
     | Admin lalu mengetiknya sebagai kegiatan bebas — dan tiketnya tidak
     | pernah ikut tertarik.
     */
    $this->getJson('/api/v1/rab/cari-tempat?q=baluran', kepalaLibur())
        ->assertOk()
        ->assertJsonPath('data.0.nama', 'Taman Nasional Baluran')
        ->assertJsonPath('data.0.asal', 'master')
        ->assertJsonPath('data.0.punya_tiket', true)
        ->assertJsonPath('data.0.provinsi', 'Jawa Timur');
});

test('satu pencarian memulangkan tempat dari provinsi mana pun', function () {
    MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Pantai Melasti', 'provinsi' => 'Bali',
        'destinasi' => 'Pantai Melasti', 'satuan' => 'orang', 'harga' => 15_000,
        'otomatis' => false, 'aktif' => true,
    ]);
    MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Pantai Pulau Merah', 'provinsi' => 'Jawa Timur',
        'destinasi' => 'Pantai Pulau Merah', 'satuan' => 'orang', 'harga' => 10_000,
        'otomatis' => false, 'aktif' => true,
    ]);

    /*
     | RAB hanya punya SATU kolom provinsi, sementara Jogja-Bromo melewati
     | dua. Pencarian yang ikut dikunci ke provinsi RAB membuat perjalanan
     | lintas provinsi tidak bisa disusun sama sekali.
     */
    $nama = collect($this->getJson('/api/v1/rab/cari-tempat?q=pantai', kepalaLibur())
        ->assertOk()->json('data'))->pluck('nama');

    expect($nama)->toContain('Pantai Melasti')
        ->and($nama)->toContain('Pantai Pulau Merah');
});

test('tempat dari master menyatu dengan katalog bawaan dalam satu daftar', function () {
    MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Bromo via Penanjakan', 'provinsi' => 'Jawa Timur',
        'destinasi' => 'Gunung Bromo', 'satuan' => 'orang', 'harga' => 320_000,
        'otomatis' => false, 'aktif' => true,
    ]);

    $hasil = collect($this->getJson('/api/v1/rab/cari-tempat?q=bromo', kepalaLibur())
        ->assertOk()->json('data'));

    // "Bromo Tengger Semeru" dari katalog bawaan, "Gunung Bromo" dari master
    // harga — satu kotak cari, bukan dua daftar yang harus dibuka bergantian.
    expect($hasil->pluck('nama'))->toContain('Bromo Tengger Semeru')
        ->and($hasil->pluck('nama'))->toContain('Gunung Bromo')
        ->and($hasil->firstWhere('nama', 'Gunung Bromo')['asal'])->toBe('master')
        ->and($hasil->firstWhere('nama', 'Bromo Tengger Semeru')['asal'])->toBe('katalog');
});

test('biaya yang bukan tempat disebut terpisah, tidak jadi kegiatan', function () {
    MasterHarga::create([
        'kategori' => 'transportasi', 'nama' => 'Sewa Hiace Jogja-Banyuwangi 4D3N',
        'provinsi' => 'DI Yogyakarta', 'satuan' => 'unit', 'harga' => 8_500_000,
        'kapasitas' => 14, 'otomatis' => false, 'aktif' => true,
    ]);

    /*
     | Mengetik "hiace" wajar dilakukan. Menjadikannya kegiatan di itinerary
     | adalah kesalahan yang terlihat benar — jadi ia dipulangkan di kotak
     | yang berbeda, bukan dicampur ke hasil tempat.
     */
    $this->getJson('/api/v1/rab/cari-tempat?q=hiace', kepalaLibur())
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('bukan_tempat.0.nama', 'Sewa Hiace Jogja-Banyuwangi 4D3N');
});

test('satu huruf tidak memulangkan katalog yang diacak', function () {
    baluran();

    $this->getJson('/api/v1/rab/cari-tempat?q=b', kepalaLibur())
        ->assertOk()->assertJsonCount(0, 'data');
});

test('nama yang ada di kedua daftar dipulangkan sekali', function () {
    // Destinasi yang ada di katalog DAN punya harga di master.
    $m = baluran();
    KatalogDestinasi::create([
        'nama' => 'Taman Nasional Baluran', 'provinsi' => 'Jawa Timur', 'daerah' => 'Banyuwangi',
    ]);

    $this->getJson('/api/v1/rab/cari-tempat?q=baluran', kepalaLibur())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        // Keterangannya dari katalog — di sanalah daerahnya tercatat.
        ->assertJsonPath('data.0.asal', 'katalog')
        ->assertJsonPath('data.0.daerah', 'Banyuwangi')
        ->assertJsonPath('data.0.punya_tiket', true);

    expect($m->destinasi)->toBe('Taman Nasional Baluran');
});
