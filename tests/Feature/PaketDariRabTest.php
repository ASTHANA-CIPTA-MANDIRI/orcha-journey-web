<?php

use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabItinerary;

/**
 * Open trip dirakit sekali di RAB, bukan diketik dua kali.
 *
 * Harga jual, modal per orang, itinerary berjam, daftar destinasi, dan daftar
 * "sudah termasuk" semuanya sudah hidup di RAB. Untuk bisa didaftari publik,
 * semua itu dulu harus diketik ulang ke borang paket wisata.
 *
 * Yang mahal bukan waktu mengetiknya, melainkan bahwa sesudah itu ada DUA
 * sumber untuk angka yang sama: RAB disunting, paketnya tidak, dan yang
 * dibaca publik diam-diam jadi harga yang sudah tidak berlaku.
 */
function kepalaPaket(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-paket');

    return ['X-Orcha-Key' => 'kunci-uji-paket', 'Accept' => 'application/json'];
}

function rabOpenTrip(array $ubah = []): Rab
{
    $rab = Rab::create(array_merge([
        'judul' => 'Open Trip Banyuwangi 3H2M', 'nama_pelanggan' => 'Umum',
        'provinsi' => 'Jawa Timur', 'jenis' => 'open', 'harga_dasar' => 'minimum',
        'tanggal_mulai' => '2026-12-12', 'jumlah_hari' => 3, 'jumlah_malam' => 2,
        'jumlah_peserta' => 20, 'peserta_minimum' => 15,
        'margin_jenis' => 'persen', 'margin_nilai' => 20,
    ], $ubah));

    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'transportasi', 'nama' => 'Hiace',
        'satuan' => 'unit_hari', 'harga_satuan' => 1_400_000, 'kapasitas' => 14, 'jumlah' => 1]);
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'akomodasi', 'nama' => 'Homestay',
        'satuan' => 'kamar_malam', 'harga_satuan' => 300_000, 'jumlah' => 1]);

    foreach ([
        [1, '08:00', 'Penjemputan peserta', ''],
        [1, '10:00', 'Taman Nasional Baluran', 'Taman Nasional Baluran'],
        [2, '09:00', 'Pantai Pulau Merah', 'Pantai Pulau Merah'],
        [3, '08:00', 'Belanja oleh-oleh', ''],
    ] as $i => [$h, $jam, $nama, $dest]) {
        RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => $h, 'jam' => $jam,
            'nama' => $nama, 'destinasi' => $dest, 'urutan' => $i]);
    }

    return $rab->fresh();
}

test('paket dirakit dari RAB, lengkap dengan harga dan itinerarynya', function () {
    $rab = rabOpenTrip();

    $data = $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())
        ->assertCreated()->json('data');

    $paket = TravelPackage::find($data['id']);
    $r = App\Support\Rab\HitungRab::ringkas($rab->fresh());

    expect($paket->name)->toBe('Open Trip Banyuwangi 3H2M')
        ->and($paket->category)->toBe('open_trip')
        ->and($paket->duration)->toBe('3 Hari 2 Malam')
        ->and($paket->tanggal_berangkat->toDateString())->toBe('2026-12-12')
        ->and($paket->tanggal_pulang->toDateString())->toBe('2026-12-14')
        ->and($paket->minimal_peserta)->toBe(15)
        // Satu sumber angka: harga paket = harga RAB, tanpa diketik ulang.
        ->and((int) $paket->price)->toBe($r['harga_per_orang'])
        ->and($paket->harga_modal)->toBe($r['untuk_pendaftaran']['harga_modal'])
        ->and($paket->destination_list)->toBe(['Taman Nasional Baluran', 'Pantai Pulau Merah'])
        ->and($paket->fasilitas)->toContain('Transportasi')
        // Tiga hari, termasuk hari yang kegiatannya cuma satu.
        ->and($paket->itinerary)->toHaveCount(3)
        ->and($paket->itinerary[0]['agenda'][0]['jam'])->toBe('08.00')
        ->and($paket->itinerary[0]['agenda'][0]['kegiatan'])->toBe('Penjemputan peserta');
});

test('paket lahir sebagai draf, tidak langsung tayang ke publik', function () {
    // Foto, kuota, dan titik jemput tidak ada di RAB. Paket yang tayang tanpa
    // ketiganya terlihat rusak di mata calon pelanggan — dan sekali terlihat
    // tidak bisa ditarik.
    $rab = rabOpenTrip();

    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())->assertCreated();

    $paket = TravelPackage::sole();

    expect($paket->status)->toBe('draf')
        ->and($paket->sedang_tayang)->toBeFalse()
        ->and(TravelPackage::tayang()->count())->toBe(0);
});

test('private trip tidak bisa dijadikan paket publik', function () {
    $rab = rabOpenTrip(['jenis' => 'private']);

    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())
        ->assertStatus(422);

    expect(TravelPackage::count())->toBe(0);
});

test('RAB tanpa jadwal ditahan, karena halaman paket memang menampilkan jadwal', function () {
    $rab = rabOpenTrip();
    RabItinerary::where('rab_id', $rab->id)->delete();

    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())
        ->assertStatus(422);
});

test('satu RAB hanya punya satu paket', function () {
    $rab = rabOpenTrip();

    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())->assertCreated();
    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())->assertStatus(422);

    expect(TravelPackage::count())->toBe(1);
});

test('paket ikut berubah saat biaya RAB disunting', function () {
    $rab = rabOpenTrip();
    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())->assertCreated();

    $sebelum = (int) TravelPackage::sole()->price;

    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'tiket', 'nama' => 'Tiket Baluran',
        'satuan' => 'orang', 'harga_satuan' => 50_000, 'jumlah' => 1]);

    expect((int) TravelPackage::sole()->price)->toBeGreaterThan($sebelum);
});

test('paket ikut berubah saat jadwal atau kepala RAB disunting', function () {
    $rab = rabOpenTrip();
    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())->assertCreated();

    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 3, 'jam' => '12:00',
        'nama' => 'Perjalanan pulang', 'destinasi' => '', 'urutan' => 99]);
    $rab->fresh()->update(['judul' => 'Open Trip Banyuwangi EDISI BARU']);

    $paket = TravelPackage::sole();

    expect($paket->name)->toBe('Open Trip Banyuwangi EDISI BARU')
        ->and(collect($paket->itinerary[2]['agenda'])->pluck('kegiatan'))->toContain('Perjalanan pulang');
});

test('kolom milik borang paket tidak pernah ditimpa penyelarasan', function () {
    // Foto, kuota, titik jemput, dan status tayang itu pekerjaan admin di
    // borang paket. Menimpanya saat RAB disunting akan menghapusnya tanpa
    // ada yang meminta.
    $rab = rabOpenTrip();
    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())->assertCreated();

    TravelPackage::sole()->update([
        'status' => 'terbit', 'kuota' => 20, 'titik_jemput' => 'Stasiun Ketapang',
        'foto' => 'paket/contoh.webp',
    ]);

    $rab->fresh()->update(['judul' => 'Judul diganti']);

    $paket = TravelPackage::sole();

    expect($paket->status)->toBe('terbit')
        ->and($paket->kuota)->toBe(20)
        ->and($paket->titik_jemput)->toBe('Stasiun Ketapang')
        ->and($paket->foto)->toBe('paket/contoh.webp')
        ->and($paket->name)->toBe('Judul diganti');
});

test('paket yang dihapus admin tidak membuat RAB galat', function () {
    $rab = rabOpenTrip();
    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())->assertCreated();

    TravelPackage::sole()->delete();

    $rab->fresh()->update(['judul' => 'Masih bisa disunting']);

    expect(Rab::sole()->travel_package_id)->toBeNull()
        ->and(Rab::sole()->judul)->toBe('Masih bisa disunting');
});

test('jawaban RAB menyebut paketnya berikut status tayangnya', function () {
    $rab = rabOpenTrip();
    $this->postJson("/api/v1/rab/{$rab->id}/jadikan-paket", [], kepalaPaket())->assertCreated();

    $data = $this->getJson("/api/v1/rab/{$rab->id}", kepalaPaket())->assertOk()->json('data');

    expect($data['paket']['nama'])->toBe('Open Trip Banyuwangi 3H2M')
        ->and($data['paket']['status'])->toBe('draf')
        // Yang menentukan orang sudah bisa mendaftar atau belum.
        ->and($data['paket']['tayang'])->toBeFalse();
});
