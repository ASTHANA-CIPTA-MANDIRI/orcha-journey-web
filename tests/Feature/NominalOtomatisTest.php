<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\TagihanPesanan;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

/**
 * Nominal di formulir bukti pembayaran diisikan sistem.
 *
 * Sebelumnya pelanggan mengetiknya dari ingatan. Salah ketik satu digit
 * membuat pembayaran tidak cocok dengan mutasi rekening, dan pekerjaan
 * mencocokkannya berakhir di WhatsApp admin.
 */
beforeEach(function () {
    Mail::fake();

    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);

    $this->pendaftaran = PendaftaranOpenTrip::create([
        'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name,
        'nama' => 'Siti Aminah',
        'whatsapp' => '081298765432',
        'jumlah_peserta' => 2,
    ]);
});

function catatBayar(string $kode, int $nominal, string $status = 'menunggu'): KonfirmasiPembayaran
{
    return KonfirmasiPembayaran::create([
        'kode' => $kode,
        'jenis' => 'dp',
        'nominal' => $nominal,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti Aminah',
        'status' => $status,
    ]);
}

test('belum ada pembayaran: yang ditawarkan uang mukanya', function () {
    // 2 × Rp 1.430.000 = Rp 2.860.000, DP 30% = Rp 858.000
    $tagihan = TagihanPesanan::untuk($this->pendaftaran);

    expect($tagihan['total_teks'])->toBe('Rp 2.860.000')
        ->and($tagihan['sudah'])->toBe(0)
        ->and($tagihan['jenis_disarankan'])->toBe('dp')
        ->and($tagihan['nominal_pokok'])->toBe(858000);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->assertSet('jenis', 'dp')
        ->assertSet('nominal', '858000')
        ->assertSet('nominalTeks', '858.000');
});

test('dp sudah masuk: yang ditawarkan sisanya', function () {
    catatBayar($this->pendaftaran->kode, 858000);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->assertSet('jenis', 'pelunasan')
        // 2.860.000 − 858.000
        ->assertSet('nominal', '2002000')
        ->assertSet('nominalTeks', '2.002.000');
});

test('bukti yang masih menunggu dicek tetap dihitung', function () {
    // Pelanggan yang baru mengirim bukti DP satu jam lalu memang sedang
    // menunggu; menawarkan DP yang sama untuk kedua kalinya menyesatkan.
    catatBayar($this->pendaftaran->kode, 858000, 'menunggu');

    expect(TagihanPesanan::untuk($this->pendaftaran)['jenis_disarankan'])->toBe('pelunasan');
});

test('bukti yang ditolak tidak ikut mengurangi tagihan', function () {
    catatBayar($this->pendaftaran->kode, 858000, 'ditolak');

    $tagihan = TagihanPesanan::untuk($this->pendaftaran);

    expect($tagihan['sudah'])->toBe(0)
        ->and($tagihan['jenis_disarankan'])->toBe('dp');
});

test('pembayaran sebagian tetap menawarkan sisa sebenarnya', function () {
    // Pelanggan mentransfer bulat Rp 800.000, bukan Rp 858.000
    catatBayar($this->pendaftaran->kode, 800000);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->assertSet('nominal', '2060000');
});

test('angka yang sudah diketik pelanggan tidak ditimpa', function () {
    // Transfer nyata sering tidak bulat — isian yang berubah sendiri
    // setelah diketik membuat orang berhenti mempercayai formulirnya.
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->set('nominalTeks', '900000')
        ->set('jenis', 'pelunasan')
        ->assertSet('nominal', '900000');
});

test('ganti jenis pembayaran mengubah angka yang ditawarkan', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->assertSet('nominal', '858000')
        ->set('jenis', 'pelunasan')
        // Belum ada yang masuk, jadi pelunasannya sebesar seluruh tagihan
        ->assertSet('nominal', '2860000');
});

test('sudah lunas: sistem berhenti menawarkan angka', function () {
    catatBayar($this->pendaftaran->kode, 2860000, 'diterima');

    $tagihan = TagihanPesanan::untuk($this->pendaftaran);

    expect($tagihan['lunas'])->toBeTrue()
        ->and($tagihan['nominal_disarankan'])->toBe(0)
        ->and(TagihanPesanan::nominalUntukJenis($tagihan, 'pelunasan'))->toBeNull();
});

test('kode yang tidak dikenal tidak mengarang angka', function () {
    expect(TagihanPesanan::untuk(null))->toBe([]);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', 'OT-9999-ZZZZ')
        ->set('empatDigit', '5432')
        ->assertSet('nominal', '')
        ->assertSet('nominalTeks', '');
});

test('pendaftaran tanpa harga sama sekali tidak mengarang angka', function () {
    /*
     | Yang benar-benar tanpa harga: paketnya tidak berharga DAN pendaftarannya
     | tidak membekukan apa pun. Ini keadaan private trip yang baru dibuat dan
     | harganya masih dirundingkan — dan mengarang angka di sini berarti
     | menagih orang untuk kesepakatan yang belum ada.
     */
    $this->pendaftaran->paket->update(['price' => 0]);
    $this->pendaftaran->forceFill(['harga_jual' => null])->save();

    expect(TagihanPesanan::untuk($this->pendaftaran->fresh()))->toBe([]);
});

test('harga paket yang dinolkan TIDAK menghapus tagihan yang sudah berjalan', function () {
    /*
     | Perilaku ini sengaja berubah, dan bukan efek samping.
     |
     | Dulu tagihan dihitung ulang dari harga paket hari ini. Seseorang yang
     | menolkan harga paket — menyiapkan musim baru, salah ketik, apa pun —
     | membuat tagihan SELURUH pendaftaran yang sudah berjalan lenyap jadi
     | kosong. Halaman pembayaran pelanggan mendadak tidak menyebut angka apa
     | pun, dan tidak ada satu pun galat yang menjelaskannya.
     |
     | Sekarang yang dipakai harga yang dibekukan saat ia mendaftar — yang
     | memang harga yang disepakatinya.
     */
    $semula = TagihanPesanan::untuk($this->pendaftaran->fresh())['total'];

    $this->pendaftaran->paket->update(['price' => 0]);

    expect(TagihanPesanan::untuk($this->pendaftaran->fresh())['total'])->toBe($semula);
});

test('tagihan sama persis dengan omzet, bukan dua angka yang berdekatan', function () {
    /*
     | Keduanya menjawab pertanyaan yang sama — berapa uang yang masuk dari
     | pendaftaran ini. Dua angka untuk satu hal yang sama adalah asal
     | perselisihan yang paling sulit diselesaikan: yang satu ditagihkan ke
     | pelanggan, yang satu masuk laporan, dan tidak ada yang bisa menjelaskan
     | kenapa berbeda.
     */
    $daftar = $this->pendaftaran->fresh();

    expect(TagihanPesanan::untuk($daftar)['total'])->toBe($daftar->omzet);
});

test('posisi tagihan baru terbuka setelah nomornya cocok', function () {
    catatBayar($this->pendaftaran->kode, 858000);

    /*
     | Tautan yang cuma membawa kode TIDAK lagi membuka apa pun. Tautan itu
     | ikut tersalin ke mana-mana — diteruskan ke grup, tertangkap tangkapan
     | layar — dan sisa tagihan seseorang bukan hal yang boleh terbaca siapa
     | pun yang kebetulan memegangnya.
     */
    $this->get(route('konfirmasi-pembayaran', ['kode' => $this->pendaftaran->kode]))
        ->assertOk()
        ->assertDontSee('Total tagihan')
        ->assertDontSee('Rp 2.002.000');

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->assertSee('Total tagihan')
        ->assertSee('Rp 2.860.000')
        ->assertSee('Sudah dilaporkan')
        ->assertSee('Rp 858.000')
        ->assertSee('Rp 2.002.000')
        // Kalimatnya berubah sejak ada kode unik: yang perlu dibaca pelanggan
        // bukan lagi "terisi otomatis", melainkan bahwa angka terakhirnya
        // harus ditransfer apa adanya.
        ->assertSee('tepat sampai angka');
});

test('nominal transfer memuat kode unik yang tetap', function () {
    /*
     | Tagihan bulat memaksa admin mencocokkan tangkapan layar dengan mutasi
     | rekening satu per satu — dan sejak kursi dilepas otomatis dalam 72 jam,
     | verifikasi yang lambat langsung berbiaya kursi.
     */
    $tagihan = App\Support\TagihanPesanan::untuk($this->pendaftaran);

    expect($tagihan['kode_unik'])->toBeGreaterThan(0)
        // Tidak pernah mencapai seribu, sesuai batas yang ditetapkan.
        ->and($tagihan['kode_unik'])->toBeLessThan(1000)
        ->and($tagihan['nominal_disarankan'])
        ->toBe($tagihan['nominal_pokok'] + $tagihan['kode_unik']);
});

test('kode unik tidak berubah tiap halaman dibuka', function () {
    /*
     | Pelanggan sering membuka halaman pembayaran berkali-kali — melihat
     | nominalnya, menutup, membuka lagi saat sudah di depan aplikasi bank.
     | Angka yang berubah tiap muat akan membuatnya mentransfer jumlah yang
     | tidak kita tunggu, dan justru merusak hal yang hendak diperbaiki.
     */
    $satu = App\Support\TagihanPesanan::untuk($this->pendaftaran)['kode_unik'];
    $dua = App\Support\TagihanPesanan::untuk($this->pendaftaran->fresh())['kode_unik'];

    expect($satu)->toBe($dua);
});

test('dua pemesanan berbeda mendapat kode unik yang berbeda', function () {
    $lain = App\Models\OpenTrip\PendaftaranOpenTrip::create([
        'nama' => 'Siti', 'whatsapp' => '081200000000', 'jumlah_peserta' => 1,
        'nama_paket' => 'Open Trip Bromo', 'harga_jual' => 2860000,
    ]);

    expect(App\Support\TagihanPesanan::kodeUnik($this->pendaftaran))
        ->not->toBe(App\Support\TagihanPesanan::kodeUnik($lain));
});

test('yang sudah lunas tidak lagi diberi kode unik', function () {
    // Tidak ada yang perlu ditransfer, jadi tidak ada yang perlu dicocokkan.
    $tagihan = App\Support\TagihanPesanan::untuk(null);

    expect($tagihan['kode_unik'] ?? 0)->toBe(0);
});
