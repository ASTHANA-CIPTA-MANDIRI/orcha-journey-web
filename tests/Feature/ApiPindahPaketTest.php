<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Pelanggan\SaldoPelanggan;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Support\Rab\HitungRab;
use App\Support\TagihanPesanan;

/**
 * Memindahkan pendaftaran ke paket lain — open trip jadi private trip.
 *
 * Sebelum jalur ini ada, satu-satunya cara adalah membatalkan lalu mendaftar
 * ulang. Itu memutus riwayat uangnya: bukti pembayaran, angsuran, dan
 * kwitansi semuanya menempel pada KODE pendaftaran, dan pendaftaran baru
 * dapat kode baru. Yang dijaga paling keras di sini justru itu — kodenya
 * tidak boleh berubah, dan uang yang sudah masuk tidak boleh hilang.
 */
function kepalaPindah(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-pindah');

    return ['X-Orcha-Key' => 'kunci-uji-pindah', 'Accept' => 'application/json'];
}

function paketPindah(string $nama, int $harga, string $kategori = 'open_trip'): TravelPackage
{
    return TravelPackage::create([
        'name' => $nama, 'category' => $kategori,
        'price' => $harga, 'status' => 'terbit',
    ]);
}

function berangkatPindah(TravelPackage $paket, string $tanggal, ?int $kuota = 20, ?int $harga = null): Keberangkatan
{
    return Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => $tanggal,
        'tanggal_pulang' => $tanggal,
        'kuota' => $kuota,
        'price' => $harga,
        'aktif' => true,
    ]);
}

/** Kasus Eva: 4 orang, open trip 6 Des, sudah bayar Rp 420.000. */
function evaPindah(TravelPackage $paket, Keberangkatan $kb, int $dibayar = 420000): PendaftaranOpenTrip
{
    $p = PendaftaranOpenTrip::create([
        'nama' => 'Eva', 'whatsapp' => '0812-3456-7890', 'jumlah_peserta' => 4,
        'travel_package_id' => $paket->id, 'nama_paket' => $paket->name,
        'keberangkatan_id' => $kb->id, 'tanggal_berangkat' => $kb->tanggal_berangkat,
        'status' => 'dp_masuk',
    ]);

    if ($dibayar > 0) {
        KonfirmasiPembayaran::create([
            // Medan wajib tabelnya diisi lengkap, mengikuti bentuk yang
            // sudah dipakai uji angsuran — bukan ditebak satu per satu dari
            // galat yang muncul.
            'kode' => $p->kode, 'jenis' => 'dp', 'nominal' => $dibayar,
            'tanggal_transfer' => '2026-11-23', 'bank_pengirim' => 'BCA',
            'atas_nama_pengirim' => 'Eva', 'status' => 'diterima',
        ]);
    }

    return $p->fresh();
}

function pindahkan(PendaftaranOpenTrip $p, array $isi)
{
    return test()->postJson("/api/v1/pendaftaran/{$p->id}/pindah-paket", $isi, kepalaPindah());
}

/* ------------------------------------------------------- Kasus pokok */

test('open trip jadi private trip: kode, uang, dan riwayatnya tetap melekat', function () {
    $open = paketPindah('Open Trip Dieng', 350000);
    $private = paketPindah('Private Trip Dieng', 500000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));
    $kodeLama = $eva->kode;

    $balasan = pindahkan($eva, [
        'travel_package_id' => $private->id,
        'keberangkatan_id' => berangkatPindah($private, '2026-12-05')->id,
    ])->assertOk();

    $eva->refresh();

    // Inti dari seluruh fitur ini: kodenya TIDAK berubah, jadi bukti
    // pembayaran yang menempel padanya tidak perlu dipindahkan.
    expect($eva->kode)->toBe($kodeLama)
        ->and($eva->nama_paket)->toBe('Private Trip Dieng')
        ->and($eva->tanggal_berangkat->toDateString())->toBe('2026-12-05')
        // Harga jual yang DIBEKUKAN saat pendaftaran dibuat harus dilepas,
        // kalau tidak tagihannya tidak akan bergerak sama sekali.
        ->and((int) $eva->harga_jual)->toBe(500000)
        ->and((int) $eva->total_tagihan)->toBe(2000000);

    expect(KonfirmasiPembayaran::where('kode', $kodeLama)->sum('nominal'))->toBe(420000);
    expect($balasan->json('ringkasan.sebelum.tagihan'))->toBe(1400000);
});

test('tanggal milik paket lain ditolak', function () {
    $open = paketPindah('Open Trip Dieng', 350000);
    $private = paketPindah('Private Trip Dieng', 500000, 'private_trip');
    $lain = paketPindah('Paket Ketiga', 400000);
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));

    pindahkan($eva, [
        'travel_package_id' => $private->id,
        'keberangkatan_id' => berangkatPindah($lain, '2026-12-05')->id,
    ])->assertStatus(422);

    expect($eva->fresh()->nama_paket)->toBe('Open Trip Dieng');
});

test('pindah ke paket dan tanggal yang sama ditolak', function () {
    $open = paketPindah('Open Trip Dieng', 350000);
    $kb = berangkatPindah($open, '2026-12-06');
    $eva = evaPindah($open, $kb);

    pindahkan($eva, ['travel_package_id' => $open->id, 'keberangkatan_id' => $kb->id])
        ->assertStatus(422);
});

test('pendaftaran yang sudah batal tidak dipindahkan', function () {
    $open = paketPindah('Open Trip Dieng', 350000);
    $private = paketPindah('Private Trip Dieng', 500000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));
    $eva->update(['status' => 'batal']);

    pindahkan($eva, [
        'travel_package_id' => $private->id,
        'keberangkatan_id' => berangkatPindah($private, '2026-12-05')->id,
    ])->assertStatus(422);
});

/* ------------------------------------------------------------- Kuota */

test('kuota penuh ditolak dulu, lalu boleh ditembus dengan sengaja', function () {
    $open = paketPindah('Open Trip Dieng', 350000);
    $private = paketPindah('Private Trip Dieng', 500000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));
    $sempit = berangkatPindah($private, '2026-12-05', kuota: 2);

    // Rombongan 4 orang ke tanggal berkuota 2: ditolak, dengan angkanya
    // disebut — bukan ditolak diam-diam.
    pindahkan($eva, ['travel_package_id' => $private->id, 'keberangkatan_id' => $sempit->id])
        ->assertStatus(422)
        ->assertJsonPath('butuh_paksa', true);

    pindahkan($eva, [
        'travel_package_id' => $private->id, 'keberangkatan_id' => $sempit->id, 'paksa' => true,
    ])->assertOk();

    expect($eva->fresh()->keberangkatan_id)->toBe($sempit->id);
});

/* --------------------------------------------------- Kelebihan bayar */

test('kelebihan bayar menahan perpindahan sampai diputuskan', function () {
    // Pindah ke paket yang LEBIH MURAH sesudah bayar banyak.
    $open = paketPindah('Open Trip Mahal', 500000);
    $murah = paketPindah('Private Trip Murah', 100000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'), dibayar: 2000000);

    $balasan = pindahkan($eva, [
        'travel_package_id' => $murah->id,
        'keberangkatan_id' => berangkatPindah($murah, '2026-12-05')->id,
    ])->assertStatus(422);

    expect($balasan->json('butuh_keputusan_kelebihan'))->toBeTrue()
        ->and($balasan->json('kelebihan'))->toBe(1600000);

    // Dan SELURUH perpindahan dibatalkan — bukan setengah jadi. Pendaftaran
    // yang sudah pindah sementara selisihnya menggantung tanpa keputusan
    // adalah keadaan yang paling mudah terlupakan.
    $eva->refresh();
    expect($eva->nama_paket)->toBe('Open Trip Mahal')
        ->and($eva->tanggal_berangkat->toDateString())->toBe('2026-12-06');
});

test('kelebihan bisa disimpan sebagai saldo untuk trip berikutnya', function () {
    $open = paketPindah('Open Trip Mahal', 500000);
    $murah = paketPindah('Private Trip Murah', 100000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'), dibayar: 2000000);

    pindahkan($eva, [
        'travel_package_id' => $murah->id,
        'keberangkatan_id' => berangkatPindah($murah, '2026-12-05')->id,
        'kelebihan' => 'saldo',
    ])->assertOk();

    $saldo = SaldoPelanggan::firstOrFail();

    expect($saldo->nominal)->toBe(1600000)
        ->and($saldo->asal_kode)->toBe($eva->kode)
        ->and($saldo->terpakai)->toBeFalse()
        // Nomornya disimpan dalam bentuk BAKU: NomorTelepon::angka() membuang
        // tanda baca DAN menyeragamkan awalannya — +62 dan 8xx sama-sama jadi
        // 08xx. Tanpa itu, saldo yang dititipkan lewat satu ejaan tidak akan
        // pernah ditemukan lewat ejaan yang lain.
        ->and($saldo->whatsapp)->toBe('081234567890');
});

test('kelebihan bisa dipilih untuk dikembalikan, tanpa membuat saldo', function () {
    $open = paketPindah('Open Trip Mahal', 500000);
    $murah = paketPindah('Private Trip Murah', 100000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'), dibayar: 2000000);

    $balasan = pindahkan($eva, [
        'travel_package_id' => $murah->id,
        'keberangkatan_id' => berangkatPindah($murah, '2026-12-05')->id,
        'kelebihan' => 'refund',
    ])->assertOk();

    expect(SaldoPelanggan::count())->toBe(0)
        ->and($balasan->json('ringkasan.perlakuan_kelebihan'))->toBe('refund')
        ->and($balasan->json('ringkasan.kelebihan'))->toBe(1600000);
});

test('naik harga tidak menanyakan apa pun — itu kasus yang biasa', function () {
    // Open ke private umumnya NAIK. Tidak ada kelebihan, jadi tidak ada yang
    // perlu diputuskan; sisanya tinggal ditagih seperti biasa.
    $open = paketPindah('Open Trip Dieng', 350000);
    $private = paketPindah('Private Trip Dieng', 500000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));

    $balasan = pindahkan($eva, [
        'travel_package_id' => $private->id,
        'keberangkatan_id' => berangkatPindah($private, '2026-12-05')->id,
    ])->assertOk();

    expect($balasan->json('ringkasan.kelebihan'))->toBe(0)
        ->and($balasan->json('ringkasan.perlakuan_kelebihan'))->toBeNull()
        ->and(SaldoPelanggan::count())->toBe(0);
});

test('harga boleh disebut sendiri bila rombongan sudah telanjur sepakat', function () {
    $open = paketPindah('Open Trip Dieng', 350000);
    $private = paketPindah('Private Trip Dieng', 500000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));

    pindahkan($eva, [
        'travel_package_id' => $private->id,
        'keberangkatan_id' => berangkatPindah($private, '2026-12-05')->id,
        'harga_jual' => 450000,
    ])->assertOk();

    expect((int) $eva->fresh()->harga_jual)->toBe(450000);
});

test('tanpa kunci API jalurnya tertutup', function () {
    config()->set('orcha.api.kunci', 'kunci-uji-pindah');
    $open = paketPindah('Open Trip Dieng', 350000);
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));

    test()->postJson("/api/v1/pendaftaran/{$eva->id}/pindah-paket", [])->assertStatus(401);
});

test('saldo ditemukan walau nomornya ditulis dengan ejaan lain', function () {
    $open = paketPindah('Open Trip Mahal', 500000);
    $murah = paketPindah('Private Trip Murah', 100000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'), dibayar: 2000000);

    pindahkan($eva, [
        'travel_package_id' => $murah->id,
        'keberangkatan_id' => berangkatPindah($murah, '2026-12-05')->id,
        'kelebihan' => 'saldo',
    ])->assertOk();

    // Dititipkan lewat "0812-3456-7890", dicari lewat "+62 812 3456 7890".
    // Inilah satu-satunya alasan nomornya dibakukan sebelum disimpan.
    expect(SaldoPelanggan::milik('+62 812 3456 7890')->tersedia()->sum('nominal'))->toBe(1600000);
    expect(SaldoPelanggan::milik('081200000000')->count())->toBe(0);
});

/* ------------------------------- Kasus Eva: tagihannya NAIK, bukan turun */

test('kasus Eva: 350rb jadi 395rb — tagihan dan kekurangannya dihitung ulang', function () {
    /*
     | Angka nyata dari layar: Eva, 4 orang, open trip Rp 350.000/orang,
     | sudah bayar Rp 420.000 dari tagihan Rp 1.400.000.
     |
     | Pindah ke private trip Rp 395.000/orang. Yang diuji di sini bukan
     | perpindahannya, melainkan bahwa ANGKA UANGNYA ikut bergerak sendiri —
     | tagihan naik, kekurangan bertambah, dan uang yang sudah masuk tidak
     | tersentuh sama sekali.
     */
    $open = paketPindah('Open Trip Dieng', 350000);
    $private = paketPindah('Private Trip Dieng', 395000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));

    expect((int) $eva->total_tagihan)->toBe(1400000);

    $balasan = pindahkan($eva, [
        'travel_package_id' => $private->id,
        'keberangkatan_id' => berangkatPindah($private, '2026-12-05')->id,
    ])->assertOk();

    $eva->refresh();

    // 395.000 x 4 = 1.580.000
    expect((int) $eva->total_tagihan)->toBe(1580000);

    // Tidak ada kelebihan: yang terjadi justru kekurangan, dan kekurangan
    // tidak perlu diputuskan apa-apa — tinggal ditagih.
    expect($balasan->json('ringkasan.kelebihan'))->toBe(0)
        ->and($balasan->json('ringkasan.dibayar'))->toBe(420000)
        ->and($balasan->json('ringkasan.sebelum.tagihan'))->toBe(1400000);

    // Dan inilah angka yang dibaca layar Pendaftaran Trip:
    // sisa = 1.580.000 - 420.000 = 1.160.000
    $tagihan = TagihanPesanan::untuk($eva->fresh(), hanyaDiterima: true);

    expect($tagihan['total'])->toBe(1580000)
        ->and($tagihan['sudah'])->toBe(420000)
        ->and($tagihan['sisa'])->toBe(1160000)
        ->and($tagihan['lunas'])->toBeFalse();
});

test('batas pelunasan ikut bergeser ke tanggal baru', function () {
    // Berangkat maju sehari (6 -> 5 Des), jadi H-5 ikut maju: 1 -> 30 Nov.
    // Kalau tidak ikut, pengingat pelunasan dikirim untuk tanggal yang sudah
    // tidak berlaku — dan rombongan ditagih terlambat.
    $open = paketPindah('Open Trip Dieng', 350000);
    $private = paketPindah('Private Trip Dieng', 395000, 'private_trip');
    $eva = evaPindah($open, berangkatPindah($open, '2026-12-06'));

    /*
     | Dibaca lewat KEBERANGKATAN, bukan lewat pendaftaran.
     |
     | batas_pelunasan bukan kolom pendaftaran — ia dihitung oleh
     | Keberangkatan dari tanggal berangkatnya. Itu justru sebabnya tenggatnya
     | ikut bergeser sendiri begitu rombongannya pindah: tidak ada satu pun
     | nilai yang perlu diperbarui di sisi pendaftaran.
     */
    expect($eva->keberangkatan->batas_pelunasan->toDateString())->toBe('2026-12-01');

    pindahkan($eva, [
        'travel_package_id' => $private->id,
        'keberangkatan_id' => berangkatPindah($private, '2026-12-05')->id,
    ])->assertOk();

    expect($eva->fresh()->keberangkatan->batas_pelunasan->toDateString())->toBe('2026-11-30');
});

/* -------------------------------------------------- Angka dari RAB */

/*
 | Memindahkan pendaftaran DENGAN angka RAB-nya.
 |
 | Tanpa jalur ini, modal pendaftaran diambil dari paket katalog sementara
 | modal sebenarnya sudah dihitung baris per baris di RAB. Keduanya hampir
 | tidak pernah sama, dan yang dibaca laporan keuntungan adalah yang salah —
 | trip yang RAB-nya rugi terbaca untung, tanpa seorang pun salah mengetik.
 |
 | Nama penolongnya diawali "pindah" dengan sengaja: penolong Pest hidup di
 | ruang nama GLOBAL, dan nama sepolos "rabPrivate" akan bertabrakan dengan
 | berkas uji lain pada rangkaian penuh — bukan saat dijalankan sendirian.
 */
function pindahRabPrivate(TravelPackage $paket, array $ubah = []): Rab
{
    $rab = Rab::create(array_merge([
        'judul' => 'Private Trip Dieng 1 Hari', 'nama_pelanggan' => 'Eva',
        'whatsapp' => '+62 812-3456-7890',
        'provinsi' => 'Jawa Tengah', 'jenis' => 'private', 'harga_dasar' => 'peserta',
        'tanggal_mulai' => '2026-12-04', 'jumlah_hari' => 2, 'jumlah_malam' => 1,
        'jumlah_peserta' => 4, 'peserta_minimum' => 4,
        'margin_jenis' => 'persen', 'margin_nilai' => 10,
        'status' => 'disetujui', 'travel_package_id' => $paket->id,
    ], $ubah));

    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'transportasi',
        'nama' => 'Hiace', 'satuan' => 'unit_hari', 'harga_satuan' => 1_400_000, 'kapasitas' => 14, 'jumlah' => 1]);
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'akomodasi',
        'nama' => 'Homestay', 'satuan' => 'kamar_malam', 'harga_satuan' => 300_000, 'jumlah' => 1]);

    return $rab->fresh();
}

test('angka pendaftaran diambil dari RAB, bukan dari harga paket', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 999_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    $rab = pindahRabPrivate($paket);

    $harap = HitungRab::ringkas($rab)['untuk_pendaftaran'];

    pindahkan($eva, ['rab_id' => $rab->id, 'kelebihan' => 'saldo'])->assertOk();

    $eva->refresh();

    // Harga paket 999.000 TIDAK dipakai sama sekali.
    expect((int) $eva->harga_jual)->toBe((int) $harap['harga_jual'])
        ->and((int) $eva->harga_modal)->toBe((int) $harap['harga_modal'])
        ->and((int) $eva->biaya_tetap)->toBe((int) $harap['biaya_tetap'])
        ->and((int) $eva->harga_jual)->not->toBe(999_000);
});

test('tanggal RAB yang belum ada di paket dibuatkan, dan kuotanya dikunci sejumlah rombongan', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    $rab = pindahRabPrivate($paket);

    expect(Keberangkatan::where('travel_package_id', $paket->id)->count())->toBe(1);

    pindahkan($eva, ['rab_id' => $rab->id, 'kelebihan' => 'saldo'])->assertOk();

    $baru = Keberangkatan::where('travel_package_id', $paket->id)
        ->whereDate('tanggal_berangkat', '2026-12-04')->first();

    expect($baru)->not->toBeNull()
        // Private trip yang tanggalnya dibiarkan terbuka di paket terbit bisa
        // didaftari orang asing — kuotanya dipatok sejumlah rombongan ini.
        ->and((int) $baru->kuota)->toBe(4)
        ->and($eva->fresh()->keberangkatan_id)->toBe($baru->id)
        ->and($eva->fresh()->tanggal_berangkat->toDateString())->toBe('2026-12-04');
});

test('RAB ditandai sudah terpakai, supaya tidak dipakai dua rombongan', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    $rab = pindahRabPrivate($paket);

    pindahkan($eva, ['rab_id' => $rab->id, 'kelebihan' => 'saldo'])->assertOk();

    expect($rab->fresh()->kode_pendaftaran)->toBe($eva->kode);
});

test('kelebihan yang belum diputuskan membatalkan SEMUANYA, termasuk tanggal yang baru dibuat', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    // Sudah bayar jauh lebih banyak daripada tagihan barunya.
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'), 9_000_000);
    $rab = pindahRabPrivate($paket);

    pindahkan($eva, ['rab_id' => $rab->id])
        ->assertStatus(422)
        ->assertJsonPath('butuh_keputusan_kelebihan', true);

    // Inilah yang dijaga: tanggal 4 Des TIDAK boleh tertinggal di paket yang
    // terbit, karena ia akan muncul sebagai pilihan berangkat di halaman
    // publik untuk perpindahan yang tidak pernah terjadi.
    expect(Keberangkatan::where('travel_package_id', $paket->id)
        ->whereDate('tanggal_berangkat', '2026-12-04')->count())->toBe(0)
        ->and($rab->fresh()->kode_pendaftaran)->toBeNull()
        ->and($eva->fresh()->tanggal_berangkat->toDateString())->toBe('2026-12-06');
});

test('paket yang sama dengan harga baru boleh, karena paketnya sendiri yang berubah', function () {
    // Kasus Eva sebenarnya: paketnya TIDAK pindah ke mana-mana — paket itu
    // yang berubah dari Open Trip 350rb jadi Private Trip 395rb.
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $kb = berangkatPindah($paket, '2026-12-04');
    $eva = evaPindah($paket, $kb);
    $rab = pindahRabPrivate($paket);

    pindahkan($eva, ['rab_id' => $rab->id, 'kelebihan' => 'saldo'])->assertOk();

    $harap = HitungRab::ringkas($rab)['untuk_pendaftaran'];

    /*
     | Yang diperiksa biaya_tetap, bukan harga_modal.
     |
     | Modal PER ORANG nol di sini dengan benar: Hiace dan homestay dibayar
     | per rombongan, bukan per kepala, jadi seluruh Rp 3,1 jt masuk biaya
     | tetap. Menuntut harga_modal > 0 berarti menuntut RAB salah menghitung.
     */
    expect($eva->fresh()->keberangkatan_id)->toBe($kb->id)
        ->and((int) $eva->fresh()->biaya_tetap)->toBe((int) $harap['biaya_tetap'])
        ->and((int) $eva->fresh()->biaya_tetap)->toBeGreaterThan(0);
});

test('RAB yang belum disetujui ditolak', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    $rab = pindahRabPrivate($paket, ['status' => 'draf']);

    pindahkan($eva, ['rab_id' => $rab->id])->assertStatus(422);
});

test('RAB milik pelanggan lain ditolak', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    $rab = pindahRabPrivate($paket, ['whatsapp' => '0899-0000-1111', 'nama_pelanggan' => 'Budi']);

    pindahkan($eva, ['rab_id' => $rab->id])->assertStatus(422);
});

test('nomor yang ejaannya berbeda tetap dianggap orang yang sama', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    // "+62 812-3456-7890" pada RAB vs "0812-3456-7890" pada pendaftaran.
    $rab = pindahRabPrivate($paket);

    pindahkan($eva, ['rab_id' => $rab->id, 'kelebihan' => 'saldo'])->assertOk();
});

test('jumlah peserta yang berbeda ditahan, bukan ditebak', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    $rab = pindahRabPrivate($paket, ['jumlah_peserta' => 6]);

    pindahkan($eva, ['rab_id' => $rab->id])->assertStatus(422);

    // Tagihannya tidak boleh bergeser sedikit pun saat ditolak.
    expect($eva->fresh()->tanggal_berangkat->toDateString())->toBe('2026-12-06');
});

test('RAB yang sudah dipakai pendaftaran lain ditolak', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    $rab = pindahRabPrivate($paket, ['kode_pendaftaran' => 'OT-2311-LAIN99']);

    pindahkan($eva, ['rab_id' => $rab->id])->assertStatus(422);
});

test('daftar RAB yang cocok hanya berisi milik nomor yang sama', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));

    pindahRabPrivate($paket);
    pindahRabPrivate($paket, ['whatsapp' => '0899-0000-1111', 'nama_pelanggan' => 'Budi']);
    pindahRabPrivate($paket, ['status' => 'draf']);

    $isi = test()->getJson("/api/v1/pendaftaran/{$eva->id}/rab-cocok", kepalaPindah())
        ->assertOk()->json('data');

    expect($isi)->toHaveCount(1)
        // modal_total, bukan harga_modal: lihat alasannya di uji di atas.
        ->and($isi[0]['modal_total'])->toBeGreaterThan(0)
        ->and($isi[0]['peserta_cocok'])->toBeTrue();
});

/*
 | Daftar ini pernah memulangkan harga yang salah tanpa satu pun galat.
 |
 | Kuerinya memilih kolom seperlunya, sementara HitungRab membaca harga_nego,
 | margin_jenis, margin_nilai, pembulatan, dan harga_dasar. Kolom yang tidak
 | ikut terambil terbaca null, dan RAB nego Rp 395.000 tampil Rp 406.250
 | dengan untung Rp 0 — persis angka yang dipakai admin memutuskan.
 |
 | Karena itu yang dibandingkan di sini BUKAN angka yang diketik ulang,
 | melainkan jawaban HitungRab atas model yang utuh.
 */
test('harga nego ikut terbaca di daftar RAB, bukan dihitung ulang tanpa nego', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 395_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));

    $rab = pindahRabPrivate($paket, ['harga_nego' => 395_000]);
    $harap = HitungRab::ringkas($rab);

    $isi = test()->getJson("/api/v1/pendaftaran/{$eva->id}/rab-cocok", kepalaPindah())
        ->assertOk()->json('data');

    expect($isi[0]['harga_jual'])->toBe(395_000)
        ->and($isi[0]['harga_jual'])->toBe((int) $harap['untuk_pendaftaran']['harga_jual'])
        ->and($isi[0]['untung'])->toBe((int) $harap['untung']);
});

test('harga nego juga yang dipakai saat pendaftarannya benar-benar dipindahkan', function () {
    $paket = paketPindah('Private Trip Dieng 1 Hari', 999_000, 'private_trip');
    $eva = evaPindah($paket, berangkatPindah($paket, '2026-12-06'));
    $rab = pindahRabPrivate($paket, ['harga_nego' => 395_000]);

    pindahkan($eva, ['rab_id' => $rab->id, 'kelebihan' => 'saldo'])->assertOk();

    // Pelanggan ditagih harga yang DISEPAKATI, bukan harga hitungan.
    expect((int) $eva->fresh()->harga_jual)->toBe(395_000);
});
