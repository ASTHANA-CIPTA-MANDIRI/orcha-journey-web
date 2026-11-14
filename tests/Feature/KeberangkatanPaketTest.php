<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\TravelPackage;

/**
 * Satu paket, banyak tanggal keberangkatan.
 *
 * Sebelumnya paket hanya punya satu tanggal, jadi open trip yang sama untuk
 * tiga tanggal berarti tiga paket — tiga kali mengetik itinerary, dan tiga
 * tempat yang harus diubah saat satu kegiatan bergeser.
 */
function paketUji(array $ubah = []): TravelPackage
{
    return TravelPackage::create(array_merge([
        'name' => 'Open Trip Banyuwangi Blue Fire',
        'category' => 'open-trip',
        'status' => 'draft',
        'duration' => '3 Hari 2 Malam',
        'price' => 1_300_000,
        'kuota' => 13,
    ], $ubah));
}

test('kuota dan harga yang kosong ikut paketnya', function () {
    /*
     | Null, bukan salinan angka paket. Angka yang disalin akan tertinggal
     | diam-diam begitu harga paketnya diubah — dan selisihnya baru ketahuan
     | saat ada yang membayar dengan angka lama.
     */
    $paket = paketUji();
    $k = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-11-27',
        'tanggal_pulang' => '2026-11-29',
    ]);

    expect($k->kuota_dipakai)->toBe(13)
        ->and($k->harga_dipakai)->toBe(1_300_000);

    $paket->update(['price' => 1_400_000]);

    expect($k->fresh()->harga_dipakai)->toBe(1_400_000);
});

test('kuota dan harga yang diisi menimpa paketnya', function () {
    // Akhir pekan lebih mahal, atau kursinya lebih sedikit karena armadanya lain.
    $paket = paketUji();
    $k = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-04', 'tanggal_pulang' => '2026-12-06',
        'kuota' => 10, 'price' => 1_500_000,
    ]);

    expect($k->kuota_dipakai)->toBe(10)
        ->and($k->harga_dipakai)->toBe(1_500_000);
});

test('kursi dihitung per tanggal, bukan digabung sepaket', function () {
    /*
     | Inti fiturnya. Kuota yang dihitung sepaket akan menolak pendaftar pada
     | tanggal yang masih kosong hanya karena tanggal LAIN sudah penuh — dan
     | penolakan itu tidak punya penjelasan yang bisa dibaca siapa pun.
     */
    $paket = paketUji(['kuota' => 13]);

    $nov = Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-11-27', 'tanggal_pulang' => '2026-11-29']);
    $des = Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11', 'tanggal_pulang' => '2026-12-13']);

    PendaftaranOpenTrip::create([
        'travel_package_id' => $paket->id, 'keberangkatan_id' => $nov->id,
        'nama' => 'Budi', 'whatsapp' => '0812', 'jumlah_peserta' => 13,
        'tanggal_berangkat' => '2026-11-27', 'status' => 'baru',
    ]);

    expect($nov->fresh()->sisa_kursi)->toBe(0)
        ->and($nov->fresh()->penuh)->toBeTrue()
        // Desember masih kosong penuh.
        ->and($des->fresh()->sisa_kursi)->toBe(13)
        ->and($des->fresh()->penuh)->toBeFalse();
});

test('tanpa kuota tidak pernah penuh', function () {
    // Perilakunya sengaja sama dengan paket tanpa kuota: menahan pendaftar
    // karena angka yang memang belum ditetapkan adalah penolakan tanpa dasar.
    $paket = paketUji(['kuota' => null]);
    $k = Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-11-27']);

    expect($k->sisa_kursi)->toBeNull()
        ->and($k->penuh)->toBeFalse();
});

test('rentang tanggal tidak menyebut bulan yang sama dua kali', function () {
    $paket = paketUji();

    $sama = Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11', 'tanggal_pulang' => '2026-12-13']);
    $beda = Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-11-27', 'tanggal_pulang' => '2026-12-01']);
    $sehari = Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-20', 'tanggal_pulang' => '2026-12-20']);

    expect($sama->rentang_tanggal)->toBe('11 – 13 Desember 2026')
        ->and($beda->rentang_tanggal)->toBe('27 November – 1 Desember 2026')
        ->and($sehari->rentang_tanggal)->toBe('20 Desember 2026');
});

test('keberangkatan yang ditutup tidak ikut daftar tersedia', function () {
    // Ditutup, bukan dihapus: pendaftaran yang sudah masuk tetap memegangnya.
    $paket = paketUji();

    Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => today()->addDays(10), 'aktif' => true]);
    Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => today()->addDays(20), 'aktif' => false]);
    Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => today()->subDays(5), 'aktif' => true]);

    expect($paket->keberangkatan()->count())->toBe(3)
        // Yang ditutup DAN yang sudah lewat sama-sama tidak ditawarkan.
        ->and($paket->keberangkatanTersedia()->count())->toBe(1);
});

test('menghapus paket ikut menghapus tanggalnya', function () {
    $paket = paketUji();
    Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => '2026-11-27']);

    $paket->delete();

    expect(Keberangkatan::count())->toBe(0);
});

/* ------------------------- LEWAT API ------------------------- */

function kepalaKb(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-kb');

    return ['X-Orcha-Key' => 'kunci-uji-kb', 'Accept' => 'application/json'];
}

test('menambah tanggal hanya meminta tanggalnya', function () {
    /*
     | Inti fiturnya bagi admin: isi paketnya dipakai bersama, jadi yang
     | ditanyakan cuma tanggalnya. Kuota & harga dibiarkan kosong supaya ikut
     | paketnya — yang memang benar hampir selalu.
     */
    $paket = paketUji();

    $this->postJson("/api/v1/paket-wisata/{$paket->id}/keberangkatan", [
        'tanggal_berangkat' => '2026-11-27',
        'tanggal_pulang' => '2026-11-29',
    ], kepalaKb())->assertCreated();

    $k = $paket->keberangkatan()->first();

    expect($k->kuota)->toBeNull()
        ->and($k->price)->toBeNull()
        ->and($k->kuota_dipakai)->toBe(13);
});

test('menyalin menggeser tanggal pulang sejauh yang sama', function () {
    /*
     | Menyalin 27–29 November ke 4 Desember hampir selalu berarti 4–6
     | Desember. Memaksa admin menghitung sendiri lama tripnya tiap kali
     | menyalin adalah pekerjaan yang salahnya tidak terlihat salah sampai
     | ada yang menanyakan tanggal pulangnya.
     */
    $paket = paketUji();
    $asal = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-11-27', 'tanggal_pulang' => '2026-11-29',
        'kuota' => 10, 'price' => 1_500_000, 'catatan' => 'Edisi Blue Fire',
    ]);

    $this->postJson("/api/v1/paket-wisata/{$paket->id}/keberangkatan/{$asal->id}/salin", [
        'tanggal_berangkat' => '2026-12-04',
    ], kepalaKb())->assertCreated();

    // whereDate, bukan where: kolom tanggal ber-cast disimpan berikut jamnya.
    $baru = $paket->keberangkatan()->whereDate('tanggal_berangkat', '2026-12-04')->first();

    expect($baru)->not->toBeNull('Salinannya tidak terbuat.');

    expect($baru->tanggal_pulang->toDateString())->toBe('2026-12-06')
        // Kuota, harga, dan catatannya ikut tersalin.
        ->and($baru->kuota)->toBe(10)
        ->and($baru->price)->toBe(1_500_000)
        ->and($baru->catatan)->toBe('Edisi Blue Fire');
});

test('dua keberangkatan bertanggal sama ditolak', function () {
    // Pendaftar melihat dua baris yang bunyinya persis sama dan memilih
    // dengan menebak, lalu kuotanya terpecah dua tanpa ada yang menyadarinya.
    $paket = paketUji();
    Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => '2026-11-27']);

    $this->postJson("/api/v1/paket-wisata/{$paket->id}/keberangkatan", [
        'tanggal_berangkat' => '2026-11-27',
    ], kepalaKb())->assertStatus(422);

    expect($paket->keberangkatan()->count())->toBe(1);
});

test('keberangkatan yang sudah punya pendaftar tidak bisa dihapus', function () {
    // Pendaftarnya sudah membayar atas tanggal itu. Yang benar: tutup.
    $paket = paketUji();
    $k = Keberangkatan::create(['travel_package_id' => $paket->id, 'tanggal_berangkat' => '2026-11-27']);

    PendaftaranOpenTrip::create([
        'travel_package_id' => $paket->id, 'keberangkatan_id' => $k->id,
        'nama' => 'Budi', 'whatsapp' => '0812', 'jumlah_peserta' => 2,
        'tanggal_berangkat' => '2026-11-27', 'status' => 'baru',
    ]);

    $this->deleteJson("/api/v1/paket-wisata/{$paket->id}/keberangkatan/{$k->id}", [], kepalaKb())
        ->assertStatus(422);

    expect(Keberangkatan::find($k->id))->not->toBeNull();
});

test('keberangkatan milik paket lain ditolak', function () {
    // Tanpa pemeriksaan ini, kuota berpindah ke rombongan yang salah.
    $a = paketUji();
    $b = paketUji(['name' => 'Paket Lain']);
    $milikB = Keberangkatan::create(['travel_package_id' => $b->id, 'tanggal_berangkat' => '2026-11-27']);

    $this->deleteJson("/api/v1/paket-wisata/{$a->id}/keberangkatan/{$milikB->id}", [], kepalaKb())
        ->assertStatus(404);
});

test('balasan paket membawa daftar tanggalnya', function () {
    $paket = paketUji();
    Keberangkatan::create(['travel_package_id' => $paket->id,
        'tanggal_berangkat' => '2026-12-11', 'tanggal_pulang' => '2026-12-13']);

    $data = $this->getJson("/api/v1/paket-wisata/{$paket->id}", kepalaKb())
        ->assertOk()->json('data.keberangkatan');

    expect($data)->toHaveCount(1)
        ->and($data[0]['rentang_tanggal'])->toBe('11 – 13 Desember 2026')
        // Angka MENTAHNYA ikut, supaya borang tahu mana yang memang diisi.
        ->and($data[0]['kuota'])->toBeNull()
        ->and($data[0]['kuota_dipakai'])->toBe(13);
});
