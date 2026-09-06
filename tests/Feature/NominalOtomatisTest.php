<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\TagihanPesanan;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

/**
 * Angka yang ditagihkan dihitung sistem, bukan diketik pelanggan.
 *
 * Dulu ia mengetiknya dari ingatan, dan salah ketik satu digit membuat
 * pembayaran tidak cocok dengan mutasi rekening — pekerjaan yang berakhir di
 * WhatsApp admin. Sejak pembayaran publik lewat gerbang DOKU, angkanya bahkan
 * tidak lagi bisa diketik: ia dikunci di tagihan gerbang sebelum halaman
 * pembayaran terbuka.
 */
beforeEach(function () {
    Mail::fake();

    // Halaman publik hanya menggambar pilihan bayarnya bila gerbangnya hidup.
    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');

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

/** Angka rupiah seperti yang tertulis di layar. */
function rupiah(int $angka): string
{
    return 'Rp '.number_format($angka, 0, ',', '.');
}

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
        ->and($tagihan['dp'])->toBe(858000);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->assertSet('jenis', 'dp')
        // Harga apa adanya. Kode unik baru ditempelkan setelah tombol bayar
        // ditekan — lihat PembayaranDokuTest.
        ->assertSee(rupiah(858000));
});

test('dp sudah masuk: yang ditawarkan sisanya', function () {
    catatBayar($this->pendaftaran->kode, 858000);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        // Uang muka tidak ditawarkan dua kali; yang tersisa pelunasannya.
        ->assertSet('jenis', 'pelunasan')
        // 2.860.000 − 858.000
        ->assertSee(rupiah(2002000))
        ->assertDontSee('Uang Muka 30%');
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
        ->assertSee(rupiah(2060000));
});

test('pilihan yang sudah ditekan pelanggan tidak ditimpa', function () {
    /*
     | Pilihan yang berubah sendiri setelah ditekan adalah cara tercepat
     | membuat orang berhenti mempercayai angka di layar — dan yang sedang ia
     | baca adalah angka yang akan keluar dari rekeningnya.
     |
     | Dulu yang dijaga isian nominalnya. Isian itu ikut tercabut bersama
     | formulir buktinya; yang tersisa untuk dijaga adalah pilihan jenisnya.
     */
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->set('jenis', 'pelunasan')
        // Mengetik ulang kodenya tidak menarik pilihannya kembali ke DP.
        ->set('kode', $this->pendaftaran->kode)
        ->assertSet('jenis', 'pelunasan');
});

test('kedua pilihan tampil berikut angkanya masing-masing', function () {
    /*
     | Angkanya harus terbaca SEBELUM memilih, bukan sesudah.
     |
     | Yang dipilih di sini menentukan berapa uang yang keluar dari rekening
     | orang. Menyembunyikan salah satunya di balik pilihan yang harus ditekan
     | dulu justru menutupi bagian yang paling perlu dibandingkan.
     */
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->assertSee(rupiah(858000))
        // Belum ada yang masuk, jadi pelunasannya sebesar seluruh tagihan
        ->assertSee(rupiah(2860000));
});

test('sudah lunas: sistem berhenti menawarkan angka', function () {
    catatBayar($this->pendaftaran->kode, 2860000, 'diterima');

    $tagihan = TagihanPesanan::untuk($this->pendaftaran);

    expect($tagihan['lunas'])->toBeTrue()
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
        // "Sudah dibayar", bukan "Sudah dilaporkan": tiap rupiah di kolom itu
        // kini dipastikan gerbang, bukan diklaim lewat gambar.
        ->assertSee('Sudah dibayar')
        ->assertSee('Rp 858.000')
        ->assertSee('Rp 2.002.000')
        // Angka di kartu pilihan masih harga apa adanya; halaman menyebut
        // sendiri bahwa kode uniknya menyusul, supaya selisih di langkah
        // berikutnya tidak datang sebagai kejutan.
        ->assertSee('belum termasuk kode unik');
});

test('yang sudah lunas tidak lagi ditawari apa pun untuk dibayar', function () {
    catatBayar($this->pendaftaran->kode, 2860000, 'diterima');

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '5432')
        ->assertSee('sudah lunas')
        ->assertDontSee('Pilih Pembayaran');
});
