<?php

/**
 * Menahan kursi untuk orang yang mengantre, ringkasan satu rombongan, dan
 * nama yang terdaftar di dua rombongan sekaligus.
 */

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\DaftarTunggu;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\TravelPackage;

function twrKepala(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-tawaran');

    return ['X-Orcha-Key' => 'kunci-uji-tawaran', 'Accept' => 'application/json'];
}

function twrPaket(): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Tawaran', 'category' => 'open_trip', 'status' => 'terbit',
        'price' => 1_000_000, 'tanggal_berangkat' => now()->addDays(30)->toDateString(),
    ]);
}

function twrRombongan(TravelPackage $paket, int $hari = 30, ?int $kuota = 10): Keberangkatan
{
    return Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => now()->addDays($hari)->toDateString(),
        'kuota' => $kuota, 'aktif' => true,
    ]);
}

function twrAntre(TravelPackage $paket, string $nama): DaftarTunggu
{
    /*
     | Nomornya dibuat berbeda tiap pemanggilan.
     |
     | tbl_daftar_tunggu punya kunci unik (paket, whatsapp) — satu nomor hanya
     | boleh mengantre sekali per paket, yang memang benar. Dua pengantre
     | dengan nomor yang sama bukan keadaan yang bisa diuji.
     */
    static $urut = 0;
    $urut++;

    return DaftarTunggu::create([
        'travel_package_id' => $paket->id,
        'nama' => $nama,
        'whatsapp' => '0812000000'.str_pad((string) $urut, 2, '0', STR_PAD_LEFT),
        'jumlah_peserta' => 2,
    ]);
}

function twrDaftar(TravelPackage $paket, Keberangkatan $kb, array $peserta, string $nama = 'Pemesan'): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => $nama, 'whatsapp' => '081200000001',
        'jumlah_peserta' => count($peserta),
        'travel_package_id' => $paket->id, 'nama_paket' => $paket->name,
        'keberangkatan_id' => $kb->id, 'tanggal_berangkat' => $kb->tanggal_berangkat,
        'daftar_peserta' => $peserta,
    ])->fresh();
}

test('kursi bisa ditahan untuk orang yang mengantre, lengkap dengan batas waktunya', function () {
    $paket = twrPaket();
    $kb = twrRombongan($paket);
    $antre = twrAntre($paket, 'Bu Rina');

    $this->withHeaders(twrKepala())
        ->postJson("/api/v1/daftar-tunggu/{$antre->id}/tawarkan", [
            'keberangkatan_id' => $kb->id,
            'tahan_jam' => 6,
        ])
        ->assertOk()
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'ditahan untuk Bu Rina'));

    $segar = $antre->fresh();

    expect($segar->keberangkatan_id)->toBe($kb->id);
    expect($segar->ditawarkan_pada)->not->toBeNull();
    expect($segar->ditahan_sampai->isFuture())->toBeTrue();
    expect($segar->keadaan_tawaran)->toBe('ditahan');
});

test('kursi yang sedang ditahan tidak bisa ditawarkan ke orang kedua', function () {
    /*
     | Inilah sebab seluruh pencatatan ini ada. Tanpa penjaga ini, dua admin
     | yang bekerja di jam yang sama menjanjikan kursi yang sama kepada dua
     | orang, dan yang kedua datang ke titik jemput tanpa kursi.
     */
    $paket = twrPaket();
    $kb = twrRombongan($paket);
    $pertama = twrAntre($paket, 'Bu Rina');
    $kedua = twrAntre($paket, 'Pak Agus');

    $this->withHeaders(twrKepala())
        ->postJson("/api/v1/daftar-tunggu/{$pertama->id}/tawarkan", ['keberangkatan_id' => $kb->id])
        ->assertOk();

    $this->withHeaders(twrKepala())
        ->postJson("/api/v1/daftar-tunggu/{$kedua->id}/tawarkan", ['keberangkatan_id' => $kb->id])
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'Bu Rina'));

    expect($kedua->fresh()->keberangkatan_id)->toBeNull();
});

test('tahanan yang sudah lewat tidak lagi menghalangi orang berikutnya', function () {
    $paket = twrPaket();
    $kb = twrRombongan($paket);
    $pertama = twrAntre($paket, 'Bu Rina');
    $kedua = twrAntre($paket, 'Pak Agus');

    $pertama->update([
        'keberangkatan_id' => $kb->id,
        'ditawarkan_pada' => now()->subDay(),
        'ditahan_sampai' => now()->subHours(2),
    ]);

    expect($pertama->fresh()->keadaan_tawaran)->toBe('lewat');

    $this->withHeaders(twrKepala())
        ->postJson("/api/v1/daftar-tunggu/{$kedua->id}/tawarkan", ['keberangkatan_id' => $kb->id])
        ->assertOk();
});

test('tawaran bisa dilepas supaya kursinya kembali bebas', function () {
    $paket = twrPaket();
    $kb = twrRombongan($paket);
    $antre = twrAntre($paket, 'Bu Rina');

    $this->withHeaders(twrKepala())
        ->postJson("/api/v1/daftar-tunggu/{$antre->id}/tawarkan", ['keberangkatan_id' => $kb->id])
        ->assertOk();

    $this->withHeaders(twrKepala())
        ->deleteJson("/api/v1/daftar-tunggu/{$antre->id}/tawarkan")
        ->assertOk();

    expect($antre->fresh()->keadaan_tawaran)->toBe('belum');
});

test('rombongan milik paket lain ditolak', function () {
    $paket = twrPaket();
    $lain = TravelPackage::create([
        'name' => 'Paket Lain', 'category' => 'open_trip', 'status' => 'terbit',
        'price' => 500_000, 'tanggal_berangkat' => now()->addDays(20)->toDateString(),
    ]);

    $antre = twrAntre($paket, 'Bu Rina');

    $this->withHeaders(twrKepala())
        ->postJson("/api/v1/daftar-tunggu/{$antre->id}/tawarkan", [
            'keberangkatan_id' => twrRombongan($lain, 20)->id,
        ])
        ->assertStatus(422);
});

test('keadaan tawaran ikut terbaca di daftar antrean', function () {
    $paket = twrPaket();
    $kb = twrRombongan($paket);
    $antre = twrAntre($paket, 'Bu Rina');

    $this->withHeaders(twrKepala())
        ->postJson("/api/v1/daftar-tunggu/{$antre->id}/tawarkan", ['keberangkatan_id' => $kb->id])
        ->assertOk();

    $balasan = $this->withHeaders(twrKepala())->getJson('/api/v1/daftar-tunggu')->assertOk();

    expect($balasan->json('data.0.keadaan_tawaran'))->toBe('ditahan');
    expect($balasan->json('data.0.rombongan'))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Ringkasan satu rombongan
|--------------------------------------------------------------------------
*/

test('daftar yang disaring per rombongan membawa ringkasan uangnya', function () {
    /*
     | Pertanyaan yang dijawabnya ditanyakan setiap kali rombongan diputuskan
     | jadi berangkat atau tidak: "sudah berapa kursi, uangnya masuk berapa".
     | Sebelum ini jawabannya dirakit admin sendiri dari daftar.
     */
    $paket = twrPaket();
    $kb = twrRombongan($paket, 30, 10);

    $a = twrDaftar($paket, $kb, [['nama' => 'A'], ['nama' => 'B']], 'Pemesan A');
    twrDaftar($paket, $kb, [['nama' => 'C']], 'Pemesan B');

    KonfirmasiPembayaran::create([
        'kode' => $a->kode, 'jenis' => 'dp', 'nominal' => 1_200_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'A', 'status' => 'diterima',
    ]);

    $meta = $this->withHeaders(twrKepala())
        ->getJson('/api/v1/pendaftaran?keberangkatan_id='.$kb->id)
        ->assertOk()
        ->json('meta.rombongan');

    expect($meta['pendaftaran'])->toBe(2);
    expect($meta['peserta'])->toBe(3);
    expect($meta['kuota'])->toBe(10);
    expect($meta['sisa_kursi'])->toBe(7);
    expect($meta['tagihan'])->toBe(3_000_000);
    expect($meta['masuk'])->toBe(1_200_000);
    expect($meta['kurang'])->toBe(1_800_000);
});

test('ringkasan tidak ikut saat daftar tidak disaring per rombongan', function () {
    /*
     | Di luar saringan rombongan angkanya tidak menjawab apa pun — ia
     | menjumlahkan tiga keberangkatan yang berbeda tanggal dan berbeda harga.
     */
    $paket = twrPaket();
    twrDaftar($paket, twrRombongan($paket), [['nama' => 'A']]);

    $meta = $this->withHeaders(twrKepala())->getJson('/api/v1/pendaftaran')->assertOk()->json('meta');

    expect($meta)->not->toHaveKey('rombongan');
});

test('pendaftaran yang batal tidak ikut dihitung di ringkasan', function () {
    $paket = twrPaket();
    $kb = twrRombongan($paket, 30, 10);

    twrDaftar($paket, $kb, [['nama' => 'A']], 'Aktif');
    twrDaftar($paket, $kb, [['nama' => 'B']], 'Batal')->update(['status' => 'batal']);

    $meta = $this->withHeaders(twrKepala())
        ->getJson('/api/v1/pendaftaran?keberangkatan_id='.$kb->id)
        ->assertOk()->json('meta.rombongan');

    expect($meta['pendaftaran'])->toBe(1);
    expect($meta['peserta'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Nama yang terdaftar di dua rombongan
|--------------------------------------------------------------------------
*/

test('peserta yang juga terdaftar di rombongan lain ditandai', function () {
    /*
     | Keduanya terhitung di kuota, keduanya dapat kursi, dan yang satu pasti
     | tidak datang.
     */
    $paket = twrPaket();
    $ini = twrRombongan($paket, 30);
    $lain = twrRombongan($paket, 60);

    twrDaftar($paket, $ini, [['nama' => 'Budi Santoso'], ['nama' => 'Siti']], 'Pemesan A');
    twrDaftar($paket, $lain, [['nama' => 'budi santoso']], 'Pemesan B');

    $peserta = collect($this->withHeaders(twrKepala())
        ->getJson("/api/v1/keberangkatan/{$ini->id}/pembagian")
        ->assertOk()
        ->json('data.peserta'))->keyBy('nama');

    expect($peserta['Budi Santoso']['juga_di'])->not->toBeNull();
    expect($peserta['Siti']['juga_di'])->toBeNull();
});

test('rombongan yang sudah lewat tidak ikut dihitung sebagai bentrok', function () {
    /*
     | Orang yang ikut trip bulan lalu lalu mendaftar lagi bukan pendaftaran
     | ganda — itu pelanggan yang kembali.
     */
    $paket = twrPaket();
    $ini = twrRombongan($paket, 30);

    $lampau = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => now()->subDays(40)->toDateString(),
        'kuota' => 10, 'aktif' => false,
    ]);

    twrDaftar($paket, $ini, [['nama' => 'Budi Santoso']], 'Sekarang');
    twrDaftar($paket, $lampau, [['nama' => 'Budi Santoso']], 'Dulu');

    $peserta = $this->withHeaders(twrKepala())
        ->getJson("/api/v1/keberangkatan/{$ini->id}/pembagian")
        ->assertOk()->json('data.peserta');

    expect($peserta[0]['juga_di'])->toBeNull();
});
