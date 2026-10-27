<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\Vendor;
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

test('jenis kegiatan ikut dikirim, supaya lemon tidak menebak ulang', function () {
    /*
     | Penggolongnya sudah dipakai kedua PDF. Menyalin aturannya ke lemon
     | berarti dua tebakan untuk perjalanan yang sama — dan suatu hari layar
     | memberi warna hijau pada kegiatan yang di PDF-nya biru, tanpa ada yang
     | bisa menyebut mana yang benar.
     */
    $rab = buatRabLewatApi($this);

    $data = $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'nama' => 'Perjalanan menuju BJ Pool'],
        ['hari_ke' => 1, 'nama' => 'Savana Bekol', 'destinasi' => 'Savana Bekol'],
        ['hari_ke' => 1, 'nama' => 'Check-in hotel'],
    ]], kepalaRab())->assertOk()->json('data.itinerary');

    expect(collect($data)->pluck('jenis')->all())->toBe(['perjalanan', 'destinasi', 'istirahat']);
});

test('harga tiket tetap terjodoh walau huruf besar-kecil dan spasinya berbeda', function () {
    /*
     | Nama destinasi di master harga pernah diketik bebas, jadi "Candi
     | Prambanan " dan "candi prambanan" memang ada di data. Dicocokkan
     | mentah-mentah, tiketnya tidak pernah ikut tertarik dan RAB-nya
     | kekurangan modal tanpa satu pun tanda.
     */
    masterUji(['nama' => 'Tiket Prambanan', 'destinasi' => '  candi prambanan ']);
    $rab = buatRabLewatApi($this);

    $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'nama' => 'Candi Prambanan', 'destinasi' => 'Candi Prambanan'],
    ]], kepalaRab())->assertOk();

    $balasan = $this->postJson('/api/v1/rab/'.$rab['id'].'/tarik-biaya', [], kepalaRab())->assertOk();

    expect($balasan->json('data.ringkasan.baris'))->toHaveCount(1)
        ->and($balasan->json('data.ringkasan.baris.0.nama'))->toBe('Tiket Prambanan')
        // Dan destinasinya tidak lagi dilaporkan tanpa tiket.
        ->and($balasan->json('tanpa_tiket'))->toBe([]);
});

test('nama yang memang berbeda tetap tidak dijodohkan', function () {
    /*
     | Menebak kemiripan akan memasukkan tiket tempat lain ke penawaran, dan
     | salah tarik jauh lebih mahal daripada tidak tertarik: yang tidak
     | tertarik kelihatan di peringatan, yang salah tarik tidak kelihatan
     | sama sekali.
     */
    masterUji(['nama' => 'Tiket Prambanan', 'destinasi' => 'prambanan']);
    $rab = buatRabLewatApi($this);

    $this->putJson('/api/v1/rab/'.$rab['id'].'/itinerary', ['itinerary' => [
        ['hari_ke' => 1, 'nama' => 'Candi Prambanan', 'destinasi' => 'Candi Prambanan'],
    ]], kepalaRab())->assertOk();

    $balasan = $this->postJson('/api/v1/rab/'.$rab['id'].'/tarik-biaya', [], kepalaRab())->assertOk();

    expect($balasan->json('data.ringkasan.baris'))->toBe([])
        ->and($balasan->json('tanpa_tiket'))->toBe(['Candi Prambanan']);
});

/*
 | ---------------------------------------------------------------------------
 | Vendor: dari siapa sebuah harga dibeli
 |
 | Sebelumnya kontak vendor cuma muat di kolom catatan master harga — teks
 | bebas, tidak bisa dicari, dan tidak ikut tersalin ke RAB.
 | ---------------------------------------------------------------------------
 */

function vendorUji(array $ubah = []): Vendor
{
    return Vendor::create(array_merge([
        'nama' => 'PO Sumber Jaya',
        'kontak' => '081234000111 (Pak Budi)',
        'jenis' => 'Armada',
    ], $ubah));
}

test('vendor bisa ditambah, dicari, diubah, dan dihapus', function () {
    $this->postJson('/api/v1/vendor', [
        'nama' => 'PO Sumber Jaya', 'kontak' => '081234000111 (Pak Budi)', 'jenis' => 'Armada',
    ], kepalaRab())->assertCreated()->assertJsonPath('data.nama', 'PO Sumber Jaya');

    $v = Vendor::first();

    // Dicari lewat nama ATAU nomornya — nomor yang diingat separuh pun cukup.
    $this->getJson('/api/v1/vendor?cari=sumber', kepalaRab())->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/vendor?cari=081234', kepalaRab())->assertOk()->assertJsonCount(1, 'data');

    $this->patchJson('/api/v1/vendor/'.$v->id, [
        'nama' => 'PO Sumber Jaya', 'kontak' => '082199900022 (Pak Budi)',
    ], kepalaRab())->assertOk();

    expect($v->fresh()->kontak)->toBe('082199900022 (Pak Budi)');

    $this->deleteJson('/api/v1/vendor/'.$v->id, [], kepalaRab())->assertOk();
});

test('vendor bernama sama tidak dibuat dua kali', function () {
    /*
     | Vendor ganda membelah kontaknya: separuh harga menunjuk baris pertama,
     | separuhnya baris kedua, dan memperbarui nomor di salah satunya
     | meninggalkan nomor lama hidup di yang lain — persis keadaan yang
     | hendak dihindari tabel ini.
     */
    vendorUji();

    $this->postJson('/api/v1/vendor', ['nama' => '  po sumber   JAYA '], kepalaRab())
        ->assertOk()
        ->assertJsonPath('pesan', 'PO Sumber Jaya sudah ada di daftar vendor.');

    expect(Vendor::count())->toBe(1);
});

test('menghapus vendor tidak menghapus harganya', function () {
    // Menghapus vendor tidak boleh menghapus harganya, apalagi mengubah
    // penawaran yang sudah dikirim.
    $v = vendorUji();
    $m = masterUji(['vendor_id' => $v->id]);

    // Dipaksa, karena vendor yang masih dipakai kini ditahan lebih dulu —
    // lihat "vendor yang masih dipakai tidak terhapus tanpa dipaksa".
    $this->deleteJson('/api/v1/vendor/'.$v->id.'?paksa=1', [], kepalaRab())->assertOk();

    expect($m->fresh())->not->toBeNull()
        ->and($m->fresh()->vendor_id)->toBeNull();
});

test('kontak vendor ikut ke biaya RAB dan terbaca HIDUP', function () {
    /*
     | Harga dibekukan, vendornya tidak. Yang dibutuhkan saat menelepon selalu
     | nomor yang berlaku HARI INI, bukan nomor yang berlaku saat penawaran
     | dibuat — dan nomor lama yang tersimpan rapi justru lebih berbahaya
     | daripada nomor yang kosong.
     */
    $v = vendorUji();
    $m = masterUji(['vendor_id' => $v->id]);
    $rab = buatRabLewatApi($this);

    $this->postJson("/api/v1/rab/{$rab['id']}/biaya", ['master_harga_id' => $m->id], kepalaRab())
        ->assertOk();

    $baris = collect($this->getJson("/api/v1/rab/{$rab['id']}", kepalaRab())
        ->assertOk()->json('data.ringkasan.baris'))->firstWhere('master_harga_id', $m->id);

    expect($baris['vendor'])->toBe('PO Sumber Jaya')
        ->and($baris['vendor_kontak'])->toBe('081234000111 (Pak Budi)');

    // Nomor diganti SESUDAH biaya masuk ke RAB: yang terbaca nomor baru.
    $v->update(['kontak' => '082199900022']);

    $baris = collect($this->getJson("/api/v1/rab/{$rab['id']}", kepalaRab())
        ->json('data.ringkasan.baris'))->firstWhere('master_harga_id', $m->id);

    expect($baris['vendor_kontak'])->toBe('082199900022')
        // Harganya TETAP beku walau vendornya hidup.
        ->and($baris['harga_satuan'])->toBe(50_000);
});

test('vendor tercetak di PDF internal, tidak pernah di penawaran', function () {
    /*
     | Lembar internal yang dibawa saat menyiapkan keberangkatan; pelanggan
     | tidak perlu — dan tidak boleh — tahu dari siapa kita membeli.
     */
    $v = vendorUji();
    $m = masterUji(['vendor_id' => $v->id]);
    $rab = buatRabLewatApi($this);

    $this->postJson("/api/v1/rab/{$rab['id']}/biaya", ['master_harga_id' => $m->id], kepalaRab())->assertOk();

    $model = Rab::find($rab['id']);

    expect(BerkasRab::buat($model->fresh(), 'internal'))->toBeString();

    // Diperiksa di markahnya, bukan di PDF jadi: dompdf memampatkan isinya
    // sehingga mencari teks di sana selalu "tidak ketemu".
    [$viewInternal, $dataInternal] = BerkasRab::bahan($model->fresh(), 'internal');
    [$viewPenawaran, $dataPenawaran] = BerkasRab::bahan($model->fresh(), 'penawaran');

    $internal = view($viewInternal, $dataInternal)->render();
    $penawaran = view($viewPenawaran, $dataPenawaran)->render();

    expect($internal)->toContain('PO Sumber Jaya')
        ->and($internal)->toContain('081234000111')
        ->and($penawaran)->not->toContain('PO Sumber Jaya')
        ->and($penawaran)->not->toContain('081234000111');
});

test('katalog RAB membawa daftar vendor beserta nomornya', function () {
    // Supaya layar penyusun tidak perlu satu panggilan lagi hanya untuk
    // mengetahui ke siapa harus menelepon.
    vendorUji();
    vendorUji(['nama' => 'Hotel Mawar', 'kontak' => '0274-555000', 'jenis' => 'Akomodasi']);
    vendorUji(['nama' => 'Vendor Nonaktif', 'aktif' => false]);

    $vendor = $this->getJson('/api/v1/rab/katalog', kepalaRab())->assertOk()->json('data.vendor');

    expect(collect($vendor)->pluck('nama')->all())->toBe(['Hotel Mawar', 'PO Sumber Jaya'])
        ->and(collect($vendor)->firstWhere('nama', 'Hotel Mawar')['kontak'])->toBe('0274-555000');
});

test('harga bisa dicari lewat nama vendornya', function () {
    // "Siapa yang memasok Hiace" sama seringnya ditanya dengan "berapa
    // harga Hiace".
    $v = vendorUji();
    masterUji(['vendor_id' => $v->id]);
    masterUji(['nama' => 'Tiket Borobudur', 'destinasi' => 'Candi Borobudur']);

    $this->getJson('/api/v1/master-harga?cari=sumber jaya', kepalaRab())
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.vendor', 'PO Sumber Jaya')
        ->assertJsonPath('data.0.vendor_kontak', '081234000111 (Pak Budi)');
});

test('vendor bisa disaring menurut jenis dan statusnya', function () {
    // Dengan 30 vendor, "mana saja yang armada" tidak boleh berarti membaca
    // tiga puluh baris satu per satu.
    vendorUji();
    vendorUji(['nama' => 'Hotel Mawar', 'jenis' => 'Akomodasi']);
    vendorUji(['nama' => 'Jip Lama', 'jenis' => 'Armada', 'aktif' => false]);

    $this->getJson('/api/v1/vendor?jenis=Armada', kepalaRab())->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/vendor?status=nonaktif', kepalaRab())->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/vendor?jenis=Armada&status=aktif', kepalaRab())->assertOk()->assertJsonCount(1, 'data');
});

test('ringkasan vendor dihitung dari seluruh tabel, bukan halamannya', function () {
    /*
     | Vendor tanpa nomor adalah pekerjaan yang belum selesai; memindainya
     | halaman demi halaman berarti tidak akan pernah dipindai.
     */
    vendorUji();
    vendorUji(['nama' => 'Tanpa Nomor', 'kontak' => null]);
    vendorUji(['nama' => 'Sudah Mati', 'aktif' => false]);

    $meta = $this->getJson('/api/v1/vendor?per_halaman=1', kepalaRab())->assertOk()->json('meta');

    expect($meta['ringkas']['tanpa_kontak'])->toBe(1)
        ->and($meta['ringkas']['nonaktif'])->toBe(1)
        ->and($meta['ringkas']['jenis'])->toContain('Armada');
});

test('dua vendor yang ternyata sama bisa digabungkan', function () {
    /*
     | Pemeriksaan kembar saat menambah hanya menangkap nama yang PERSIS
     | sama; "PO Sumber Jaya" dan "Sumber Jaya" lolos jadi dua baris. Begitu
     | terlanjur, keduanya membelah kontaknya.
     */
    $utama = vendorUji();
    $kembar = vendorUji(['nama' => 'Sumber Jaya', 'kontak' => '08999']);
    $m = masterUji(['vendor_id' => $kembar->id]);

    $rab = buatRabLewatApi($this);
    $this->postJson("/api/v1/rab/{$rab['id']}/biaya", ['master_harga_id' => $m->id], kepalaRab())->assertOk();

    $this->postJson("/api/v1/vendor/{$kembar->id}/gabung", ['ke' => $utama->id], kepalaRab())
        ->assertOk()->assertJsonPath('pesan', '1 harga dipindahkan ke PO Sumber Jaya.');

    expect(Vendor::find($kembar->id))->toBeNull()
        ->and($m->fresh()->vendor_id)->toBe($utama->id)
        // Baris biaya RAB ikut menunjuk vendor yang benar — kalau tidak,
        // nomor di penawaran lama jadi nomor yang sudah tidak ada.
        ->and(RabBiaya::where('rab_id', $rab['id'])->value('vendor_id'))->toBe($utama->id);
});

test('vendor tidak bisa digabungkan ke dirinya sendiri', function () {
    $v = vendorUji();

    $this->postJson("/api/v1/vendor/{$v->id}/gabung", ['ke' => $v->id], kepalaRab())
        ->assertStatus(422);

    expect(Vendor::find($v->id))->not->toBeNull();
});

test('kontak yang masih tercecer di catatan bisa ditemukan', function () {
    /*
     | Sebelum ada tabel vendor, nomor ditulis di catatan — dan catatan itu
     | tidak ikut ke mana-mana. Daftar ini yang membuat sisa data lama
     | terlihat, bukan menunggu diingat.
     */
    masterUji(['nama' => 'Sewa Hiace', 'catatan' => 'PO Sumber Jaya 081234000111']);
    masterUji(['nama' => 'Tiket Borobudur', 'catatan' => 'Naik DP 30% mulai Juli']);
    masterUji(['nama' => 'Hotel Mawar', 'catatan' => null]);

    $v = vendorUji();
    masterUji(['nama' => 'Sudah Bervendor', 'catatan' => 'telp 0274-555000', 'vendor_id' => $v->id]);

    $data = $this->getJson('/api/v1/vendor/kontak-tercecer', kepalaRab())->assertOk()->json('data');

    expect(collect($data)->pluck('nama')->all())->toBe(['Sewa Hiace'])
        ->and($data[0]['catatan'])->toBe('PO Sumber Jaya 081234000111');
});

test('harga bisa disaring menurut vendornya, termasuk yang belum punya', function () {
    // "Harga apa saja milik PO Sumber Jaya" dan "mana yang belum ada
    // vendornya" — dua pertanyaan yang sama seringnya ditanya.
    $v = vendorUji();
    masterUji(['vendor_id' => $v->id]);
    masterUji(['nama' => 'Tiket Borobudur']);

    $this->getJson("/api/v1/master-harga?vendor={$v->id}", kepalaRab())
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.vendor', 'PO Sumber Jaya');

    $this->getJson('/api/v1/master-harga?vendor=-', kepalaRab())
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama', 'Tiket Borobudur');
});

test('rincian vendor menyebut harga, RAB, dan nilai belanjanya', function () {
    /*
     | "RAB mana saja yang pakai vendor ini" dan "sudah belanja berapa" —
     | dua pertanyaan yang datanya sudah ada sejak awal tetapi tidak pernah
     | dijumlahkan. Angka belanjanya memakai rumus yang SAMA dengan rincian
     | biaya RAB: satu bus 1,1 juta yang jadi 2 unit selama 3 hari bernilai
     | 6,6 juta, dan angka itulah yang dipakai menawar.
     */
    $v = vendorUji();
    $bus = masterUji([
        'nama' => 'Sewa Hiace', 'kategori' => 'transportasi', 'destinasi' => null,
        'satuan' => 'unit_hari', 'harga' => 1_100_000, 'kapasitas' => 14, 'vendor_id' => $v->id,
    ]);

    $rab = buatRabLewatApi($this);   // 17 peserta, 3 hari
    $this->postJson("/api/v1/rab/{$rab['id']}/biaya", ['master_harga_id' => $bus->id], kepalaRab())->assertOk();

    $data = $this->getJson("/api/v1/vendor/{$v->id}/rincian", kepalaRab())->assertOk()->json('data');

    expect($data['vendor']['nama'])->toBe('PO Sumber Jaya')
        ->and(collect($data['harga'])->pluck('nama')->all())->toBe(['Sewa Hiace'])
        ->and($data['rab'])->toHaveCount(1)
        ->and($data['rab'][0]['kode'])->toBe($rab['kode'])
        // 1.100.000 × 2 unit × 3 hari
        ->and($data['belanja'])->toBe(6_600_000);
});

test('vendor yang masih dipakai tidak terhapus tanpa dipaksa', function () {
    /*
     | Menghapusnya memang tidak merusak harganya, tetapi kontaknya hilang
     | dari seluruh RAB yang memakainya dan itu tidak bisa dibatalkan. Yang
     | hampir selalu dimaksud admin adalah "jangan tawarkan lagi".
     */
    $v = vendorUji();
    $m = masterUji(['vendor_id' => $v->id]);

    $this->deleteJson('/api/v1/vendor/'.$v->id, [], kepalaRab())
        ->assertStatus(422)
        ->assertJsonPath('dipakai', 1);

    expect(Vendor::find($v->id))->not->toBeNull();

    // Masih bisa dipaksa: baris salah ketik memang pantas dibuang.
    $this->deleteJson('/api/v1/vendor/'.$v->id.'?paksa=1', [], kepalaRab())->assertOk();

    expect(Vendor::find($v->id))->toBeNull()
        ->and($m->fresh())->not->toBeNull();
});

test('vendor tanpa pemakaian tetap bisa langsung dihapus', function () {
    $v = vendorUji();

    $this->deleteJson('/api/v1/vendor/'.$v->id, [], kepalaRab())->assertOk();

    expect(Vendor::find($v->id))->toBeNull();
});

test('vendor disaring wilayah, yang tanpa provinsi ikut terbawa', function () {
    // Kosong berarti BERLAKU DI MANA SAJA — asuransi dan dokumentasi memang
    // tidak terikat tempat.
    vendorUji(['nama' => 'PO Jogja', 'provinsi' => 'DI Yogyakarta']);
    vendorUji(['nama' => 'PO Bali', 'provinsi' => 'Bali']);
    vendorUji(['nama' => 'Asuransi Nasional', 'provinsi' => null]);

    $nama = collect($this->getJson('/api/v1/vendor?provinsi=Bali', kepalaRab())->assertOk()->json('data'))
        ->pluck('nama')->all();

    expect($nama)->toBe(['Asuransi Nasional', 'PO Bali']);

    // "-" berarti hanya yang memang tidak terikat provinsi.
    $nama = collect($this->getJson('/api/v1/vendor?provinsi=-', kepalaRab())->json('data'))->pluck('nama')->all();

    expect($nama)->toBe(['Asuransi Nasional']);
});

test('katalog mengurutkan vendor menurut seberapa sering dipakai', function () {
    /*
     | Urut abjad membuat vendor yang dipakai tiap minggu terdampar di huruf
     | S, sementara yang sekali setahun duduk di atas.
     */
    $sering = vendorUji(['nama' => 'Zebra Trans']);
    $jarang = vendorUji(['nama' => 'Abadi Trans']);

    masterUji(['nama' => 'Hiace A', 'vendor_id' => $sering->id]);
    masterUji(['nama' => 'Bus B', 'vendor_id' => $sering->id]);
    masterUji(['nama' => 'Elf C', 'vendor_id' => $jarang->id]);

    $vendor = $this->getJson('/api/v1/rab/katalog', kepalaRab())->assertOk()->json('data.vendor');

    expect(collect($vendor)->pluck('nama')->all())->toBe(['Zebra Trans', 'Abadi Trans']);
});

test('daftar RAB di rincian vendor dipotong per halaman, belanjanya tidak', function () {
    /*
     | Dua hal yang berbeda, dan menyamakannya adalah cacat yang paling mudah
     | lolos: "sudah dibelanjakan Rp 6 juta" yang ternyata cuma halaman
     | pertama adalah angka yang salah tanpa kelihatan salah.
     */
    $v = vendorUji();
    $m = masterUji(['vendor_id' => $v->id]);   // per orang, 50.000 × 17 = 850.000

    foreach (range(1, 3) as $i) {
        $rab = buatRabLewatApi($this, ['judul' => "Trip {$i}"]);
        $this->postJson("/api/v1/rab/{$rab['id']}/biaya", ['master_harga_id' => $m->id], kepalaRab())->assertOk();
    }

    $data = $this->getJson("/api/v1/vendor/{$v->id}/rincian?per_halaman=2", kepalaRab())->assertOk()->json('data');

    expect($data['rab'])->toHaveCount(2)
        ->and($data['rab_meta']['total'])->toBe(3)
        ->and($data['rab_meta']['halaman_terakhir'])->toBe(2)
        // Belanjanya tetap dari ketiganya, bukan dari dua yang tampil.
        ->and($data['belanja'])->toBe(3 * 850_000);

    $halaman2 = $this->getJson("/api/v1/vendor/{$v->id}/rincian?per_halaman=2&halaman=2", kepalaRab())
        ->assertOk()->json('data');

    expect($halaman2['rab'])->toHaveCount(1)
        ->and($halaman2['belanja'])->toBe(3 * 850_000);
});

test('vendor bisa dipasang ke beberapa harga sekaligus', function () {
    /*
     | Satu vendor biasanya menempel pada beberapa baris harga sekaligus.
     | Memasangnya satu per satu lewat formulir harga berarti membuka,
     | menyimpan, dan menutup tiga kali untuk satu keputusan yang sama.
     */
    $v = vendorUji();
    $a = masterUji(['nama' => 'Sewa Hiace', 'harga' => 1_100_000]);
    $b = masterUji(['nama' => 'Sewa Elf', 'harga' => 1_450_000]);
    $lain = masterUji(['nama' => 'Tiket Borobudur']);

    $this->postJson("/api/v1/vendor/{$v->id}/pasang", ['harga' => [$a->id, $b->id]], kepalaRab())
        ->assertOk()->assertJsonPath('pesan', '2 harga dipasangkan ke PO Sumber Jaya.');

    expect($a->fresh()->vendor_id)->toBe($v->id)
        ->and($b->fresh()->vendor_id)->toBe($v->id)
        ->and($lain->fresh()->vendor_id)->toBeNull()
        // Hanya kolom vendor yang disentuh — harganya tidak ikut berubah
        // karena payload-nya memang tidak membawa harga.
        ->and($a->fresh()->harga)->toBe(1_100_000)
        ->and($b->fresh()->harga)->toBe(1_450_000);
});

test('memasang vendor menolak daftar harga kosong atau id yang tidak ada', function () {
    $v = vendorUji();

    $this->postJson("/api/v1/vendor/{$v->id}/pasang", ['harga' => []], kepalaRab())
        ->assertStatus(422)->assertJsonValidationErrors('harga');

    $this->postJson("/api/v1/vendor/{$v->id}/pasang", ['harga' => [99999]], kepalaRab())
        ->assertStatus(422)->assertJsonValidationErrors('harga.0');
});

test('nomor dicari walau formatnya berbeda', function () {
    /*
     | Satu nomor ditulis bermacam-macam: "+62 895-6302-79695",
     | "0895630279695", "0895 6302 79695". Dicocokkan mentah-mentah, mencari
     | nomor yang PERSIS ada di data pun tidak ketemu — dan yang terbaca
     | admin adalah "vendornya belum didaftarkan", lalu ia mendaftarkannya
     | lagi.
     */
    vendorUji(['nama' => 'PO Nol Depan', 'kontak' => '0895630279695']);
    vendorUji(['nama' => 'PO Plus Enam Dua', 'kontak' => '+62 812-3456-7890']);

    $cari = fn ($kata) => collect(
        $this->getJson('/api/v1/vendor?cari='.urlencode($kata), kepalaRab())->assertOk()->json('data')
    )->pluck('nama')->all();

    // Dicari berformat internasional, tersimpan berawalan nol.
    expect($cari('+62 895-6302-79695'))->toBe(['PO Nol Depan'])
        ->and($cari('62895630279695'))->toBe(['PO Nol Depan'])
        // Dan sebaliknya: dicari berawalan nol, tersimpan internasional.
        ->and($cari('081234567890'))->toBe(['PO Plus Enam Dua'])
        ->and($cari('0812 3456 7890'))->toBe(['PO Plus Enam Dua'])
        // Potongan tengah nomor tetap ketemu.
        ->and($cari('6302-79695'))->toBe(['PO Nol Depan'])
        // Nama tetap jalan seperti biasa.
        ->and($cari('plus enam'))->toBe(['PO Plus Enam Dua']);
});

test('angka pendek tidak diperlakukan sebagai nomor', function () {
    /*
     | "30" dari "DP 30%" akan mencocoki hampir semua nomor telepon, dan
     | hasil carinya jadi seluruh tabel — yang terbaca seperti pencarian
     | yang tidak bekerja.
     */
    vendorUji(['nama' => 'PO Berpagar', 'kontak' => '+62 812-3456-7890']);

    /*
     | "12345" ada di dalam nomor itu, tetapi hanya SESUDAH tanda hubungnya
     | dibuang. Di bawah tujuh digit pembersihan itu sengaja tidak
     | dijalankan — kalau dijalankan, "30" dari "DP 30%" akan mencocoki
     | hampir semua nomor dan hasil carinya jadi seluruh tabel.
     */
    expect($this->getJson('/api/v1/vendor?cari=12345', kepalaRab())->assertOk()->json('data'))->toBe([]);

    // Tujuh digit ke atas baru dicocokkan tanpa memedulikan formatnya.
    expect(collect($this->getJson('/api/v1/vendor?cari=1234567890', kepalaRab())->json('data'))
        ->pluck('nama')->all())->toBe(['PO Berpagar']);
});

test('PDF vendor terbentuk dan mengikuti saringannya', function () {
    vendorUji(['nama' => 'PO Armada Jaya', 'jenis' => 'Armada']);
    vendorUji(['nama' => 'Hotel Mawar', 'jenis' => 'Akomodasi']);

    $balasan = $this->get('/api/v1/vendor/pdf?jenis=Armada', kepalaRab())->assertOk();

    expect($balasan->getContent())->toStartWith('%PDF');

    /*
     | Isinya diperiksa di markahnya, bukan di PDF jadi: dompdf memampatkan
     | teksnya sehingga mencari kata di sana selalu "tidak ketemu".
     */
    $markah = view('pdf.vendor', [
        'baris' => [[
            'id' => 1, 'nama' => 'PO Armada Jaya', 'kontak' => '081234000111', 'jenis' => 'Armada',
            'provinsi' => null, 'catatan' => null, 'aktif' => true, 'jumlah_harga' => 2,
        ]],
        'saringan' => ['Jenis:' => 'Armada'],
        'terbit' => '27 Oktober 2026, 09:00',
        'kontak' => ['whatsapp' => '0812', 'instagram' => '@orcha_journey', 'alamat' => 'Jalan Durmo, Sleman'],
        'logo' => null,
    ])->render();

    expect($markah)->toContain('PO Armada Jaya')
        // Kop & kaki seperti berkas itinerary.
        ->and($markah)->toContain('ORCHA <span>JOURNEY</span>')
        ->and($markah)->toContain('Kantor Orcha Journey')
        ->and($markah)->toContain('Jalan Durmo, Sleman')
        ->and($markah)->toContain('orchajourney.com')
        ->and($markah)->toContain('counter(page)')
        // Saringannya ikut tercetak: berkas sebagian yang tidak menyebutkan
        // sebagiannya apa terbaca sebagai daftar lengkap yang ternyata bukan.
        ->and($markah)->toContain('Jenis:')
        ->and($markah)->toContain('JANGAN DIBAGIKAN — BERISI NOMOR MITRA');
});

test('PDF vendor tanpa hasil tetap terbentuk dan mengatakannya', function () {
    // Berkas kosong tanpa penjelasan terbaca seperti berkas yang gagal.
    $markah = view('pdf.vendor', [
        'baris' => [], 'saringan' => ['Kata kunci:' => 'tidak ada'],
        'terbit' => '27 Oktober 2026, 09:00',
        'kontak' => ['whatsapp' => null, 'instagram' => null, 'alamat' => 'Sleman'],
        'logo' => null,
    ])->render();

    expect($markah)->toContain('Tidak ada vendor yang cocok dengan saringannya.');
});

test('PDF vendor dikelompokkan per jenis dalam SATU tabel', function () {
    /*
     | Enam tabel bertumpuk berarti kepala kolom terulang enam kali — "VENDOR
     | WILAYAH KONTAK HARGA" yang sama dibaca berulang-ulang, dan yang
     | membedakan kelompoknya justru tenggelam di antaranya.
     */
    $markah = view('pdf.vendor', [
        'baris' => [
            ['id' => 1, 'nama' => 'PO Sumber Jaya', 'kontak' => '0812', 'jenis' => 'Armada',
                'provinsi' => null, 'catatan' => null, 'aktif' => true, 'jumlah_harga' => 3],
            ['id' => 2, 'nama' => 'Hotel Mawar', 'kontak' => '0274', 'jenis' => 'Akomodasi',
                'provinsi' => null, 'catatan' => null, 'aktif' => true, 'jumlah_harga' => 1],
            ['id' => 3, 'nama' => 'Jip Lama', 'kontak' => null, 'jenis' => null,
                'provinsi' => null, 'catatan' => null, 'aktif' => false, 'jumlah_harga' => 0],
        ],
        'saringan' => [],
        'terbit' => '27 Oktober 2026, 09:00',
        'kontak' => ['whatsapp' => null, 'instagram' => null, 'alamat' => 'Sleman'],
        'logo' => null,
    ])->render();

    // Satu kepala kolom saja, berapa pun kelompoknya.
    expect(substr_count($markah, '<thead>'))->toBe(1)
        ->and(substr_count($markah, 'class="pita"'))->toBe(3)
        // Yang belum berjenis di AKHIR: ia bukan kategori, melainkan yang
        // belum sempat dikategorikan.
        ->and(strpos($markah, 'Belum berjenis'))->toBeGreaterThan(strpos($markah, 'Armada'));

    /*
     | Hanya yang MENYIMPANG yang ditandai. Kolom status berisi "AKTIF" di
     | hampir setiap baris adalah satu kolom penuh yang tidak memberi tahu
     | apa pun, dan yang satu-dua tidak aktif tenggelam di antaranya.
     */
    // Diperiksa lewat jumlah kolomnya, bukan lewat ada-tidaknya sebuah kata:
    // komentar CSS ikut terkirim ke markahnya, dan kata yang dipakai
    // menjelaskan aturan akan mencocoki pemeriksaan tentang aturan itu.
    expect(substr_count($markah, '</th>'))->toBe(4)
        ->and(substr_count($markah, 'class="pil-mati"'))->toBe(1);

    // Empat ubin ringkasan, sebentuk dengan berkas itinerary.
    expect(substr_count($markah, 'class="ubin-isi"'))->toBe(4)
        // Labelnya memakai bahasa sehari-hari, bukan istilah basis data.
        ->and($markah)->toContain('Belum ada nomornya')
        ->and($markah)->toContain('Nama vendor')
        ->and($markah)->toContain('Nomor yang dihubungi')
        // Keterangan cara membacanya, satu baris per hal.
        ->and($markah)->toContain('Cara membaca daftar ini');
});

test('master harga menyebut berapa RAB memakainya', function () {
    /*
     | Pertanyaan yang selalu ada di kepala sebelum mengubah angka —
     | "menaikkan harga ini menyentuh berapa penawaran" — dan selama ini
     | dijawab dengan menebak.
     */
    $m = masterUji();
    $lain = masterUji(['nama' => 'Tiket Borobudur', 'destinasi' => 'Candi Borobudur']);

    $rab = buatRabLewatApi($this);
    $this->postJson("/api/v1/rab/{$rab['id']}/biaya", ['master_harga_id' => $m->id], kepalaRab())->assertOk();

    $data = collect($this->getJson('/api/v1/master-harga', kepalaRab())->assertOk()->json('data'));

    expect($data->firstWhere('id', $m->id)['jumlah_rab'])->toBe(1)
        ->and($data->firstWhere('id', $lain->id)['jumlah_rab'])->toBe(0)
        // Kapan terakhir diubah, dalam kalimat yang bisa dibaca langsung.
        ->and($data->firstWhere('id', $m->id)['diubah_teks'])->toBeString();
});

test('harga yang belum pernah dipakai bisa disaring', function () {
    // Daftar baris yang boleh dirapikan tanpa menyentuh penawaran mana pun.
    $m = masterUji();
    masterUji(['nama' => 'Tiket Borobudur', 'destinasi' => 'Candi Borobudur']);

    $rab = buatRabLewatApi($this);
    $this->postJson("/api/v1/rab/{$rab['id']}/biaya", ['master_harga_id' => $m->id], kepalaRab())->assertOk();

    $belum = $this->getJson('/api/v1/master-harga?dipakai=belum', kepalaRab())->assertOk()->json('data');
    $sudah = $this->getJson('/api/v1/master-harga?dipakai=ya', kepalaRab())->assertOk()->json('data');

    expect(collect($belum)->pluck('nama')->all())->toBe(['Tiket Borobudur'])
        ->and(collect($sudah)->pluck('nama')->all())->toBe(['Tiket Candi Prambanan']);
});

test('harga kembar ditahan sebelum modalnya terhitung dua kali', function () {
    /*
     | Dua baris dengan destinasi yang sama di provinsi yang sama sama-sama
     | ikut tertarik saat destinasinya dipilih di itinerary — dan modalnya
     | terhitung dua kali tanpa satu pun tanda.
     */
    masterUji();   // Tiket Candi Prambanan, destinasi Candi Prambanan, DI Yogyakarta

    $kembar = [
        'kategori' => 'tiket', 'nama' => 'Tiket Prambanan (turis asing)',
        'provinsi' => 'DI Yogyakarta', 'destinasi' => 'Candi Prambanan',
        'satuan' => 'orang', 'harga' => 375_000,
    ];

    $this->postJson('/api/v1/master-harga', $kembar, kepalaRab())
        ->assertStatus(422)
        ->assertJsonPath('kembaran.nama', 'Tiket Candi Prambanan');

    expect(MasterHarga::count())->toBe(1);

    // Dua tiket Prambanan untuk domestik dan turis asing memang sah —
    // tetapi dipaksa dengan sadar.
    $this->postJson('/api/v1/master-harga?paksa=1', $kembar, kepalaRab())->assertCreated();

    expect(MasterHarga::count())->toBe(2);
});

test('nama harga kembar di wilayah yang sama juga ditahan', function () {
    // Dua baris bernama persis sama tidak bisa dibedakan admin mana pun
    // saat memilihnya di RAB.
    masterUji(['destinasi' => null]);

    $this->postJson('/api/v1/master-harga', [
        'kategori' => 'tiket', 'nama' => 'TIKET CANDI PRAMBANAN  ',
        'provinsi' => 'DI Yogyakarta', 'satuan' => 'orang', 'harga' => 60_000,
    ], kepalaRab())->assertStatus(422);

    // Provinsi berbeda bukan kembaran: harga tiket memang berbeda per daerah.
    $this->postJson('/api/v1/master-harga', [
        'kategori' => 'tiket', 'nama' => 'Tiket Candi Prambanan',
        'provinsi' => 'Jawa Tengah', 'satuan' => 'orang', 'harga' => 60_000,
    ], kepalaRab())->assertCreated();
});

test('menyunting harga tidak dianggap kembaran dirinya sendiri', function () {
    $m = masterUji();

    $this->patchJson('/api/v1/master-harga/'.$m->id, [
        'kategori' => 'tiket', 'nama' => 'Tiket Candi Prambanan',
        'provinsi' => 'DI Yogyakarta', 'destinasi' => 'Candi Prambanan',
        'satuan' => 'orang', 'harga' => 65_000,
    ], kepalaRab())->assertOk();

    expect($m->fresh()->harga)->toBe(65_000);
});

test('PDF master harga terbentuk dan dikelompokkan per kategori', function () {
    masterUji();
    masterUji(['nama' => 'Sewa Hiace', 'kategori' => 'transportasi', 'destinasi' => null,
        'satuan' => 'unit_hari', 'harga' => 1_100_000, 'kapasitas' => 14]);

    $balasan = $this->get('/api/v1/master-harga/pdf', kepalaRab())->assertOk();

    expect($balasan->getContent())->toStartWith('%PDF');

    /*
     | Isinya diperiksa di markahnya, bukan di PDF jadi: dompdf memampatkan
     | teksnya sehingga mencari kata di sana selalu "tidak ketemu".
     */
    $markah = view('pdf.master-harga', [
        'baris' => [
            ['id' => 1, 'nama' => 'Tiket Candi Prambanan', 'kategori_label' => 'Tiket & Retribusi',
                'destinasi' => 'Candi Prambanan', 'provinsi' => 'DI Yogyakarta', 'catatan' => null,
                'satuan_label' => 'per orang', 'kapasitas' => null, 'harga' => 50000,
                'harga_teks' => 'Rp 50.000', 'vendor' => null, 'aktif' => true, 'jumlah_rab' => 2],
            ['id' => 2, 'nama' => 'Sewa Hiace', 'kategori_label' => 'Transportasi',
                'destinasi' => null, 'provinsi' => null, 'catatan' => null,
                'satuan_label' => 'per unit per hari', 'kapasitas' => 14, 'harga' => 1100000,
                'harga_teks' => 'Rp 1.100.000', 'vendor' => 'PO Sumber Jaya', 'aktif' => false, 'jumlah_rab' => 0],
        ],
        'saringan' => [],
        'terbit' => '27 Oktober 2026, 09:00',
        'kontak' => ['whatsapp' => null, 'instagram' => null, 'alamat' => 'Sleman'],
        'logo' => null,
    ])->render();

    // Kerangkanya sama dengan PDF vendor — satu tata letak, bukan dua
    // berkas yang kopnya sedikit berbeda.
    expect($markah)->toContain('ORCHA <span>JOURNEY</span>')
        ->and($markah)->toContain('Kantor Orcha Journey')
        ->and($markah)->toContain('counter(page)')
        // Isinya khas master harga.
        ->and($markah)->toContain('JANGAN DIBERIKAN KE PELANGGAN — INI HARGA BELI')
        ->and($markah)->toContain('Tiket untuk Candi Prambanan')
        ->and($markah)->toContain('Semua provinsi')
        ->and($markah)->toContain('Belum punya vendor')
        ->and(substr_count($markah, 'class="pita"'))->toBe(2)
        ->and(substr_count($markah, 'class="pil-mati"'))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Master harga: menemukan, mengurutkan, dan mengubah banyak sekaligus
|--------------------------------------------------------------------------
*/

test('harga yang dimatikan bisa dicari, tanpa disembunyikan diam-diam', function () {
    /*
     | Layar tidak punya kolom status, jadi tanpa saringan ini harga mati
     | hanya bisa ditemukan dengan memelototi daftar.
     |
     | Tetapi bawaannya tetap memuat semuanya: menyembunyikan yang mati
     | secara diam-diam menukar satu keluhan dengan keluhan yang lebih
     | buruk — "harga saya hilang".
     */
    masterUji(['nama' => 'Tiket hidup']);
    masterUji(['nama' => 'Tiket mati', 'aktif' => false]);

    $semua = $this->getJson('/api/v1/master-harga', kepalaRab())->json('data');
    expect(collect($semua)->pluck('nama')->sort()->values()->all())->toBe(['Tiket hidup', 'Tiket mati']);

    $mati = $this->getJson('/api/v1/master-harga?status=mati', kepalaRab())->json('data');
    expect(collect($mati)->pluck('nama')->all())->toBe(['Tiket mati']);

    $aktif = $this->getJson('/api/v1/master-harga?status=aktif', kepalaRab())->json('data');
    expect(collect($aktif)->pluck('nama')->all())->toBe(['Tiket hidup']);
});

test('harga yang masa berlakunya lewat bisa disaring', function () {
    /*
     | Kosong berarti "tidak ada batas", bukan "sudah lewat" — kalau tidak,
     | hampir seluruh daftar tertandai dan tandanya berhenti berarti.
     */
    masterUji(['nama' => 'Tanpa batas']);
    masterUji(['nama' => 'Masih berlaku', 'berlaku_sampai' => now()->addMonth()->toDateString()]);
    masterUji(['nama' => 'Sudah lewat', 'berlaku_sampai' => now()->subDay()->toDateString()]);

    $lewat = $this->getJson('/api/v1/master-harga?status=kedaluwarsa', kepalaRab())->json('data');

    expect(collect($lewat)->pluck('nama')->all())->toBe(['Sudah lewat'])
        ->and($lewat[0]['kedaluwarsa'])->toBeTrue();
});

test('daftar bisa diurutkan menurut pertanyaan yang sedang ditanya', function () {
    masterUji(['nama' => 'Murah', 'harga' => 10_000]);
    masterUji(['nama' => 'Mahal', 'harga' => 900_000]);
    masterUji(['nama' => 'Sedang', 'harga' => 100_000]);

    $termahal = $this->getJson('/api/v1/master-harga?urut=termahal', kepalaRab())->json('data');
    $termurah = $this->getJson('/api/v1/master-harga?urut=termurah', kepalaRab())->json('data');

    expect(collect($termahal)->pluck('nama')->first())->toBe('Mahal')
        ->and(collect($termurah)->pluck('nama')->first())->toBe('Murah');
});

test('ringkasan dihitung dari seluruh tabel, bukan halaman yang terlihat', function () {
    /*
     | "Berapa yang belum punya vendor" yang hanya menghitung sepuluh baris
     | pertama menjawab pertanyaan yang tidak pernah ditanyakan.
     */
    foreach (range(1, 12) as $i) {
        masterUji(['nama' => "Harga {$i}"]);
    }
    masterUji(['nama' => 'Mati', 'aktif' => false]);
    masterUji(['nama' => 'Lewat', 'berlaku_sampai' => now()->subWeek()->toDateString()]);

    $balasan = $this->getJson('/api/v1/master-harga?per_halaman=10', kepalaRab());

    expect(count($balasan->json('data')))->toBe(10)
        ->and($balasan->json('meta.ringkas.jumlah'))->toBe(14)
        ->and($balasan->json('meta.ringkas.tanpa_vendor'))->toBe(14)
        ->and($balasan->json('meta.ringkas.mati'))->toBe(1)
        ->and($balasan->json('meta.ringkas.kedaluwarsa'))->toBe(1);
});

test('kenaikan massal membulatkan ke ratusan dan tercatat di riwayat', function () {
    /*
     | Kenaikan vendor datang serentak. Yang diperiksa di sini bukan
     | perkaliannya saja, tetapi bahwa hasilnya angka yang benar-benar
     | ditawarkan orang — bukan "Rp 60.500,25".
     */
    $a = masterUji(['nama' => 'Hiace', 'harga' => 1_100_000]);
    $b = masterUji(['nama' => 'Elf', 'harga' => 777_777]);

    $balasan = $this->postJson('/api/v1/master-harga/massal', [
        'id' => [$a->id, $b->id], 'aksi' => 'naik', 'persen' => 10,
    ], kepalaRab())->assertOk();

    expect($balasan->json('jumlah'))->toBe(2)
        ->and($a->fresh()->harga)->toBe(1_210_000)
        // 777.777 × 1,1 = 855.554,7 → dibulatkan ke ratusan terdekat.
        ->and($b->fresh()->harga)->toBe(855_600)
        ->and($a->riwayat()->count())->toBe(1)
        ->and($a->riwayat()->first()->harga_lama)->toBe(1_100_000);
});

test('persen yang kelewatan ditolak sebelum menyentuh satu baris pun', function () {
    /*
     | Satu angka nol kelebihan saat mengetik tidak boleh menghapus harga
     | seisi kategori.
     */
    $m = masterUji(['harga' => 100_000]);

    $this->postJson('/api/v1/master-harga/massal', [
        'id' => [$m->id], 'aksi' => 'turun', 'persen' => 100,
    ], kepalaRab())->assertStatus(422);

    expect($m->fresh()->harga)->toBe(100_000);
});

test('mematikan massal tidak menyentuh RAB yang sudah memakainya', function () {
    $m = masterUji();
    $rab = buatRabLewatApi($this);

    $this->postJson("/api/v1/rab/{$rab['id']}/biaya", [
        'master_harga_id' => $m->id, 'jumlah' => 2,
    ], kepalaRab());

    $sebelum = RabBiaya::where('rab_id', $rab['id'])->get()->toArray();

    $this->postJson('/api/v1/master-harga/massal', [
        'id' => [$m->id], 'aksi' => 'matikan',
    ], kepalaRab())->assertOk();

    expect($m->fresh()->aktif)->toBeFalse()
        ->and(RabBiaya::where('rab_id', $rab['id'])->get()->toArray())->toBe($sebelum);
});

test('riwayat hanya mencatat saat angkanya yang berubah', function () {
    /*
     | Riwayat yang penuh baris "harga tetap" tidak bisa dibaca, dan yang
     | dicari orang saat membukanya selalu uangnya.
     */
    $m = masterUji(['harga' => 50_000]);

    $kirim = fn (array $ubah) => $this->patchJson('/api/v1/master-harga/'.$m->id, array_merge([
        'kategori' => 'tiket', 'nama' => 'Tiket Candi Prambanan',
        'provinsi' => 'DI Yogyakarta', 'destinasi' => 'Candi Prambanan',
        'satuan' => 'orang', 'harga' => 50_000,
    ], $ubah), kepalaRab());

    $kirim(['catatan' => 'ganti catatan saja'])->assertOk();
    expect($m->riwayat()->count())->toBe(0);

    $kirim(['harga' => 65_000])->assertOk();

    $riwayat = $this->getJson("/api/v1/master-harga/{$m->id}/riwayat", kepalaRab())->assertOk();

    expect($riwayat->json('data.0.harga_lama'))->toBe('Rp 50.000')
        ->and($riwayat->json('data.0.harga_baru'))->toBe('Rp 65.000')
        ->and($riwayat->json('data.0.naik'))->toBeTrue()
        ->and($riwayat->json('data.0.persen'))->toEqual(30.0);
});
