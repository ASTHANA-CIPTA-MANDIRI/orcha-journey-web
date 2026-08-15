<?php

use App\Models\Kontak\PesanKontak;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\OpenTrip\RiwayatKesehatan;
use App\Models\PaketWisata\TravelPackage;
use App\Models\User;
use Livewire\Volt\Volt;

/* ============================ FORM KONTAK ============================ */

test('form kontak menyimpan pesan yang sah', function () {
    Volt::test('public.kontak.index')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@example.com')
        ->set('keperluan', 'open_trip')
        ->set('pesan', 'Saya ingin tanya jadwal open trip ke Karimunjawa bulan depan.')
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertSet('terkirim', true);

    $pesan = PesanKontak::first();

    expect($pesan->nama)->toBe('Budi Santoso')
        ->and($pesan->keperluan)->toBe('open_trip')
        ->and($pesan->dibaca_pada)->toBeNull();
});

test('form kontak menolak isian yang tidak lengkap', function () {
    Volt::test('public.kontak.index')
        ->set('nama', 'Bu')
        ->set('whatsapp', 'bukan-nomor')
        ->set('pesan', 'pendek')
        ->call('kirim')
        ->assertHasErrors(['nama', 'whatsapp', 'pesan']);

    expect(PesanKontak::count())->toBe(0);
});

test('form kontak mengabaikan kiriman bot lewat perangkap', function () {
    Volt::test('public.kontak.index')
        ->set('situs', 'https://spam.example')
        ->set('nama', 'Bot')
        ->call('kirim');

    expect(PesanKontak::count())->toBe(0);
});

/* ====================== PENDAFTARAN OPEN TRIP ======================= */

test('pendaftaran open trip menghasilkan kode dan tersimpan', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Karimunjawa', 'category' => 'open_trip', 'price' => 850000,
        'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
        'destination_list' => ['Karimunjawa'],
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'titik_jemput' => 'Jogja, Klaten, Surakarta',
    ]);

    $komponen = Volt::test('public.open-trip.pendaftaran')
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('paketId', $paket->uuid)
        ->set('jumlahPeserta', 3)
        ->set('peserta', [
            ['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja'],
            ['nama' => 'Budi Santoso', 'titik_jemput' => 'Klaten'],
            ['nama' => 'Rina Wijaya', 'titik_jemput' => 'Surakarta'],
        ])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();

    $pendaftaran = PendaftaranOpenTrip::first();

    expect(collect($pendaftaran->peserta)->pluck('nama')->all())
        ->toBe(['Siti Aminah', 'Budi Santoso', 'Rina Wijaya'])
        // Titik jemput yang benar-benar dipakai, bukan seluruh yang ditawarkan
        ->and($pendaftaran->titik_jemput)->toBe('Jogja, Klaten, Surakarta');

    expect($pendaftaran->nama)->toBe('Siti Aminah')
        ->and($pendaftaran->nama_paket)->toBe('Open Trip Karimunjawa')
        ->and($pendaftaran->jumlah_peserta)->toBe(3)
        ->and($pendaftaran->status)->toBe('baru')
        ->and($pendaftaran->kode)->toStartWith('OT-')
        // Tanggal & titik jemput diambil dari paket, bukan dari isian pengunjung
        ->and($pendaftaran->tanggal_berangkat->toDateString())->toBe($paket->tanggal_berangkat->toDateString())
        ->and($pendaftaran->titik_jemput)->toBe('Jogja, Klaten, Surakarta');

    $komponen->assertSet('kodeTerdaftar', $pendaftaran->kode);
});

test('pendaftaran wajib memilih paket dan menyetujui syarat', function () {
    Volt::test('public.open-trip.pendaftaran')
        ->set('nama', 'Rudi Hartono')
        ->set('whatsapp', '081211112222')
        ->set('setuju', false)
        ->call('daftar')
        ->assertHasErrors(['paketId', 'setuju']);

    expect(PendaftaranOpenTrip::count())->toBe(0);
});

test('paket yang tanggalnya sudah lewat tidak muncul di formulir', function () {
    TravelPackage::create([
        'name' => 'Open Trip Sudah Lewat', 'category' => 'open_trip', 'price' => 500000,
        'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
        'destination_list' => ['Contoh'], 'tanggal_berangkat' => now()->subWeek()->toDateString(),
    ]);
    TravelPackage::create([
        'name' => 'Open Trip Akan Datang', 'category' => 'open_trip', 'price' => 500000,
        'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
        'destination_list' => ['Contoh'], 'tanggal_berangkat' => now()->addWeek()->toDateString(),
    ]);

    $this->get(route('pendaftaran-open-trip'))
        ->assertOk()
        ->assertSee('Open Trip Akan Datang')
        ->assertDontSee('Open Trip Sudah Lewat');
});

test('kode pendaftaran selalu unik', function () {
    $kode = collect(range(1, 5))->map(fn () => PendaftaranOpenTrip::create([
        'nama' => 'Peserta', 'whatsapp' => '0812', 'jumlah_peserta' => 1,
    ])->kode);

    expect($kode->unique())->toHaveCount(5);
});

/* ====================== RIWAYAT KESEHATAN =========================== */

test('riwayat kesehatan tersimpan dan terhubung ke pendaftaran', function () {
    $pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Dewi', 'whatsapp' => '0812', 'jumlah_peserta' => 2,
    ]);

    Volt::test('public.open-trip.riwayat-kesehatan')
        ->set('kode', $pendaftaran->kode)
        ->set('namaPeserta', 'Dewi Lestari')
        ->set('usia', 28)
        ->set('jenisKelamin', 'Perempuan')
        ->set('kemampuanRenang', 'sedikit')
        ->set('golonganDarah', 'O')
        ->set('kondisiKhusus', ['asma', 'maag'])
        ->set('riwayatPenyakit', 'Asma ringan sejak kecil.')
        ->set('alergi', 'Udang')
        ->set('kontakNama', 'Andi')
        ->set('kontakHp', '081277778888')
        ->set('kontakHubungan', 'Suami')
        ->set('setuju', true)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertSet('terkirim', true);

    $riwayat = RiwayatKesehatan::first();

    expect($riwayat->nama_peserta)->toBe('Dewi Lestari')
        ->and($riwayat->jenis_kelamin)->toBe('Perempuan')
        ->and($riwayat->kemampuan_renang)->toBe('sedikit')
        ->and($riwayat->kondisi_khusus)->toBe(['asma', 'maag'])
        ->and($riwayat->ada_catatan_khusus)->toBeTrue()
        ->and($riwayat->pendaftaran->id)->toBe($pendaftaran->id)
        ->and($pendaftaran->riwayatKesehatan)->toHaveCount(1);
});

test('riwayat kesehatan menolak kode pendaftaran yang tidak ada', function () {
    Volt::test('public.open-trip.riwayat-kesehatan')
        ->set('kode', 'OT-0000-XXXX')
        ->set('namaPeserta', 'Peserta Uji')
        ->set('usia', 30)
        ->set('jenisKelamin', 'Laki-laki')
        ->set('kemampuanRenang', 'lancar')
        ->set('kontakNama', 'Kontak')
        ->set('kontakHp', '081200001111')
        ->set('kontakHubungan', 'Kakak')
        ->set('setuju', true)
        ->call('simpan')
        ->assertHasErrors(['kode']);

    expect(RiwayatKesehatan::count())->toBe(0);
});

test('riwayat kesehatan wajib menyertakan persetujuan dan kontak darurat', function () {
    $pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Joko', 'whatsapp' => '0812', 'jumlah_peserta' => 1,
    ]);

    Volt::test('public.open-trip.riwayat-kesehatan')
        ->set('kode', $pendaftaran->kode)
        ->set('namaPeserta', 'Joko Susilo')
        ->set('setuju', false)
        ->call('simpan')
        ->assertHasErrors(['usia', 'jenisKelamin', 'kemampuanRenang', 'kontakNama', 'kontakHp', 'kontakHubungan', 'setuju']);
});

/* ============================ ADMIN ================================= */

test('data kesehatan tidak pernah tampil di halaman publik', function () {
    $pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Peserta Rahasia', 'whatsapp' => '0812', 'jumlah_peserta' => 1,
    ]);

    RiwayatKesehatan::create([
        'kode_pendaftaran' => $pendaftaran->kode,
        'nama_peserta' => 'Peserta Rahasia',
        'riwayat_penyakit' => 'Penyakit yang sangat rahasia',
        'kontak_darurat_nama' => 'Kontak',
        'kontak_darurat_hp' => '0812',
        'setuju_data_kesehatan' => true,
    ]);

    foreach (['/', route('destinasi'), route('testimoni'), route('riwayat-kesehatan')] as $url) {
        $this->get($url)->assertOk()->assertDontSee('Penyakit yang sangat rahasia');
    }
});

test('membuka pesan di admin menandainya sudah dibaca', function () {
    $this->actingAs(User::factory()->create());

    $pesan = PesanKontak::create([
        'nama' => 'Andi', 'whatsapp' => '0812', 'keperluan' => 'lainnya',
        'pesan' => 'Halo, saya mau bertanya soal paket wisata.',
    ]);

    Volt::test('admin.pesan.index')
        ->call('buka', $pesan->id)
        ->assertSet('showModal', true);

    expect($pesan->fresh()->dibaca_pada)->not->toBeNull();
});

test('menghapus pendaftaran ikut menghapus data kesehatannya', function () {
    $this->actingAs(User::factory()->create());

    $pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Tono', 'whatsapp' => '0812', 'jumlah_peserta' => 1,
    ]);
    RiwayatKesehatan::create([
        'kode_pendaftaran' => $pendaftaran->kode, 'nama_peserta' => 'Tono',
        'kontak_darurat_nama' => 'Ibu', 'kontak_darurat_hp' => '0813',
        'setuju_data_kesehatan' => true,
    ]);

    Volt::test('admin.pendaftaran.index')
        ->call('openDeleteModal', $pendaftaran->id)
        ->call('hapus');

    expect(PendaftaranOpenTrip::count())->toBe(0)
        ->and(RiwayatKesehatan::count())->toBe(0);
});

/* ------------------- NAMA PESERTA & KELENGKAPAN ------------------- */

test('jumlah kotak peserta mengikuti jumlah peserta', function () {
    $komponen = Volt::test('public.open-trip.pendaftaran')
        ->set('nama', 'Siti Aminah')
        ->set('jumlahPeserta', 3);

    // Peserta pertama terisi sendiri dari nama pemesan
    expect($komponen->get('peserta'))->toHaveCount(3)
        ->and($komponen->get('peserta')[0]['nama'])->toBe('Siti Aminah');

    // Dikurangi lagi, yang sudah diisi tidak hilang
    $komponen->set('peserta.1.nama', 'Budi Santoso')->set('jumlahPeserta', 2);

    expect(collect($komponen->get('peserta'))->pluck('nama')->all())
        ->toBe(['Siti Aminah', 'Budi Santoso']);
});

test('nama peserta wajib diisi semuanya', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Uji', 'category' => 'open_trip', 'price' => 500000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);

    Volt::test('public.open-trip.pendaftaran')
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('paketId', $paket->uuid)
        ->set('jumlahPeserta', 2)
        ->set('peserta', [
            ['nama' => 'Siti Aminah', 'titik_jemput' => ''],
            ['nama' => '', 'titik_jemput' => ''],
        ])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasErrors(['peserta.1.nama']);

    expect(PendaftaranOpenTrip::count())->toBe(0);
});

test('kelengkapan riwayat kesehatan terbaca dari jumlah peserta', function () {
    $pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Siti Aminah', 'whatsapp' => '0812', 'jumlah_peserta' => 3,
        'daftar_peserta' => ['Siti Aminah', 'Budi Santoso', 'Rina Wijaya'],   // bentuk lama
    ]);

    expect($pendaftaran->kesehatan_terisi)->toBe(0)
        ->and($pendaftaran->kesehatan_lengkap)->toBeFalse()
        ->and($pendaftaran->peserta_belum_isi)->toBe(['Siti Aminah', 'Budi Santoso', 'Rina Wijaya']);

    RiwayatKesehatan::create([
        'kode_pendaftaran' => $pendaftaran->kode,
        'nama_peserta' => 'budi santoso',   // beda huruf besar-kecil, tetap terhitung
        'kontak_darurat_nama' => 'Sari',
        'kontak_darurat_hp' => '0812',
        'kontak_darurat_hubungan' => 'Istri',
        'setuju_data_kesehatan' => true,
    ]);

    $pendaftaran->refresh();

    expect($pendaftaran->kesehatan_terisi)->toBe(1)
        ->and($pendaftaran->kesehatan_lengkap)->toBeFalse()
        ->and($pendaftaran->peserta_belum_isi)->toBe(['Siti Aminah', 'Rina Wijaya']);
});

/* --------------------------- TITIK JEMPUT --------------------------- */

test('paket dengan beberapa titik jemput mewajibkan tiap peserta memilih', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'titik_jemput' => 'Jogja, Klaten, Surakarta',
    ]);

    expect($paket->titik_jemput_list)->toBe(['Jogja', 'Klaten', 'Surakarta'])
        ->and($paket->punya_pilihan_jemput)->toBeTrue();

    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('jumlahPeserta', 2)
        ->set('peserta', [
            ['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja'],
            ['nama' => 'Budi Santoso', 'titik_jemput' => ''],
        ])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasErrors(['peserta.1.titik_jemput']);

    expect(PendaftaranOpenTrip::count())->toBe(0);
});

test('titik jemput di luar tawaran paket ditolak', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'titik_jemput' => 'Jogja, Klaten, Surakarta',
    ]);

    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('jumlahPeserta', 1)
        // Prambanan tidak ditawarkan paket ini
        ->set('peserta', [['nama' => 'Siti Aminah', 'titik_jemput' => 'Prambanan']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasErrors(['peserta.0.titik_jemput']);
});

test('paket dengan satu titik jemput mengisinya sendiri', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Jogja Hemat', 'category' => 'open_trip', 'price' => 350000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'titik_jemput' => 'Jogja',
    ]);

    $komponen = Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('jumlahPeserta', 2);

    // Peserta tidak ditanya, langsung terisi
    expect(collect($komponen->get('peserta'))->pluck('titik_jemput')->all())
        ->toBe(['Jogja', 'Jogja']);

    $komponen->set('peserta.1.nama', 'Budi Santoso')
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();
});

test('rombongan terkelompok per titik jemput untuk dibaca sopir', function () {
    $pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Siti Aminah', 'whatsapp' => '0812', 'jumlah_peserta' => 3,
        'daftar_peserta' => [
            ['nama' => 'Siti Aminah', 'titik_jemput' => 'Surakarta'],
            ['nama' => 'Budi Santoso', 'titik_jemput' => 'Jogja'],
            ['nama' => 'Rina Wijaya', 'titik_jemput' => 'Surakarta'],
        ],
    ]);

    expect($pendaftaran->jemput_per_titik)->toBe([
        'Surakarta' => ['Siti Aminah', 'Rina Wijaya'],
        'Jogja' => ['Budi Santoso'],
    ]);
});

test('data lama yang hanya berisi nama tetap terbaca', function () {
    $pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Siti Aminah', 'whatsapp' => '0812', 'jumlah_peserta' => 2,
        'daftar_peserta' => ['Siti Aminah', 'Budi Santoso'],
        'titik_jemput' => 'Jogja',
    ]);

    expect($pendaftaran->peserta)->toBe([
        ['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja'],
        ['nama' => 'Budi Santoso', 'titik_jemput' => 'Jogja'],
    ]);
});
