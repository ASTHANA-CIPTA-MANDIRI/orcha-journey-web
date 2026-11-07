<?php

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabRealisasi;
use App\Models\Rab\RabRealisasiBayar;
use App\Support\Rab\RekapRab;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Rekap pengeluaran sesudah trip jalan: RAB vs tagihan vs dibayar.
 *
 * Yang dijaga di sini — dan ini seluruh gunanya fitur ini: HEMAT dan KURANG
 * BAYAR tidak boleh tertukar. Keduanya memberi "RAB − dibayar" yang sama
 * persis, tetapi yang satu untung dan yang satu utang.
 *
 * Angkanya dari Hotel Fave: RAB 8jt, cashback jadi tagihan 7jt.
 */
function kepalaRekap(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-rekap');

    return ['X-Orcha-Key' => 'kunci-uji-rekap', 'Accept' => 'application/json'];
}

function rabRekap(string $status = 'selesai', int $peserta = 11): Rab
{
    return Rab::create([
        'judul' => 'Private Trip Dieng',
        'nama_pelanggan' => 'Pak Hendra',
        'provinsi' => 'Jawa Tengah',
        'tanggal_mulai' => '2026-11-01',
        'jumlah_hari' => 3,
        'jumlah_malam' => 2,
        'jumlah_peserta' => $peserta,
        'margin_jenis' => 'persen',
        'margin_nilai' => 10,
        'status' => $status,
    ])->fresh();
}

function biayaRekap(Rab $rab, string $nama, int $harga, array $ubah = []): RabBiaya
{
    return RabBiaya::create(array_merge([
        'rab_id' => $rab->id,
        'kategori' => 'akomodasi',
        'nama' => $nama,
        'satuan' => 'rombongan',
        'harga_satuan' => $harga,
        'jumlah' => 1,
        'urutan' => 1,
    ], $ubah));
}

/* ============ YANG MEMBEDAKAN HEMAT DARI KURANG BAYAR ============ */

it('membaca cashback sebagai hemat, bukan kurang bayar', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    // Tagihan turun jadi 7jt karena cashback, dan dibayar lunas 7jt.
    $r = RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 7_000_000]);
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'dp', 'nominal' => 500_000]);
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'pelunasan', 'nominal' => 6_500_000]);

    $rekap = RekapRab::untuk($rab->fresh());

    expect($rekap['hemat'])->toBe(1_000_000, 'cashback 1jt masuk hemat');
    expect($rekap['kurang_bayar'])->toBe(0, 'sudah lunas ke vendor');
});

it('membaca hotel yang belum dilunasi sebagai kurang bayar, bukan hemat', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    // Tagihan tetap 8jt, baru dibayar 7jt.
    $r = RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 8_000_000]);
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'dp', 'nominal' => 500_000]);
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'pelunasan', 'nominal' => 6_500_000]);

    $rekap = RekapRab::untuk($rab->fresh());

    /*
     | "RAB − dibayar" pada kedua uji ini sama persis: 8jt − 7jt = 1jt.
     | Tanpa tagihan yang dicatat terpisah, hotel yang lupa dilunasi akan
     | tampil sebagai cashback satu juta — laporan menjanjikan untung yang
     | uangnya justru masih harus keluar.
     */
    expect($rekap['hemat'])->toBe(0, 'tidak ada diskon: tagihan sama dengan RAB');
    expect($rekap['kurang_bayar'])->toBe(1_000_000, 'masih utang 1jt');
});

it('baluran yang dibayar sekali tanpa DP menutup rapi', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Tiket Baluran', 10_000, ['kategori' => 'tiket', 'satuan' => 'orang']);

    $r = RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 110_000]);
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'pelunasan', 'nominal' => 110_000]);

    $rekap = RekapRab::untuk($rab->fresh());

    expect($rekap['rab_total'])->toBe(110_000, '10rb x 11 orang');
    expect($rekap['hemat'])->toBe(0);
    expect($rekap['kurang_bayar'])->toBe(0);
    expect($rekap['baris'][0]['lunas'])->toBeTrue();
});

it('refund mengurangi uang keluar, bukan menambah', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $r = RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 8_000_000]);
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'pelunasan', 'nominal' => 8_000_000]);
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'refund', 'nominal' => 1_000_000]);

    /*
     | Nominal refund disimpan POSITIF dan arahnya dari jenisnya. Ditulis
     | sebagai angka negatif, setiap penjumlahan di seluruh laporan harus
     | ingat memeriksa tandanya — dan satu yang lupa salah tanpa berbunyi.
     */
    expect(RekapRab::untuk($rab->fresh())['dibayar_total'])->toBe(7_000_000);
});

/* ==================== BELUM DIREKAP ≠ NOL ==================== */

it('membedakan belum direkap dari tagihan nol', function () {
    $rab = rabRekap();
    $gratis = biayaRekap($rab, 'Parkir gratis', 50_000);
    $belum = biayaRekap($rab, 'Hotel Fave', 8_000_000, ['urutan' => 2]);

    // Yang satu benar-benar nol; yang satu belum diperiksa sama sekali.
    RabRealisasi::create(['rab_biaya_id' => $gratis->id, 'tagihan' => 0]);

    $rekap = RekapRab::untuk($rab->fresh());

    expect($rekap['belum_direkap'])->toBe(['Hotel Fave']);
    expect($rekap['lengkap'])->toBeFalse();
    expect(collect($rekap['baris'])->firstWhere('nama', 'Parkir gratis')['sudah_direkap'])->toBeTrue();
});

it('menghitung baris yang belum direkap sebesar RAB-nya, bukan nol', function () {
    $rab = rabRekap();
    biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $rekap = RekapRab::untuk($rab->fresh());

    /*
     | Dianggap nol, untung nyata akan melonjak seolah hotelnya gratis —
     | laporan setengah jadi yang terbaca seperti kabar baik, dan dipercaya
     | justru karena angkanya menyenangkan.
     */
    expect($rekap['hemat'])->toBe(0);
    expect($rekap['untung_nyata'])->toBe($rekap['untung_rencana']);
});

it('menyebut lengkap saat semua baris sudah direkap', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);
    RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 7_000_000]);

    $rekap = RekapRab::untuk($rab->fresh());

    expect($rekap['lengkap'])->toBeTrue();
    expect($rekap['belum_direkap'])->toBe([]);
});

/* ======================== UNTUNG NYATA ======================== */

it('menghitung untung nyata dari tagihan, bukan dari yang sudah dibayar', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $r = RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 8_000_000]);
    // Baru DP; sisanya belum dibayar.
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'dp', 'nominal' => 500_000]);

    $rekap = RekapRab::untuk($rab->fresh());

    /*
     | Trip yang belum melunasi hotelnya BELUM untung lebih banyak — ia cuma
     | belum mengeluarkan uangnya. Dihitung dari yang dibayar, laporan akan
     | berkata untung 7,5jt lebih besar daripada seharusnya.
     */
    expect($rekap['untung_nyata'])->toBe($rekap['untung_rencana']);
    expect($rekap['kurang_bayar'])->toBe(7_500_000);
});

it('cashback menambah untung nyata di atas untung rencana', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);
    RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 7_000_000]);

    $rekap = RekapRab::untuk($rab->fresh());

    expect($rekap['untung_nyata'] - $rekap['untung_rencana'])->toBe(1_000_000);
});

/* ========================= PER KATEGORI ========================= */

it('mengelompokkan selisih per kategori', function () {
    $rab = rabRekap();
    $hotel = biayaRekap($rab, 'Hotel Fave', 8_000_000);
    $tiket = biayaRekap($rab, 'Tiket Baluran', 110_000, ['kategori' => 'tiket', 'urutan' => 2]);

    RabRealisasi::create(['rab_biaya_id' => $hotel->id, 'tagihan' => 7_000_000]);
    RabRealisasi::create(['rab_biaya_id' => $tiket->id, 'tagihan' => 110_000]);

    $kat = collect(RekapRab::untuk($rab->fresh())['per_kategori'])->keyBy('kategori');

    expect($kat['akomodasi']['hemat'])->toBe(1_000_000);
    expect($kat['tiket']['hemat'])->toBe(0);
});

/* ======================= PEMBAYARAN & BUKTI ======================= */

it('mencatat pembayaran lewat API beserta buktinya', function () {
    Storage::fake('rahasia');
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $this->post('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id.'/bayar', [
        'jenis' => 'dp',
        'nominal' => 500_000,
        'tanggal' => '2026-10-20',
        'bukti' => UploadedFile::fake()->image('transfer.jpg'),
    ], kepalaRekap())->assertCreated();

    $bayar = RabRealisasiBayar::first();

    expect($bayar->jenis)->toBe('dp');
    expect($bayar->nominal)->toBe(500_000);
    expect($bayar->bukti)->not->toBeNull();
});

it('menerima pembayaran tanpa bukti dan menyebutnya', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    /*
     | Pembayaran yang sudah terjadi tetap harus bisa dicatat walau fotonya
     | masih di ponsel orang lain. Yang belum punya bukti DISEBUT, jadi ia
     | tidak hilang diam-diam.
     */
    $this->postJson('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id.'/bayar', [
        'jenis' => 'pelunasan', 'nominal' => 1_000_000,
    ], kepalaRekap())->assertCreated();

    expect(RekapRab::untuk($rab->fresh())['bayar_tanpa_bukti'])->toBe(1);
});

it('tidak mengisi tagihan dari nominal pembayaran pertama', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $this->postJson('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id.'/bayar', [
        'jenis' => 'dp', 'nominal' => 500_000,
    ], kepalaRekap())->assertCreated();

    /*
     | Mengisinya otomatis akan membuat DP Rp 500.000 terbaca sebagai
     | tagihan Rp 500.000 — lalu hotel delapan juta tampil hemat tujuh
     | setengah juta.
     */
    expect(RabRealisasi::first()->tagihan)->toBeNull();
    expect(RekapRab::untuk($rab->fresh())['belum_direkap'])->toBe(['Hotel Fave']);
});

it('mengarsipkan bukti lama saat diganti', function () {
    Storage::fake('rahasia');
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $this->post('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id.'/bayar', [
        'jenis' => 'dp', 'nominal' => 500_000,
        'bukti' => UploadedFile::fake()->image('lama.jpg'),
    ], kepalaRekap())->assertCreated();

    $bayar = RabRealisasiBayar::first();
    $lama = $bayar->bukti;

    $this->post('/api/v1/rab/'.$rab->id.'/rekap/bayar/'.$bayar->id.'/bukti', [
        'bukti' => UploadedFile::fake()->image('baru.jpg'),
    ], kepalaRekap())->assertOk();

    $bayar = $bayar->fresh();

    /*
     | Mengganti bukti pada catatan uang adalah tindakan yang paling mungkin
     | dipersoalkan belakangan, dan yang mempersoalkannya akan bertanya
     | "yang lama mana?".
     */
    expect($bayar->bukti)->not->toBe($lama);
    expect(collect($bayar->bukti_riwayat)->pluck('jalur'))->toContain($lama);
});

it('menolak nominal negatif', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    // Refund punya jenisnya sendiri; angka negatif tidak pernah sah.
    $this->postJson('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id.'/bayar', [
        'jenis' => 'refund', 'nominal' => -1_000_000,
    ], kepalaRekap())->assertStatus(422)->assertJsonValidationErrors('nominal');
});

it('menyimpan tagihan dan PIC lewat API', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $this->postJson('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id, [
        'tagihan' => 7_000_000, 'pic' => 'Berto',
    ], kepalaRekap())->assertOk();

    $r = RabRealisasi::first();

    expect($r->tagihan)->toBe(7_000_000);
    expect($r->pic)->toBe('Berto');
});

it('mengosongkan tagihan mengembalikannya ke belum direkap', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);
    RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 7_000_000]);

    $this->postJson('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id,
        ['tagihan' => null], kepalaRekap())->assertOk();

    expect(RekapRab::untuk($rab->fresh())['belum_direkap'])->toBe(['Hotel Fave']);
});

it('menolak baris biaya milik RAB lain', function () {
    $rab = rabRekap();
    $lain = rabRekap();
    $b = biayaRekap($lain, 'Hotel Fave', 8_000_000);

    $this->postJson('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id,
        ['tagihan' => 7_000_000], kepalaRekap())->assertNotFound();
});

it('menghapus pembayaran tanpa membuang berkas buktinya', function () {
    Storage::fake('rahasia');
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $this->post('/api/v1/rab/'.$rab->id.'/rekap/biaya/'.$b->id.'/bayar', [
        'jenis' => 'dp', 'nominal' => 500_000,
        'bukti' => UploadedFile::fake()->image('bukti.jpg'),
    ], kepalaRekap())->assertCreated();

    $bayar = RabRealisasiBayar::first();
    $jalur = str_replace('/storage/', '', $bayar->bukti);

    $this->deleteJson('/api/v1/rab/'.$rab->id.'/rekap/bayar/'.$bayar->id, [], kepalaRekap())->assertOk();

    expect(RabRealisasiBayar::count())->toBe(0);
    // Bukti sebuah transaksi yang pernah dicatat tidak boleh hilang karena
    // satu baris salah ketik.
    Storage::disk('rahasia')->assertExists($jalur);
});

/* ========================== TUNGGAKAN ========================== */

it('menyebut trip selesai yang rekapnya belum beres', function () {
    $rab = rabRekap('selesai');
    biayaRekap($rab, 'Hotel Fave', 8_000_000);

    $daftar = $this->getJson('/api/v1/rab/rekap-tunggakan', kepalaRekap())
        ->assertOk()->json('data');

    expect(collect($daftar)->pluck('kode'))->toContain($rab->fresh()->kode);
});

it('tidak menyebut trip yang rekapnya sudah lengkap', function () {
    $rab = rabRekap('selesai');
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);
    RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 7_000_000]);

    $daftar = $this->getJson('/api/v1/rab/rekap-tunggakan', kepalaRekap())
        ->assertOk()->json('data');

    expect(collect($daftar)->pluck('kode'))->not->toContain($rab->fresh()->kode);
});

it('tidak menyebut trip yang belum selesai', function () {
    $rab = rabRekap('disetujui');
    biayaRekap($rab, 'Hotel Fave', 8_000_000);

    /*
     | RAB draf yang belum tentu jadi tidak boleh muncul di daftar yang
     | harus direkap — daftar tunggakan yang penuh hal yang belum terjadi
     | adalah daftar yang berhenti dibaca.
     */
    expect($this->getJson('/api/v1/rab/rekap-tunggakan', kepalaRekap())->json('data'))->toBe([]);
});

it('status selesai dikenali dan bisa disimpan', function () {
    $rab = rabRekap('disetujui');

    $this->patchJson('/api/v1/rab/'.$rab->id, ['status' => 'selesai'], kepalaRekap())->assertOk();

    expect($rab->fresh()->status)->toBe('selesai');
    expect($rab->fresh()->status_label)->toBe('Selesai');
});

it('rekap ikut terhapus saat baris biayanya dihapus', function () {
    $rab = rabRekap();
    $b = biayaRekap($rab, 'Hotel Fave', 8_000_000);
    $r = RabRealisasi::create(['rab_biaya_id' => $b->id, 'tagihan' => 7_000_000]);
    RabRealisasiBayar::create(['realisasi_id' => $r->id, 'jenis' => 'dp', 'nominal' => 500_000]);

    $b->delete();

    expect(RabRealisasi::count())->toBe(0);
    expect(RabRealisasiBayar::count())->toBe(0, 'pembayaran ikut lewat cascade');
});
