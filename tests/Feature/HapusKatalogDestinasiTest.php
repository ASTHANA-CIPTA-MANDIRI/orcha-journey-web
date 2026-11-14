<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\KatalogDestinasi;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabItinerary;

/**
 * Menghapus nama dari katalog destinasi.
 *
 * Tiga hal yang kalau salah TIDAK menimbulkan galat apa pun:
 *
 * - Baris harga & itinerary menyimpan NAMA, bukan id katalog. Menghapus
 *   entri katalog tidak menyentuh mereka — dan justru itu bahayanya:
 *   barisnya hidup terus sambil namanya hilang dari kotak pilih.
 * - Katalog adalah tumpukan TIGA sumber. Nama yang juga bawaan atau sudah
 *   tercatat tetap muncul sesudah baris tambahannya dihapus.
 * - Provinsi & daerahnya dulu diisikan pencari lokasi, jadi hapus permanen
 *   berarti tidak ada yang bisa mengetiknya ulang dengan benar.
 */
function kepalaHapusKatalog(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-katalog');

    return ['X-Orcha-Key' => 'kunci-uji-katalog', 'Accept' => 'application/json'];
}

test('destinasi yang tidak dipakai siapa pun bisa dihapus', function () {
    $entri = KatalogDestinasi::create([
        'nama' => 'Pantai Uji Sepi', 'provinsi' => 'DI Yogyakarta', 'daerah' => 'Gunung Kidul',
    ]);

    $this->deleteJson("/api/v1/katalog-destinasi/{$entri->id}", [], kepalaHapusKatalog())
        ->assertOk();

    // Hilang dari daftar, tetapi barisnya MASIH ADA — itulah yang membuatnya
    // bisa dikembalikan.
    expect(KatalogDestinasi::find($entri->id))->toBeNull()
        ->and(KatalogDestinasi::withTrashed()->find($entri->id))->not->toBeNull()
        ->and(array_key_exists('Pantai Uji Sepi', KatalogDestinasi::gabungan()))->toBeFalse();
});

test('destinasi yang dipakai baris harga DITOLAK dihapus', function () {
    $entri = KatalogDestinasi::create(['nama' => 'Pantai Uji Bertiket', 'provinsi' => 'DI Yogyakarta']);

    MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Pantai Uji Bertiket',
        'provinsi' => 'DI Yogyakarta', 'destinasi' => 'Pantai Uji Bertiket',
        'satuan' => 'orang', 'harga' => 10_000, 'aktif' => true,
    ]);

    $this->deleteJson("/api/v1/katalog-destinasi/{$entri->id}", [], kepalaHapusKatalog())
        ->assertStatus(422)
        ->assertJsonPath('dipakai.harga', 1);

    expect(KatalogDestinasi::find($entri->id))->not->toBeNull();
});

test('destinasi yang cuma ikut tiket terusan juga DITOLAK dihapus', function () {
    // destinasi_lain tersimpan sebagai JSON, bukan kolom destinasi — penjaga
    // yang cuma melihat kolom utamanya akan melewatkannya.
    $entri = KatalogDestinasi::create(['nama' => 'Kawah Uji Sikidang', 'provinsi' => 'Jawa Tengah']);

    MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Terusan Uji', 'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Candi Uji Arjuna', 'destinasi_lain' => ['Kawah Uji Sikidang'],
        'satuan' => 'orang', 'harga' => 30_000, 'aktif' => true,
    ]);

    $this->deleteJson("/api/v1/katalog-destinasi/{$entri->id}", [], kepalaHapusKatalog())
        ->assertStatus(422)
        ->assertJsonPath('dipakai.harga', 1);
});

test('destinasi yang dipakai itinerary RAB DITOLAK dihapus', function () {
    $entri = KatalogDestinasi::create(['nama' => 'Pantai Uji Dijadwalkan', 'provinsi' => 'DI Yogyakarta']);

    $rab = Rab::create([
        'judul' => 'Trip Uji', 'nama_pelanggan' => 'Uji', 'provinsi' => 'DI Yogyakarta',
        'jumlah_hari' => 1, 'jumlah_peserta' => 5, 'margin_jenis' => 'persen', 'margin_nilai' => 20,
    ]);
    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 1, 'jam' => '09:00',
        'nama' => 'Pantai Uji Dijadwalkan', 'destinasi' => 'Pantai Uji Dijadwalkan', 'urutan' => 1]);

    $this->deleteJson("/api/v1/katalog-destinasi/{$entri->id}", [], kepalaHapusKatalog())
        ->assertStatus(422)
        ->assertJsonPath('dipakai.itinerary', 1);

    // Yang dijaga bukan cuma penolakannya: itinerarynya harus tetap utuh.
    expect(RabItinerary::where('rab_id', $rab->id)->count())->toBe(1);
});

test('menambahkan nama yang sama mengembalikan baris yang dihapus', function () {
    $entri = KatalogDestinasi::create([
        'nama' => 'Pantai Uji Pulang', 'provinsi' => 'DI Yogyakarta', 'daerah' => 'Gunung Kidul',
    ]);
    $entri->delete();

    $this->postJson('/api/v1/katalog-destinasi', ['nama' => '  Pantai   Uji Pulang '], kepalaHapusKatalog())
        ->assertOk();

    $pulih = KatalogDestinasi::where('nama', 'Pantai Uji Pulang')->first();

    // Baris YANG SAMA, bukan kembaran baru — dan provinsi & daerah lamanya
    // ikut pulang tanpa ditanyakan ulang.
    expect($pulih?->id)->toBe($entri->id)
        ->and($pulih->provinsi)->toBe('DI Yogyakarta')
        ->and($pulih->daerah)->toBe('Gunung Kidul')
        ->and(KatalogDestinasi::withTrashed()->where('nama', 'Pantai Uji Pulang')->count())->toBe(1);
});

test('katalog RAB menandai mana yang boleh dihapus', function () {
    KatalogDestinasi::create(['nama' => 'Pantai Uji Kustom', 'provinsi' => 'DI Yogyakarta']);

    // Nama yang JUGA tercatat sebagai destinasi tetap muncul sesudah baris
    // tambahannya dihapus, jadi tombol hapusnya tidak boleh ditawarkan.
    KatalogDestinasi::create(['nama' => 'Pantai Uji Ganda', 'provinsi' => 'DI Yogyakarta']);
    DestinationPopuler::create([
        'destination_name' => 'Pantai Uji Ganda', 'slug' => 'pantai-uji-ganda',
        'provinsi' => 'DI Yogyakarta',
    ]);

    $data = $this->getJson('/api/v1/rab/katalog?provinsi=DI%20Yogyakarta', kepalaHapusKatalog())
        ->assertOk()->json('data.destinasi');

    $baris = collect($data)->keyBy('nama');

    expect($baris['Pantai Uji Kustom']['katalog_id'])->not->toBeNull()
        ->and($baris['Pantai Uji Ganda']['katalog_id'])->toBeNull();
});

/* ---------------------- MENGGANTI NAMA ---------------------- */

test('ganti nama katalog ikut merambat ke harga, destinasi tambahan, dan itinerary', function () {
    $entri = KatalogDestinasi::create([
        'nama' => 'Candi Uji Lama', 'provinsi' => 'Jawa Tengah', 'daerah' => 'Magelang',
    ]);

    // Dipakai sebagai destinasi UTAMA...
    $utama = MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Candi Uji Lama', 'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Candi Uji Lama', 'satuan' => 'orang', 'harga' => 20_000, 'aktif' => true,
    ]);

    // ...dan sebagai destinasi TAMBAHAN di baris lain (kolom JSON).
    $terusan = MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Terusan Uji', 'provinsi' => 'Jawa Tengah',
        'destinasi' => 'Gerbang Uji', 'destinasi_lain' => ['Candi Uji Lama', 'Tempat Lain'],
        'satuan' => 'orang', 'harga' => 30_000, 'aktif' => true,
    ]);

    $rab = Rab::create([
        'judul' => 'Trip Uji', 'nama_pelanggan' => 'Uji', 'provinsi' => 'Jawa Tengah',
        'jumlah_hari' => 1, 'jumlah_peserta' => 5, 'margin_jenis' => 'persen', 'margin_nilai' => 20,
    ]);
    $baris = RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 1, 'jam' => '09:00',
        'nama' => 'Candi Uji Lama', 'destinasi' => 'Candi Uji Lama', 'urutan' => 1]);

    $this->patchJson("/api/v1/katalog-destinasi/{$entri->id}", ['nama' => 'Candi Uji Baru'], kepalaHapusKatalog())
        ->assertOk()
        ->assertJsonPath('dari', 'Candi Uji Lama')
        ->assertJsonPath('ke', 'Candi Uji Baru');

    /*
     | Inti ujinya. Tanpa perambatan, ketiga baris di bawah tetap memegang
     | nama lama yang sudah hilang dari kotak pilih — jodoh harga ke
     | itinerary putus TANPA satu pun galat.
     */
    expect($entri->fresh()->nama)->toBe('Candi Uji Baru')
        ->and($utama->fresh()->destinasi)->toBe('Candi Uji Baru')
        ->and($terusan->fresh()->destinasi_lain)->toBe(['Candi Uji Baru', 'Tempat Lain'])
        ->and($baris->fresh()->destinasi)->toBe('Candi Uji Baru');
});

test('ganti nama tidak menyentuh destinasi lain yang namanya mirip', function () {
    $entri = KatalogDestinasi::create(['nama' => 'Pantai Uji', 'provinsi' => 'DI Yogyakarta']);

    $lain = MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Pantai Uji Selatan', 'provinsi' => 'DI Yogyakarta',
        'destinasi' => 'Pantai Uji Selatan', 'satuan' => 'orang', 'harga' => 15_000, 'aktif' => true,
    ]);

    $this->patchJson("/api/v1/katalog-destinasi/{$entri->id}", ['nama' => 'Pantai Uji Utara'], kepalaHapusKatalog())
        ->assertOk();

    // "Pantai Uji Selatan" BUKAN "Pantai Uji": pencocokannya utuh, bukan awalan.
    expect($lain->fresh()->destinasi)->toBe('Pantai Uji Selatan');
});

test('ganti nama ditolak bila bentrok dengan nama yang sudah ada', function () {
    KatalogDestinasi::create(['nama' => 'Pantai Uji Satu', 'provinsi' => 'DI Yogyakarta']);
    $kedua = KatalogDestinasi::create(['nama' => 'Pantai Uji Dua', 'provinsi' => 'DI Yogyakarta']);

    $this->patchJson("/api/v1/katalog-destinasi/{$kedua->id}", ['nama' => 'Pantai Uji Satu'], kepalaHapusKatalog())
        ->assertStatus(422);

    // Dua nama kembar di satu kotak pilih membuat admin memilih dengan menebak.
    expect($kedua->fresh()->nama)->toBe('Pantai Uji Dua');
});
