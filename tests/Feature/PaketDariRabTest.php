<?php

use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabItinerary;
use App\Models\Rab\Vendor;
use App\Support\Rab\BerkasRab;
use App\Support\Rab\HitungRab;
use App\Support\Rab\PaketDariRab;

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
    $r = HitungRab::ringkas($rab->fresh());

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

/*
|--------------------------------------------------------------------------
| Open trip tidak punya pelanggan
|--------------------------------------------------------------------------
|
| Harganya diumumkan DULU, pesertanya menyusul — itu definisinya. Mewajibkan
| nama pelanggan di sana memaksa admin mengarang isian supaya tombol simpan
| mau jalan, dan isian karangan lebih buruk daripada kolom kosong: ia terlihat
| seperti data, lalu tercetak di penawaran yang dibaca orang.
*/
test('open trip bisa dibuat tanpa nama pelanggan', function () {
    $data = $this->postJson('/api/v1/rab', [
        'judul' => 'Open Trip Banyuwangi 3H2M', 'provinsi' => 'Jawa Timur',
        'jenis' => 'open', 'jumlah_hari' => 3, 'jumlah_peserta' => 20,
    ], kepalaPaket())->assertCreated()->json('data');

    // Kolomnya tetap terisi supaya daftar RAB tidak menampilkan baris tanpa
    // nama — tetapi yang mengisi sistem, bukan admin yang dipaksa mengarang.
    expect($data['nama_pelanggan'])->toBe('Umum');
});

test('private trip tetap mewajibkan nama pelanggannya', function () {
    $this->postJson('/api/v1/rab', [
        'judul' => 'Private Trip Banyuwangi', 'provinsi' => 'Jawa Timur',
        'jenis' => 'private', 'jumlah_hari' => 3, 'jumlah_peserta' => 5,
    ], kepalaPaket())->assertUnprocessable()->assertJsonValidationErrors('nama_pelanggan');
});

test('penawaran open trip tidak menyapa pembacanya dengan kata "Umum"', function () {
    $rab = rabOpenTrip(['nama_pelanggan' => 'Umum']);

    $html = view(...BerkasRab::bahan($rab, 'penawaran'))->render();

    expect($html)->toContain('Open Trip')
        ->and($html)->toContain('Terbuka untuk umum')
        ->and($html)->not->toContain('Disiapkan khusus untuk');
});

test('penawaran open trip tidak lagi menulis "Private Trip" di barisnya yang paling atas', function () {
    // Salah sebelum satu angka pun dibaca.
    $html = view(...BerkasRab::bahan(rabOpenTrip(), 'penawaran'))->render();

    expect($html)->not->toContain('>Private Trip<');
});

test('penawaran private trip tetap menyebut nama pelanggannya', function () {
    $rab = rabOpenTrip(['jenis' => 'private', 'nama_pelanggan' => 'Keluarga Bpk. Hendra']);

    $html = view(...BerkasRab::bahan($rab, 'penawaran'))->render();

    expect($html)->toContain('Disiapkan khusus untuk')
        ->and($html)->toContain('Keluarga Bpk. Hendra')
        ->and($html)->toContain('>Private Trip<');
});

/* ---------------- PRIVASI: REKANAN TIDAK TAMPIL DI PUBLIK ---------------- */

test('nama rumah makan & penginapan tidak ikut terbit ke paket publik', function () {
    /*
     | Rumah makan dan penginapan yang dipakai trip adalah hasil negosiasi —
     | harga, komisi, dan kesediaannya dibangun bertahun-tahun. Memajangnya di
     | halaman paket sama dengan menyerahkan daftar rekanan kepada siapa pun
     | yang membuka situs, termasuk pesaing yang tinggal menelepon satu per
     | satu.
     |
     | Yang BOLEH tampil cuma destinasinya: Kawah Ijen, Baluran, De Djawatan.
     */
    $rab = rabOpenTrip();

    $vendor = Vendor::create(['nama' => 'Naila Homestay', 'kontak' => '0811', 'aktif' => true]);
    $master = MasterHarga::create([
        'kategori' => 'akomodasi', 'nama' => 'Naila Homestay Banyuwangi', 'provinsi' => 'Jawa Timur',
        'satuan' => 'kamar_malam', 'harga' => 280_000, 'vendor_id' => $vendor->id, 'aktif' => true,
    ]);
    RabBiaya::create(['rab_id' => $rab->id, 'master_harga_id' => $master->id,
        'kategori' => 'akomodasi', 'nama' => 'Naila Homestay Banyuwangi',
        'satuan' => 'kamar_malam', 'harga_satuan' => 100_000, 'jumlah' => 1]);

    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 2, 'jam' => '20:35', 'urutan' => 90,
        'nama' => 'Naila Homestay Banyuwangi', 'keterangan' => 'check in & istirahat', 'destinasi' => '']);
    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 2, 'jam' => '17:30', 'urutan' => 80,
        'nama' => 'Yogi Cafe & resto Pantai Pulau Merah', 'keterangan' => 'makan malam & ishoma', 'destinasi' => '']);
    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 2, 'jam' => '05:00', 'urutan' => 10,
        'nama' => 'Warung Asri Banyuwangi', 'keterangan' => 'Sarapan & Bersih-Bersih', 'destinasi' => '']);

    $paket = PaketDariRab::buat($rab->fresh());
    $semua = json_encode($paket->itinerary, JSON_UNESCAPED_UNICODE);

    expect($semua)->not->toContain('Naila Homestay')
        ->and($semua)->not->toContain('Yogi Cafe')
        ->and($semua)->not->toContain('Warung Asri')
        // Diganti label umum yang dibaca dari isyarat di keterangannya.
        ->and($semua)->toContain('Check-in penginapan')
        ->and($semua)->toContain('Makan malam')
        ->and($semua)->toContain('Sarapan')
        // Destinasinya TETAP tampil — itu memang yang dijual.
        ->and($semua)->toContain('Taman Nasional Baluran')
        ->and($semua)->toContain('Pantai Pulau Merah');
});

test('kegiatan biasa tetap memakai namanya sendiri', function () {
    // Penyaringnya tidak boleh rakus: "Penjemputan peserta" dan "Belanja
    // oleh-oleh" tidak menyebut rekanan siapa pun, dan menggantinya dengan
    // "Kegiatan" justru membuat jadwalnya tidak bisa dibaca.
    $paket = PaketDariRab::buat(rabOpenTrip());
    $semua = json_encode($paket->itinerary, JSON_UNESCAPED_UNICODE);

    expect($semua)->toContain('Penjemputan peserta')
        ->and($semua)->toContain('Belanja oleh-oleh');
});

test('nama rekanan di kegiatan perjalanan ikut disaring', function () {
    /*
     | Jaring kedua. "Menuju Naila Homestay" lolos penggolong jenis — ia
     | memang perjalanan, bukan istirahat — tetapi tetap menyebut nama
     | penginapannya. Tanpa jaring ini kebocorannya lewat pintu samping.
     */
    $rab = rabOpenTrip();

    $vendor = Vendor::create(['nama' => 'Naila Homestay', 'kontak' => '0811', 'aktif' => true]);
    $master = MasterHarga::create([
        'kategori' => 'akomodasi', 'nama' => 'Naila Homestay Banyuwangi', 'provinsi' => 'Jawa Timur',
        'satuan' => 'kamar_malam', 'harga' => 280_000, 'vendor_id' => $vendor->id, 'aktif' => true,
    ]);
    RabBiaya::create(['rab_id' => $rab->id, 'master_harga_id' => $master->id,
        'kategori' => 'akomodasi', 'nama' => 'Naila Homestay Banyuwangi',
        'satuan' => 'kamar_malam', 'harga_satuan' => 100_000, 'jumlah' => 1]);

    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 2, 'jam' => '18:30', 'urutan' => 85,
        'nama' => 'Menuju Naila Homestay', 'keterangan' => 'perjalanan menuju penginapan', 'destinasi' => '']);

    $paket = PaketDariRab::buat($rab->fresh());

    expect(json_encode($paket->itinerary, JSON_UNESCAPED_UNICODE))->not->toContain('Naila Homestay');
});

test('jumlah kegiatan paket sama persis dengan jadwal RAB', function () {
    // "Itinerarynya jadi beda" harus bisa dijawab dengan angka: tidak ada
    // baris yang hilang dalam pemindahan, hanya namanya yang disamarkan.
    $rab = rabOpenTrip();

    $paket = PaketDariRab::buat($rab);

    foreach ($paket->itinerary as $i => $hari) {
        expect(count($hari['agenda']))
            ->toBe($rab->itinerary->where('hari_ke', $i + 1)->count(), 'Hari '.($i + 1).' berbeda jumlahnya.');
    }
});

/* ---------------- JAM SELESAI IKUT TERBAWA ---------------- */

test('jam selesai ikut ke paket, bukan cuma jam mulainya', function () {
    /*
     | Sebelumnya hanya jam mulai yang ikut, sehingga
     | "00:00 – 01:20 Menuju destinasi" terbaca di halaman publik sebagai
     | "00.00 Menuju destinasi" — berapa lamanya hilang, padahal itulah yang
     | paling sering ditanyakan calon peserta.
     */
    $rab = rabOpenTrip();

    RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => 1, 'jam' => '13:10',
        'jam_selesai' => '23:30', 'nama' => 'Menuju destinasi', 'destinasi' => '', 'urutan' => 99]);

    $paket = PaketDariRab::buat($rab->fresh());
    $jam = collect($paket->itinerary[0]['agenda'])->pluck('jam');

    expect($jam)->toContain('13.10 - 23.30')
        // Yang tanpa jam selesai tetap satu angka, bukan "08.00 - ".
        ->and($jam)->toContain('08.00');
});

test('rentang jam terbaca lagi saat paket dipakai jadi sumber itinerary', function () {
    /*
     | Arah sebaliknya. Regex lama hanya menerima satu jam utuh, jadi rentang
     | jatuh ke null TANPA galat — seluruh jadwal kehilangan jamnya begitu
     | paket dipakai sebagai sumber itinerary RAB baru.
     */
    $paket = PaketDariRab::buat(rabOpenTrip());

    $paket->update(['itinerary' => [[
        'agenda' => [
            ['jam' => '13.10 - 23.30', 'kegiatan' => 'Menuju destinasi'],
            ['jam' => '08.00', 'kegiatan' => 'Penjemputan peserta'],
        ],
    ]]]);

    // Balasannya satu daftar rata: templat tim lebih dulu, lalu paket
    // katalog. Keduanya dibedakan lewat 'asal'.
    $sumber = collect($this->getJson('/api/v1/rab/sumber-itinerary', kepalaPaket())
        ->assertOk()->json('data'))
        ->first(fn ($x) => $x['asal'] === 'paket' && $x['id'] === $paket->id);

    expect($sumber)->not->toBeNull('Paketnya tidak muncul sebagai sumber itinerary.');

    $baris = collect($sumber['itinerary']);

    expect($baris->firstWhere('nama', 'Menuju destinasi')['jam'])->toBe('13:10')
        ->and($baris->firstWhere('nama', 'Menuju destinasi')['jam_selesai'])->toBe('23:30')
        // Yang hanya satu jam tetap terbaca, dan jam selesainya kosong.
        ->and($baris->firstWhere('nama', 'Penjemputan peserta')['jam'])->toBe('08:00')
        ->and($baris->firstWhere('nama', 'Penjemputan peserta')['jam_selesai'])->toBeNull();
});
