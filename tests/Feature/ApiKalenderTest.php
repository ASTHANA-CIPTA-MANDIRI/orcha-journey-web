<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\Rab;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

/**
 * Agenda Orcha untuk Kalender Kegiatan di lemon.
 *
 * Kalender di lemon membaca tabel `kegiatan` miliknya sendiri; keberangkatan
 * rombongan dan penyewaan kendaraan tidak ada di basis data itu. Jalur ini
 * jembatannya, dan yang dijaga di sini DUA hal: apa yang boleh muncul, dan
 * bagaimana trip yang lebih dari sehari dikabarkan.
 */

/*
 | Namanya diawali 'kalender' supaya tidak bertabrakan.
 |
 | Penolong Pest hidup di ruang nama GLOBAL: dua berkas uji yang sama-sama
 | punya fungsi bernama kepala() akan saling menimpa, dan --filter tidak akan
 | pernah menangkapnya karena sendirian keduanya hijau.
 */
function kepalaKalender(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-kalender');

    return ['X-Orcha-Key' => 'kunci-uji-kalender', 'Accept' => 'application/json'];
}

function paketKalender(string $kategori = 'private_trip'): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Trip '.$kategori,
        'category' => $kategori,
        'price' => 1500000,
        'status' => 'terbit',
    ]);
}

function daftarkanKalender(array $isi = []): PendaftaranOpenTrip
{
    $paket = $isi['paket'] ?? paketKalender();
    unset($isi['paket']);

    return PendaftaranOpenTrip::create(array_merge([
        'nama' => 'Bu Rina',
        'whatsapp' => '081200000001',
        'jumlah_peserta' => 12,
        'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name,
        'tanggal_berangkat' => '2026-12-10',
        'titik_jemput' => 'Jogja, Klaten',
        'status' => 'dp_masuk',
    ], $isi))->fresh();
}

function sewakanKalender(array $isi = []): PenyewaanKendaraan
{
    return PenyewaanKendaraan::create(array_merge([
        'nama' => 'Pak Budi',
        'whatsapp' => '081200000002',
        'nama_kendaraan' => 'HiAce Premio',
        // Wajib di tabelnya, dan tidak relevan bagi uji ini — diisi supaya
        // barisnya bisa dibuat, bukan karena kalender membacanya.
        'transmisi' => 'manual',
        'satuan' => 'hari',
        'durasi' => 3,
        'tanggal_mulai' => '2026-12-12',
        'tanggal_selesai' => '2026-12-14',
        'lokasi_antar' => 'Bandara YIA',
        'tujuan' => 'Bromo',
        'status' => 'dp_masuk',
    ], $isi))->fresh();
}

function mintaKalender(string $dari = '2026-12-01', string $sampai = '2026-12-31')
{
    return test()->getJson("/api/v1/kalender?dari={$dari}&sampai={$sampai}", kepalaKalender());
}

/* ------------------------------------------------ Apa yang boleh muncul */

test('hanya pendaftaran yang sudah DP yang muncul', function () {
    daftarkanKalender(['status' => 'dp_masuk', 'nama' => 'Sudah DP']);
    daftarkanKalender(['status' => 'lunas', 'nama' => 'Sudah Lunas', 'whatsapp' => '081200000003']);

    // Ketiganya belum tentu jadi berangkat. Kalender yang penuh rencana yang
    // batal berhenti dipercaya, jadi ketiganya TIDAK boleh muncul.
    daftarkanKalender(['status' => 'baru', 'nama' => 'Masih Baru', 'whatsapp' => '081200000004']);
    daftarkanKalender(['status' => 'dihubungi', 'nama' => 'Baru Dihubungi', 'whatsapp' => '081200000005']);
    daftarkanKalender(['status' => 'batal', 'nama' => 'Sudah Batal', 'whatsapp' => '081200000006']);

    $data = mintaKalender()->assertOk()->json('data');

    expect(collect($data)->pluck('pemesan')->sort()->values()->all())
        ->toBe(['Sudah DP', 'Sudah Lunas']);
});

test('hanya penyewaan yang sudah DP yang muncul', function () {
    sewakanKalender(['status' => 'dp_masuk', 'nama' => 'DP Masuk']);
    sewakanKalender(['status' => 'berjalan', 'nama' => 'Sedang Jalan']);
    sewakanKalender(['status' => 'selesai', 'nama' => 'Sudah Selesai']);

    // 'dikonfirmasi' berarti jadwalnya disepakati, BUKAN uangnya diterima.
    sewakanKalender(['status' => 'dikonfirmasi', 'nama' => 'Baru Dikonfirmasi']);
    sewakanKalender(['status' => 'baru', 'nama' => 'Masih Baru']);
    sewakanKalender(['status' => 'batal', 'nama' => 'Sudah Batal']);

    $data = mintaKalender()->assertOk()->json('data');

    expect(collect($data)->pluck('pemesan')->sort()->values()->all())
        ->toBe(['DP Masuk', 'Sedang Jalan', 'Sudah Selesai']);
});

/* --------------------------------------------- Isi yang dibawa tiap baris */

test('baris keberangkatan membawa jumlah peserta, tujuan, dan titik jemput', function () {
    daftarkanKalender([
        'paket' => paketKalender('private_trip'),
        'jumlah_peserta' => 18,
        'titik_jemput' => 'Terminal Giwangan',
    ]);

    $baris = mintaKalender()->assertOk()->json('data.0');

    expect($baris['sumber'])->toBe('pendaftaran')
        ->and($baris['kategori'])->toBe('private_trip')
        ->and($baris['kategori_label'])->toBe('Private Trip')
        ->and($baris['jumlah_peserta'])->toBe(18)
        ->and($baris['titik_jemput'])->toBe('Terminal Giwangan')
        ->and($baris['judul'])->toContain('private_trip')
        ->and($baris['status_label'])->toBe('DP Masuk');
});

test('penyewaan tidak mengaku punya jumlah peserta', function () {
    // Yang disewa kendaraannya, bukan kursinya. Nol akan terbaca sebagai
    // "tidak ada yang ikut", jadi yang benar null.
    sewakanKalender();

    $baris = mintaKalender()->assertOk()->json('data.0');

    expect($baris['sumber'])->toBe('penyewaan')
        ->and($baris['jumlah_peserta'])->toBeNull()
        ->and($baris['tujuan'])->toBe('Bromo')
        ->and($baris['titik_jemput'])->toBe('Bandara YIA');
});

/* ------------------------------------------------------- Trip lintas hari */

test('tanggal pulang diambil dari keberangkatan, bukan dari pendaftaran', function () {
    $paket = paketKalender();
    $berangkat = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-10',
        'tanggal_pulang' => '2026-12-13',
        'kuota' => 20,
        'aktif' => true,
    ]);

    daftarkanKalender(['paket' => $paket, 'keberangkatan_id' => $berangkat->id]);

    $baris = mintaKalender()->assertOk()->json('data.0');

    expect($baris['mulai'])->toBe('2026-12-10')
        ->and($baris['selesai'])->toBe('2026-12-13');
});

test('trip yang melewati pergantian bulan tetap terlihat di bulan keduanya', function () {
    $paket = paketKalender();
    $berangkat = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-11-29',
        'tanggal_pulang' => '2026-12-02',
        'kuota' => 20,
        'aktif' => true,
    ]);

    daftarkanKalender([
        'paket' => $paket,
        'keberangkatan_id' => $berangkat->id,
        'tanggal_berangkat' => '2026-11-29',
    ]);

    // Yang diuji TUMPANG TINDIHNYA, bukan tanggal mulainya: trip ini berangkat
    // di November, dan kalender Desember yang menyembunyikannya membuat admin
    // mengira armadanya kosong di dua hari pertama bulan itu.
    expect(mintaKalender('2026-12-01', '2026-12-31')->assertOk()->json('data'))->toHaveCount(1);
    expect(mintaKalender('2026-11-01', '2026-11-30')->assertOk()->json('data'))->toHaveCount(1);
});

test('tanggal pulang yang lebih awal daripada berangkat diabaikan', function () {
    $paket = paketKalender();
    $berangkat = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-10',
        // Salah ketik admin. Dipakai apa adanya, pitanya akan digambar mundur.
        'tanggal_pulang' => '2026-12-08',
        'kuota' => 20,
        'aktif' => true,
    ]);

    daftarkanKalender(['paket' => $paket, 'keberangkatan_id' => $berangkat->id]);

    expect(mintaKalender()->assertOk()->json('data.0.selesai'))->toBeNull();
});

/* --------------------------------------------------------------- Penjagaan */

test('rentang tanggal wajib dan tidak boleh terbalik', function () {
    test()->getJson('/api/v1/kalender', kepalaKalender())->assertStatus(422);
    test()->getJson('/api/v1/kalender?dari=2026-12-31&sampai=2026-12-01', kepalaKalender())
        ->assertStatus(422);
});

test('rentang yang terlalu lebar ditolak', function () {
    // Tanpa batas ini, satu permintaan "seluruh tahun" menarik ribuan baris
    // sekaligus — dan kalender tidak pernah membutuhkannya.
    mintaKalender('2026-01-01', '2026-12-31')->assertStatus(422);
});

test('tanpa kunci API jalurnya tertutup', function () {
    config()->set('orcha.api.kunci', 'kunci-uji-kalender');

    test()->getJson('/api/v1/kalender?dari=2026-12-01&sampai=2026-12-31')
        ->assertStatus(401);
});

/* ----------------------------------------------- RAB yang sudah deal */

function rabKalender(array $isi = []): Rab
{
    return Rab::create(array_merge([
        'judul' => 'Private Trip Dieng 2H1M',
        'nama_pelanggan' => 'Bu Sari',
        'jenis' => 'private',
        'provinsi' => 'Jawa Tengah',
        'daerah' => 'Dieng',
        'tanggal_mulai' => '2026-12-10',
        'jumlah_hari' => 3,
        'jumlah_malam' => 2,
        'jumlah_peserta' => 15,
        'status' => 'disetujui',
    ], $isi))->fresh();
}

test('RAB yang sudah disetujui muncul sebagai tanggal terpakai', function () {
    rabKalender();

    $baris = mintaKalender()->assertOk()->json('data.0');

    expect($baris['sumber'])->toBe('rab')
        ->and($baris['kategori'])->toBe('private_trip')
        ->and($baris['jumlah_peserta'])->toBe(15)
        ->and($baris['pemesan'])->toBe('Bu Sari')
        ->and($baris['tujuan'])->toBe('Dieng, Jawa Tengah')
        ->and($baris['status_label'])->toBe('Disetujui');
});

test('tanggal selesai RAB dihitung dari jumlah harinya', function () {
    // RAB tidak menyimpan tanggal pulang. Trip 3 hari yang mulai tanggal 10
    // berakhir tanggal 12 — hari pertama ikut dihitung.
    rabKalender(['tanggal_mulai' => '2026-12-10', 'jumlah_hari' => 3]);

    $baris = mintaKalender()->assertOk()->json('data.0');

    expect($baris['mulai'])->toBe('2026-12-10')
        ->and($baris['selesai'])->toBe('2026-12-12');
});

test('RAB sehari tidak mengaku punya tanggal selesai', function () {
    rabKalender(['jumlah_hari' => 1]);

    expect(mintaKalender()->assertOk()->json('data.0.selesai'))->toBeNull();
});

test('penawaran yang belum dijawab TIDAK menguasai tanggal', function () {
    // Draf dan "dikirim" baru penawaran. Kalender yang memasukkannya akan
    // menolak pesanan yang sebenarnya masih bisa dilayani.
    rabKalender(['status' => 'draf']);
    rabKalender(['status' => 'dikirim']);
    rabKalender(['status' => 'batal']);

    expect(mintaKalender()->assertOk()->json('data'))->toHaveCount(0);
});

test('RAB yang sudah jadi pendaftaran tidak muncul dua kali', function () {
    // Ia sudah terlihat lewat pendaftarannya; dua pita untuk satu perjalanan
    // membuat satu hari terbaca dua kali lebih penuh daripada keadaannya.
    rabKalender(['kode_pendaftaran' => 'OT-2612-ABC123']);

    expect(mintaKalender()->assertOk()->json('data'))->toHaveCount(0);
});

test('RAB yang mulai sebelum rentang tetap terlihat bila masih berjalan', function () {
    // Trip 29 November–2 Desember harus tetap tampak di kalender Desember:
    // dua hari pertama bulan itu armadanya sudah terpakai.
    rabKalender(['tanggal_mulai' => '2026-11-29', 'jumlah_hari' => 4]);

    expect(mintaKalender('2026-12-01', '2026-12-31')->assertOk()->json('data'))->toHaveCount(1);
});

test('trip yang sudah selesai tetap mengisi tanggalnya', function () {
    // Kalau dibuang, kalender bulan-bulan lampau terlihat kosong padahal
    // armadanya penuh.
    rabKalender(['status' => 'selesai']);

    expect(mintaKalender()->assertOk()->json('data'))->toHaveCount(1);
});

/* ------------------------------------ Jadwal yang sedang dijual di katalog */

/*
 | Sumber keempat: tanggal yang sudah dibuka untuk dijual, belum deal, dan
 | belum ada yang bayar.
 |
 | Tiga sumber lain semuanya komitmen, sehingga tanggal yang open trip-nya
 | sudah tayang untuk umum terlihat KOSONG — dan private trip lalu ditawarkan
 | di tanggal yang armadanya sudah dialokasikan. Yang dijaga di sini dua hal:
 | ia muncul dengan sumber sendiri (supaya bisa digambar berbeda), dan ia
 | TIDAK muncul dua kali bersama sumber lain.
 */
function jualKalender(array $isi = [], ?TravelPackage $paket = null): Keberangkatan
{
    $paket = $paket ?: paketKalender('open_trip');

    return Keberangkatan::create(array_merge([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-05',
        'tanggal_pulang' => '2026-12-06',
        'kuota' => 20,
        'aktif' => true,
    ], $isi))->fresh();
}

test('tanggal yang sedang dijual muncul dengan sumber sendiri', function () {
    jualKalender();

    $isi = mintaKalender()->assertOk()->json('data');

    expect($isi)->toHaveCount(1)
        ->and($isi[0]['sumber'])->toBe('katalog')
        ->and($isi[0]['status'])->toBe('dijual')
        ->and($isi[0]['status_label'])->toBe('Dijual — belum ada pendaftar')
        // Belum ada yang memesannya, jadi tidak boleh mengaku punya kode.
        ->and($isi[0]['kode'])->toBeNull()
        ->and($isi[0]['mulai'])->toBe('2026-12-05')
        ->and($isi[0]['selesai'])->toBe('2026-12-06');
});

test('pendaftar yang belum bayar disebutkan jumlahnya, bukan disembunyikan', function () {
    $paket = paketKalender('open_trip');
    $kb = jualKalender([], $paket);

    // 'baru' = belum bayar. Tanggalnya belum terkunci, tetapi ada yang antre.
    daftarkanKalender([
        'paket' => $paket, 'keberangkatan_id' => $kb->id,
        'tanggal_berangkat' => '2026-12-05', 'status' => 'baru',
    ]);

    $isi = mintaKalender()->assertOk()->json('data');

    expect($isi)->toHaveCount(1)
        ->and($isi[0]['sumber'])->toBe('katalog')
        ->and($isi[0]['jumlah_peserta'])->toBe(1)
        ->and($isi[0]['status_label'])->toBe('Dijual — 1 pendaftar belum bayar');
});

test('tanggal yang sudah ada pendaftar berbayar TIDAK terhitung dua kali', function () {
    $paket = paketKalender('open_trip');
    $kb = jualKalender([], $paket);

    daftarkanKalender([
        'paket' => $paket, 'keberangkatan_id' => $kb->id,
        'tanggal_berangkat' => '2026-12-05', 'status' => 'dp_masuk',
    ]);

    $isi = mintaKalender()->assertOk()->json('data');

    // Hanya satu baris, dan yang menang sumber 'pendaftaran' — komitmen
    // mengalahkan rencana.
    expect($isi)->toHaveCount(1)
        ->and($isi[0]['sumber'])->toBe('pendaftaran');
});

test('tanggal yang sudah diwakili RAB disetujui TIDAK terhitung dua kali', function () {
    $paket = paketKalender('open_trip');
    jualKalender([], $paket);

    rabKalender([
        'jenis' => 'open',
        'tanggal_mulai' => '2026-12-05',
        'jumlah_hari' => 2,
        'travel_package_id' => $paket->id,
        'status' => 'disetujui',
    ]);

    $isi = mintaKalender()->assertOk()->json('data');

    expect($isi)->toHaveCount(1)
        ->and($isi[0]['sumber'])->toBe('rab');
});

test('RAB untuk paket LAIN di tanggal sama tidak ikut menyembunyikan jadwal jualan', function () {
    $paket = paketKalender('open_trip');
    jualKalender([], $paket);

    // Paket berbeda, tanggal sama: dua rombongan yang benar-benar berbeda.
    rabKalender([
        'jenis' => 'open',
        'tanggal_mulai' => '2026-12-05',
        'jumlah_hari' => 1,
        'travel_package_id' => paketKalender('open_trip')->id,
        'status' => 'disetujui',
    ]);

    $isi = collect(mintaKalender()->assertOk()->json('data'));

    expect($isi)->toHaveCount(2)
        ->and($isi->pluck('sumber')->sort()->values()->all())->toBe(['katalog', 'rab']);
});

test('keberangkatan yang ditutup dan paket yang belum terbit tidak dijual', function () {
    jualKalender(['aktif' => false]);

    $draf = TravelPackage::create([
        'name' => 'Open Trip Draf', 'category' => 'open_trip',
        'price' => 500000, 'status' => 'draf',
    ]);
    jualKalender(['tanggal_berangkat' => '2026-12-07', 'tanggal_pulang' => '2026-12-07'], $draf);

    expect(mintaKalender()->assertOk()->json('data'))->toBe([]);
});
