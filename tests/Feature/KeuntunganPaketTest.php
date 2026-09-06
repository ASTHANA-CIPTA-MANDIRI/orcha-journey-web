<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\PaketWisata\Keuntungan;

const KUNCI_UNTUNG = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_UNTUNG);
    config()->set('orcha.api.ip_diizinkan', []);
});

function kepalaUntung(): array
{
    return [
        'X-Orcha-Key' => KUNCI_UNTUNG,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

function paketUntung(array $ubah = []): TravelPackage
{
    return TravelPackage::create(array_merge([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'price' => 1430000,
        'harga_modal' => 1400000,
        'minimal_peserta' => 6,
    ], $ubah));
}

function daftarUntung(TravelPackage $paket, array $ubah = []): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create(array_merge([
        'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name,
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 2,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'status' => 'lunas',
    ], $ubah));
}

/* ---------------------------- MARGIN PAKET ---------------------------- */

test('margin per orang adalah selisih harga jual dan modal', function () {
    $paket = paketUntung();

    expect($paket->margin_per_orang)->toBe(30000)
        ->and($paket->margin_per_orang_teks)->toBe('Rp 30.000')
        ->and($paket->margin_persen)->toBe(2.1)
        ->and($paket->modal_terisi)->toBeTrue();
});

test('modal kosong berarti belum dihitung, bukan untung penuh', function () {
    $paket = paketUntung(['harga_modal' => null]);

    expect($paket->modal_terisi)->toBeFalse()
        ->and($paket->margin_per_orang)->toBeNull()
        ->and($paket->margin_persen)->toBeNull()
        ->and($paket->margin_per_orang_teks)->toBe('Belum dihitung');
});

test('paket yang dijual di bawah modal dilaporkan rugi apa adanya', function () {
    $paket = paketUntung(['price' => 1350000]);

    expect($paket->margin_per_orang)->toBe(-50000);
});

test('modal tidak pernah ikut saat paket diubah jadi larik', function () {
    $paket = paketUntung();

    expect($paket->toArray())->not->toHaveKey('harga_modal');
});

test('modal tidak bocor ke halaman paket yang dibuka pengunjung', function () {
    $paket = paketUntung(['harga_modal' => 1234567]);

    $halaman = $this->get('/paket/'.$paket->uuid);

    $halaman->assertOk();
    expect($halaman->getContent())
        ->not->toContain('1234567')
        ->and($halaman->getContent())->not->toContain('1.234.567');
});

/* ------------------------- JEJAK DI PENDAFTARAN ------------------------- */

test('harga jual dan modal dibekukan saat pendaftaran dibuat', function () {
    $paket = paketUntung();
    $daftar = daftarUntung($paket);

    expect($daftar->harga_jual)->toBe(1430000)
        ->and($daftar->harga_modal)->toBe(1400000);

    // Modal naik bulan berikutnya — pendaftaran lama tidak ikut berubah.
    $paket->update(['harga_modal' => 1410000, 'price' => 1500000]);

    $daftar = $daftar->fresh();

    expect($daftar->modal_satuan)->toBe(1400000)
        ->and($daftar->jual_satuan)->toBe(1430000)
        ->and($daftar->margin_satuan)->toBe(30000)
        ->and($daftar->keuntungan)->toBe(60000);
});

test('pendaftaran tanpa jejak meminjam angka paketnya', function () {
    $paket = paketUntung();
    $daftar = daftarUntung($paket);

    // Meniru baris yang masuk sebelum pembekuan ini ada.
    $daftar->forceFill(['harga_jual' => null, 'harga_modal' => null])->save();

    expect($daftar->fresh()->margin_satuan)->toBe(30000);
});

test('omzet dan keuntungan mengikuti jumlah peserta', function () {
    $daftar = daftarUntung(paketUntung(), ['jumlah_peserta' => 5]);

    expect($daftar->omzet)->toBe(7150000)
        ->and($daftar->modal_total)->toBe(7000000)
        ->and($daftar->keuntungan)->toBe(150000);
});

/* ----------------------------- LAPORAN ----------------------------- */

test('hanya pendaftaran lunas yang dihitung sebagai keuntungan', function () {
    $paket = paketUntung();

    daftarUntung($paket, ['status' => 'lunas', 'jumlah_peserta' => 2]);
    daftarUntung($paket, ['status' => 'dp_masuk', 'jumlah_peserta' => 3]);
    daftarUntung($paket, ['status' => 'batal', 'jumlah_peserta' => 4]);

    $ringkas = Keuntungan::laporan()['ringkasan'];

    expect($ringkas['pendaftaran'])->toBe(1)
        ->and($ringkas['peserta'])->toBe(2)
        ->and($ringkas['keuntungan'])->toBe(60000)
        ->and($ringkas['keuntungan_teks'])->toBe('Rp 60.000')
        // Yang ber-DP tercatat terpisah; yang batal tidak di mana-mana.
        ->and($ringkas['potensi_pendaftaran'])->toBe(1)
        ->and($ringkas['potensi_peserta'])->toBe(3)
        ->and($ringkas['potensi_keuntungan'])->toBe(90000);
});

test('paket yang modalnya belum diisi dihitung sebagai belum lengkap', function () {
    $adaModal = paketUntung();
    $tanpaModal = paketUntung(['name' => 'Private Trip Dieng', 'harga_modal' => null]);

    daftarUntung($adaModal);
    daftarUntung($tanpaModal);

    $ringkas = Keuntungan::laporan()['ringkasan'];

    expect($ringkas['pendaftaran'])->toBe(2)
        ->and($ringkas['belum_lengkap'])->toBe(1)
        ->and($ringkas['paket_belum_lengkap'])->toBe(['Private Trip Dieng'])
        // Omzetnya tetap terhitung, keuntungannya tidak dikarang.
        ->and($ringkas['keuntungan'])->toBe(60000)
        ->and($ringkas['omzet'])->toBe(5720000);
});

test('rekap per paket dan per kategori memisahkan sumbernya', function () {
    $open = paketUntung();
    $studi = paketUntung(['name' => 'Study Tour Bali', 'category' => 'study_tour', 'price' => 1600000, 'harga_modal' => 1500000]);

    daftarUntung($open, ['jumlah_peserta' => 2]);   // 60.000
    daftarUntung($studi, ['jumlah_peserta' => 10]); // 1.000.000

    $laporan = Keuntungan::laporan();

    // Diurutkan dari yang paling besar keuntungannya.
    expect($laporan['per_paket'][0]['nama'])->toBe('Study Tour Bali')
        ->and($laporan['per_paket'][0]['keuntungan'])->toBe(1000000)
        ->and($laporan['per_paket'][0]['margin_per_orang'])->toBe(100000)
        ->and($laporan['per_paket'][1]['keuntungan'])->toBe(60000)
        ->and($laporan['per_kategori'][0]['label'])->toBe('Study Tour')
        ->and($laporan['ringkasan']['margin_rata_per_orang'])->toBe(88333);
});

test('rentang tanggal menyaring menurut dasar yang dipilih', function () {
    $paket = paketUntung();

    $lama = daftarUntung($paket, ['tanggal_berangkat' => now()->addMonths(3)->toDateString()]);
    $lama->forceFill(['created_at' => now()->subMonths(2)])->save();

    daftarUntung($paket, ['tanggal_berangkat' => now()->addDays(5)->toDateString()]);

    $dari = now()->subWeek()->toDateString();

    // Menurut tanggal mendaftar: yang dua bulan lalu tersaring keluar.
    expect(Keuntungan::laporan(['dari' => $dari])['ringkasan']['pendaftaran'])->toBe(1);

    // Menurut keberangkatan: keduanya masih di depan, jadi keduanya masuk.
    expect(Keuntungan::laporan(['dari' => $dari, 'dasar' => 'berangkat'])['ringkasan']['pendaftaran'])->toBe(2);
});

/* ------------------------------- API ------------------------------- */

test('laporan keuntungan dijaga kunci api', function () {
    $this->getJson('/api/v1/keuntungan')->assertStatus(401);
});

test('api keuntungan mengirim ringkasan, rekap, dan daftar paket', function () {
    $paket = paketUntung();
    daftarUntung($paket);

    $this->getJson('/api/v1/keuntungan', kepalaUntung())
        ->assertOk()
        ->assertJsonPath('data.ringkasan.keuntungan', 60000)
        ->assertJsonPath('data.ringkasan.keuntungan_teks', 'Rp 60.000')
        ->assertJsonPath('data.per_paket.0.nama', 'Open Trip Banyuwangi')
        ->assertJsonPath('data.paket.0.margin_per_orang', 30000)
        ->assertJsonPath('data.saringan.dasar', 'daftar');
});

test('api rincian keuntungan berhalaman dan menyebut kodenya', function () {
    $paket = paketUntung();
    $daftar = daftarUntung($paket);

    $this->getJson('/api/v1/keuntungan/rincian?per_halaman=5', kepalaUntung())
        ->assertOk()
        ->assertJsonPath('data.0.kode', $daftar->kode)
        ->assertJsonPath('data.0.keuntungan', 60000)
        ->assertJsonPath('meta.per_halaman', 5);
});

test('rincian bisa dibatasi hanya yang lunas', function () {
    $paket = paketUntung();
    daftarUntung($paket, ['status' => 'lunas']);
    daftarUntung($paket, ['status' => 'dp_masuk']);
    daftarUntung($paket, ['status' => 'batal']);

    // Bawaannya: semua kecuali batal, supaya admin melihat yang menggantung.
    $this->getJson('/api/v1/keuntungan/rincian', kepalaUntung())
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/keuntungan/rincian?hanya_lunas=1', kepalaUntung())
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('modal paket bisa disimpan dan diubah lewat api', function () {
    $balasan = $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Karimunjawa',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 1430000,
        'harga_modal' => 1400000,
    ], kepalaUntung());

    $balasan->assertCreated()
        ->assertJsonPath('data.harga_modal', 1400000)
        ->assertJsonPath('data.margin_per_orang', 30000);

    $id = $balasan->json('data.id');

    // Dikosongkan lagi: kembali jadi "belum dihitung", bukan nol.
    $this->postJson("/api/v1/paket-wisata/{$id}", [
        '_method' => 'PUT',
        'nama' => 'Open Trip Karimunjawa',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 1430000,
    ], kepalaUntung())
        ->assertOk()
        ->assertJsonPath('data.harga_modal', null)
        ->assertJsonPath('data.modal_terisi', false);
});

test('modal yang dikirim sebagai isian kosong tidak jadi nol', function () {
    // Lemon mengirimkannya begitu saat admin mengosongkan isiannya: perataan
    // multipart membuang nilai null, jadi yang sampai ke sini teks kosong.
    $balasan = $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Private Trip Dieng',
        'kategori' => 'private_trip',
        'minimal_peserta' => 4,
        'harga' => 1430000,
        'harga_modal' => '',
    ], kepalaUntung());

    $balasan->assertCreated()
        ->assertJsonPath('data.harga_modal', null)
        ->assertJsonPath('data.modal_terisi', false)
        ->assertJsonPath('data.margin_per_orang', null);
});

test('menu orcha menyebut halaman keuntungan', function () {
    $this->getJson('/api/v1/menu', kepalaUntung())
        ->assertOk()
        ->assertJsonFragment(['jalur' => 'keuntungan', 'label' => 'Keuntungan Paket', 'ikon' => 'chart-bar']);
});

/* ------------------- BIAYA TETAP PER ROMBONGAN ------------------- */

test('biaya tetap masuk modal utuh, tidak dikalikan peserta', function () {
    /*
     | Itulah seluruh gunanya. Carter bus tidak jadi lebih mahal karena
     | penumpangnya bertambah satu, dan memaksanya jadi angka per orang
     | menuntut admin membagi sendiri tiap kali — yang benar-benar terjadi
     | adalah ia memakai ulang angka rombongan sebelumnya.
     */
    $paket = paketUntung(['category' => 'private_trip', 'harga_modal' => null]);

    $daftar = daftarUntung($paket, [
        'jumlah_peserta' => 10,
        'harga_jual' => 1_000_000,
        'harga_modal' => 400_000,
        'biaya_tetap' => 3_000_000,
    ]);

    expect($daftar->modal_total)->toBe(7_000_000)      // 400rb x 10 + 3jt
        ->and($daftar->omzet)->toBe(10_000_000)
        ->and($daftar->keuntungan)->toBe(3_000_000)
        // Modal sesungguhnya per kepala: Rp 700.000, bukan Rp 400.000 yang
        // diketik admin. Inilah angka yang menentukan harganya masuk akal.
        ->and($daftar->modal_per_kepala)->toBe(700_000);
});

test('rombongan kecil menanggung biaya tetap jauh lebih berat', function () {
    /*
     | Alasan kolom ini ada. Angka "modal per orang" yang sama dipakai ulang
     | untuk rombongan bertiga dan tiga puluh akan benar untuk salah satunya
     | saja — dan yang salah tidak pernah berbunyi.
     */
    $paket = paketUntung(['category' => 'private_trip', 'harga_modal' => null]);

    $besar = daftarUntung($paket, [
        'jumlah_peserta' => 30, 'harga_jual' => 750_000,
        'harga_modal' => 300_000, 'biaya_tetap' => 3_000_000,
    ]);

    $kecil = daftarUntung($paket, [
        'jumlah_peserta' => 3, 'harga_jual' => 750_000,
        'harga_modal' => 300_000, 'biaya_tetap' => 3_000_000,
    ]);

    expect($besar->modal_per_kepala)->toBe(400_000)
        ->and($besar->keuntungan)->toBe(10_500_000)
        // Harga jual yang sama, rombongan bertiga, dan hasilnya MERUGI.
        // Tanpa kolom biaya tetap, keduanya dilaporkan untung.
        ->and($kecil->modal_per_kepala)->toBe(1_300_000)
        ->and($kecil->keuntungan)->toBe(-1_650_000);
});

test('biaya tetap nol tidak mengubah apa pun', function () {
    // Penjaga seluruh open trip yang sudah berjalan: kolomnya berisi nol,
    // dan hitungannya harus persis seperti sebelum kolom ini ada.
    $paket = paketUntung();
    $daftar = daftarUntung($paket, ['jumlah_peserta' => 4]);

    expect($daftar->biaya_tetap)->toBe(0)
        ->and($daftar->modal_total)->toBe(5_600_000)
        ->and($daftar->keuntungan)->toBe(120_000)
        ->and($daftar->modal_per_kepala)->toBe(1_400_000);
});

test('pendamping gratis menanggung biaya, bukan menghasilkan uang', function () {
    /*
     | Asimetri yang paling mudah salah, dan paling mahal salahnya. Guru
     | pendamping study tour tidak dibayar sekolahnya, tetapi ia tetap
     | menempati kursi bus, makan siang, dan kamar hotel.
     |
     | Omzet memakai peserta_dibayar, modal memakai jumlah_peserta. Kalau
     | modal ikut memakai peserta_dibayar, laporan mengaku untung lebih besar
     | sejumlah modal satu orang untuk setiap pendamping.
     */
    $paket = paketUntung(['category' => 'private_trip', 'harga_modal' => null]);

    $daftar = daftarUntung($paket, [
        'jumlah_peserta' => 11,
        'pendamping_gratis' => 1,
        'harga_jual' => 1_000_000,
        'harga_modal' => 500_000,
        'biaya_tetap' => 3_000_000,
    ]);

    expect($daftar->peserta_dibayar)->toBe(10)
        ->and($daftar->omzet)->toBe(10_000_000)        // 10 yang membayar
        ->and($daftar->modal_total)->toBe(8_500_000)   // 11 yang berangkat + carter
        ->and($daftar->keuntungan)->toBe(1_500_000);
});

test('modal kosong tetap menolak dihitung walau biaya tetap terisi', function () {
    /*
     | Biaya tetap yang terisi TIDAK membuat modalnya jadi diketahui. Diam
     | soal biaya per orang bukan sama dengan nol — dan laporan yang mengaku
     | untung untuk pesanan yang biaya makannya belum pernah dimasukkan lebih
     | menyesatkan daripada laporan yang mengakui ada yang kosong.
     */
    $paket = paketUntung(['category' => 'private_trip', 'harga_modal' => null]);

    $daftar = daftarUntung($paket, [
        'jumlah_peserta' => 5, 'harga_jual' => 1_000_000, 'biaya_tetap' => 3_000_000,
    ]);

    expect($daftar->modal_satuan)->toBeNull()
        ->and($daftar->modal_total)->toBeNull()
        ->and($daftar->keuntungan)->toBeNull()
        ->and($daftar->modal_per_kepala)->toBeNull();
});

/* ------------- PENGHITUNG YANG BELUM LENGKAP ------------- */

test('pesanan potensi bermodal kosong ikut dihitung belum lengkap', function () {
    /*
     | Perbaikan atas kesalahan yang sempat berjalan: penghitungnya hanya
     | memeriksa sisi lunas, sehingga pesanan potensi bermodal kosong
     | menyumbang omzetnya penuh tetapi keuntungannya nol — tanpa satu pun
     | penanda yang menjelaskan sebabnya. Yang membacanya menyimpulkan
     | marginnya tipis, lalu mengambil keputusan atas kesimpulan itu.
     */
    $paket = paketUntung(['name' => 'Private Trip Premium', 'category' => 'private_trip', 'harga_modal' => null]);

    daftarUntung($paket, ['jumlah_peserta' => 3, 'harga_jual' => 2_500_000, 'status' => 'dp']);
    daftarUntung($paket, ['jumlah_peserta' => 3, 'harga_jual' => 750_000, 'status' => 'baru']);

    $r = Keuntungan::laporan()['ringkasan'];

    expect($r['potensi_belum_lengkap'])->toBe(2)
        // Omzetnya tetap terhitung; yang tidak dikarang keuntungannya.
        ->and($r['potensi_omzet'])->toBe(9_750_000)
        ->and($r['potensi_keuntungan'])->toBe(0)
        // Paketnya disebut supaya admin tahu apa yang harus diisi.
        ->and($r['paket_belum_lengkap'])->toContain('Private Trip Premium');
});

test('nama paket belum lengkap digabung dari sisi lunas dan potensi', function () {
    // Pekerjaan yang menunggu admin sama saja — mengisi modalnya — dan dua
    // daftar terpisah hanya menyuruhnya membaca dua kali untuk satu pekerjaan.
    $lunasan = paketUntung(['name' => 'Paket Lunas Tanpa Modal', 'harga_modal' => null]);
    $potensian = paketUntung(['name' => 'Paket Potensi Tanpa Modal', 'harga_modal' => null]);

    daftarUntung($lunasan, ['harga_jual' => 1_000_000, 'status' => 'lunas']);
    daftarUntung($potensian, ['harga_jual' => 1_000_000, 'status' => 'dp']);

    $r = Keuntungan::laporan()['ringkasan'];

    expect($r['belum_lengkap'])->toBe(1)
        ->and($r['potensi_belum_lengkap'])->toBe(1)
        ->and($r['paket_belum_lengkap'])->toContain('Paket Lunas Tanpa Modal')
        ->and($r['paket_belum_lengkap'])->toContain('Paket Potensi Tanpa Modal');
});

test('margin per paket ikut menanggung biaya tetap', function () {
    /*
     | Dulu diambil dari baris pertama kelompoknya — selisih harga jual dan
     | modal per orang, yang sejak biaya tetap ada tidak lagi sama dengan
     | untung per kepala. Untuk private trip, rombongan pertama juga tidak
     | mewakili apa-apa.
     */
    $paket = paketUntung(['name' => 'Private Trip Premium', 'category' => 'private_trip', 'harga_modal' => null]);

    daftarUntung($paket, [
        'jumlah_peserta' => 10, 'harga_jual' => 1_000_000,
        'harga_modal' => 400_000, 'biaya_tetap' => 3_000_000, 'status' => 'lunas',
    ]);

    $baris = collect(Keuntungan::laporan()['per_paket'])->firstWhere('nama', 'Private Trip Premium');

    // Rp 300.000 (untung 3jt / 10 orang), BUKAN Rp 600.000 (1jt - 400rb).
    expect($baris['margin_per_orang'])->toBe(300_000)
        ->and($baris['keuntungan'])->toBe(3_000_000);
});
