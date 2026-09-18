<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Support\Rab\BerkasRab;

/**
 * Jalur admin untuk master harga dan RAB.
 *
 * Yang dijaga di sini: angka modal tidak pernah sampai ke berkas pelanggan,
 * tombol yang boleh ditekan dua kali tidak menggandakan biaya, dan pendaftaran
 * yang lahir dari RAB membawa modal yang sama persis.
 */
function kepalaRab(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-rab');

    return ['X-Orcha-Key' => 'kunci-uji-rab', 'Accept' => 'application/json'];
}

function masterUji(array $ubah = []): MasterHarga
{
    return MasterHarga::create(array_merge([
        'kategori' => 'tiket',
        'nama' => 'Tiket Candi Prambanan',
        'provinsi' => 'DI Yogyakarta',
        'destinasi' => 'Candi Prambanan',
        'satuan' => 'orang',
        'harga' => 50_000,
        'otomatis' => false,
        'aktif' => true,
    ], $ubah));
}

function buatRabLewatApi($uji, array $ubah = []): array
{
    return $uji->postJson('/api/v1/rab', array_merge([
        'judul' => 'Private Trip Jogja',
        'nama_pelanggan' => 'Bu Siti',
        'whatsapp' => '081234567890',
        'provinsi' => 'DI Yogyakarta',
        'jumlah_hari' => 3,
        'jumlah_malam' => 2,
        'jumlah_peserta' => 17,
        'margin_jenis' => 'persen',
        'margin_nilai' => 20,
    ], $ubah), kepalaRab())->assertCreated()->json('data');
}

test('master harga per unit menolak tanpa kapasitas', function () {
    $this->postJson('/api/v1/master-harga', [
        'kategori' => 'transportasi', 'nama' => 'Hiace', 'provinsi' => 'DI Yogyakarta',
        'satuan' => 'unit_hari', 'harga' => 1_100_000,
    ], kepalaRab())->assertUnprocessable()->assertJsonValidationErrors('kapasitas');

    $this->postJson('/api/v1/master-harga', [
        'kategori' => 'transportasi', 'nama' => 'Hiace', 'provinsi' => 'DI Yogyakarta',
        'satuan' => 'unit_hari', 'harga' => 1_100_000, 'kapasitas' => 14,
    ], kepalaRab())->assertCreated();

    expect(MasterHarga::where('nama', 'Hiace')->value('kapasitas'))->toBe(14);
});

test('master harga bisa diubah, dicari, dan dihapus', function () {
    $m = masterUji();

    $this->patchJson('/api/v1/master-harga/'.$m->id, [
        'kategori' => 'tiket', 'nama' => 'Tiket Candi Prambanan', 'provinsi' => 'DI Yogyakarta',
        'destinasi' => 'Candi Prambanan', 'satuan' => 'orang', 'harga' => 60_000,
    ], kepalaRab())->assertOk();

    expect($m->fresh()->harga)->toBe(60_000);

    $this->getJson('/api/v1/master-harga?cari=prambanan', kepalaRab())
        ->assertOk()->assertJsonCount(1, 'data');

    $this->deleteJson('/api/v1/master-harga/'.$m->id, [], kepalaRab())->assertOk();
    expect(MasterHarga::count())->toBe(0);
});

test('rute katalog tidak tertelan rute berparameter', function () {
    masterUji();

    $data = $this->getJson('/api/v1/rab/katalog?provinsi=DI%20Yogyakarta', kepalaRab())
        ->assertOk()->json('data');

    $prambanan = collect($data['destinasi'])->firstWhere('nama', 'Candi Prambanan');

    expect($prambanan)->not->toBeNull()
        ->and($prambanan['punya_tiket'])->toBeTrue()
        ->and(collect($data['destinasi'])->pluck('nama'))->not->toContain('Kawah Ijen')
        ->and($data['master'])->toHaveCount(1)
        ->and($data['provinsi'])->toContain('DI Yogyakarta');
});

test('RAB baru langsung membawa biaya wajib provinsinya, bukan provinsi lain', function () {
    masterUji(['kategori' => 'lainnya', 'nama' => 'Asuransi', 'destinasi' => null, 'provinsi' => null, 'otomatis' => true, 'harga' => 15_000]);
    masterUji(['kategori' => 'konsumsi', 'nama' => 'Makan', 'destinasi' => null, 'satuan' => 'orang_hari', 'otomatis' => true, 'harga' => 75_000]);
    masterUji(['kategori' => 'konsumsi', 'nama' => 'Makan Bali', 'destinasi' => null, 'provinsi' => 'Bali', 'otomatis' => true]);
    masterUji(['nama' => 'Parkir (nonaktif)', 'destinasi' => null, 'otomatis' => true, 'aktif' => false]);

    $rab = buatRabLewatApi($this);

    expect(collect($rab['ringkasan']['baris'])->pluck('nama')->sort()->values()->all())
        ->toBe(['Asuransi', 'Makan'])
        ->and($rab['kode'])->toStartWith('RAB-')
        ->and($rab['status'])->toBe('draf');
});

test('malam melebihi hari dan margin persen mustahil ditolak', function () {
    $this->postJson('/api/v1/rab', [
        'judul' => 'Trip', 'nama_pelanggan' => 'Bu Siti', 'provinsi' => 'DI Yogyakarta',
        'jumlah_hari' => 2, 'jumlah_malam' => 3, 'jumlah_peserta' => 5,
    ], kepalaRab())->assertUnprocessable()->assertJsonValidationErrors('jumlah_malam');

    $rab = buatRabLewatApi($this, ['margin_jenis' => 'total', 'margin_nilai' => 3_000_000]);

    // Beralih ke persen tanpa mengubah nilainya: 3.000.000% pasti salah ketik.
    $this->patchJson('/api/v1/rab/'.$rab['id'], ['margin_jenis' => 'persen'], kepalaRab())
        ->assertUnprocessable()->assertJsonValidationErrors('margin_nilai');
});

test('itinerary menolak hari di luar lama perjalanan dan menomori ulang urutan', function () {
    $rab = buatRabLewatApi($this);

    $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 4, 'nama' => 'Kelebihan hari'],
    ]], kepalaRab())->assertUnprocessable()->assertJsonValidationErrors('itinerary.0.hari_ke');

    $data = $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'jam' => '09:00', 'nama' => 'Candi Prambanan', 'destinasi' => 'Candi Prambanan'],
        ['hari_ke' => 2, 'nama' => 'Borobudur'],
        ['hari_ke' => 1, 'jam' => '15:00', 'nama' => 'Check-in'],
    ]], kepalaRab())->assertOk()->json('data.itinerary');

    expect(collect($data)->map(fn ($i) => $i['hari_ke'].'.'.$i['urutan'])->all())
        ->toBe(['1.1', '1.2', '2.1']);
});

test('tarik biaya aman ditekan dua kali dan menyebut destinasi tanpa tiket', function () {
    masterUji();
    $rab = buatRabLewatApi($this);

    $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'nama' => 'Prambanan', 'destinasi' => 'Candi Prambanan'],
        ['hari_ke' => 2, 'nama' => 'Malioboro', 'destinasi' => 'Malioboro'],
    ]], kepalaRab())->assertOk();

    $pertama = $this->postJson('/api/v1/rab/'.$rab['id'].'/tarik-biaya', [], kepalaRab())->assertOk();
    expect($pertama->json('tanpa_tiket'))->toBe(['Malioboro'])
        ->and($pertama->json('data.ringkasan.baris'))->toHaveCount(1);

    $kedua = $this->postJson('/api/v1/rab/'.$rab['id'].'/tarik-biaya', [], kepalaRab())->assertOk();
    expect($kedua->json('data.ringkasan.baris'))->toHaveCount(1)
        ->and($kedua->json('pesan'))->toContain('Tidak ada biaya baru');
});

test('harga beku tidak ikut berubah saat master naik, sampai admin memintanya', function () {
    $m = masterUji();
    $rab = buatRabLewatApi($this);

    $baris = $this->postJson('/api/v1/rab/'.$rab['id'].'/biaya', ['master_harga_id' => $m->id], kepalaRab())
        ->json('data.ringkasan.baris.0');

    $m->update(['harga' => 75_000]);

    $r = $this->getJson('/api/v1/rab/'.$rab['id'], kepalaRab())->json('data.ringkasan');
    expect($r['baris'][0]['harga_satuan'])->toBe(50_000)
        ->and($r['baris'][0]['harga_master_kini'])->toBe(75_000)
        ->and($r['ada_harga_basi'])->toBeTrue();

    $r = $this->patchJson('/api/v1/rab/'.$rab['id'].'/biaya/'.$baris['id'], ['pakai_harga_master' => true], kepalaRab())
        ->assertOk()->json('data.ringkasan');
    expect($r['baris'][0]['harga_satuan'])->toBe(75_000)
        ->and($r['ada_harga_basi'])->toBeFalse();
});

test('biaya milik RAB lain tidak bisa diubah lewat RAB ini', function () {
    $a = buatRabLewatApi($this);
    $b = buatRabLewatApi($this);

    $biaya = $this->postJson('/api/v1/rab/'.$b['id'].'/biaya', [
        'kategori' => 'lainnya', 'nama' => 'Dokumentasi', 'satuan' => 'rombongan', 'harga_satuan' => 500_000,
    ], kepalaRab())->assertOk()->json('data.ringkasan.baris.0');

    $this->deleteJson('/api/v1/rab/'.$a['id'].'/biaya/'.$biaya['id'], [], kepalaRab())->assertNotFound();
});

test('PDF penawaran tidak memuat angka modal, PDF internal memuatnya', function () {
    $rab = buatRabLewatApi($this);
    $this->postJson('/api/v1/rab/'.$rab['id'].'/biaya', [
        'kategori' => 'transportasi', 'nama' => 'Sewa Hiace', 'satuan' => 'unit_hari',
        'harga_satuan' => 1_234_567, 'kapasitas' => 14,
    ], kepalaRab())->assertOk();

    $model = Rab::find($rab['id']);
    // HTML sebelum jadi PDF: teks di dalam PDF termampatkan.
    $data = fn ($jenis) => view(...BerkasRab::bahan($model, $jenis))->render();

    $penawaran = $data('penawaran');
    expect($penawaran)->toContain('Sewa Hiace')
        ->and($penawaran)->not->toContain('1.234.567')
        ->and($penawaran)->not->toContain('Modal')
        ->and($penawaran)->not->toContain('INTERNAL');

    $internal = $data('internal');
    expect($internal)->toContain('1.234.567')
        ->and($internal)->toContain('JANGAN DIKIRIM KE PELANGGAN');
});

test('endpoint PDF mengirim berkas PDF', function () {
    $rab = buatRabLewatApi($this);

    $res = $this->get('/api/v1/rab/'.$rab['id'].'/pdf?jenis=internal', kepalaRab())->assertOk();
    expect($res->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($res->headers->get('Content-Disposition'))->toContain('rab-internal')
        ->and(substr($res->getContent(), 0, 4))->toBe('%PDF');
});

test('jadikan pendaftaran membawa modal yang sama persis dan tidak bisa diulang', function () {
    $rab = buatRabLewatApi($this);
    foreach ([
        ['kategori' => 'tiket', 'nama' => 'Tiket', 'satuan' => 'orang', 'harga_satuan' => 50_001],
        ['kategori' => 'transportasi', 'nama' => 'Hiace', 'satuan' => 'unit_hari', 'harga_satuan' => 1_100_000, 'kapasitas' => 14],
    ] as $b) {
        $this->postJson('/api/v1/rab/'.$rab['id'].'/biaya', $b, kepalaRab())->assertOk();
    }

    $paket = TravelPackage::create(['name' => 'Private Trip', 'category' => 'private_trip', 'price' => 0, 'status' => 'terbit']);
    $modalRab = $this->getJson('/api/v1/rab/'.$rab['id'], kepalaRab())->json('data.ringkasan.modal_total');

    $kode = $this->postJson('/api/v1/rab/'.$rab['id'].'/jadikan-pendaftaran', ['travel_package_id' => $paket->id], kepalaRab())
        ->assertCreated()->json('data.kode');

    $p = PendaftaranOpenTrip::where('kode', $kode)->firstOrFail();
    expect($p->harga_modal * $p->jumlah_peserta + $p->biaya_tetap)->toBe($modalRab)
        ->and($p->jumlah_peserta)->toBe(17)
        ->and(Rab::find($rab['id'])->status)->toBe('disetujui');

    $this->postJson('/api/v1/rab/'.$rab['id'].'/jadikan-pendaftaran', ['travel_package_id' => $paket->id], kepalaRab())
        ->assertUnprocessable();
    $this->deleteJson('/api/v1/rab/'.$rab['id'], [], kepalaRab())->assertUnprocessable();

    expect(PendaftaranOpenTrip::count())->toBe(1);
});

test('jadikan pendaftaran menolak RAB tanpa WhatsApp', function () {
    $rab = buatRabLewatApi($this, ['whatsapp' => null]);
    $paket = TravelPackage::create(['name' => 'Private Trip', 'category' => 'private_trip', 'price' => 0, 'status' => 'terbit']);

    $this->postJson('/api/v1/rab/'.$rab['id'].'/jadikan-pendaftaran', ['travel_package_id' => $paket->id], kepalaRab())
        ->assertUnprocessable()->assertJsonPath('pesan', fn ($p) => str_contains($p, 'WhatsApp'));
});

test('tanpa kunci API semua jalur RAB tertutup', function () {
    config()->set('orcha.api.kunci', 'kunci-uji-rab');

    $this->getJson('/api/v1/rab')->assertUnauthorized();
    $this->getJson('/api/v1/master-harga')->assertUnauthorized();
});
