<?php

use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabItinerary;
use App\Support\Rab\HitungRab;

/**
 * Satu tiket untuk beberapa destinasi.
 *
 * Tiket terusan Dieng berlaku untuk Candi Arjuna DAN Kawah Sikidang, dibayar
 * sekali. Sebelum ada `destinasi_lain`, tidak ada cara memasukkannya dengan
 * benar — dan dua-duanya salah ke arah yang berlawanan:
 *
 * - DUA baris master, satu per destinasi: keduanya tertarik, tiketnya ditagih
 *   dua kali. Rp 30.000 jadi Rp 60.000 per orang.
 * - SATU baris dengan destinasi Candi Arjuna: Kawah Sikidang dilaporkan belum
 *   ada tiketnya, pil harinya cuma menyebut hari Candi Arjuna, dan begitu
 *   Candi Arjuna digeser keluar jadwal tiketnya IKUT TERHAPUS — padahal Kawah
 *   Sikidang masih dikunjungi dan masih membayarnya.
 */
function kepalaTerusan(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-terusan');

    return ['X-Orcha-Key' => 'kunci-uji-terusan', 'Accept' => 'application/json'];
}

function tiketTerusanDieng(): MasterHarga
{
    return MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Terusan Dieng', 'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Candi Arjuna', 'destinasi_lain' => ['Kawah Sikidang'],
        'satuan' => 'orang', 'harga' => 30_000, 'aktif' => true,
    ]);
}

function rabDieng(): Rab
{
    $rab = Rab::create([
        'judul' => 'Trip Dieng', 'nama_pelanggan' => 'Rombongan Uji', 'provinsi' => 'Jawa Tengah',
        'jumlah_hari' => 2, 'jumlah_malam' => 1, 'jumlah_peserta' => 10,
        'margin_jenis' => 'persen', 'margin_nilai' => 20,
    ]);

    // Sesuai rencana pelanggan: dua tempat ini dikunjungi di HARI BERBEDA.
    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 1, 'jam' => '09:00',
        'nama' => 'Candi Arjuna', 'destinasi' => 'Candi Arjuna', 'urutan' => 1]);
    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 2, 'jam' => '08:00',
        'nama' => 'Kawah Sikidang', 'destinasi' => 'Kawah Sikidang', 'urutan' => 2]);

    return $rab->fresh();
}

test('tiket terusan masuk SEKALI walau kedua destinasinya dikunjungi', function () {
    tiketTerusanDieng();
    $rab = rabDieng();

    $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk();

    $baris = RabBiaya::where('rab_id', $rab->id)->get();

    expect($baris)->toHaveCount(1)
        ->and((int) $baris->first()->harga_satuan)->toBe(30_000);
});

test('destinasi yang tercakup tidak lagi dilaporkan belum ada tiketnya', function () {
    tiketTerusanDieng();
    $rab = rabDieng();

    $hasil = $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk()->json();

    expect($hasil['tanpa_tiket'])->toBe([]);
});

test('pil harinya menyebut semua hari destinasinya, bukan hari yang utama saja', function () {
    tiketTerusanDieng();
    $rab = rabDieng();
    $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk();

    $baris = collect(HitungRab::ringkas($rab->fresh())['baris'])->first();

    expect($baris['hari'])->toBe([1, 2]);
});

test('tiket terusan TIDAK terhapus saat satu destinasinya digeser keluar jadwal', function () {
    // Kerugian yang paling mahal: barisnya hilang tanpa pemberitahuan, RAB-nya
    // tetap terlihat wajar, dan modalnya berkurang diam-diam.
    tiketTerusanDieng();
    $rab = rabDieng();
    $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk();

    RabItinerary::where('rab_id', $rab->id)->where('hari_ke', 1)->delete();
    $hasil = $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk()->json();

    expect(RabBiaya::where('rab_id', $rab->id)->count())->toBe(1)
        ->and($hasil['tiket_dibuang'])->toBe([]);
});

test('baru dibuang setelah SELURUH destinasinya tidak lagi dikunjungi', function () {
    tiketTerusanDieng();
    $rab = rabDieng();
    $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk();

    RabItinerary::where('rab_id', $rab->id)->delete();
    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 1, 'jam' => '09:00',
        'nama' => 'Telaga Warna', 'destinasi' => 'Telaga Warna', 'urutan' => 1]);

    $hasil = $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk()->json();

    expect(RabBiaya::where('rab_id', $rab->id)->count())->toBe(0)
        ->and($hasil['tiket_dibuang'])->toBe(['Tiket Terusan Dieng']);
});

test('katalog menandai kedua destinasinya sudah punya tiket', function () {
    tiketTerusanDieng();

    $data = $this->getJson('/api/v1/rab/katalog?provinsi=Jawa%20Tengah', kepalaTerusan())
        ->assertOk()->json('data');

    $master = collect($data['master'])->firstWhere('nama', 'Tiket Terusan Dieng');

    expect($master['destinasi_lain'])->toBe(['Kawah Sikidang']);
});

test('menambah destinasi yang sudah punya tiket sendiri ditahan', function () {
    // Kalau lolos, keduanya tertarik ke RAB yang sama dan modalnya terhitung
    // dua kali — persis kerugian yang hendak dicegah pemeriksa kembar.
    MasterHarga::create(['kategori' => 'tiket', 'nama' => 'Tiket Kawah Sikidang',
        'provinsi' => 'Jawa Tengah', 'destinasi' => 'Kawah Sikidang',
        'satuan' => 'orang', 'harga' => 15_000, 'aktif' => true]);

    $this->postJson('/api/v1/master-harga', [
        'kategori' => 'tiket', 'nama' => 'Tiket Terusan Dieng', 'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Candi Arjuna', 'destinasi_lain' => ['Kawah Sikidang'],
        'satuan' => 'orang', 'harga' => 30_000,
    ], kepalaTerusan())->assertStatus(422);
});

test('tetap bisa disimpan paksa bila memang dua harga yang berbeda', function () {
    MasterHarga::create(['kategori' => 'tiket', 'nama' => 'Tiket Kawah Sikidang',
        'provinsi' => 'Jawa Tengah', 'destinasi' => 'Kawah Sikidang',
        'satuan' => 'orang', 'harga' => 15_000, 'aktif' => true]);

    $this->postJson('/api/v1/master-harga?paksa=1', [
        'kategori' => 'tiket', 'nama' => 'Tiket Terusan Dieng', 'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Candi Arjuna', 'destinasi_lain' => ['Kawah Sikidang'],
        'satuan' => 'orang', 'harga' => 30_000,
    ], kepalaTerusan())->assertCreated();
});

test('harga tanpa destinasi lain tetap berperilaku persis seperti dulu', function () {
    MasterHarga::create(['kategori' => 'tiket', 'nama' => 'Tiket Telaga Warna',
        'provinsi' => 'Jawa Tengah', 'destinasi' => 'Telaga Warna',
        'satuan' => 'orang', 'harga' => 20_000, 'aktif' => true]);

    $rab = Rab::create(['judul' => 'Trip Dieng', 'nama_pelanggan' => 'Uji', 'provinsi' => 'Jawa Tengah',
        'jumlah_hari' => 1, 'jumlah_peserta' => 10, 'margin_jenis' => 'persen', 'margin_nilai' => 20]);
    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 1, 'jam' => '09:00',
        'nama' => 'Telaga Warna', 'destinasi' => 'Telaga Warna', 'urutan' => 1]);

    $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk();
    expect(RabBiaya::where('rab_id', $rab->id)->count())->toBe(1);

    RabItinerary::where('rab_id', $rab->id)->delete();
    $hasil = $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaTerusan())->assertOk()->json();

    expect(RabBiaya::where('rab_id', $rab->id)->count())->toBe(0)
        ->and($hasil['tiket_dibuang'])->toBe(['Tiket Telaga Warna']);
});
