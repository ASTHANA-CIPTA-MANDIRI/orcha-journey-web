<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Livewire\Volt\Volt;

function buatMobil(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'Avanza Uji',
        'brand' => 'Toyota',
        'type' => 'mobil',
        'price_per_day' => 350000,
        'harga_per_jam' => 55000,
        'harga_12_jam' => 280000,
        'harga_sopir' => 150000,
        'transmission' => 'Manual',
        'transmisi_tersedia' => ['Manual', 'Matic'],
        'capacity' => 7,
        'is_available' => true,
    ], $ubah));
}

/* ---------------------------- TARIF ---------------------------- */

test('tarif per jam, 12 jam, dan per hari disimpan terpisah', function () {
    $mobil = buatMobil();

    expect($mobil->tarif('jam'))->toBe(55000)
        ->and($mobil->tarif('12jam'))->toBe(280000)
        ->and($mobil->tarif('hari'))->toBe(350000);
});

test('estimasi biaya menghitung durasi dan biaya sopir', function () {
    $mobil = buatMobil();

    // 6 jam × 55.000 + sopir 1 hari 150.000
    expect($mobil->estimasiBiaya('jam', 6, true))->toBe(480000)
        // 3 hari × 350.000 + sopir 3 hari
        ->and($mobil->estimasiBiaya('hari', 3, true))->toBe(1500000)
        // tanpa sopir
        ->and($mobil->estimasiBiaya('hari', 2, false))->toBe(700000);
});

test('satuan yang tidak dijual mengembalikan null', function () {
    $bus = buatMobil(['name' => 'Big Bus Uji', 'type' => 'bus', 'harga_per_jam' => null, 'transmisi_tersedia' => ['Manual']]);

    expect($bus->tarif('jam'))->toBeNull()
        ->and($bus->estimasiBiaya('jam', 5))->toBeNull();
});

/* ------------------------- TAMPILAN DAFTAR ------------------------- */

test('kartu daftar menampilkan semua transmisi yang tersedia dan tarifnya', function () {
    buatMobil();

    $this->get(route('sewa-kendaraan'))
        ->assertOk()
        ->assertSee('Manual &amp; Matic', false)   // bukan cuma "Manual"
        ->assertSee('Per jam')
        ->assertSee('Rp 55.000')
        ->assertSee('Per hari (24 jam)')
        ->assertSee('Rp 350.000');
});

test('unit satu transmisi hanya menampilkan transmisi itu', function () {
    buatMobil(['name' => 'HiAce Uji', 'type' => 'hiace', 'transmisi_tersedia' => ['Manual']]);

    $this->get(route('sewa-kendaraan', 'hiace'))
        ->assertOk()
        ->assertSee('HiAce Uji')
        ->assertDontSee('Manual &amp; Matic', false);
});

/* ------------------------ FORMULIR PEMESANAN ------------------------ */

test('formulir sewa bisa dibuka dan memakai uuid unit', function () {
    $mobil = buatMobil();

    $this->get(route('sewa-kendaraan.pesan'))->assertOk();

    $this->get(route('sewa-kendaraan.pesan', ['unit' => $mobil->uuid]))
        ->assertOk()
        ->assertSee('Avanza Uji');

    expect($mobil->uuid)->toHaveLength(36);
});

test('pemesanan sewa tersimpan lengkap dengan estimasi biaya', function () {
    $mobil = buatMobil();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'jam')
        ->set('durasi', 6)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('jamMulai', '07:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::firstOrFail();

    expect($sewa->kode)->toStartWith('SK-')
        ->and($sewa->nama_kendaraan)->toBe('Avanza Uji')
        ->and($sewa->transmisi)->toBe('Matic')
        ->and($sewa->satuan)->toBe('jam')
        ->and($sewa->durasi)->toBe(6)
        ->and($sewa->dengan_sopir)->toBeTrue()
        ->and($sewa->estimasi_biaya)->toBe(480000)
        ->and($sewa->status)->toBe('baru');
});

test('pemesanan menolak transmisi yang tidak tersedia pada unit', function () {
    $mobil = buatMobil(['transmisi_tersedia' => ['Manual']]);

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'hari')
        ->set('durasi', 1)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasErrors(['transmisi']);

    expect(PenyewaanKendaraan::count())->toBe(0);
});

test('pemesanan menolak satuan yang tidak dijual unit itu', function () {
    $bus = buatMobil(['name' => 'Bus Uji', 'type' => 'bus', 'harga_per_jam' => null, 'transmisi_tersedia' => ['Manual']]);

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $bus->uuid)
        ->set('transmisi', 'Manual')
        ->set('satuan', 'jam')
        ->set('durasi', 5)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasErrors(['satuan']);

    expect(PenyewaanKendaraan::count())->toBe(0);
});

test('pemesanan menolak isian yang tidak lengkap', function () {
    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('nama', 'A')
        ->set('tanggalMulai', now()->subWeek()->toDateString())
        ->call('pesan')
        ->assertHasErrors(['unit', 'transmisi', 'tanggalMulai', 'nama', 'whatsapp', 'setuju']);
});

/* --------------------- PENATAAN BERKAS PER FITUR --------------------- */

test('berkas blade dikelompokkan per fitur', function () {
    $wajibAda = [
        'resources/views/livewire/public/sewa-kendaraan/index.blade.php',
        'resources/views/livewire/public/sewa-kendaraan/pemesanan.blade.php',
        'resources/views/livewire/public/paket-wisata/index.blade.php',
        'resources/views/livewire/public/paket-wisata/detail.blade.php',
        'resources/views/livewire/public/open-trip/pendaftaran.blade.php',
        'resources/views/livewire/public/informasi/faq.blade.php',
        'resources/views/livewire/admin/sewa-kendaraan/index.blade.php',
        'resources/views/livewire/admin/penyewaan/index.blade.php',
        'resources/views/components/sewa-kendaraan/kartu.blade.php',
        'resources/views/components/paket-wisata/kartu.blade.php',
    ];

    foreach ($wajibAda as $berkas) {
        expect(file_exists(base_path($berkas)))->toBeTrue("$berkas tidak ditemukan");
    }

    // Tidak ada lagi berkas publik yang menggantung di luar folder fitur
    $menggantung = glob(base_path('resources/views/livewire/public/*.blade.php'));
    expect($menggantung)->toBeEmpty();
});

/* ---------------- PENGEMBALIAN, DENDA & PEMERIKSAAN FISIK ---------------- */

test('tenggat pengembalian dihitung dan disimpan saat memesan', function () {
    $mobil = buatMobil();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'jam')
        ->set('durasi', 6)
        ->set('tanggalMulai', '2026-09-10')
        ->set('jamMulai', '07:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::firstOrFail();

    // Sewa 6 jam mulai 07.00 → kembali 13.00 di hari yang sama
    expect($sewa->tanggal_selesai->toDateString())->toBe('2026-09-10')
        ->and(substr((string) $sewa->jam_selesai, 0, 5))->toBe('13:00')
        ->and($sewa->lokasi_kembali)->toBe('Kantor Orcha')
        ->and($sewa->email)->toBe('budi@contoh.test');
});

test('email dan kedua lokasi wajib diisi', function () {
    $mobil = buatMobil();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'hari')
        ->set('durasi', 1)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('jamMulai', '07:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('setuju', true)
        ->call('pesan')
        // Pada sewa bersopir yang wajib adalah tujuannya, bukan lokasi
        // pengembalian — unitnya tidak diserahkan ke penyewa.
        ->assertHasErrors(['email', 'lokasiAntar', 'tujuan']);
});

test('durasi harian menghasilkan tenggat pada jam yang sama', function () {
    $selesai = PenyewaanKendaraan::hitungSelesai('2026-09-10', '08:00', 'hari', 3);

    expect($selesai->format('Y-m-d H:i'))->toBe('2026-09-13 08:00');
});

test('terlambat dalam tenggang tidak didenda', function () {
    $mobil = buatMobil(['price_per_day' => 500000]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'dikembalikan_pada' => '2026-09-11 08:20',   // telat 20 menit
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    // Macet di jalan bukan hal yang pantas didendakan
    expect($sewa->terlambat_menit)->toBe(20)
        ->and($sewa->terlambat)->toBeFalse()
        ->and($sewa->denda_keterlambatan_usulan)->toBe(0);
});

test('denda keterlambatan dihitung per jam dari tarif harian', function () {
    $mobil = buatMobil(['price_per_day' => 500000]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'dikembalikan_pada' => '2026-09-11 11:00',   // telat 3 jam
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    // Lewat tenggang 30 menit → 2,5 jam dibulatkan 3 jam × 10% × 500.000
    expect($sewa->denda_keterlambatan_usulan)->toBe(150000);
});

test('denda keterlambatan dibatasi tarif sehari per hari telat', function () {
    $mobil = buatMobil(['price_per_day' => 500000]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'dikembalikan_pada' => '2026-09-12 08:00',   // telat sehari penuh
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    // Tanpa batas, 24 jam × 10% = 240% tarif harian untuk telat sehari
    expect($sewa->denda_keterlambatan_usulan)->toBe(500000);
});

test('hanya kerusakan baru yang ditagihkan ke penyewa', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
        // Bodi kanan SUDAH lecet sebelum unit diserahkan
        'kondisi_awal' => ['bodi_kanan' => 'lecet', 'kaca' => 'baik', 'ban' => 'baik'],
        'kondisi_akhir' => ['bodi_kanan' => 'lecet', 'kaca' => 'rusak', 'ban' => 'baik'],
    ]);

    $baru = $sewa->kerusakan_baru;

    // Lecet lama tidak ikut terhitung; hanya kaca yang memburuk
    expect($baru)->toHaveCount(1)
        ->and($baru[0]['bagian'])->toBe('Kaca & spion')
        ->and($baru[0]['dari'])->toBe('Baik')
        ->and($baru[0]['jadi'])->toBe('Rusak');
});

test('total tagihan menjumlahkan sewa dengan seluruh denda', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'selesai',
        'denda_keterlambatan' => 150000, 'denda_kerusakan' => 300000, 'denda_lain' => 50000,
    ]);

    expect($sewa->total_denda)->toBe(500000)
        ->and($sewa->total_tagihan)->toBe(1000000);
});

/* ------------- SURAT PEMESANAN & OTOMATISASI SERAH TERIMA ------------- */

test('pemesanan sewa mengirim surat ke kantor dan penyewa', function () {
    Illuminate\Support\Facades\Mail::fake();
    config()->set('orcha.email_pemberitahuan', 'halo@orchajourney.com');

    $mobil = buatMobil();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'hari')
        ->set('durasi', 2)
        ->set('tanggalMulai', '2026-09-10')
        ->set('jamMulai', '08:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    Illuminate\Support\Facades\Mail::assertSent(App\Mail\PemberitahuanFormulir::class,
        fn ($surat) => $surat->hasTo('halo@orchajourney.com') && $surat->untukPelanggan === false);

    Illuminate\Support\Facades\Mail::assertSent(App\Mail\PemberitahuanFormulir::class, function ($surat) {
        if (! $surat->untukPelanggan) {
            return false;
        }

        $isi = $surat->render();

        return $surat->hasTo('budi@contoh.test')
            // Jam pengembalian ikut disebut: itu yang dipakai menagih denda
            && str_contains($isi, 'Ditunggu kembali')
            && str_contains($isi, 'Kantor Orcha')
            // Berkas rinciannya ikut terlampir
            && count($surat->berkasPdf) === 1
            && str_starts_with(reset($surat->berkasPdf), '%PDF-');
    });
});

test('memilih usulan menuliskan nama berikut alamatnya ke isian', function () {
    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        ['lat' => '-7.87', 'lon' => '110.40', 'name' => 'SMAN 1 Pleret',
            'display_name' => 'SMAN 1 Pleret, Jalan Nyi Truntum, Pleret, Bantul'],
    ])]);

    $mobil = buatMobil();

    // Daftar usulan menampilkan dua baris — nama tempat dan alamatnya — lalu
    // baris kedua hilang begitu dipilih, padahal justru itu yang membedakannya
    // dari sekolah bernama sama di kabupaten lain. Yang tersimpan tinggal
    // namanya, dan itulah satu-satunya yang dibaca sopir saat menjemput.
    $k = Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)->set('denganSopir', 'ya')
        ->call('cariTitik', 'jemput', 'SMAN 1 Pleret')
        ->call('pilihTitik', 'jemput', 0);

    expect($k->get('lokasiAntar'))->toBe('SMAN 1 Pleret — Jalan Nyi Truntum, Pleret, Bantul');

    // Titiknya juga tergambar di peta, jadi yang terbaca sama dengan yang terlihat
    expect($k->get('peta')['jemput']['lat'])->toBe(-7.87);
});

test('usulan tanpa alamat tambahan tidak menuliskan namanya dua kali', function () {
    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        ['lat' => '-7.79', 'lon' => '110.36', 'display_name' => 'Borobudur'],
    ])]);

    $mobil = buatMobil();

    $k = Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)->set('denganSopir', 'ya')
        ->call('cariTitik', 'tujuan', 'Borobudur')
        ->call('pilihTitik', 'tujuan', 0);

    expect($k->get('tujuan'))->toBe('Borobudur');
});

test('alamat yang kepanjangan dipotong agar tetap lolos pemeriksaan isian', function () {
    $panjang = 'Jalan '.str_repeat('Purwokerto Selatan ', 20);

    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        ['lat' => '-7.42', 'lon' => '109.23', 'display_name' => 'Terminal Bulupitu, '.$panjang.', Banyumas'],
    ])]);

    $mobil = buatMobil();

    // Isiannya dibatasi 191 huruf oleh kolom dan aturan pemeriksaannya. Tanpa
    // pemotongan, memilih usulan justru membuat formulirnya gagal dikirim —
    // ditolak karena tulisan yang ditaruh sistemnya sendiri.
    $k = Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)->set('denganSopir', 'ya')
        ->call('cariTitik', 'jemput', 'Terminal Bulupitu')
        ->call('pilihTitik', 'jemput', 0);

    expect(mb_strlen($k->get('lokasiAntar')))->toBeLessThanOrEqual(191)
        // Yang dipotong ekor alamatnya, bukan nama tempatnya
        ->and($k->get('lokasiAntar'))->toStartWith('Terminal Bulupitu — ');

    $k->set('transmisi', 'Matic')->set('satuan', 'hari')->set('durasi', 2)
        ->set('tanggalMulai', '2026-09-10')->set('jamMulai', '08:00')
        ->set('nama', 'Budi Santoso')->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('tujuan', 'Borobudur')->set('setuju', true)
        ->call('pesan')->assertHasNoErrors();
});

test('pesanan baru menyimpan perincian estimasi, bukan cuma totalnya', function () {
    $mobil = buatMobil(['harga_sopir' => 150000]);

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)->set('transmisi', 'Matic')
        ->set('satuan', 'hari')->set('durasi', 2)
        ->set('tanggalMulai', '2026-09-10')->set('jamMulai', '08:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')->set('setuju', true)
        ->call('pesan')->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::latest('id')->first();

    // Disalin saat pesanan dibuat, bukan dihitung ulang belakangan: tarif unit
    // bisa berubah kapan saja, dan perincian yang jumlahnya tidak lagi sama
    // dengan total yang dipesan lebih membingungkan daripada tanpa perincian.
    expect($sewa->rincian_estimasi)->toHaveCount(2)
        ->and($sewa->rincian_estimasi[0]['label'])->toBe('Tarif sewa')
        ->and($sewa->rincian_estimasi[1]['label'])->toBe('Sopir')
        ->and(array_sum(array_column($sewa->rincian_estimasi, 'jumlah')))
        ->toBe((int) $sewa->estimasi_biaya);

    $mobil->update(['price_per_day' => 999000]);

    // Tarifnya sudah naik; notanya tetap menceritakan harga yang dipesan
    expect(App\Support\NotaSewa::untuk($sewa->fresh())['baris'][0]['nilai'])
        ->toBe('Rp 700.000');
});

test('berkas pesanan baru memuat perincian biaya, bukan satu angka saja', function () {
    Illuminate\Support\Facades\Mail::fake();
    config()->set('orcha.email_pemberitahuan', 'halo@orchajourney.com');

    $mobil = buatMobil(['harga_sopir' => 150000]);

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)->set('transmisi', 'Matic')
        ->set('satuan', 'hari')->set('durasi', 2)
        ->set('tanggalMulai', '2026-09-10')->set('jamMulai', '08:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')->set('setuju', true)
        ->call('pesan')->assertHasNoErrors();

    // Angka tunggal tanpa perincian membuat penyewa bertanya "kok segitu?" —
    // lalu menanyakannya lewat WhatsApp satu per satu. Perinciannya sudah ia
    // lihat di layar sebelum memesan; berkas yang cuma menulis totalnya justru
    // mencabut penjelasan yang tadi ada.
    Illuminate\Support\Facades\Mail::assertSent(App\Mail\PemberitahuanFormulir::class, function ($surat) {
        if (! $surat->untukPelanggan) {
            return false;
        }

        $nota = $surat->berkasPdf ? true : false;

        return $nota
            // Badan suratnya menyebut angkanya, terbaca tanpa membuka lampiran
            && str_contains($surat->render(), 'Rp 700.000')
            && str_contains($surat->render(), 'Rp 300.000')
            && str_contains($surat->render(), 'Estimasi total');
    });
});

test('pesanan lama tanpa perincian tetap dapat satu baris seperti sedia kala', function () {
    $mobil = buatMobil();

    // Dibuat sebelum perinciannya ikut disimpan
    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 2, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-12', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 700000, 'status' => 'baru',
    ]);

    expect(App\Support\NotaSewa::untuk($sewa)['baris'])->toHaveCount(1)
        ->and(App\Support\NotaSewa::untuk($sewa)['baris'][0]['label'])->toBe('Biaya sewa');
});

test('perincian yang tidak lagi berjumlah sama dengan totalnya tidak dipakai', function () {
    $mobil = buatMobil();

    // Bisa terjadi bila totalnya disunting admin tanpa menyentuh perinciannya.
    // Menampilkan keduanya berarti satu berkas menagih dua angka berbeda.
    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 2, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-12', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 900000, 'status' => 'baru',
        'rincian_estimasi' => [
            ['label' => 'Tarif sewa', 'keterangan' => 'Rp 350.000 × 2 hari', 'jumlah' => 700000],
        ],
    ]);

    $nota = App\Support\NotaSewa::untuk($sewa);

    expect($nota['baris'])->toHaveCount(1)
        ->and($nota['baris'][0]['label'])->toBe('Biaya sewa')
        ->and($nota['baris'][0]['nilai'])->toBe('Rp 900.000');
});

test('berkas yang uangnya masih ditunggu memuat cara membayar', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 2, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-12', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 700000, 'status' => 'baru',
    ]);

    // Penanda dulunya tabel biaya open trip, sehingga berkas pemesanan sewa —
    // yang capnya jelas-jelas "Belum Dibayar" — malah diberi kalimat kwitansi
    // lunas: "simpan berkas ini sampai perjalanan selesai", tanpa sepatah pun
    // tentang cara membayarnya.
    $isi = Illuminate\Support\Facades\Blade::render(
        file_get_contents(resource_path('views/pdf/kwitansi.blade.php')),
        [
            'judul' => 'Rincian Pemesanan Sewa Kendaraan', 'kode' => $sewa->kode,
            'rincian' => ['Penyewa' => $sewa->nama], 'catatan' => null,
            'jumlah' => 'Rp 700.000', 'jumlahLabel' => 'Estimasi biaya sewa',
            'capStatus' => 'Belum Dibayar', 'biaya' => [], 'tagihan' => [],
            'nota' => App\Support\NotaSewa::untuk($sewa), 'keadaan' => [], 'caraBayar' => true,
        ]
    );

    expect($isi)->toContain('Cara Pembayaran')
        ->toContain('Konfirmasi Pembayaran')
        ->not->toContain('Simpan berkas ini sampai perjalanan selesai');
});

test('tanda terima pembayaran memuat posisi tagihan, bukan cuma nominalnya', function () {
    $paket = App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);

    $pendaftaran = App\Models\OpenTrip\PendaftaranOpenTrip::create([
        'travel_package_id' => $paket->id, 'nama_paket' => $paket->name,
        'nama' => 'Siti', 'whatsapp' => '0812', 'jumlah_peserta' => 2,
    ]);

    $tagihan = App\Support\TagihanPesanan::untuk($pendaftaran);

    $html = view('pdf.kwitansi', [
        'judul' => 'Tanda Terima Pembayaran', 'kode' => $pendaftaran->kode,
        'rincian' => ['Nominal' => 'Rp 858.000'], 'catatan' => null,
        'jumlah' => 'Rp 858.000', 'jumlahLabel' => 'Nominal dilaporkan',
        'capStatus' => 'Menunggu Dicek', 'biaya' => [], 'tagihan' => $tagihan,
    ])->render();

    // Yang ingin diketahui pelanggan sesudah mentransfer adalah sisanya
    expect($html)->toContain('Posisi Tagihan')
        ->and($html)->toContain('Rp 2.860.000')
        ->and($html)->toContain('Sisa pembayaran');
});

test('denda kerusakan diusulkan dari hasil pemeriksaan', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 350000, 'status' => 'berjalan',
        // Bodi kanan sudah lecet sebelum diserahkan, kaca masih baik
        'kondisi_awal' => ['bodi_kanan' => 'lecet', 'kaca' => 'baik', 'ban' => 'baik'],
        'kondisi_akhir' => ['bodi_kanan' => 'rusak', 'kaca' => 'rusak', 'ban' => 'baik'],
    ]);

    // Kaca baik → rusak = 900.000 penuh.
    // Bodi kanan lecet → rusak hanya SELISIHNYA: 1.200.000 − 200.000 = 1.000.000,
    // karena unit memang sudah lecet saat diserahkan.
    expect($sewa->denda_kerusakan_usulan)->toBe(1900000)
        ->and($sewa->rincian_denda_kerusakan)->toHaveCount(2);

    $bodi = collect($sewa->rincian_denda_kerusakan)->firstWhere('bagian', 'Bodi samping kanan');
    expect($bodi['biaya'])->toBe(1000000)
        ->and($bodi['dari'])->toBe('Lecet / minor');
});

test('unit yang kembali tanpa kerusakan baru tidak diusulkan denda', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 350000, 'status' => 'berjalan',
        'kondisi_awal' => ['bodi_kanan' => 'lecet'],
        'kondisi_akhir' => ['bodi_kanan' => 'lecet'],
    ]);

    expect($sewa->denda_kerusakan_usulan)->toBe(0)
        ->and($sewa->rincian_denda_kerusakan)->toBe([]);
});

test('sisa tagihan sewa ikut menghitung dendanya, bukan biaya sewanya saja', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 300000, 'denda_keterlambatan' => 1200000,
        'denda_kerusakan' => 650000, 'status' => 'berjalan',
    ]);

    App\Models\OpenTrip\KonfirmasiPembayaran::create([
        'kode' => $sewa->kode, 'jenis' => 'dp', 'nominal' => 90000,
        'tanggal_transfer' => '2026-09-09', 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'diterima',
    ]);

    /*
     | Denda keterlambatan dan kerusakan sama-sama ditagihkan ke penyewa dan
     | sama-sama harus ia bayar.
     |
     | Selama yang dihitung hanya biaya sewa, halaman serah terima menyebut
     | "Total tagihan Rp 2.150.000" sementara "Sisa tagihan" di kartu pembayaran
     | menyebut Rp 210.000 — dua angka untuk satu tagihan yang sama, di layar
     | yang sama.
     */
    $tagihan = App\Support\TagihanPesanan::untuk($sewa->fresh(), hanyaDiterima: true);

    expect($tagihan['total'])->toBe(2150000)
        ->and($tagihan['sudah'])->toBe(90000)
        ->and($tagihan['sisa'])->toBe(2060000)
        ->and($tagihan['lunas'])->toBeFalse();
});

test('pembayaran yang diterima dipecah menurut jenisnya', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 3000000, 'status' => 'berjalan',
    ]);

    $bukti = fn (array $u) => App\Models\OpenTrip\KonfirmasiPembayaran::create(array_merge([
        'kode' => $sewa->kode, 'tanggal_transfer' => '2026-09-09',
        'bank_pengirim' => 'BCA', 'atas_nama_pengirim' => 'Budi', 'status' => 'diterima',
    ], $u));

    $bukti(['jenis' => 'dp', 'nominal' => 900000]);
    $bukti(['jenis' => 'pelunasan', 'nominal' => 700000]);
    $bukti(['jenis' => 'pelunasan', 'nominal' => 500000]);
    // Yang belum dicek belum uang: tidak boleh ikut mengurangi tagihan
    $bukti(['jenis' => 'pelunasan', 'nominal' => 999000, 'status' => 'menunggu']);

    $perJenis = collect(App\Support\TagihanPesanan::diterimaPerJenis($sewa->fresh()))
        ->keyBy('jenis');

    expect($perJenis)->toHaveCount(2)
        ->and($perJenis['dp']['nominal'])->toBe(900000)
        ->and($perJenis['dp']['label'])->toBe('Uang Muka (DP)')
        ->and($perJenis['pelunasan']['nominal'])->toBe(1200000)
        ->and($perJenis['pelunasan']['berkas'])->toBe(2);
});

test('sewa yang perlu ditindak bisa dihitung sendiri lewat api', function () {
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $mobil = buatMobil();

    $sewa = fn (array $u) => PenyewaanKendaraan::create(array_merge([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 300000,
    ], $u));

    // Dua pemesanan yang belum disentuh siapa pun
    $sewa(['status' => 'baru']);
    $sewa(['status' => 'baru']);

    // Satu unit yang sudah lewat tenggat dan belum dicatat kembali
    $sewa([
        'status' => 'berjalan',
        'tanggal_selesai' => now()->subWeek()->toDateString(), 'jam_selesai' => '08:00',
    ]);

    // Yang sudah kembali dan yang batal tidak menuntut apa pun lagi
    $sewa([
        'status' => 'selesai', 'dikembalikan_pada' => now()->subDay(),
        'tanggal_selesai' => now()->subWeek()->toDateString(), 'jam_selesai' => '08:00',
    ]);
    $sewa(['status' => 'batal', 'tanggal_selesai' => now()->subWeek()->toDateString()]);

    $data = $this->getJson('/api/v1/penyewaan/perhatian', [
        'X-Orcha-Key' => 'kunci-uji', 'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ])->assertOk()->json('data');

    expect($data['baru'])->toBe(2)
        ->and($data['telat'])->toBe(1);
});

test('nota mengurangi pembayaran yang sudah diterima, bukan menagih penuh', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-08-16', 'jam_mulai' => '09:00',
        'tanggal_selesai' => '2026-08-17', 'jam_selesai' => '09:00',
        'dikembalikan_pada' => '2026-08-20 14:38',
        'estimasi_biaya' => 300000, 'denda_keterlambatan' => 1200000,
        'denda_kerusakan' => 650000, 'status' => 'dp_masuk',
    ]);

    App\Models\OpenTrip\KonfirmasiPembayaran::create([
        'kode' => $sewa->kode, 'jenis' => 'dp', 'nominal' => 90000,
        'tanggal_transfer' => '2026-08-16', 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'diterima',
    ]);

    /*
     | Tanpa pengurangan ini nota berhenti di total dan menagih seluruhnya —
     | padahal penyewa sudah membayar uang muka. Yang dibacanya: diminta
     | membayar DP untuk kedua kalinya. Itu bukan salah paham yang bisa
     | diluruskan belakangan; nota adalah dokumen yang ia pegang.
     */
    $nota = App\Support\NotaSewa::untuk($sewa->fresh());

    expect($nota['total'])->toBe('Rp 2.150.000')
        ->and($nota['sudah'])->toBe(90000)
        ->and($nota['sisa'])->toBe('Rp 2.060.000')
        ->and($nota['lunas'])->toBeFalse()
        ->and($nota['pembayaran'][0]['label'])->toBe('Uang Muka (DP)')
        ->and($nota['pembayaran'][0]['nilai'])->toBe('Rp 90.000');
});

test('bukti yang masih menunggu dicek tidak mengurangi nota', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 300000, 'status' => 'baru',
    ]);

    App\Models\OpenTrip\KonfirmasiPembayaran::create([
        'kode' => $sewa->kode, 'jenis' => 'dp', 'nominal' => 90000,
        'tanggal_transfer' => '2026-09-09', 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'menunggu',
    ]);

    // Mengurangkannya berarti mengakui pembayaran berdasarkan gambar yang belum
    // diperiksa siapa pun — dan nota adalah dokumen resmi.
    $nota = App\Support\NotaSewa::untuk($sewa->fresh());

    expect($nota['pembayaran'])->toBeEmpty()
        ->and($nota['sudah'])->toBe(0);
});

test('unit yang kembalinya telat tapi dendanya sudah ditetapkan tidak dihitung', function () {
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $mobil = buatMobil();

    $sewa = fn (array $u) => PenyewaanKendaraan::create(array_merge([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => now()->subWeeks(2)->toDateString(), 'jam_mulai' => '08:00',
        'tanggal_selesai' => now()->subWeek()->toDateString(), 'jam_selesai' => '08:00',
        'estimasi_biaya' => 300000, 'status' => 'dp_masuk',
        'dikembalikan_pada' => now()->subDays(3),
    ], $u));

    // Kembalinya telat, tetapi dendanya SUDAH ditetapkan: pekerjaan menagihnya
    // selesai, tidak ada yang perlu ditindak lagi.
    $sewa(['denda_keterlambatan' => 1200000]);

    // Kembali juga telat, tetapi belum satu rupiah pun ditetapkan. Nota yang
    // dikirim ke penyewa masih menyebut Rp 0.
    $sewa([]);

    $data = $this->getJson('/api/v1/penyewaan/perhatian', [
        'X-Orcha-Key' => 'kunci-uji', 'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ])->assertOk()->json('data');

    expect($data['telat'])->toBe(0)
        ->and($data['denda'])->toBe(1);
});

test('jalur perhatian tidak terbaca sebagai nomor penyewaan', function () {
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    // Rutenya harus terdaftar SEBELUM /penyewaan/{penyewaan}; kalau tidak,
    // "perhatian" ditangkap sebagai nomor dan jawabannya 404.
    $this->getJson('/api/v1/penyewaan/perhatian', [
        'X-Orcha-Key' => 'kunci-uji', 'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ])->assertOk()->assertJsonStructure(['data' => ['baru', 'telat']]);
});

test('nota akhir menjumlahkan biaya sewa dengan seluruh denda', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 2, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-12', 'jam_selesai' => '08:00',
        'dikembalikan_pada' => '2026-09-12 11:00',
        'estimasi_biaya' => 700000, 'denda_keterlambatan' => 150000,
        'denda_kerusakan' => 900000, 'denda_lain' => 50000, 'status' => 'selesai',
    ]);

    $nota = App\Support\NotaSewa::untuk($sewa);

    // Sebelumnya denda hanya jadi baris keterangan dan tidak pernah dijumlahkan
    expect($nota['total'])->toBe('Rp 1.800.000')
        ->and($nota['baris'])->toHaveCount(4)
        ->and($nota['baris'][0]['label'])->toBe('Biaya sewa')
        ->and($nota['baris'][1]['nilai'])->toBe('Rp 150.000');

    // Denda yang nol tidak ikut ditampilkan
    $sewa->update(['denda_lain' => 0]);
    expect(App\Support\NotaSewa::untuk($sewa->fresh())['baris'])
        ->toHaveCount(3);
});

test('unit yang kembali mengirim nota akhir ke penyewa', function () {
    Illuminate\Support\Facades\Mail::fake();
    config()->set('orcha.email_pemberitahuan', 'halo@orchajourney.com');
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'budi@contoh.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 350000, 'status' => 'berjalan',
    ]);

    $this->patchJson("/api/v1/penyewaan/{$sewa->id}/serah-terima", [
        'dikembalikan_pada' => '2026-09-11 11:00',
        'denda_keterlambatan' => 105000,
        'catatan_denda' => 'Telat 3 jam.',
    ], [
        'X-Orcha-Key' => config('orcha.api.kunci'),
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ])->assertOk();

    Illuminate\Support\Facades\Mail::assertSent(App\Mail\PemberitahuanFormulir::class, function ($surat) {
        if (! $surat->untukPelanggan) {
            return false;
        }

        // Bukti penagihan: dendanya disebut, dan berkasnya terlampir
        return $surat->hasTo('budi@contoh.test')
            && $surat->judul === 'Nota Akhir Sewa — Ada Denda'
            && count($surat->berkasPdf) === 1
            && str_starts_with(reset($surat->berkasPdf), '%PDF-');
    });
});

test('unit yang bentrok tetap diterima, lalu ditandai perlu dicarikan', function () {
    /*
     | Armada di katalog bukan unit milik sendiri melainkan contoh: begitu ada
     | yang memesan, unitnya dicarikan dari vendor rekanan. Dua pesanan pada
     | unit dan tanggal yang sama karena itu BUKAN hal mustahil — yang kedua
     | cuma perlu dicarikan dari vendor lain.
     |
     | Dulu yang kedua ditolak mentah-mentah. Yang terjadi: pelanggan yang
     | sebenarnya bisa dilayani disuruh pergi memilih tanggal lain, dan
     | sebagian dari mereka tidak kembali. Itu pesanan yang hilang tanpa
     | alasan.
     */
    $mobil = buatMobil();

    PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Penyewa Pertama',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 3,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-13', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 1050000, 'status' => 'dp_masuk',
    ]);

    // Orang kedua memesan unit yang sama, tanggalnya bersinggungan
    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'hari')
        ->set('durasi', 2)
        ->set('tanggalMulai', '2026-09-12')
        ->set('jamMulai', '08:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Penyewa Kedua')
        ->set('whatsapp', '081234567890')
        ->set('email', 'kedua@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    // Pesanannya masuk...
    expect(PenyewaanKendaraan::count())->toBe(2);

    // ...dan yang kedua ditandai supaya tim tahu harus mencarikan unitnya.
    $kedua = PenyewaanKendaraan::where('nama', 'Penyewa Kedua')->firstOrFail();
    expect($kedua->perlu_dicarikan)->toBeTrue();
});

test('unit yang tidak bentrok tidak ditandai perlu dicarikan', function () {
    // Tanda yang muncul pada pesanan yang sebenarnya aman akan membuat tim
    // berhenti mempercayainya, lalu mengabaikannya juga saat benar-benar perlu.
    $mobil = buatMobil();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)->set('transmisi', 'Matic')
        ->set('satuan', 'hari')->set('durasi', 2)
        ->set('tanggalMulai', '2026-09-12')->set('jamMulai', '08:00')
        ->set('denganSopir', 'ya')->set('nama', 'Penyewa Sendirian')
        ->set('whatsapp', '081234567890')->set('email', 'sendiri@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    expect(PenyewaanKendaraan::firstOrFail()->perlu_dicarikan)->toBeFalse();
});

test('tanda perlu dicarikan hilang setelah pesanan yang bentrok dibatalkan', function () {
    /*
     | Dihitung saat dibaca, bukan disimpan sebagai kolom — sebab jawabannya
     | berubah. Kolom yang ditulis sekali akan terus menyuruh tim mencari unit
     | yang sebenarnya sudah bebas.
     */
    $mobil = buatMobil();

    $pertama = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Pertama',
        'whatsapp' => '0812', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 3,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-13', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 1050000, 'status' => 'dp_masuk',
    ]);

    $kedua = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Kedua',
        'whatsapp' => '0813', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 2,
        'tanggal_mulai' => '2026-09-12', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-14', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 700000, 'status' => 'baru',
    ]);

    expect($kedua->perlu_dicarikan)->toBeTrue();

    $pertama->update(['status' => 'batal']);

    expect($kedua->fresh()->perlu_dicarikan)->toBeFalse();
});

test('unit bebas dipesan lagi tepat setelah yang sebelumnya selesai', function () {
    $mobil = buatMobil();

    PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Penyewa Pertama',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 2,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-12', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 700000, 'status' => 'dp_masuk',
    ]);

    // Mulai persis saat yang sebelumnya selesai — itu memang cara unit
    // berpindah tangan, bukan tabrakan.
    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'hari')
        ->set('durasi', 1)
        ->set('tanggalMulai', '2026-09-12')
        ->set('jamMulai', '08:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Penyewa Kedua')
        ->set('whatsapp', '081234567890')
        ->set('email', 'kedua@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('tujuan', 'Borobudur')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    expect(PenyewaanKendaraan::count())->toBe(2);
});

test('pesanan yang batal tidak menghalangi unitnya dipesan lagi', function () {
    $mobil = buatMobil();

    PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Batal',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 3,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-13', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 1050000, 'status' => 'batal',
    ]);

    expect(PenyewaanKendaraan::bentrok(
        $mobil->id,
        Carbon\Carbon::parse('2026-09-11 08:00'),
        Carbon\Carbon::parse('2026-09-12 08:00'),
    ))->toHaveCount(0);
});

test('satuan sewa tertulis di dalam isian lama sewa', function (string $satuan, string $sufiks, string $kelas) {
    $mobil = Car::create([
        'name' => 'HiAce Commuter', 'brand' => 'Toyota', 'type' => 'hiace',
        'transmission' => 'Manual', 'transmisi_tersedia' => ['Manual'],
        'capacity' => 14, 'lepas_kunci' => false, 'termasuk_sopir' => true,
        'price_per_day' => 1200000, 'harga_per_jam' => 150000, 'harga_12_jam' => 800000,
        'is_available' => true,
    ]);

    // Angka tanpa satuan mudah salah baca: "2" pada paket 12 jam berarti 24 jam,
    // bukan dua hari.
    $html = Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('satuan', $satuan)
        ->html();

    $isian = substr($html, strpos($html, 'id="sk-durasi"'), 900);

    expect($isian)->toContain($sufiks)
        // Kelasnya menentukan lebar ruang di kanan. Utilitas Tailwind tidak bisa
        // dipakai karena .isian-orcha memakai shorthand padding di berkas yang
        // dimuat belakangan — terukur di peramban, padding kanannya tetap 16px
        // dan angkanya bertumpuk dengan satuannya.
        ->and($isian)->toContain($kelas)
        // Keterangan lama tidak lagi mengulang hal yang sama.
        ->and($html)->not->toContain('Dihitung dalam');
})->with([
    ['hari', 'hari', 'isian-satuan'],
    ['jam', 'jam', 'isian-satuan'],
    ['12jam', '× 12 jam', 'isian-satuan-lebar'],
]);
