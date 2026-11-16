<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\TravelPackage;
use App\Support\PengingatPesanan;

require_once __DIR__.'/KeberangkatanPaketTest.php';

/**
 * Tiga kekurangan paket wisata yang ditutup sekaligus.
 *
 * Ketiganya sama-sama tidak pernah memunculkan galat: batas pelunasan yang
 * salah terbaca seperti angka yang benar, daftar "tidak termasuk" yang tidak
 * ada hanya jadi keluhan di lokasi, dan paket yang harus diketik ulang cuma
 * memakan waktu admin tanpa ada yang mencatatnya.
 */

/*
 | Kunci API disiapkan di SINI, bukan dengan me-require berkas uji lain.
 |
 | require atas berkas Pest mendaftarkan ulang seluruh uji di dalamnya — satu
 | require sempat membuat 100 uji milik ApiDashboardTest berjalan dua kali dan
 | merah semua. Yang dipinjam cukup nilainya, bukan berkasnya.
 */
beforeEach(function () {
    config()->set('orcha.api.kunci', 'kunci-uji-paket-lengkap');
    config()->set('orcha.api.ip_diizinkan', []);
});

/** Pendaftaran minimum; model ini tidak punya factory. */
function daftarPaketUji(array $ubah = []): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create(array_merge([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 2,
        'nama_paket' => 'Open Trip Banyuwangi',
        'tanggal_berangkat' => '2026-12-11',
    ], $ubah));
}

function kirimPaket(array $tambahan = []): array
{
    return array_merge([
        'X-Orcha-Key' => 'kunci-uji-paket-lengkap',
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ], $tambahan);
}

/* ===================== BATAS PELUNASAN PER TANGGAL ===================== */

test('batas pelunasan dihitung dari tanggal keberangkatan, bukan tanggal paket', function () {
    $paket = paketUji(['tanggal_berangkat' => '2026-11-27', 'tanggal_pulang' => '2026-11-29']);

    $jauh = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11',
        'tanggal_pulang' => '2026-12-13',
    ]);

    $jarak = (int) config('orcha.pembayaran.pelunasan_hari_sebelum');

    /*
     | Paketnya berangkat 27 November, rombongan ini 11 Desember. Batas milik
     | paket terlambat dua minggu untuk rombongan ini — dan arahnya ke
     | belakang, jadi yang terbaca pemesan adalah tanggal yang SUDAH LEWAT.
     */
    expect($jauh->batas_pelunasan->toDateString())
        ->toBe(now()->parse('2026-12-11')->subDays($jarak)->toDateString())
        ->and($jauh->batas_pelunasan->toDateString())
        ->not->toBe($paket->batas_pelunasan->toDateString());
});

test('surat pelunasan memakai batas milik rombongan pendaftarnya', function () {
    $paket = paketUji(['tanggal_berangkat' => '2026-11-27']);

    $kb = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11',
        'tanggal_pulang' => '2026-12-13',
    ]);

    $daftar = daftarPaketUji([
        'travel_package_id' => $paket->id,
        'keberangkatan_id' => $kb->id,
        'tanggal_berangkat' => '2026-12-11',
        'status' => 'dp_masuk',
    ]);

    // Dibaca lewat relasinya, persis seperti yang dilakukan suratnya.
    expect($daftar->fresh()->keberangkatan->batas_pelunasan->toDateString())
        ->toBe($kb->batas_pelunasan->toDateString());
});

test('pendaftaran lama tanpa keberangkatan tetap punya batas pelunasan', function () {
    // Pendaftaran yang masuk sebelum tabel keberangkatan ada: jalur mundurnya
    // ke paket WAJIB tetap bekerja, bukan memulangkan null dan menulis "—".
    $paket = paketUji(['tanggal_berangkat' => '2026-11-27']);

    $daftar = daftarPaketUji([
        'travel_package_id' => $paket->id,
        'keberangkatan_id' => null,
        'tanggal_berangkat' => '2026-11-27',
        'status' => 'dp_masuk',
    ]);

    $batas = $daftar->keberangkatan?->batas_pelunasan ?? $daftar->paket?->batas_pelunasan;

    expect($batas)->not->toBeNull()
        ->and($batas->toDateString())->toBe($paket->batas_pelunasan->toDateString());
});

/* ========================== TIDAK TERMASUK ========================== */

test('tidak termasuk tersimpan sebagai daftarnya sendiri', function () {
    $paket = paketUji([
        'fasilitas' => ['Transport AC', 'Makan 3x'],
        'tidak_termasuk' => ['Tiket masuk wisata', 'Pengeluaran pribadi'],
    ]);

    expect($paket->fresh()->tidak_termasuk)
        ->toBe(['Tiket masuk wisata', 'Pengeluaran pribadi'])
        // Tidak mencemari fasilitas: keduanya daftar terpisah, dan menggabung
        // keduanya membuat "Tiket masuk" terbaca sebagai yang DIDAPAT.
        ->and($paket->fresh()->fasilitas)->toBe(['Transport AC', 'Makan 3x']);
});

test('tidak termasuk yang belum diisi tetap null, bukan senarai kosong', function () {
    // Null berarti "belum diisi" dan senarai kosong berarti "memang tidak
    // ada". Paket lama seluruhnya yang pertama, dan halaman publik tidak
    // boleh memasang judul "Tidak Termasuk" di atas daftar kosong.
    expect(paketUji()->fresh()->tidak_termasuk)->toBeNull();
});

/* ============================ GALERI FOTO ============================ */

test('galeri paket menyimpan banyak foto, terpisah dari sampulnya', function () {
    $paket = paketUji([
        'foto' => '/storage/paket/sampul.webp',
        'foto_galeri' => ['/storage/paket/a.webp', '/storage/paket/b.webp'],
    ]);

    expect($paket->fresh()->foto_galeri)->toHaveCount(2)
        ->and($paket->fresh()->foto)->toBe('/storage/paket/sampul.webp');
});

/* =========================== DUPLIKAT PAKET =========================== */

test('salinan paket lahir sebagai draf tanpa tanggal dan tanpa pendaftar', function () {
    $paket = paketUji([
        'status' => 'terbit',
        'tanggal_berangkat' => '2026-11-27',
        'tanggal_pulang' => '2026-11-29',
        'tayang_mulai' => '2026-10-01 08:00:00',
        'tayang_sampai' => '2026-11-26 23:59:00',
        'fasilitas' => ['Transport AC'],
        'tidak_termasuk' => ['Tiket masuk'],
        'itinerary' => [['hari' => 'Hari 1', 'agenda' => [['jam' => '05.00', 'kegiatan' => 'Berangkat']]]],
    ]);

    Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-11-27',
    ]);

    $balasan = $this->postJson("/api/v1/paket-wisata/{$paket->id}/salin", [], kirimPaket());

    $balasan->assertCreated();

    $salinan = TravelPackage::where('name', 'like', '%(salinan)%')->firstOrFail();

    /*
     | Isi yang mahal diketik ulang IKUT; keadaan yang mengikat waktu TIDAK.
     |
     | Salinan yang langsung terbit membuat dua paket serupa berdiri
     | bersebelahan di katalog sampai ada yang menyadarinya, dan jendela
     | penayangan milik paket lama bisa membuat salinannya berakhir sebelum
     | sempat disunting.
     */
    expect($salinan->fasilitas)->toBe(['Transport AC'])
        ->and($salinan->tidak_termasuk)->toBe(['Tiket masuk'])
        ->and($salinan->itinerary)->toHaveCount(1)
        ->and($salinan->status)->toBe('draf')
        ->and($salinan->tanggal_berangkat)->toBeNull()
        ->and($salinan->tayang_mulai)->toBeNull()
        ->and($salinan->tayang_sampai)->toBeNull()
        ->and($salinan->keberangkatan()->count())->toBe(0)
        ->and($salinan->uuid)->not->toBe($paket->uuid);
});

test('menyalin dua kali tidak menghasilkan dua nama yang sama persis', function () {
    // Dua baris bernama persis sama di katalog admin hanya bisa dibedakan
    // dengan membuka keduanya satu per satu.
    $paket = paketUji();

    $this->postJson("/api/v1/paket-wisata/{$paket->id}/salin", [], kirimPaket())->assertCreated();
    $this->postJson("/api/v1/paket-wisata/{$paket->id}/salin", [], kirimPaket())->assertCreated();

    $nama = TravelPackage::where('name', 'like', '%salinan%')->pluck('name');

    expect($nama)->toHaveCount(2)
        ->and($nama->unique())->toHaveCount(2);
});

/* ====================== JUMLAH TANGGAL DI DAFTAR ====================== */

test('daftar paket menyebut berapa tanggal yang dijual', function () {
    $paket = paketUji(['status' => 'terbit']);

    foreach (['2026-11-27', '2026-12-04', '2026-12-11'] as $tanggal) {
        Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => $tanggal]);
    }

    // Tanpa angka ini, layar daftar hanya melihat tanggal lama milik paket
    // dan membacanya sebagai satu tanggal saja.
    $this->getJson('/api/v1/paket-wisata', kirimPaket())
        ->assertOk()
        ->assertJsonPath('data.0.jumlah_keberangkatan', 3);
});
