<?php

use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 3,
        'nama_paket' => 'Open Trip Banyuwangi',
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
});

test('halaman pembatalan bisa dibuka publik', function () {
    $this->get(route('pembatalan'))
        ->assertOk()
        ->assertSee('Pengajuan Pembatalan')
        ->assertSee('Rekening Pengembalian');
});

test('pengajuan pembatalan yang sah tersimpan', function () {
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('alasan', 'kondisi_kesehatan')
        ->set('penjelasan', 'Saya sakit dan disarankan tidak bepergian jauh.')
        ->set('jumlahDibatalkan', 2)
        ->set('bank', 'BCA')
        ->set('nomorRekening', '1234567890')
        ->set('atasNama', 'Budi Santoso')
        ->set('setuju', true)
        ->call('ajukan')
        ->assertHasNoErrors()
        ->assertSet('terkirim', true);

    $pembatalan = Pembatalan::firstOrFail();

    expect($pembatalan->kode_pendaftaran)->toBe($this->pendaftaran->kode)
        ->and($pembatalan->alasan_label)->toBe('Kondisi kesehatan')
        ->and($pembatalan->jumlah_dibatalkan)->toBe(2)
        ->and($pembatalan->status)->toBe('diajukan')
        ->and($pembatalan->status_label)->toBe('Diajukan')
        ->and($pembatalan->pendaftaran->id)->toBe($this->pendaftaran->id);
});

test('pengajuan pembatalan menolak isian tidak lengkap', function () {
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', 'OT-0000-XXXX')
        ->set('empatDigit', '7890')
        ->set('nama', 'A')
        ->call('ajukan')
        ->assertHasErrors(['kode', 'nama', 'whatsapp', 'alasan', 'bank', 'nomorRekening', 'atasNama', 'setuju']);

    expect(Pembatalan::count())->toBe(0);
});

test('kode pendaftaran menampilkan data pemesanan di formulir pembatalan', function () {
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->assertSee('Pemesanan ditemukan')
        ->assertSee('Budi Santoso')
        ->assertSee('Open Trip Banyuwangi');
});

test('sewa kendaraan juga bisa diajukan pembatalannya', function () {
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => 'Avanza Uji', 'nama' => 'Rina Wijaya',
        'whatsapp' => '081298765432', 'email' => 'rina@contoh.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 2, 'tanggal_mulai' => now()->addWeeks(2)->toDateString(),
        'jam_mulai' => '08:00', 'estimasi_biaya' => 1000000, 'status' => 'dp_masuk',
    ]);

    // Dulu kode SK- selalu ditolak "tidak ditemukan" karena hanya tabel
    // pendaftaran open trip yang diperiksa — padahal kodenya benar.
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $sewa->kode)
        ->set('empatDigit', '5432')
        ->assertSee('Pesanan sewa ditemukan')
        ->assertSee('Avanza Uji')
        // Satu unit tidak bisa dibatalkan sebagian, jadi isiannya tidak ada
        ->assertDontSee('Jumlah peserta yang dibatalkan')
        ->set('alasan', 'kondisi_kesehatan')
        ->set('bank', 'BCA')
        ->set('nomorRekening', '1234567890')
        ->set('setuju', true)
        ->call('ajukan')
        ->assertHasNoErrors()
        ->assertSet('terkirim', true);

    $pembatalan = Pembatalan::firstOrFail();

    expect($pembatalan->kode_pendaftaran)->toBe($sewa->kode)
        ->and($pembatalan->pesanan()->id)->toBe($sewa->id)
        ->and($pembatalan->jumlah_dibatalkan)->toBe(1);
});

test('tangga pengembalian yang tampil mengikuti jenis pesanannya', function () {
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => 'Avanza Uji', 'nama' => 'Rina Wijaya',
        'whatsapp' => '081298765432', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 2,
        'tanggal_mulai' => now()->addWeeks(2)->toDateString(), 'jam_mulai' => '08:00',
        'estimasi_biaya' => 1000000, 'status' => 'dp_masuk',
    ]);

    // Orang membuat keputusan membatalkan berdasarkan angka yang ia baca di
    // sini. Menampilkan tangga open trip kepada penyewa kendaraan bukan
    // sekadar keliru di layar — ia menyesatkan keputusan uang.
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $sewa->kode)
        ->set('empatDigit', '5432')
        ->assertSee('Lebih dari 7 hari sebelum mulai sewa')
        ->assertSee('25% dari total biaya')
        ->assertSee('Tidak datang tanpa kabar')
        ->assertDontSee('30 hari sebelum keberangkatan')
        // Aturan khas sewa yang tidak muat di tabel ikut tampil
        ->assertSee('biaya sopir satu hari');

    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->assertSee('Lebih dari 30 hari sebelum keberangkatan')
        ->assertDontSee('sebelum mulai sewa');
});

test('halaman kebijakan memuat tangga sewa dari sumber yang sama', function () {
    // Dua tempat yang mengeja aturan yang sama akan berbeda cepat atau lambat.
    $this->get(route('kebijakan-pengembalian'))
        ->assertOk()
        ->assertSee('Pembatalan Sewa Kendaraan')
        ->assertSee('24 jam – 3 hari sebelum mulai sewa')
        ->assertSee('25% dari total biaya')
        ->assertSee('seluruh pembayaran dikembalikan penuh tanpa potongan apa pun')
        // Aturan lama yang diketik langsung di halaman sudah tidak ada lagi
        ->assertDontSee('uang muka kembali 50%');
});

test('pelunasan lebih awal tidak lagi menghapus potongan pembatalan', function () {
    // Dulu potongan dihitung dari uang muka dan sisanya selalu dikembalikan
    // penuh. Pelanggan yang melunasi di awal lalu batal H-3 hanya kehilangan
    // 30% — padahal pada hari itu biaya Orcha sudah keluar hampir seluruhnya.
    // Yang membayar paling awal justru paling terlindungi; itu terbalik.
    $halaman = $this->get(route('kebijakan-pengembalian'))->assertOk();

    $halaman->assertSee('Potongan dihitung dari')
        ->assertSee('total biaya pemesanan')
        ->assertSee('100% dari total biaya')
        // Batas yang menjaga arah sebaliknya: yang baru bayar DP tidak
        // tiba-tiba berutang saat membatalkan di menit akhir.
        ->assertSee('tidak pernah melebihi jumlah yang sudah Anda bayarkan')
        // Kalimat lama yang justru menjadi sebab kerugiannya sudah hilang
        ->assertDontSee('sisa pembayaran di luar uang muka dikembalikan penuh');
});

test('aturan potongan ikut tampil di formulir pembatalan', function () {
    // Keputusan membatalkan diambil di layar formulir, bukan di halaman
    // kebijakan yang jarang dibuka.
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->assertSee('Melunasi lebih awal tidak menghapus potongan')
        ->assertSee('tidak pernah melebihi jumlah yang sudah Anda bayarkan');
});

test('isian pemohon terisi sendiri dari pesanan yang ditemukan', function () {
    // Orang yang sedang membatalkan biasanya terburu-buru; mengetik ulang
    // nama dan nomor yang sudah ada di pesanannya hanya menambah salah ketik,
    // dan nomor yang salah berarti perhitungan pengembalian tidak sampai.
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->assertSet('nama', 'Budi Santoso')
        ->assertSet('whatsapp', '0812-3456-7890')
        ->assertSet('atasNama', 'Budi Santoso')
        // Jumlah peserta ikut terisi sesuai pesanannya, bukan selalu 1
        ->assertSet('jumlahDibatalkan', 3);
});

test('tautan email mengisi kodenya, tetapi datanya baru terbuka setelah nomor cocok', function () {
    /*
     | Dulu tautan di email langsung membuka seluruh data pemesanan. Enak
     | dipakai, tetapi tautan itu ikut tersalin ke mana-mana — diteruskan ke
     | grup, dikirim ulang, tertangkap tangkapan layar — dan siapa pun yang
     | memegangnya melihat nama, trip, dan tanggal berangkat pemesan.
     |
     | Sekarang tautannya tetap mengisikan kodenya (bagian yang merepotkan
     | untuk diketik), sedangkan yang membuka datanya tetap harus tahu empat
     | digit terakhir nomor pemesan.
     */
    $this->get(route('pembatalan', ['kode' => $this->pendaftaran->kode]))
        ->assertOk()
        ->assertDontSee('Pemesanan ditemukan')
        ->assertDontSee('&quot;atasNama&quot;:&quot;Budi Santoso&quot;', false);

    Volt::test('public.open-trip.pembatalan', ['kode' => $this->pendaftaran->kode])
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->assertSet('atasNama', 'Budi Santoso')
        ->assertSet('jumlahDibatalkan', 3);
});

test('kode yang benar dengan nomor yang salah tidak membuka apa pun', function () {
    /*
     | Inti perbaikannya. Kode pesanan bisa ditebak — 'OT-' + tanggal-bulan +
     | empat karakter yang hanya punya 36 kemungkinan per huruf — dan tidak ada
     | pembatasan percobaan di grup web.
     |
     | Tanpa kunci kedua, penebak yang beruntung melihat nama, trip, dan
     | tanggal berangkat orang lain, lalu boleh mengajukan pembatalan berikut
     | nomor rekening tujuan dananya sendiri.
     */
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '0000')
        ->assertDontSee('Pemesanan ditemukan')
        ->assertDontSee('Budi Santoso')
        // Isian otomatis pun tidak berjalan: tidak ada satu pun data pesanan
        // yang menyeberang ke layar.
        ->assertSet('atasNama', '')
        ->assertSet('nama', '');
});

test('pesan gagalnya tidak membedakan kode salah dari nomor salah', function () {
    /*
     | Pesan yang berbeda akan mengubah halaman ini jadi alat pemeriksa kode:
     | penebak cukup melihat pesan mana yang muncul untuk tahu kodenya sudah
     | benar, lalu menyisakan empat digit saja untuk ditebak.
     */
    $kodeSalah = Volt::test('public.open-trip.pembatalan')
        ->set('kode', 'OT-0000-XXXX')->set('empatDigit', '7890')
        ->call('ajukan')->errors()->get('kode');

    $nomorSalah = Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)->set('empatDigit', '0000')
        ->call('ajukan')->errors()->get('kode');

    expect($kodeSalah)->toBe($nomorSalah)->not->toBeEmpty();
});

test('isian yang sudah diketik pemohon tidak ditimpa isian otomatis', function () {
    // Rekening pengembalian kadang atas nama orang lain, dan nomornya bisa
    // sudah berganti. Yang sudah diketik manusia menang.
    Volt::test('public.open-trip.pembatalan')
        ->set('nama', 'Siti Aminah')
        ->set('atasNama', 'Siti Aminah')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->assertSet('nama', 'Siti Aminah')
        ->assertSet('atasNama', 'Siti Aminah');
});

test('halaman kebijakan pengembalian menautkan formulir pembatalan', function () {
    $this->get(route('kebijakan-pengembalian'))
        ->assertOk()
        ->assertSee(route('pembatalan'), false);
});
