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

/* ==================== SARING & URUT DAFTAR PAKET ==================== */

test('saringan keadaan penayangan sepakat dengan lencana yang tergambar', function () {
    /*
     | scopeStatusTayang menyalin aturan getStatusTayangAttribute, dan dua
     | tempat yang memutuskan hal sama bisa berbeda jawabannya suatu saat.
     | Yang dijaga di sini: untuk tiap keadaan, saringan SQL memulangkan
     | persis paket yang lencananya menyebut keadaan itu.
     */
    $buat = fn (array $u) => paketUji(array_merge(['name' => 'Paket '.\Illuminate\Support\Str::random(6)], $u));

    $buat(['status' => 'draf']);
    $buat(['status' => 'arsip']);
    $buat(['status' => 'terbit', 'tayang_mulai' => now()->addWeek()]);
    $buat(['status' => 'terbit', 'tayang_sampai' => now()->subDay()]);
    $buat(['status' => 'terbit', 'tanggal_berangkat' => now()->subWeek()->toDateString(), 'berakhir_otomatis' => true]);
    $buat(['status' => 'terbit', 'tanggal_berangkat' => now()->addMonth()->toDateString()]);

    foreach (['tayang', 'terjadwal', 'berakhir', 'draf', 'arsip'] as $keadaan) {
        $lewatSql = TravelPackage::statusTayang($keadaan)->pluck('id')->sort()->values();
        $lewatLencana = TravelPackage::all()
            ->filter(fn ($p) => $p->status_tayang === $keadaan)
            ->pluck('id')->sort()->values();

        expect($lewatSql->all())->toBe($lewatLencana->all(), "Saringan '$keadaan' tidak sepakat dengan lencananya.");
    }
});

test('saringan yang tidak dikenal memulangkan kosong, bukan semuanya', function () {
    // Saringan yang gagal tanpa suara terbaca sebagai data yang hilang —
    // dan "semuanya" justru terbaca sebagai saringan yang tidak bekerja.
    paketUji();

    expect(TravelPackage::statusTayang('entah-apa')->count())->toBe(0)
        ->and(TravelPackage::statusTayang('')->count())->toBe(1);
});

test('pengurutan selalu punya pemecah seri', function () {
    /*
     | Dua paket berharga sama tanpa pemecah seri bisa bertukar tempat antar
     | halaman: satu baris terbit dua kali dan baris lain tidak pernah
     | terlihat. SQLite kebetulan stabil, jadi yang diperiksa SQL-nya.
     */
    $sql = [];
    \Illuminate\Support\Facades\DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });

    foreach (['nama', 'harga-termurah', 'harga-termahal', 'berangkat', null] as $urut) {
        TravelPackage::urutkanDaftar($urut)->get();
    }

    foreach ($sql as $satu) {
        expect($satu)->toContain('order by');
        expect(str_contains($satu, '"id"') || str_contains($satu, '`id`'))
            ->toBeTrue("Pengurutan tanpa pemecah seri: {$satu}");
    }
});

test('daftar bisa disaring dan diurutkan lewat api', function () {
    paketUji(['name' => 'Zeta Trip', 'status' => 'terbit', 'price' => 900_000,
        'tanggal_berangkat' => now()->addMonth()->toDateString()]);
    paketUji(['name' => 'Alfa Trip', 'status' => 'draf', 'price' => 100_000]);

    $this->getJson('/api/v1/paket-wisata?tampil=draf', kirimPaket())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Alfa Trip');

    $this->getJson('/api/v1/paket-wisata?urut=nama', kirimPaket())
        ->assertOk()
        ->assertJsonPath('data.0.nama', 'Alfa Trip');

    $this->getJson('/api/v1/paket-wisata?urut=harga-termahal', kirimPaket())
        ->assertOk()
        ->assertJsonPath('data.0.nama', 'Zeta Trip');
});

/* ===================== PRATINJAU SEBELUM TERBIT ===================== */

test('paket draf tetap 404 untuk pengunjung biasa', function () {
    // Pengecualian pratinjau TIDAK boleh membuka paket draf untuk siapa saja
    // yang kebetulan tahu alamatnya.
    $paket = paketUji(['status' => 'draf']);

    $this->get(route('paket-detail', $paket->uuid))->assertNotFound();
});

test('tautan pratinjau bertanda tangan membuka paket yang belum tayang', function () {
    $paket = paketUji(['status' => 'draf']);

    $tautan = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'paket-detail', now()->addDays(7), ['paket' => $paket->uuid]
    );

    $this->get($tautan)
        ->assertOk()
        // Pita wajib ada: halaman pratinjau yang terlihat persis seperti
        // halaman terbit membuat admin mengira paketnya sudah tayang.
        ->assertSee('PRATINJAU')
        ->assertSee('belum tampil');
});

test('tautan pratinjau yang kedaluwarsa berhenti bekerja', function () {
    $paket = paketUji(['status' => 'draf']);

    $tautan = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'paket-detail', now()->addDay(), ['paket' => $paket->uuid]
    );

    // Tautan yang terlanjur tersebar berhenti bekerja tanpa perlu dicabut.
    $this->travel(8)->days();

    $this->get($tautan)->assertNotFound();
});

test('paket yang sudah tayang tidak memasang pita pratinjau', function () {
    $paket = paketUji(['status' => 'terbit', 'tanggal_berangkat' => now()->addMonth()->toDateString()]);

    $this->get(route('paket-detail', $paket->uuid))
        ->assertOk()
        ->assertDontSee('PRATINJAU');
});

test('menghapus seluruh foto galeri benar-benar mengosongkannya', function () {
    /*
     | Perataan multipart membuang senarai kosong, jadi borang yang
     | menghapus SEMUA foto mengirim muatan yang terbaca persis seperti
     | muatan yang tidak menyinggung galeri sama sekali. Tanpa penanda
     | eksplisit, foto yang baru dihapus tetap tampil tanpa satu pun galat.
     */
    $paket = paketUji([
        'foto_galeri' => ['/storage/paket/a.webp', '/storage/paket/b.webp'],
    ]);

    $this->postJson("/api/v1/paket-wisata/{$paket->id}", [
        'nama' => $paket->name,
        'kategori' => 'open_trip',
        'minimal_peserta' => 4,
        'harga' => 1_000_000,
        'galeri_disentuh' => 1,
        // galeri_tetap sengaja TIDAK dikirim — persis seperti yang terjadi
        // saat seluruh fotonya dihapus.
    ], kirimPaket())->assertOk();

    expect($paket->fresh()->foto_galeri)->toBeNull();
});

test('permintaan yang tidak menyinggung galeri tidak menghapusnya', function () {
    // Menyimpan perubahan harga saja tidak boleh menghilangkan dua belas foto.
    $paket = paketUji(['foto_galeri' => ['/storage/paket/a.webp']]);

    $this->postJson("/api/v1/paket-wisata/{$paket->id}", [
        'nama' => $paket->name,
        'kategori' => 'open_trip',
        'minimal_peserta' => 4,
        'harga' => 2_000_000,
    ], kirimPaket())->assertOk();

    expect($paket->fresh()->foto_galeri)->toBe(['/storage/paket/a.webp']);
});

/* ============ TANGGAL PAKET vs TANGGAL KEBERANGKATAN ============ */

test('kartu menyebut keberangkatan terdekat, bukan kolom lama paket', function () {
    /*
     | Keadaan nyata paket 7 di produksi: kolom tanggal_berangkat miliknya
     | tertinggal di 27 November, sementara rombongan yang benar-benar dijual
     | 4–6 dan 11–13 Desember. Kartu katalog memajang tanggal yang tidak bisa
     | dipesan siapa pun, dan tidak ada galat yang menyebutkannya.
     */
    $paket = paketUji([
        'status' => 'terbit',
        'tanggal_berangkat' => '2026-11-27',
        'tanggal_pulang' => '2026-11-29',
    ]);

    // Dibuat lewat query builder supaya kait penyelarasnya TIDAK ikut jalan —
    // inilah bentuk data yang sudah telanjur ada di produksi.
    foreach ([['2026-12-04', '2026-12-06'], ['2026-12-11', '2026-12-13']] as [$b, $p]) {
        \Illuminate\Support\Facades\DB::table('tbl_paket_keberangkatan')->insert([
            'travel_package_id' => $paket->id, 'tanggal_berangkat' => $b,
            'tanggal_pulang' => $p, 'aktif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $this->travelTo('2026-11-20');

    expect($paket->fresh()->jadwal_label)->toBe('4 – 6 Desember 2026')
        ->and($paket->fresh()->jumlah_tanggal_lain)->toBe(1);
});

test('paket tidak hilang dari katalog saat kolom lamanya lewat', function () {
    /*
     | Ini akibat yang jauh lebih mahal daripada tanggal yang salah tulis.
     | Dengan berakhir_otomatis, kolom 27 November membuat paketnya berhenti
     | tampil pada 28 November — sepuluh hari sebelum trip pertamanya, dengan
     | dua rombongan yang masih menerima pendaftar.
     */
    $paket = paketUji([
        'status' => 'terbit', 'berakhir_otomatis' => true,
        'tanggal_berangkat' => '2026-11-27', 'tanggal_pulang' => '2026-11-29',
    ]);

    \Illuminate\Support\Facades\DB::table('tbl_paket_keberangkatan')->insert([
        'travel_package_id' => $paket->id, 'tanggal_berangkat' => '2026-12-11',
        'tanggal_pulang' => '2026-12-13', 'aktif' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->travelTo('2026-11-28');

    expect(TravelPackage::tayang()->whereKey($paket->id)->exists())->toBeTrue()
        ->and($paket->fresh()->sedang_tayang)->toBeTrue();
});

test('paket benar-benar berhenti tampil saat SEMUA tanggalnya lewat', function () {
    // Jangan sampai perbaikannya berubah jadi "tidak pernah berakhir".
    $paket = paketUji([
        'status' => 'terbit', 'berakhir_otomatis' => true,
        'tanggal_berangkat' => '2026-11-27',
    ]);

    Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11', 'tanggal_pulang' => '2026-12-13',
    ]);

    $this->travelTo('2026-12-14');

    expect(TravelPackage::tayang()->whereKey($paket->id)->exists())->toBeFalse()
        ->and($paket->fresh()->sedang_tayang)->toBeFalse();
});

test('keberangkatan yang ditutup tidak menahan paket tetap tampil', function () {
    $paket = paketUji([
        'status' => 'terbit', 'berakhir_otomatis' => true,
        'tanggal_berangkat' => '2026-11-27',
    ]);

    Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11', 'aktif' => false,
    ]);

    $this->travelTo('2026-11-28');

    expect(TravelPackage::tayang()->whereKey($paket->id)->exists())->toBeFalse();
});

test('menyimpan keberangkatan ikut menyelaraskan kolom tanggal paketnya', function () {
    // Supaya kolom peninggalan itu tidak pernah lagi tertinggal diam-diam di
    // belasan tempat yang masih membacanya: PDF, pengingat, kartu katalog.
    $paket = paketUji(['tanggal_berangkat' => '2026-11-27', 'tanggal_pulang' => '2026-11-29']);

    $this->travelTo('2026-11-20');

    Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-04', 'tanggal_pulang' => '2026-12-06',
    ]);

    expect($paket->fresh()->tanggal_berangkat->toDateString())->toBe('2026-12-04')
        ->and($paket->fresh()->tanggal_pulang->toDateString())->toBe('2026-12-06');
});

test('menghapus keberangkatan terdekat memundurkan kolomnya ke yang berikutnya', function () {
    $paket = paketUji();

    $this->travelTo('2026-11-20');

    $dekat = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-04', 'tanggal_pulang' => '2026-12-06',
    ]);

    Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11', 'tanggal_pulang' => '2026-12-13',
    ]);

    expect($paket->fresh()->tanggal_berangkat->toDateString())->toBe('2026-12-04');

    $dekat->delete();

    expect($paket->fresh()->tanggal_berangkat->toDateString())->toBe('2026-12-11');
});

test('menghapus keberangkatan terakhir tidak mengosongkan kolomnya', function () {
    // Mengosongkannya membuat paket kehilangan satu-satunya tanggal yang
    // pernah dimilikinya, dan aturan berakhir-otomatis kehilangan acuan.
    $paket = paketUji(['tanggal_berangkat' => '2026-12-04']);

    $this->travelTo('2026-11-20');

    $satu = Keberangkatan::create([
        'travel_package_id' => $paket->id, 'tanggal_berangkat' => '2026-12-04',
    ]);

    $satu->delete();

    expect($paket->fresh()->tanggal_berangkat?->toDateString())->toBe('2026-12-04');
});

/* ====== RINGKASAN PENDAFTARAN IKUT TANGGAL YANG DIPILIH ====== */

test('ringkasan pendaftaran menyebut tanggal yang dipilih, bukan yang terdekat', function () {
    /*
     | Pemesan memilih 11–13 Desember, tetapi kotak "Sudah ditetapkan oleh
     | Orcha Journey" menulis 4–6 Desember — rombongan TERDEKAT milik paket.
     | Ia lalu menekan Daftar dengan yakin sudah memesan tanggal yang benar,
     | dan salahnya baru ketahuan di hari keberangkatan saat orangnya datang
     | pada tanggal yang lain.
     */
    $paket = paketUji([
        'status' => 'terbit', 'category' => 'open_trip',
        'minimal_peserta' => 1, 'kuota' => 20, 'titik_jemput' => 'Jogja',
    ]);

    $this->travelTo('2026-11-20');

    $dekat = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-04', 'tanggal_pulang' => '2026-12-06',
    ]);

    $jauh = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11', 'tanggal_pulang' => '2026-12-13',
    ]);

    $halaman = \Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('keberangkatanId', (string) $jauh->id);

    // Yang dipilih tampil; yang terdekat TIDAK ikut muncul sebagai tanggal
    // berangkat.
    $halaman->assertSee('11 – 13 Desember 2026');

    expect($halaman->instance()->keberangkatanTerpilih()?->id)->toBe($jauh->id)
        ->and($paket->fresh()->jadwal_label)->toBe('4 – 6 Desember 2026');
});

test('pilihan paket tidak menyebut satu tanggal bila rombongannya banyak', function () {
    // Menyebut salah satunya membuat dua isian terbaca saling bertentangan:
    // kotak paket bilang 4 Desember, kotak tanggal menawarkan 11 Desember.
    $paket = paketUji(['status' => 'terbit', 'category' => 'open_trip', 'minimal_peserta' => 1]);

    $this->travelTo('2026-11-20');

    foreach (['2026-12-04', '2026-12-11'] as $tgl) {
        Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => $tgl]);
    }

    \Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->assertSee('2 pilihan tanggal')
        ->assertDontSee($paket->name.' · 4 Desember 2026');
});

test('paket bertanggal satu tetap menyebut tanggalnya', function () {
    // Jangan sampai perbaikannya menghilangkan keterangan yang memang
    // berguna pada paket yang cuma punya satu rombongan.
    $paket = paketUji(['status' => 'terbit', 'category' => 'open_trip', 'minimal_peserta' => 1]);

    $this->travelTo('2026-11-20');

    Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-04', 'tanggal_pulang' => '2026-12-06',
    ]);

    \Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->assertSee('4 – 6 Desember 2026')
        ->assertDontSee('pilihan tanggal');
});

test('pilihan tanggal ganjil tidak menyisakan lubang di barisnya', function () {
    /*
     | Tiga tanggal di kisi dua kolom menyisakan satu kotak kosong di sebelah
     | yang terakhir. Kotak itu bertepi — sama seperti tetangganya — sehingga
     | terbaca sebagai tanggal keempat yang gagal dimuat, bukan sebagai ruang
     | kosong. Yang ganjil di baris terakhir karena itu dilebarkan.
     */
    $paket = paketUji(['status' => 'terbit', 'category' => 'open_trip']);

    $this->travelTo('2026-11-16');

    foreach (['2026-11-27', '2026-12-04', '2026-12-11'] as $tgl) {
        Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => $tgl]);
    }

    $isi = $this->get(route('paket-detail', $paket->uuid))->assertOk()->getContent();

    $mulai = strpos($isi, 'Pilihan tanggal keberangkatan');
    $kotak = substr($isi, $mulai, strpos($isi, '</ul>', $mulai) - $mulai);

    // Tepat SATU yang dilebarkan, dan itu yang terakhir.
    expect(substr_count($kotak, 'sm:col-span-2'))->toBe(1)
        ->and(strrpos($kotak, 'sm:col-span-2'))->toBeGreaterThan(strrpos($kotak, '11 – 13') - 400);
});

test('pilihan tanggal genap tidak ada yang dilebarkan', function () {
    // Barisnya sudah penuh sendiri; melebarkan salah satunya justru membuat
    // satu tanggal terlihat berbeda derajat dari yang lain.
    $paket = paketUji(['status' => 'terbit', 'category' => 'open_trip']);

    $this->travelTo('2026-11-16');

    foreach (['2026-12-04', '2026-12-11'] as $tgl) {
        Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => $tgl]);
    }

    $isi = $this->get(route('paket-detail', $paket->uuid))->assertOk()->getContent();

    $mulai = strpos($isi, 'Pilihan tanggal keberangkatan');
    $kotak = substr($isi, $mulai, strpos($isi, '</ul>', $mulai) - $mulai);

    expect($kotak)->not->toContain('sm:col-span-2');
});

/* ========= PENDAFTARAN: ROMBONGAN IKUT SAMPAI KE LEMON ========= */

test('daftar pendaftaran bisa disaring per rombongan, bukan cuma per paket', function () {
    /*
     | Sejak satu paket boleh dijual untuk beberapa tanggal, "satu
     | keberangkatan = satu paket" tidak lagi benar. Manifes tour leader
     | dibentuk dari daftar yang sedang tampil — saringan per paket membuatnya
     | memuat tiga rombongan sekaligus, dan kelebihannya baru ketahuan di
     | parkiran.
     */
    $paket = paketUji(['status' => 'terbit', 'category' => 'open_trip']);

    $this->travelTo('2026-11-16');

    $awal = Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => '2026-12-04']);
    $akhir = Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => '2026-12-11']);

    daftarPaketUji(['travel_package_id' => $paket->id, 'keberangkatan_id' => $awal->id, 'nama' => 'Rombongan Awal']);
    daftarPaketUji(['travel_package_id' => $paket->id, 'keberangkatan_id' => $akhir->id, 'nama' => 'Rombongan Akhir']);

    $this->getJson("/api/v1/pendaftaran?keberangkatan_id={$akhir->id}", kirimPaket())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Rombongan Akhir');

    // Saringan per paket tetap bekerja, dan memang memuat keduanya.
    $this->getJson("/api/v1/pendaftaran?paket_id={$paket->id}", kirimPaket())
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('tiap pendaftaran menyebut rombongan mana yang diikutinya', function () {
    // tanggal_berangkat saja tidak memberi tahu rombongan MANA; pada paket
    // dengan dua tanggal berdekatan, dua baris bertanggal mirip terbaca
    // seperti salah satunya salah ketik.
    $paket = paketUji(['status' => 'terbit', 'category' => 'open_trip']);

    $this->travelTo('2026-11-16');

    $kb = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11', 'tanggal_pulang' => '2026-12-13',
    ]);

    daftarPaketUji(['travel_package_id' => $paket->id, 'keberangkatan_id' => $kb->id]);

    $this->getJson('/api/v1/pendaftaran', kirimPaket())
        ->assertOk()
        ->assertJsonPath('data.0.keberangkatan_id', $kb->id)
        ->assertJsonPath('data.0.keberangkatan.rentang_tanggal', '11 – 13 Desember 2026');
});

test('rujukan paket membawa daftar rombongannya untuk pemilih di lemon', function () {
    // Tanpa ini lemon tidak punya bahan untuk menawarkan pilihan tanggal,
    // dan admin terpaksa mendaftarkan rombongan tanpa menyebut tanggalnya.
    $paket = paketUji(['status' => 'terbit', 'category' => 'open_trip']);

    $this->travelTo('2026-11-16');

    foreach (['2026-12-04', '2026-12-11'] as $tgl) {
        Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => $tgl]);
    }

    $balasan = $this->getJson('/api/v1/rujukan', kirimPaket())->assertOk();

    $isi = collect($balasan->json('data.paket_wisata'))->firstWhere('id', $paket->id);

    expect($isi['keberangkatan'])->toHaveCount(2)
        ->and($isi['keberangkatan'][0]['rentang_tanggal'])->toBe('4 Desember 2026');
});
