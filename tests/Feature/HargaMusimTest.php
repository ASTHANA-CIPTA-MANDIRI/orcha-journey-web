<?php

use App\Models\Rab\HargaMusim;
use App\Models\Rab\HariLibur;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\MusimHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabItinerary;
use App\Models\Rab\RiwayatHarga;
use App\Models\Rab\VersiPenawaran;
use App\Support\Rab\HargaHari;
use App\Support\Rab\HitungRab;
use Illuminate\Support\Carbon;

/**
 * Harga MUSIM — Nataru, Lebaran, long weekend.
 *
 * Tingkat ketiga di atas hari kerja & akhir pekan, dan yang dijaga di sini
 * adalah urutan menangnya: musim → akhir pekan → hari kerja. Membiarkan akhir
 * pekan menang membuat Nataru dibayar tarif hari Sabtu, yaitu persis
 * kesalahan yang jadi alasan musim dibuat.
 *
 * Yang kedua: tarif musim TIDAK BOLEH bocor ke kolom akhir pekan. Satu kali
 * bocor, setiap Sabtu biasa sepanjang tahun ikut jadi tarif Nataru.
 *
 * Angkanya dari Hotel Dieng: hari biasa Rp 450.000, akhir pekan Rp 550.000,
 * Nataru Rp 900.000.
 */
function kepalaMusim(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-musim');

    return ['X-Orcha-Key' => 'kunci-uji-musim', 'Accept' => 'application/json'];
}

function musimNataru(array $ubah = []): MusimHarga
{
    return MusimHarga::create(array_merge([
        'nama' => 'Nataru 2026/2027',
        'tanggal_mulai' => '2026-12-20',
        'tanggal_sampai' => '2027-01-05',
        'prioritas' => 0,
        'aktif' => true,
    ], $ubah));
}

function hotelMusim(array $ubah = []): MasterHarga
{
    return MasterHarga::create(array_merge([
        'kategori' => 'akomodasi',
        'nama' => 'Hotel Dieng',
        'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Dieng',
        'satuan' => 'kamar_malam',
        'kapasitas' => 2,
        'harga' => 450_000,
        'harga_akhir_pekan' => 550_000,
        'otomatis' => false,
        'aktif' => true,
    ], $ubah));
}

/** RAB berangkat $mulai, satu kunjungan Dieng di hari ke-$hariKe. */
function rabMusim(string $mulai, int $hariKe = 1, int $hari = 1): Rab
{
    $rab = Rab::create([
        'judul' => 'Private Trip Dieng',
        'nama_pelanggan' => 'Bu Sari',
        'provinsi' => 'Jawa Tengah',
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
        'nama' => 'Menginap di Dieng',
        'destinasi' => 'Dieng',
    ]);

    return $rab->fresh();
}

/* ======================= TANGGA TIGA TINGKAT ======================= */

it('memakai tarif musim, bukan tarif akhir pekan, pada tanggal yang keduanya', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    // 26 Desember 2026 jatuh hari SABTU dan ada di dalam Nataru.
    $pilih = $hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-26'));

    expect($pilih['harga'])->toBe(900_000, 'tanggal yang masuk musim DAN akhir pekan harus memakai tarif musim');
    expect($pilih['tingkat'])->toBe('musim');
    expect($pilih['musim_id'])->toBe($musim->id);
});

it('tidak menandai baris musim sebagai akhir pekan', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $pilih = $hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-26'));

    /*
     | Penanda ini berarti "angkanya dari kolom harga_akhir_pekan". Diisi
     | true, "Simpan ke master" akan menulis Rp 900.000 ke kolom itu — lalu
     | setiap Sabtu biasa sepanjang tahun ikut jadi tarif Nataru.
     */
    expect($pilih['akhir_pekan'])->toBeFalse(
        'tarif musim tidak boleh berpenanda akhir pekan — ia akan ditulis ke kolom yang salah',
    );
});

it('memakai tarif musim walau tanggalnya hari kerja biasa', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    // 23 Desember 2026 = Rabu.
    expect($hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-23'))['harga'])->toBe(900_000);
});

it('mengalahkan daftar hari libur', function () {
    HariLibur::create(['tanggal' => '2026-12-25', 'nama' => 'Natal']);
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    expect($hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-25'), HariLibur::peta())['harga'])
        ->toBe(900_000, 'tanggal merah di dalam musim tetap memakai tarif musim');
});

it('jatuh balik ke tarif akhir pekan bila baris itu tidak punya harga musim', function () {
    musimNataru();
    $hotel = hotelMusim();
    // Sengaja TIDAK diberi harga musim: tidak semua harga naik di Nataru.

    // 26 Des 2026 = Sabtu, di dalam Nataru.
    $pilih = $hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-26'));

    expect($pilih['harga'])->toBe(550_000);
    expect($pilih['tingkat'])->toBe('akhir_pekan');
});

it('kedua ujung rentang musim ikut terhitung', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);
    $hotel = $hotel->fresh();

    expect($hotel->hargaUntuk(Carbon::parse('2026-12-20'))['harga'])->toBe(900_000, 'tanggal mulai ikut');
    expect($hotel->hargaUntuk(Carbon::parse('2027-01-05'))['harga'])->toBe(900_000, 'tanggal selesai ikut');
    /*
     | Sehari di luar kedua ujungnya bukan lagi tarif musim.
     |
     | Yang diperiksa TINGKATNYA, bukan 'hari_kerja': 19 Desember 2026 jatuh
     | hari Sabtu, jadi ia memang turun ke tarif akhir pekan — dan itu benar.
     */
    expect($hotel->hargaUntuk(Carbon::parse('2026-12-19'))['tingkat'])
        ->not->toBe('musim', 'sehari sebelum rentangnya: di luar musim');
    expect($hotel->hargaUntuk(Carbon::parse('2027-01-06'))['tingkat'])
        ->not->toBe('musim', 'sehari sesudah rentangnya: di luar musim');
});

/* ========================= MUSIM BERTUMPUK ========================= */

it('musim berprioritas lebih tinggi menang di tanggal yang bertumpuk', function () {
    $besar = musimNataru();
    $kecil = MusimHarga::create([
        'nama' => 'Puncak Tahun Baru',
        'tanggal_mulai' => '2026-12-31',
        'tanggal_sampai' => '2027-01-01',
        'prioritas' => 10,
        'aktif' => true,
    ]);

    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $besar->id, 'harga' => 900_000]);
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $kecil->id, 'harga' => 1_500_000]);

    expect($hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-31'))['harga'])->toBe(1_500_000);
    expect($hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-28'))['harga'])->toBe(900_000);
});

it('rentang yang lebih sempit menang bila prioritasnya seri', function () {
    $besar = musimNataru();
    $kecil = MusimHarga::create([
        'nama' => 'Puncak Tahun Baru',
        'tanggal_mulai' => '2026-12-31',
        'tanggal_sampai' => '2027-01-01',
        'prioritas' => 0,
        'aktif' => true,
    ]);

    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $besar->id, 'harga' => 900_000]);
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $kecil->id, 'harga' => 1_500_000]);

    expect($hotel->fresh()->hargaUntuk(Carbon::parse('2027-01-01'))['harga'])->toBe(1_500_000);
});

it('melewati musim pemenang yang tidak punya harga di baris itu', function () {
    $nataru = musimNataru();
    MusimHarga::create([
        'nama' => 'Cuti Bersama',
        'tanggal_mulai' => '2026-12-28',
        'tanggal_sampai' => '2026-12-28',
        'prioritas' => 50,
        'aktif' => true,
    ]);

    $hotel = hotelMusim();
    // Hanya Nataru yang punya angka; cuti bersama menang tetapi kosong.
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $nataru->id, 'harga' => 900_000]);

    /*
     | Yang benar adalah tarif Nataru, bukan jatuh ke akhir pekan hanya karena
     | musim pemenangnya tidak punya angka di baris ini.
     */
    expect($hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-28'))['harga'])->toBe(900_000);
});

it('musim yang dimatikan tidak dipakai', function () {
    $musim = musimNataru(['aktif' => false]);
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    expect($hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-23'))['tingkat'])->toBe('hari_kerja');
});

/* ===================== DIBEKUKAN DI BARIS BIAYA ===================== */

it('membekukan tingkat dan musimnya saat biaya ditarik', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();

    $biaya = $rab->fresh()->biaya->firstWhere('master_harga_id', $hotel->id);

    expect($biaya->harga_satuan)->toBe(900_000);
    expect($biaya->tingkat_harga)->toBe('musim');
    expect($biaya->musim_id)->toBe($musim->id);
    expect($biaya->pakai_akhir_pekan)->toBeFalse();
    expect($biaya->harga_sebab)->toContain('Nataru');
});

it('tidak menawarkan penurunan harga pada baris bertarif musim', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();

    $ringkas = HitungRab::ringkas($rab->fresh());
    $baris = collect($ringkas['baris'])->firstWhere('master_harga_id', $hotel->id);

    /*
     | Dibandingkan dengan tarif MUSIM-nya. Dibandingkan dengan harga hari
     | kerja, layar akan berteriak "harga master Rp 450.000" dan menawarkan
     | penurunan Rp 450.000 sebagai pembaruan.
     */
    expect($baris['harga_master_kini'])->toBeNull();
    expect($baris['tingkat_harga'])->toBe('musim');
    expect($baris['musim_nama'])->toBe('Nataru 2026/2027');
});

it('menyebut baris yang jatuh di musim tanpa punya tarif musim', function () {
    musimNataru();
    $hotel = hotelMusim();

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();

    $ringkas = HitungRab::ringkas($rab->fresh());

    expect($ringkas['musim_perjalanan']['nama'])->toBe('Nataru 2026/2027');
    expect(collect($ringkas['musim_belum_diisi'])->pluck('nama'))->toContain('Hotel Dieng');
});

it('tidak menyebut baris yang sudah punya tarif musim', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();

    expect(HitungRab::ringkas($rab->fresh())['musim_belum_diisi'])->toBe([]);
});

it('menyebut harga beku yang jadi salah tingkat saat jadwalnya digeser keluar musim', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();

    // Digeser ke Februari — jauh di luar Nataru.
    $rab->fresh()->update(['tanggal_mulai' => '2027-02-10']);

    $ringkas = HitungRab::ringkas($rab->fresh());
    $baris = collect($ringkas['baris'])->firstWhere('master_harga_id', $hotel->id);

    expect($baris['harga_sebab_seharusnya'])->not->toBeNull(
        'baris bertarif Nataru yang digeser ke Februari harus ditandai',
    );
    expect($ringkas['ada_harga_salah_hari'])->toBeTrue();
});

it('menyebut baris per-malam yang harganya berbeda di sepanjang perjalanan', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    // 3–6 Januari: dua hari masih Nataru (sampai 5 Jan), sisanya tidak.
    $rab = rabMusim('2027-01-03', 1, 4);

    $campuran = (new HargaHari($rab))->campuran([$hotel->fresh()]);

    expect($campuran)->toContain('Hotel Dieng');
});

it('tidak menyebut campuran bila harga baris itu sama sepanjang perjalanan', function () {
    musimNataru();
    // Tanpa tarif akhir pekan maupun tarif musim: satu harga setiap hari.
    $hotel = hotelMusim(['harga_akhir_pekan' => null]);

    $rab = rabMusim('2027-01-03', 1, 4);

    expect((new HargaHari($rab))->campuran([$hotel->fresh()]))->toBe([]);
});

/* ================== SIMPAN KE MASTER / PAKAI MASTER ================== */

it('menyimpan temuan tim ke tarif musim, bukan ke kolom hari kerja', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();
    $biaya = $rab->fresh()->biaya->firstWhere('master_harga_id', $hotel->id);

    // Tim menemukan Rp 850.000 untuk Nataru.
    $biaya->update(['harga_satuan' => 850_000]);

    $this->postJson('/api/v1/rab/'.$rab->id.'/biaya/'.$biaya->id.'/ke-master', [], kepalaMusim())
        ->assertOk();

    $hotel = $hotel->fresh();

    expect((int) $hotel->harga)->toBe(450_000, 'harga hari kerja tidak boleh tersentuh');
    expect((int) $hotel->harga_akhir_pekan)->toBe(550_000, 'harga akhir pekan tidak boleh tersentuh');
    expect((int) $hotel->musim->firstWhere('musim_id', $musim->id)->harga)->toBe(850_000);
});

it('menolak menyimpan ke musim yang masternya belum punya tarifnya', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();
    $biaya = $rab->fresh()->biaya->firstWhere('master_harga_id', $hotel->id);
    $biaya->update(['harga_satuan' => 850_000]);

    /*
     | Tombol bernama "simpan" tidak boleh MEMBUAT tarif musim baru: tarif
     | yang lahir tanpa sengaja akan dipakai seluruh RAB berikutnya.
     */
    $this->postJson('/api/v1/rab/'.$rab->id.'/biaya/'.$biaya->id.'/ke-master',
        ['sisi' => 'musim:'.$musim->id], kepalaMusim())
        ->assertStatus(422);

    expect($hotel->fresh()->musim)->toHaveCount(0);
});

it('mengambil harga master dari tarif musim saat "pakai harga master" ditekan', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    $hm = HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();
    $biaya = $rab->fresh()->biaya->firstWhere('master_harga_id', $hotel->id);

    // Admin menawar turun di baris, lalu tarif musim master naik.
    $biaya->update(['harga_satuan' => 800_000]);
    $hm->update(['harga' => 950_000]);

    $this->patchJson('/api/v1/rab/'.$rab->id.'/biaya/'.$biaya->id,
        ['pakai_harga_master' => true], kepalaMusim())->assertOk();

    expect((int) $biaya->fresh()->harga_satuan)->toBe(950_000);
});

it('menolak "pakai harga master" bila tarif musimnya sudah dihapus', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    $hm = HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();
    $biaya = $rab->fresh()->biaya->firstWhere('master_harga_id', $hotel->id);

    $hm->delete();

    /*
     | Menimpanya dengan harga hari kerja adalah penurunan Rp 450.000 yang
     | dilakukan tombol bernama "pakai harga master".
     */
    $this->patchJson('/api/v1/rab/'.$rab->id.'/biaya/'.$biaya->id,
        ['pakai_harga_master' => true], kepalaMusim())->assertStatus(422);

    expect((int) $biaya->fresh()->harga_satuan)->toBe(900_000);
});

/* ============================ LAYAR MUSIM ============================ */

it('menolak tanggal selesai yang lebih awal daripada tanggal mulai', function () {
    $this->postJson('/api/v1/musim-harga', [
        'nama' => 'Nataru',
        'tanggal_mulai' => '2026-12-20',
        'tanggal_sampai' => '2026-12-10',
    ], kepalaMusim())->assertStatus(422)->assertJsonValidationErrors('tanggal_sampai');
});

it('menerima musim sehari', function () {
    $this->postJson('/api/v1/musim-harga', [
        'nama' => 'Tahun Baru',
        'tanggal_mulai' => '2027-01-01',
        'tanggal_sampai' => '2027-01-01',
    ], kepalaMusim())->assertCreated();

    expect(MusimHarga::first()->jumlah_hari)->toBe(1);
});

it('menolak rentang yang panjangnya satu tahun', function () {
    // Salah ketik tahun di salah satu ujung menghasilkan tepat ini.
    $this->postJson('/api/v1/musim-harga', [
        'nama' => 'Nataru',
        'tanggal_mulai' => '2026-12-20',
        'tanggal_sampai' => '2027-12-20',
    ], kepalaMusim())->assertStatus(422)->assertJsonValidationErrors('tanggal_sampai');
});

it('menolak menghapus musim yang masih dipakai harga', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $this->deleteJson('/api/v1/musim-harga/'.$musim->id, [], kepalaMusim())->assertStatus(422);

    expect(MusimHarga::count())->toBe(1);
    expect(HargaMusim::count())->toBe(1);
});

it('menyebut musim lain yang rentangnya bertumpuk', function () {
    $nataru = musimNataru();
    MusimHarga::create([
        'nama' => 'Cuti Bersama',
        'tanggal_mulai' => '2026-12-28',
        'tanggal_sampai' => '2026-12-28',
        'prioritas' => 50,
        'aktif' => true,
    ]);

    $hasil = $this->getJson('/api/v1/musim-harga', kepalaMusim())->assertOk()->json('data');
    $baris = collect($hasil)->firstWhere('id', $nataru->id);

    expect($baris['tumpang'])->toHaveCount(1);
    expect($baris['tumpang'][0]['nama'])->toBe('Cuti Bersama');
    expect($baris['tumpang'][0]['menang'])->toBeTrue();
});

it('memasang tarif musim untuk banyak harga sekaligus', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    $hotel2 = hotelMusim(['nama' => 'Homestay Dieng', 'harga' => 300_000]);

    $this->postJson('/api/v1/musim-harga/'.$musim->id.'/pasang', [
        'baris' => [
            ['master_harga_id' => $hotel->id, 'harga' => 900_000],
            ['master_harga_id' => $hotel2->id, 'harga' => 600_000],
        ],
    ], kepalaMusim())->assertOk();

    expect(HargaMusim::count())->toBe(2);
});

it('mengosongkan tarif musim berarti menghapusnya, bukan menyimpan nol', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $this->postJson('/api/v1/musim-harga/'.$musim->id.'/pasang', [
        'baris' => [['master_harga_id' => $hotel->id, 'harga' => null]],
    ], kepalaMusim())->assertOk();

    expect(HargaMusim::count())->toBe(0);
    // Nol adalah harga yang sah dan TIDAK sama dengan dikosongkan.
    expect($hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-23'))['tingkat'])->toBe('hari_kerja');
});

it('menyimpan nol sebagai harga yang sah', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();

    $this->postJson('/api/v1/musim-harga/'.$musim->id.'/pasang', [
        'baris' => [['master_harga_id' => $hotel->id, 'harga' => 0]],
    ], kepalaMusim())->assertOk();

    $pilih = $hotel->fresh()->hargaUntuk(Carbon::parse('2026-12-23'));

    expect($pilih['harga'])->toBe(0);
    expect($pilih['tingkat'])->toBe('musim');
});

/* ======================== BORANG MASTER HARGA ======================== */

it('menyimpan tarif musim dari borang master harga', function () {
    $musim = musimNataru();

    $id = $this->postJson('/api/v1/master-harga', [
        'kategori' => 'akomodasi',
        'nama' => 'Hotel Dieng',
        'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Dieng',
        'satuan' => 'kamar_malam',
        'kapasitas' => 2,
        'harga' => 450_000,
        'harga_akhir_pekan' => 550_000,
        'harga_musim' => [['musim_id' => $musim->id, 'harga' => 900_000]],
    ], kepalaMusim())->assertCreated()->json('data.id');

    expect(HargaMusim::where('master_harga_id', $id)->value('harga'))->toBe(900_000);
});

it('mencatat perubahan tarif musim ke riwayat harga', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $this->patchJson('/api/v1/master-harga/'.$hotel->id, [
        'kategori' => 'akomodasi',
        'nama' => 'Hotel Dieng',
        'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Dieng',
        'satuan' => 'kamar_malam',
        'kapasitas' => 2,
        'harga' => 450_000,
        'harga_akhir_pekan' => 550_000,
        'harga_musim' => [['musim_id' => $musim->id, 'harga' => 975_000]],
    ], kepalaMusim())->assertOk();

    $riwayat = RiwayatHarga::where('master_harga_id', $hotel->id)->first();

    expect($riwayat)->not->toBeNull('tarif musim yang naik tanpa jejak adalah angka yang berubah tanpa sebab');
    expect(collect($riwayat->perubahan)->pluck('medan'))->toContain('Harga Nataru 2026/2027');
});

it('memulangkan tarif musim pada satu baris master harga', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $data = $this->getJson('/api/v1/master-harga/'.$hotel->id, kepalaMusim())->assertOk()->json('data');

    expect($data['harga_musim'])->toHaveCount(1);
    expect($data['harga_musim'][0]['nama'])->toBe('Nataru 2026/2027');
    expect($data['harga_musim'][0]['harga'])->toBe(900_000);
});

/* ============================== POTRET ============================== */

it('potret versi penawaran ikut menyimpan tingkat harganya', function () {
    $musim = musimNataru();
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();
    // Versi direkam saat penawarannya DIKIRIM, bukan lewat tombol sendiri.
    $this->patchJson('/api/v1/rab/'.$rab->id, ['status' => 'dikirim'], kepalaMusim())->assertOk();

    $versi = VersiPenawaran::where('rab_id', $rab->id)->first();
    $baris = collect($versi->rincian['baris'])->firstWhere('master_harga_id', $hotel->id);

    /*
     | Tanpa ini, versi yang dipulihkan kembali terbaca 'hari_kerja' dan
     | "Pakai harga master" menimpa tarif Nataru dengan harga hari biasa.
     */
    expect($baris['tingkat_harga'])->toBe('musim');
    expect($baris['musim_id'])->toBe($musim->id);
});

it('memangkas sebab harga ke lebar kolomnya', function () {
    $musim = musimNataru(['nama' => str_repeat('Nataru Panjang Sekali ', 6)]);
    $hotel = hotelMusim();
    HargaMusim::create(['master_harga_id' => $hotel->id, 'musim_id' => $musim->id, 'harga' => 900_000]);

    $rab = rabMusim('2026-12-23');
    $this->postJson('/api/v1/rab/'.$rab->id.'/tarik-biaya', [], kepalaMusim())->assertOk();

    $sebab = $rab->fresh()->biaya->firstWhere('master_harga_id', $hotel->id)->harga_sebab;

    expect(mb_strlen($sebab))->toBeLessThanOrEqual(120);
});
