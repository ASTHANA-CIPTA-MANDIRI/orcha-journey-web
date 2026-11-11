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
