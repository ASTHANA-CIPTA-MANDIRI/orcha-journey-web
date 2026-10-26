<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Support\Rab\BerkasRab;
use App\Support\Rab\HitungRab;

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

test('kegiatan menyimpan rentang waktu dan lamanya ikut terhitung', function () {
    /*
     | Kertas kerja yang dipakai tim menulis waktunya sebagai RENTANG —
     | "07.30 – 09.00" — berikut lamanya. Satu jam mulai saja tidak menjawab
     | pertanyaan yang sebenarnya diajukan pembaca jadwal: kegiatan ini
     | memakan waktu berapa lama, dan kapan rombongan bisa bergerak lagi.
     */
    $rab = buatRabLewatApi($this);

    $data = $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'jam' => '07:30', 'jam_selesai' => '09:00', 'nama' => 'Baluran'],
        ['hari_ke' => 1, 'jam' => '18:00', 'jam_selesai' => '05:00', 'nama' => 'Perjalanan malam'],
        ['hari_ke' => 2, 'jam' => '08:00', 'nama' => 'Tanpa jam selesai'],
    ]], kepalaRab())->assertOk()->json('data.itinerary');

    expect($data[0]['durasi_menit'])->toBe(90)
        ->and($data[0]['durasi_teks'])->toBe('1 jam 30 menit')
        // Lewat tengah malam dihitung sebagai hari berikutnya: perjalanan
        // 18.00–05.00 memang sebelas jam, bukan minus tiga belas.
        ->and($data[1]['durasi_menit'])->toBe(660)
        ->and($data[1]['durasi_teks'])->toBe('11 jam')
        // Tanpa jam selesai tidak ada durasi yang bisa dikarang.
        ->and($data[2]['durasi_menit'])->toBeNull()
        ->and($data[2]['durasi_teks'])->toBeNull();
});

test('jam selesai yang bentuknya salah ditolak, bukan disimpan apa adanya', function () {
    $rab = buatRabLewatApi($this);

    $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'jam' => '07:30', 'jam_selesai' => 'sore', 'nama' => 'Baluran'],
    ]], kepalaRab())->assertUnprocessable()->assertJsonValidationErrors('itinerary.0.jam_selesai');
});

test('ringkasan membawa titik impas ke pemanggilnya', function () {
    // Dihitung sekali di HitungRab; lemon dan PDF membacanya, tidak
    // menghitung ulang — hitungan yang diulang di dua tempat akan berbeda.
    masterUji();
    $rab = buatRabLewatApi($this);

    $this->postJson('/api/v1/rab/'.$rab['id'].'/biaya', [
        'kategori' => 'transportasi', 'nama' => 'Bus', 'satuan' => 'rombongan', 'harga_satuan' => 5_000_000,
    ], kepalaRab())->assertOk();

    $r = $this->getJson('/api/v1/rab/'.$rab['id'], kepalaRab())->assertOk()->json('data.ringkasan');

    expect($r)->toHaveKeys(['bep_peserta', 'bep_tercapai', 'untung_per_orang', 'sumbangan_per_orang']);
});

/*
 | ---------------------------------------------------------------------------
 | Bentuk berkas: selengkap kertas kerja tim, tanpa menggeser batas penawaran
 | ---------------------------------------------------------------------------
 */

test('kedua berkas memuat jadwal berkolom, penanda jenis, dan urutan singgah', function () {
    $rab = buatRabLewatApi($this);

    $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'jam' => '18:00', 'jam_selesai' => '05:00', 'nama' => 'Kumpul di meeting point', 'keterangan' => 'Briefing & packing armada'],
        ['hari_ke' => 2, 'jam' => '07:30', 'jam_selesai' => '09:00', 'nama' => 'Baluran', 'destinasi' => 'Baluran'],
        ['hari_ke' => 2, 'jam' => '09:00', 'jam_selesai' => '10:30', 'nama' => 'Perjalanan menuju BJ Pool'],
    ]], kepalaRab())->assertOk();

    $model = Rab::find($rab['id']);

    foreach (['penawaran', 'internal'] as $jenis) {
        $isi = view(...BerkasRab::bahan($model, $jenis))->render();

        // Empat kolom seperti kertas kerjanya: waktu, lamanya, tujuan, kegiatan.
        expect($isi)->toContain('Waktu (WIB)')
            ->and($isi)->toContain('Durasi')
            ->and($isi)->toContain('Tujuan')
            ->and($isi)->toContain('Kegiatan')
            // Lamanya dihitung, tidak diketik: 18.00–05.00 memang sebelas jam.
            ->and($isi)->toContain('11 jam')
            ->and($isi)->toContain('1 jam 30 menit')
            // Arti warnanya disebut, bukan ditebak pembaca.
            ->and($isi)->toContain('Destinasi')
            ->and($isi)->toContain('Urutan Singgah');
    }
});

test('ruas perjalanan tidak ikut dinomori sebagai singgahan', function () {
    /*
     | Perjalanan adalah RUAS antara dua singgahan, bukan singgahannya.
     | Menomorinya membuat urutan terbaca seolah rombongan berhenti di tengah
     | jalan tol.
     */
    $rab = buatRabLewatApi($this);

    $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'jam' => '07:00', 'nama' => 'Warung Prasmanan Cakput'],
        ['hari_ke' => 1, 'jam' => '07:30', 'nama' => 'Perjalanan ke Baluran National Park'],
        ['hari_ke' => 1, 'jam' => '08:00', 'nama' => 'Savana Bekol', 'destinasi' => 'Savana Bekol'],
    ]], kepalaRab())->assertOk();

    [$view, $data] = BerkasRab::bahan(Rab::find($rab['id']), 'penawaran');

    $titik = collect($data['rute'])->firstWhere('hari_ke', 1)['titik'];

    expect(collect($titik)->pluck('nama')->all())->toBe(['Warung Prasmanan Cakput', 'Savana Bekol'])
        // Nomornya rapat: 1 lalu 2, bukan 1 lalu 3.
        ->and(collect($titik)->pluck('no')->all())->toBe([1, 2]);
});

test('jenis kegiatan ditebak dari namanya, dan yang tak terbaca tidak dikarang', function () {
    expect(BerkasRab::jenis('Perjalanan menuju BJ Pool'))->toBe('perjalanan')
        ->and(BerkasRab::jenis('ISHOMA dan mandi, sarapan'))->toBe('makan')
        ->and(BerkasRab::jenis('Pembagian snack & merchandise'))->toBe('snack')
        ->and(BerkasRab::jenis('Briefing untuk esok dan istirahat'))->toBe('istirahat')
        ->and(BerkasRab::jenis('Savana Bekol', null, 'Savana Bekol'))->toBe('destinasi')
        // Tanpa penanda lebih baik daripada penanda yang salah.
        ->and(BerkasRab::jenis('Agenda khusus rombongan'))->toBe('lain');
});

test('bagian baru tidak membocorkan satu angka biaya pun ke penawaran', function () {
    /*
     | Penjaga yang paling penting di berkas ini. Urutan singgah, judul hari,
     | dan penanda jenis semuanya dirakit dari itinerary — tetapi satu
     | kelalaian di templat bisa menyeret angka modal ikut masuk. Yang
     | diperiksa di sini bukan kata kuncinya, melainkan SELURUH angka rupiah
     | yang muncul: hanya harga jual yang boleh ada.
     */
    $rab = buatRabLewatApi($this);

    foreach ([
        ['kategori' => 'transportasi', 'nama' => 'Hiace', 'satuan' => 'unit_hari', 'harga_satuan' => 850_000, 'kapasitas' => 14],
        ['kategori' => 'akomodasi', 'nama' => 'Homestay', 'satuan' => 'kamar_malam', 'harga_satuan' => 350_000, 'kapasitas' => 4],
        ['kategori' => 'operasional', 'nama' => 'Koordinator', 'satuan' => 'rombongan', 'harga_satuan' => 541_667],
    ] as $biaya) {
        $this->postJson('/api/v1/rab/'.$rab['id'].'/biaya', $biaya, kepalaRab())->assertOk();
    }

    $model = Rab::find($rab['id']);
    $r = HitungRab::ringkas($model);
    $isi = view(...BerkasRab::bahan($model, 'penawaran'))->render();

    $teks = preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<style.*?</style>#s', '', $isi)));
    preg_match_all('/Rp\s?[\d.]+/', $teks, $cocok);

    $boleh = [$r['harga_per_orang_teks'], $r['harga_total_teks']];

    expect(array_values(array_unique($cocok[0])))->toBe($boleh);
});

/*
 | ---------------------------------------------------------------------------
 | Open trip: harga diumumkan lebih dulu, jumlah orangnya belum pasti
 | ---------------------------------------------------------------------------
 */

/** RAB berisi satu pos tetap besar dan satu pos per orang. */
function rabOpenUji($t, array $ubah = []): array
{
    $rab = buatRabLewatApi($t);

    $t->patchJson('/api/v1/rab/'.$rab['id'], array_merge([
        'jenis' => 'open',
        'jumlah_peserta' => 14,
        'peserta_minimum' => 5,
        'harga_dasar' => 'minimum',
    ], $ubah), kepalaRab())->assertOk();

    foreach ([
        ['kategori' => 'transportasi', 'nama' => 'Hiace', 'satuan' => 'unit_hari', 'harga_satuan' => 850_000, 'kapasitas' => 14],
        ['kategori' => 'konsumsi', 'nama' => 'Makan', 'satuan' => 'orang', 'harga_satuan' => 170_000],
    ] as $biaya) {
        $t->postJson('/api/v1/rab/'.$rab['id'].'/biaya', $biaya, kepalaRab())->assertOk();
    }

    return $rab;
}

test('harga open trip dihitung dari jumlah paling rendah, bukan dari target', function () {
    /*
     | Inti bedanya dengan private trip. Harga diumumkan lebih dulu atas dasar
     | jumlah paling rendah yang masih mau diberangkatkan; yang datang di atas
     | angka itu jadi untung, bukan penyelamat.
     |
     | Menyamakan dasar dengan target berarti harga ikut TURUN tiap kali
     | targetnya dinaikkan — persis kebalikan dari yang dimaksud.
     */
    $rab = rabOpenUji($this);

    $dasarMinimum = $this->getJson('/api/v1/rab/'.$rab['id'], kepalaRab())->json('data.ringkasan');

    $this->patchJson('/api/v1/rab/'.$rab['id'], ['harga_dasar' => 'peserta'], kepalaRab())->assertOk();
    $dasarTarget = $this->getJson('/api/v1/rab/'.$rab['id'], kepalaRab())->json('data.ringkasan');

    expect($dasarMinimum['peserta_dasar'])->toBe(5)
        ->and($dasarTarget['peserta_dasar'])->toBe(14)
        // Biaya tetap yang sama dibagi lima kepala jauh lebih mahal daripada
        // dibagi empat belas.
        ->and($dasarMinimum['harga_per_orang'])->toBeGreaterThan($dasarTarget['harga_per_orang']);
});

test('private trip tidak bergeser satu rupiah pun oleh perubahan ini', function () {
    // Penawaran yang sudah dikirim tidak boleh berubah harganya karena fitur
    // yang ditambahkan untuk jenis perjalanan yang lain.
    $rab = buatRabLewatApi($this);

    $this->postJson('/api/v1/rab/'.$rab['id'].'/biaya', [
        'kategori' => 'transportasi', 'nama' => 'Hiace', 'satuan' => 'unit_hari',
        'harga_satuan' => 850_000, 'kapasitas' => 14,
    ], kepalaRab())->assertOk();

    $r = $this->getJson('/api/v1/rab/'.$rab['id'], kepalaRab())->json('data');

    expect($r['jenis'])->toBe('private')
        // Dasar harganya jumlah pesertanya sendiri — seperti sebelum ada
        // jenis trip sama sekali.
        ->and($r['ringkasan']['peserta_dasar'])->toBe($r['jumlah_peserta']);
});

test('open trip di bawah titik impas ditahan sebelum jadi pendaftaran', function () {
    /*
     | Yang menekan tombol ini biasanya sedang mengejar tenggat. Rugi dua juta
     | jauh lebih mudah dicegah di sini daripada dijelaskan di laporan bulan
     | depan.
     */
    /*
     | Harga sudah diumumkan atas dasar 5 orang, tetapi yang mendaftar baru 2.
     | Inilah kerugian yang nyata pada open trip — bukan "harga ikut jumlah
     | yang ada", karena harga yang ikut jumlah peserta tidak mungkin rugi
     | dan juga tidak mungkin diumumkan lebih dulu.
     */
    $rab = rabOpenUji($this, ['jumlah_peserta' => 2, 'peserta_minimum' => 5, 'harga_dasar' => 'minimum']);

    $paket = TravelPackage::create(['name' => 'Open Trip Uji', 'category' => 'open_trip', 'price' => 0, 'status' => 'terbit']);

    $balasan = $this->postJson('/api/v1/rab/'.$rab['id'].'/jadikan-pendaftaran', [
        'travel_package_id' => $paket->id,
    ], kepalaRab());

    // Ditahan, dan alasannya disebut dengan angka — bukan "tidak bisa".
    $balasan->assertStatus(422);
    expect($balasan->json('pesan'))->toContain('balik modal mulai')
        ->and($balasan->json('impas'))->toBeInt();
});

test('penahanan itu bisa dilewati, tetapi harus disebut terang-terangan', function () {
    // Kadang trip memang sengaja dijalankan merugi demi nama baik; keputusan
    // itu milik manusia, bukan milik pemeriksaan ini.
    $rab = rabOpenUji($this, ['jumlah_peserta' => 2, 'peserta_minimum' => 5, 'harga_dasar' => 'minimum']);

    $paket = TravelPackage::create(['name' => 'Open Trip Uji', 'category' => 'open_trip', 'price' => 0, 'status' => 'terbit']);

    $this->postJson('/api/v1/rab/'.$rab['id'].'/jadikan-pendaftaran', [
        'travel_package_id' => $paket->id,
        'abaikan_impas' => true,
    ], kepalaRab())->assertCreated();
});

test('private trip tidak ikut ditahan — jumlah orangnya sudah pasti', function () {
    $rab = buatRabLewatApi($this);

    $this->postJson('/api/v1/rab/'.$rab['id'].'/biaya', [
        'kategori' => 'transportasi', 'nama' => 'Hiace', 'satuan' => 'rombongan', 'harga_satuan' => 9_000_000,
    ], kepalaRab())->assertOk();

    $paket = TravelPackage::create(['name' => 'Open Trip Uji', 'category' => 'open_trip', 'price' => 0, 'status' => 'terbit']);

    $this->postJson('/api/v1/rab/'.$rab['id'].'/jadikan-pendaftaran', [
        'travel_package_id' => $paket->id,
    ], kepalaRab())->assertCreated();
});

test('simulasi ikut dikirim supaya angka impasnya bisa diperiksa', function () {
    // Angka impas tanpa tabelnya menuntut pembacanya percaya begitu saja.
    $rab = rabOpenUji($this);

    $r = $this->getJson('/api/v1/rab/'.$rab['id'], kepalaRab())->json('data.ringkasan');

    expect($r['simulasi'])->toBeArray()
        ->and(count($r['simulasi']))->toBeGreaterThanOrEqual(14);

    $baris = collect($r['simulasi'])->firstWhere('peserta', $r['bep_peserta']);
    $sebelum = collect($r['simulasi'])->firstWhere('peserta', $r['bep_peserta'] - 1);

    expect($baris['laba'])->toBeGreaterThanOrEqual(0);

    if ($sebelum) {
        expect($sebelum['laba'])->toBeLessThan(0);
    }
});
