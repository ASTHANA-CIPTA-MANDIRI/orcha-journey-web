<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;

/**
 * Angka di tab daftar pendaftaran.
 *
 * Angka itulah yang membuat tabnya berguna. Tanpanya "Perlu ditagih" adalah
 * tombol yang harus ditekan dulu untuk tahu apakah ada pekerjaan di baliknya
 * — dan tombol semacam itu berhenti ditekan setelah dua kali kosong,
 * termasuk pada hari ia berisi.
 */
function kepalaTab(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-tab');

    return ['X-Orcha-Key' => 'kunci-uji-tab', 'Accept' => 'application/json'];
}

function daftarUji(array $ubah = []): PendaftaranOpenTrip
{
    static $n = 0;
    $n++;

    return PendaftaranOpenTrip::create(array_merge([
        'kode' => 'OT-UJI-'.$n,
        'nama' => 'Peserta '.$n,
        'whatsapp' => '08120000'.$n,
        'email' => 'uji'.$n.'@contoh.test',
        'jumlah_peserta' => 2,
        'status' => 'baru',
        'nama_paket' => 'Open Trip Uji',
        'tanggal_berangkat' => now()->addDays(60)->toDateString(),
    ], $ubah));
}

it('menghitung tiap status dari seluruh data, bukan halaman yang tampil', function () {
    daftarUji(['status' => 'baru']);
    daftarUji(['status' => 'baru']);
    daftarUji(['status' => 'lunas']);
    daftarUji(['status' => 'batal']);

    $tab = $this->getJson('/api/v1/pendaftaran?per_halaman=1', kepalaTab())
        ->assertOk()->json('meta.jumlah_tab');

    // per_halaman=1, tapi angkanya tetap menghitung keempatnya.
    expect($tab['semua'])->toBe(4);
    expect($tab['baru'])->toBe(2);
    expect($tab['lunas'])->toBe(1);
    expect($tab['batal'])->toBe(1);
});

it('menyediakan kunci untuk setiap status di config', function () {
    daftarUji();

    $tab = $this->getJson('/api/v1/pendaftaran', kepalaTab())->assertOk()->json('meta.jumlah_tab');

    /*
     | Status yang ditambahkan di config lalu terlupa di sini menghasilkan
     | tab tanpa angka — dan tab yang selalu nol terbaca sebagai "tidak ada
     | pekerjaan di sana".
     */
    foreach (array_keys(config('orcha.status_pendaftaran')) as $status) {
        expect($tab)->toHaveKey($status);
    }
});

it('menghitung yang perlu ditagih', function () {
    // DP masuk & berangkat lusa — masuk hitungan.
    daftarUji(['status' => 'dp_masuk', 'tanggal_berangkat' => now()->addDays(2)->toDateString()]);
    // DP masuk tapi masih jauh — tidak.
    daftarUji(['status' => 'dp_masuk', 'tanggal_berangkat' => now()->addDays(90)->toDateString()]);
    // Sudah lunas — tidak.
    daftarUji(['status' => 'lunas', 'tanggal_berangkat' => now()->addDays(2)->toDateString()]);

    $tab = $this->getJson('/api/v1/pendaftaran', kepalaTab())->assertOk()->json('meta.jumlah_tab');

    expect($tab['perlu_ditagih'])->toBe(1);
});

it('angka tab TIDAK ikut menyusut saat status sedang disaring', function () {
    daftarUji(['status' => 'baru']);
    daftarUji(['status' => 'lunas']);

    $tab = $this->getJson('/api/v1/pendaftaran?status=lunas', kepalaTab())
        ->assertOk()->json('meta.jumlah_tab');

    /*
     | Angka di tab menjawab "kalau saya pindah ke sana, ada berapa" —
     | bukan memantulkan pilihan yang sedang aktif. Tab yang angkanya ikut
     | menyusut begitu ditekan tidak menjawab apa pun.
     */
    expect($tab['semua'])->toBe(2);
    expect($tab['baru'])->toBe(1);
});

it('angka tab IKUT menyusut saat paket atau pencarian disaring', function () {
    // Tanpa factory: paket cukup diwakili id-nya, dan yang diuji adalah
    // saringannya — bukan isi paketnya.
    daftarUji(['status' => 'baru', 'travel_package_id' => 777]);
    daftarUji(['status' => 'baru']);

    $tab = $this->getJson('/api/v1/pendaftaran?paket_id=777', kepalaTab())
        ->assertOk()->json('meta.jumlah_tab');

    /*
     | Berbeda dari status: memilih paket berarti "saya sedang mengurus
     | keberangkatan ini", dan angka yang masih menghitung paket lain
     | membuat tabnya berbohong tentang pekerjaan yang ada di depan mata.
     */
    expect($tab['semua'])->toBe(1);
    expect($tab['baru'])->toBe(1);
});

it('daftar tetap berbentuk seperti sebelumnya', function () {
    daftarUji();

    // Penomoran halaman di lemon membaca meta apa adanya; bentuknya tidak
    // boleh bergeser hanya karena satu kunci ditambahkan.
    $this->getJson('/api/v1/pendaftaran', kepalaTab())->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['halaman', 'per_halaman', 'total', 'halaman_terakhir', 'jumlah_tab']]);
});
