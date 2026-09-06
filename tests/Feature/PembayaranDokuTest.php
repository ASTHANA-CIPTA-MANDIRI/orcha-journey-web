<?php

use App\Mail\TagihanPembayaran;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PembayaranDoku;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Services\DokuCheckout;
use App\Support\TagihanPesanan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

/**
 * Pembayaran publik lewat gerbang DOKU.
 *
 * Yang diuji di sini bukan "apakah DOKU jalan" — itu urusan sandbox DOKU.
 * Yang diuji adalah tiga hal yang, bila salah, membuat orang bisa membayar
 * tanpa membayar:
 *
 *   1. Nominalnya dihitung server, tidak pernah diterima dari peramban.
 *   2. Notifikasi tanpa tanda tangan sah tidak mencatat apa pun.
 *   3. Notifikasi yang diulang tidak mencatat dua kali.
 */
beforeEach(function () {
    config()->set('doku.aktif', true);
    config()->set('doku.lingkungan', 'sandbox');
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');

    $this->pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'email' => 'budi@example.test',
        'jumlah_peserta' => 2,
        'harga_jual' => 1000000,
        'nama_paket' => 'Open Trip Banyuwangi',
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ])->fresh();
});

/** Halaman DOKU palsu yang selalu berhasil. */
function dokuMenjawab(string $url = 'https://sandbox.doku.com/checkout/link/abc123'): void
{
    Http::fake([
        'api-sandbox.doku.com/*' => Http::response([
            'response' => [
                'payment' => [
                    'url' => $url,
                    'token_id' => 'token-uji',
                    'expired_date' => '20260905120000',
                ],
            ],
        ], 200),
    ]);
}

/** Mengirim notifikasi seperti DOKU: badan mentah + tanda tangan di header. */
function kirimNotifikasi(array $badan, bool $tandaTanganSah = true)
{
    $mentah = json_encode($badan);
    $requestId = 'notif-'.uniqid();
    $waktu = gmdate('Y-m-d\TH:i:s\Z');

    $tandaTangan = $tandaTanganSah
        ? app(DokuCheckout::class)->tandaTangan('/pembayaran/doku/notifikasi', $requestId, $waktu, $mentah)
        : 'HMACSHA256=jelas-salah';

    return test()->call('POST', '/pembayaran/doku/notifikasi', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_CLIENT_ID' => 'BRN-0227-UJI',
        'HTTP_REQUEST_ID' => $requestId,
        'HTTP_REQUEST_TIMESTAMP' => $waktu,
        'HTTP_SIGNATURE' => $tandaTangan,
    ], $mentah);
}

/* -------------------------------------------------------------------------
 | Tanda tangan
 * ------------------------------------------------------------------------- */

test('tanda tangan mengikuti spesifikasi non-SNAP DOKU', function () {
    /*
     | Nilai harapannya DITULIS TETAP, bukan dihitung ulang di sini.
     |
     | Menghitungnya dengan rumus yang sama dengan yang diuji akan selalu
     | cocok — termasuk saat rumusnya salah. Angka di bawah dihitung sekali
     | dari contoh di dokumentasi DOKU; kalau ada yang menukar urutan komponen
     | atau mengubah kapitalisasinya, uji ini yang jatuh, bukan pelanggan yang
     | gagal membayar di produksi.
     */
    $tandaTangan = app(DokuCheckout::class)->tandaTangan(
        '/checkout/v1/payment',
        '11111111-2222-3333-4444-555555555555',
        '2026-09-04T00:00:00Z',
        '{"order":{"amount":20000,"invoice_number":"INV-0001"}}',
    );

    expect($tandaTangan)->toBe('HMACSHA256=EnOQVp3bJB/JEiMXZ46up4JVJ8LyHfB3ob6lVW3rkPE=');
});

test('kode unik dikirim sebagai baris tagihan tersendiri', function () {
    /*
     | DOKU mengirim surel tagihannya sendiri ke pelanggan, dan isi surel itu
     | disusun dari rincian yang kita kirim. Selama kode uniknya dilebur ke
     | dalam harga, surel itu cuma menyebut satu angka bulat-ganjil yang tidak
     | bisa diterangkan siapa pun yang membacanya seminggu kemudian.
     |
     | Halaman kita memang sudah menerangkannya sebelum ia membayar — tetapi
     | halaman itu ditutup. Surel DOKU yang tinggal.
     */
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'dp')
        ->call('bayar');

    $bayar = PembayaranDoku::firstOrFail();

    Http::assertSent(function ($permintaan) use ($bayar) {
        $baris = $permintaan['order']['line_items'];

        return count($baris) === 2
            && $baris[0]['name'] === 'Uang Muka (DP) - Open Trip Banyuwangi'
            && $baris[0]['price'] === 600000
            && $baris[1]['name'] === 'Kode unik pembayaran'
            && $baris[1]['price'] === $bayar->kode_unik
            // Dan jumlahnya sama dengan yang benar-benar ditagihkan.
            && $baris[0]['price'] + $baris[1]['price'] === $permintaan['order']['amount'];
    });
});

test('rincian yang tidak berjumlah nominal ditolak sebelum tagihannya lahir', function () {
    /*
     | Yang dijaga bukan DOKU — ia menagih order.amount, apa pun isi
     | rinciannya. Yang dijaga pelanggan: tagihan yang barisnya berjumlah
     | Rp 858.000 tetapi menagih Rp 859.257 tidak bisa dipertanggungjawabkan,
     | dan kekeliruan seperti itu lahir dari satu penyuntingan kecil yang lupa
     | menyesuaikan sisi lainnya.
     */
    dokuMenjawab();

    expect(fn () => app(DokuCheckout::class)->buatHalamanBayar(
        invoice: 'UJI-SELISIH',
        nominal: 100000,
        judul: 'Uji',
        rincian: [['nama' => 'Salah', 'harga' => 99000]],
    ))->toThrow(RuntimeException::class, 'Rincian tagihan berjumlah 99000, bukan 100000.');

    // Ditolak sebelum satu permintaan pun keluar.
    Http::assertNothingSent();
});

test('teks yang dikirim ke DOKU dibersihkan dari karakter terlarang', function () {
    /*
     | DOKU menolak SELURUH permintaan dengan HTTP 400 bila ada satu saja
     | karakter di luar a-z A-Z 0-9 . - / + , = _ : ' @ % ( ) dan spasi.
     |
     | Ini bukan kasus aneh-aneh, dan sudah pernah terjadi: kalimat yang kita
     | susun sendiri memakai tanda pisah "—", dan nama paket seperti
     | "Bromo & Ijen" memuat "&". Keduanya gagal di tempat paling mahal —
     | tepat setelah pelanggan menekan tombol bayar.
     |
     | Yang dibuang diganti spasi, bukan dihapus rapat: "Bromo&Ijen" yang
     | menjadi "BromoIjen" lebih sulit dibaca, dan teks ini muncul di halaman
     | pembayaran serta surel tagihan pelanggan.
     */
    dokuMenjawab();

    app(DokuCheckout::class)->buatHalamanBayar(
        invoice: 'UJI-BERSIH',
        nominal: 100000,
        judul: 'Uang Muka (DP) — Bromo & Ijen',
        pelanggan: ['nama' => 'Budi «Santoso»'],
    );

    Http::assertSent(function ($permintaan) {
        return $permintaan['order']['line_items'][0]['name'] === 'Uang Muka (DP) Bromo Ijen'
            && $permintaan['customer']['name'] === 'Budi Santoso';
    });
});

test('judul pembayaran memakai tanda pisah yang diterima DOKU', function () {
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'dp')
        ->call('bayar');

    Http::assertSent(fn ($permintaan) => $permintaan['order']['line_items'][0]['name']
        === 'Uang Muka (DP) - Open Trip Banyuwangi');
});

/* -------------------------------------------------------------------------
 | Kode unik
 * ------------------------------------------------------------------------- */

test('kode unik selalu di antara 500 dan 1500', function () {
    for ($i = 0; $i < 200; $i++) {
        expect(PembayaranDoku::kodeUnikAcak())
            ->toBeGreaterThanOrEqual(500)
            ->toBeLessThanOrEqual(1500);
    }
});

test('kode unik diacak, bukan diturunkan dari kode pesanan', function () {
    /*
     | Dulu ia crc32 dari kode pesanan, supaya selalu menghasilkan angka yang
     | sama — pelanggan mengetik sendiri nominalnya di aplikasi bank, dan angka
     | yang berubah tiap muat membuatnya mentransfer jumlah yang tidak kita
     | tunggu.
     |
     | Alasan itu habis begitu pembayaran lewat gerbang: nominalnya dikunci ke
     | dalam tagihan DOKU, dan pelanggan tidak mengetik apa pun.
     |
     | Dua ratus tarikan yang seluruhnya kembar praktis mustahil bila benar
     | acak; bila rumusnya diam-diam kembali menjadi turunan kode pesanan,
     | justru itu yang terjadi.
     */
    $tarikan = collect(range(1, 200))->map(fn () => PembayaranDoku::kodeUnikAcak());

    expect($tarikan->unique()->count())->toBeGreaterThan(50);
});

test('dua percobaan atas pesanan yang sama tidak bernominal kembar', function () {
    dokuMenjawab();

    $kodeUnik = collect(range(1, 6))->map(function () {
        Volt::test('public.open-trip.konfirmasi-pembayaran')
            ->set('kode', $this->pendaftaran->kode)
            ->set('empatDigit', '7890')
            ->set('jenis', 'dp')
            ->call('bayar');

        $bayar = PembayaranDoku::latest('id')->first();

        /*
         | Dimatikan sebelum percobaan berikutnya, karena tagihan yang masih
         | hidup memang SENGAJA dipakai ulang — dan itu perilaku yang benar:
         | dua VA hidup untuk satu tagihan berarti keduanya bisa dibayar.
         |
         | Yang dijaga uji ini keadaan sesudahnya: dua tagihan yang
         | benar-benar berbeda tidak boleh bernominal kembar, supaya keduanya
         | bisa dibedakan di mutasi rekening.
         */
        $bayar->update(['kedaluwarsa_pada' => now()->subMinute()]);

        return $bayar->kode_unik;
    });

    // Di mutasi rekening, dua baris beda angka bisa dibedakan; dua baris
    // seangka hanya bisa ditebak.
    expect($kodeUnik->unique()->count())->toBeGreaterThan(1);
});

/* -------------------------------------------------------------------------
 | Membuka halaman bayar
 * ------------------------------------------------------------------------- */

test('tombol bayar menampilkan angka akhirnya sebelum meninggalkan situs', function () {
    /*
     | Kode unik baru ditempelkan pada langkah ini, jadi nominal yang akan
     | ditagihkan berbeda dari angka yang ditekan pelanggan di kartu pilihan.
     |
     | Melompat langsung ke DOKU berarti ia menemukan selisih itu pertama kali
     | di halaman milik pihak lain, tanpa keterangan apa pun — dan yang
     | dilakukan orang yang menemukan nominal tak terduga di halaman
     | pembayaran adalah berhenti.
     */
    dokuMenjawab();

    $halaman = Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'dp')
        ->call('bayar')
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $bayar = PembayaranDoku::firstOrFail();

    $halaman->assertSee('Total yang harus dibayar')
        ->assertSee('Rp '.number_format($bayar->nominal, 0, ',', '.'))
        ->assertSee((string) $bayar->kode_unik)
        // Alamat DOKU-nya diserahkan sebagai tautan, bukan lompatan.
        ->assertSee('https://sandbox.doku.com/checkout/link/abc123', false);
});

test('kartu pilihan menampilkan harga apa adanya, tanpa kode unik', function () {
    dokuMenjawab();

    $halaman = Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890');

    // Uang muka 30% dari Rp 2.000.000, bulat.
    $halaman->assertSee('Rp 600.000')
        ->assertSee('Rp 2.000.000')
        ->assertDontSee('Total yang harus dibayar');
});

test('ganti pilihan mengembalikan ke kartu harga', function () {
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->call('bayar')
        ->assertSee('Total yang harus dibayar')
        ->call('ulangi')
        ->assertDontSee('Total yang harus dibayar')
        ->assertSee('Pilih Pembayaran');
});

test('nominal yang ditagihkan dihitung server, termasuk kode uniknya', function () {
    /*
     | Peramban tidak lagi punya isian nominal untuk dikirim — isian itu ikut
     | tercabut bersama formulir buktinya, dan uji "halaman publik tidak lagi
     | punya jalan mengirim bukti sendiri" yang menjaganya.
     |
     | Yang dipastikan di sini: angka yang dikirim ke DOKU memang lahir dari
     | tagihan yang tersimpan, bukan dari apa pun yang datang bersama
     | permintaan.
     */
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'dp')
        ->call('bayar');

    $bayar = PembayaranDoku::firstOrFail();

    // omzet 2 × Rp 1.000.000, uang muka 30% = Rp 600.000.
    expect($bayar->nominal_pokok)->toBe(600000)
        ->and($bayar->kode_unik)->toBeGreaterThanOrEqual(500)->toBeLessThanOrEqual(1500)
        ->and($bayar->nominal)->toBe(600000 + $bayar->kode_unik);

    // Dan angka itulah yang benar-benar dikirim ke DOKU.
    Http::assertSent(fn ($permintaan) => $permintaan['order']['amount'] === $bayar->nominal);
});

test('pelunasan menagih seluruh sisanya', function () {
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'pelunasan')
        ->call('bayar');

    expect(PembayaranDoku::firstOrFail()->nominal_pokok)->toBe(2000000);
});

test('halaman bayar yang dibuka belum mengurangi tagihan', function () {
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->call('bayar');

    /*
     | Inti dari tabel yang terpisah.
     |
     | Kalau percobaan pembayaran ikut tercatat sebagai konfirmasi, pelanggan
     | yang membuka halaman bayar tiga kali lalu meninggalkannya akan terlihat
     | sudah membayar tiga kali lipat — dan sisa tagihannya jatuh ke nol tanpa
     | satu rupiah pun masuk.
     */
    $tagihan = TagihanPesanan::untuk($this->pendaftaran->fresh());

    expect($tagihan['sudah'])->toBe(0)
        ->and($tagihan['sisa'])->toBe(2000000)
        ->and(KonfirmasiPembayaran::count())->toBe(0);
});

test('pesanan yang tidak cocok nomornya tidak bisa dibayar', function () {
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        // Empat digit yang salah: kode saja tidak pernah cukup.
        ->set('empatDigit', '0000')
        ->call('bayar')
        ->assertHasErrors('kode');

    expect(PembayaranDoku::count())->toBe(0);
    Http::assertNothingSent();
});

test('gerbang mati tidak pernah memunculkan formulir bukti transfer', function () {
    /*
     | Unggah bukti transfer sudah DICABUT dari sisi publik, dan gerbang yang
     | sedang bermasalah bukan alasan untuk menghidupkannya kembali.
     |
     | Memunculkannya diam-diam justru memulihkan persis hal yang hendak
     | dihilangkan: pembayaran yang dinyatakan lewat gambar, dicek manusia,
     | sementara kursi pelanggan menggantung. Yang benar adalah mengabari
     | apa adanya dan mengantar ke admin — dan jalur admin memang sengaja
     | tidak bisa ditempuh tanpa admin.
     */
    config()->set('doku.aktif', false);

    $this->get(route('konfirmasi-pembayaran'))
        ->assertOk()
        ->assertSee('Pembayaran online sedang tidak tersedia')
        ->assertDontSee('Kirim Bukti Transfer')
        ->assertDontSee('Foto atau tangkapan layar')
        ->assertDontSee('Nama pemilik rekening pengirim');
});

test('halaman publik tidak lagi punya jalan mengirim bukti sendiri', function () {
    // Bukan sekadar tombolnya yang hilang: metodenya sendiri sudah tidak ada,
    // jadi tidak ada yang bisa dipanggil langsung lewat Livewire.
    expect(method_exists(
        Volt::test('public.open-trip.konfirmasi-pembayaran')->instance(),
        'kirim',
    ))->toBeFalse();
});

test('gerbang hidup menampilkan halaman bayar', function () {
    $this->get(route('konfirmasi-pembayaran'))
        ->assertOk()
        ->assertSee('Bayar Pesanan')
        ->assertDontSee('Kirim Bukti Transfer');
});

/* -------------------------------------------------------------------------
 | Surat tagihan
 * ------------------------------------------------------------------------- */

test('tagihan dikirim dari Orcha, bukan menunggu surel gerbang', function () {
    /*
     | DOKU bisa mengirim surelnya sendiri, dan isinya benar. Yang tidak bisa
     | diubah pengirimnya: selalu noreply@doku.com, berbahasa Inggris, dari
     | nama yang tidak dikenal pelanggan. Untuk orang yang baru memesan trip ke
     | Orcha, surat semacam itu lebih mirip penipuan daripada tagihan.
     */
    Mail::fake();
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'dp')
        ->call('bayar');

    $bayar = PembayaranDoku::firstOrFail();

    Mail::assertSent(TagihanPembayaran::class, function ($surat) use ($bayar) {
        return $surat->hasTo('budi@example.test')
            // Angkanya sama persis dengan yang dilihat pelanggan di layar
            // sebelum ia menekan lanjut — tiga tempat, satu penjelasan.
            && $surat->pokok === 600000
            && $surat->kodeUnik === $bayar->kode_unik
            && $surat->nominal === $bayar->nominal
            && $surat->invoice === $bayar->invoice
            && $surat->url === 'https://sandbox.doku.com/checkout/link/abc123';
    });

    // Kotak kantor TIDAK ikut dikirimi. Tiap penekanan tombol bayar membuat
    // satu tagihan, termasuk yang ditinggalkan setengah jalan; kantor dikabari
    // saat uangnya benar-benar masuk, bukan saat halaman bayarnya dibuka.
    Mail::assertNotSent(App\Mail\PemberitahuanFormulir::class);
});

test('faktur tagihan berbentuk faktur, bukan daftar pemberitahuan', function () {
    Mail::fake();
    dokuMenjawab();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'dp')
        ->call('bayar');

    $bayar = PembayaranDoku::firstOrFail();

    Mail::assertSent(TagihanPembayaran::class, function ($surat) use ($bayar) {
        $html = $surat->render();

        return str_contains($html, 'Ditagihkan kepada')
            && str_contains($html, 'Ditagihkan oleh')
            && str_contains($html, config('orcha.pembayaran.atas_nama'))
            // Kode unik jadi barisnya sendiri, bukan dilebur ke dalam total.
            && str_contains($html, 'Kode unik pembayaran')
            && str_contains($html, 'Rp 600.000')
            && str_contains($html, 'Rp '.number_format($bayar->nominal, 0, ',', '.'))
            // Tombol bayar berikut alamat mentahnya untuk klien surat yang
            // memotong tabel.
            && substr_count($html, $bayar->url) >= 2
            /*
             | Tanggal kedaluwarsa terbaca sebagai tanggal.
             |
             | Kata "pukul" pernah ditulis begitu saja di dalam pola format,
             | dan Carbon membaca u, k, dan l sebagai kode format — hasilnya
             | "6 September 2026 p000000k000000Minggu 06:38 WIB" di surat yang
             | sudah terlanjur sampai ke pelanggan.
             */
            && ! str_contains($html, '000000')
            && str_contains($html, $bayar->kedaluwarsa_pada->translatedFormat('j F Y'));
    });
});

test('surat yang gagal terkirim tidak menjatuhkan pembayarannya', function () {
    /*
     | Halaman bayarnya sudah hidup di DOKU dan pelanggan sudah menunggu.
     | Surat yang tidak terkirim adalah kerugian yang jauh lebih kecil
     | daripada tombol bayar yang berakhir dengan pesan galat.
     */
    dokuMenjawab();
    Mail::shouldReceive('send')->andThrow(new RuntimeException('server surat mati'));

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'dp')
        ->call('bayar')
        ->assertHasNoErrors()
        ->assertSee('Total yang harus dibayar');

    expect(PembayaranDoku::firstOrFail()->url)->toBe('https://sandbox.doku.com/checkout/link/abc123');
});

/* -------------------------------------------------------------------------
 | Notifikasi
 * ------------------------------------------------------------------------- */

function pembayaranMenunggu(PendaftaranOpenTrip $pendaftaran): PembayaranDoku
{
    return PembayaranDoku::create([
        'invoice' => $pendaftaran->kode.'-DP-TEST',
        'kode' => $pendaftaran->kode,
        'jenis' => 'dp',
        'nominal_pokok' => 600000,
        'kode_unik' => 913,
        'nominal' => 600913,
        'status' => 'menunggu',
    ]);
}

function badanSukses(string $invoice, int $nominal = 600913): array
{
    return [
        'service' => ['id' => 'VIRTUAL_ACCOUNT'],
        'acquirer' => ['id' => 'BCA'],
        'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
        'transaction' => ['status' => 'SUCCESS', 'date' => '2026-09-04T03:24:23Z'],
        'order' => ['invoice_number' => $invoice, 'amount' => $nominal],
    ];
}

test('notifikasi tanpa tanda tangan sah ditolak dan tidak mencatat apa pun', function () {
    $bayar = pembayaranMenunggu($this->pendaftaran);

    kirimNotifikasi(badanSukses($bayar->invoice), tandaTanganSah: false)
        ->assertStatus(401);

    expect(KonfirmasiPembayaran::count())->toBe(0)
        ->and($bayar->fresh()->status)->toBe('menunggu')
        ->and($this->pendaftaran->fresh()->status)->not->toBe('dp_masuk');
});

test('notifikasi sah mencatat pembayaran sebagai diterima', function () {
    $bayar = pembayaranMenunggu($this->pendaftaran);

    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();

    $konfirmasi = KonfirmasiPembayaran::firstOrFail();

    expect($konfirmasi->kode)->toBe($this->pendaftaran->kode)
        /*
         | Yang tercatat sebagai pembayaran adalah POKOKNYA, bukan yang
         | ditagihkan. Uang yang masuk memang Rp 600.913 dan angka itu tersimpan
         | utuh di tbl_pembayaran_doku — tetapi Rp 913 di antaranya penanda,
         | bukan cicilan.
         */
        ->and($konfirmasi->nominal)->toBe(600000)
        ->and($konfirmasi->kanal)->toBe('doku')
        // Langsung diterima: notifikasi bertanda tangan sah adalah uang yang
        // sudah ada, bukan klaim yang menunggu diperiksa.
        ->and($konfirmasi->status)->toBe('diterima')
        ->and($konfirmasi->bank_pengirim)->toBe('Virtual Account BCA');

    expect($bayar->fresh()->status)->toBe('berhasil')
        ->and($bayar->fresh()->konfirmasi_id)->toBe($konfirmasi->id);

    // Status pesanannya ikut maju, lewat jalur yang sama dengan persetujuan
    // admin di lemon.
    expect($this->pendaftaran->fresh()->status)->toBe('dp_masuk');
});

test('notifikasi yang diulang tidak mencatat dua kali', function () {
    $bayar = pembayaranMenunggu($this->pendaftaran);

    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();
    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();
    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();

    expect(KonfirmasiPembayaran::count())->toBe(1)
        ->and(TagihanPesanan::untuk($this->pendaftaran->fresh())['sudah'])->toBe(600000);
});

test('pelunasan lewat DOKU membuat pesanan lunas', function () {
    $bayar = PembayaranDoku::create([
        'invoice' => $this->pendaftaran->kode.'-PL-TEST',
        'kode' => $this->pendaftaran->kode,
        'jenis' => 'pelunasan',
        'nominal_pokok' => 2000000,
        'kode_unik' => 913,
        'nominal' => 2000913,
        'status' => 'menunggu',
    ]);

    kirimNotifikasi(badanSukses($bayar->invoice, 2000913))->assertOk();

    expect($this->pendaftaran->fresh()->status)->toBe('lunas');
});

test('notifikasi gagal menandai percobaannya, tanpa mencatat pembayaran', function () {
    $bayar = pembayaranMenunggu($this->pendaftaran);

    $badan = badanSukses($bayar->invoice);
    $badan['transaction']['status'] = 'FAILED';

    kirimNotifikasi($badan)->assertOk();

    expect($bayar->fresh()->status)->toBe('gagal')
        ->and(KonfirmasiPembayaran::count())->toBe(0);
});

test('nomor tagihan yang tidak dikenal dijawab 200 tanpa mencatat apa pun', function () {
    /*
     | Sengaja BUKAN 4xx.
     |
     | DOKU mengulang notifikasi yang tidak dijawab 200, dan mengulang tagihan
     | yang tidak kita kenal seribu kali tidak akan membuatnya dikenal — yang
     | terjadi hanyalah antrean DOKU penuh oleh pesan yang tidak pernah bisa
     | selesai. Penelusurannya lewat log, bukan lewat kode jawaban.
     */
    kirimNotifikasi(badanSukses('OT-9999-XXXX-DP-ZZZZ'))->assertOk();

    expect(KonfirmasiPembayaran::count())->toBe(0);
});

test('nominal notifikasi yang berbeda dicatat sesuai yang benar-benar dibayar', function () {
    $bayar = pembayaranMenunggu($this->pendaftaran);

    kirimNotifikasi(badanSukses($bayar->invoice, 500000))->assertOk();

    /*
     | Uang yang nyata berpindah adalah yang dipakai menghitung, bukan yang
     | kita harapkan — selisihnya urusan log, bukan urusan menyeragamkan angka
     | diam-diam. Kode uniknya tetap dikeluarkan, karena ia penanda pada
     | pembayaran mana pun yang datang lewat tagihan ini.
     */
    expect(KonfirmasiPembayaran::firstOrFail()->nominal)->toBe(500000 - 913);
});

test('kode unik tidak pernah ikut mengurangi tagihan', function () {
    /*
     | Kesalahan ini MENUMPUK, dan itu yang membuatnya berbahaya. Sekali bayar
     | melesetnya cuma ratusan rupiah; tiga kali bayar dan pesanannya dinyatakan
     | lunas padahal pelanggan masih kurang beberapa ribu.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();

    $tagihan = TagihanPesanan::untuk($this->pendaftaran->fresh());

    // Total 2.000.000, uang muka 600.000 — sisanya bulat, tanpa terpotong 913.
    expect($tagihan['sudah'])->toBe(600000)
        ->and($tagihan['sisa'])->toBe(1400000);

    // Yang benar-benar diterima tetap tercatat utuh, untuk rekonsiliasi.
    expect(PembayaranDoku::firstOrFail()->nominal)->toBe(600913);
});

/* -------------------------------------------------------------------------
 | Cek status — jalur cadangan saat notifikasi tidak sampai
 * ------------------------------------------------------------------------- */

test('tanda tangan GET disusun tanpa Digest', function () {
    /*
     | Permintaan GET tidak punya badan, dan spesifikasi DOKU meminta
     | komponennya berhenti di Request-Target. Menambahkan "Digest:" berisi
     | hash dari string kosong menghasilkan tanda tangan yang selalu ditolak —
     | dan penolakannya tidak menyebut sebabnya, jadi kekeliruan ini mahal
     | untuk ditelusuri.
     */
    $doku = app(DokuCheckout::class);

    $tanpaBadan = $doku->tandaTangan('/orders/v1/status/INV-1', 'req-1', '2026-09-04T00:00:00Z');
    $denganBadan = $doku->tandaTangan('/orders/v1/status/INV-1', 'req-1', '2026-09-04T00:00:00Z', '');

    // Badan kosong tetap menghasilkan Digest, dan itu tanda tangan yang lain.
    expect($tanpaBadan)->not->toBe($denganBadan);

    $harusnya = 'HMACSHA256='.base64_encode(hash_hmac('sha256', implode("\n", [
        'Client-Id:BRN-0227-UJI',
        'Request-Id:req-1',
        'Request-Timestamp:2026-09-04T00:00:00Z',
        'Request-Target:/orders/v1/status/INV-1',
    ]), 'SK-UJI-RAHASIA', true));

    expect($tanpaBadan)->toBe($harusnya);
});

test('notifikasi yang hilang dipulihkan dengan bertanya ke DOKU', function () {
    /*
     | Notifikasi dikirim SEKALI ke alamat yang kita daftarkan. Kalau saat itu
     | server sedang di-deploy, jaringannya putus, atau alamatnya sudah basi,
     | kabarnya hilang — dan yang terlihat kemudian adalah pelanggan yang sudah
     | membayar sementara layar kami tetap menyatakan menunggu.
     |
     | Sudah pernah terjadi sungguhan: terowongan pengujian mati, DOKU mengirim
     | ke alamat yang tidak ada lagi, dan pembayaran yang berhasil menggantung
     | tanpa satu pun cara memperbaikinya selain mengetik ke basis data.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);

    Http::fake([
        'api-sandbox.doku.com/orders/v1/status/*' => Http::response(badanSukses($bayar->invoice), 200),
    ]);

    Volt::test('public.open-trip.pembayaran-selesai', ['invoice' => $bayar->invoice])
        ->call('periksa')
        ->assertSee('Pembayaran berhasil');

    // Akibatnya identik dengan yang masuk lewat notifikasi: bukan jalur kedua
    // yang setengah jadi, melainkan jalur yang sama dari arah berlawanan.
    expect($bayar->fresh()->status)->toBe('berhasil')
        ->and(KonfirmasiPembayaran::where('kode', $this->pendaftaran->kode)->where('status', 'diterima')->count())->toBe(1)
        ->and($this->pendaftaran->fresh()->status)->toBe('dp_masuk');
});

test('yang sudah selesai atau kedaluwarsa tidak ditanyakan lagi', function () {
    Http::fake();

    $lunas = pembayaranMenunggu($this->pendaftaran);
    $lunas->update(['status' => 'berhasil']);

    Volt::test('public.open-trip.pembayaran-selesai', ['invoice' => $lunas->invoice])->call('periksa');

    $mati = PembayaranDoku::create([
        'invoice' => $this->pendaftaran->kode.'-DP-MATI',
        'kode' => $this->pendaftaran->kode,
        'jenis' => 'dp',
        'nominal_pokok' => 600000,
        'kode_unik' => 913,
        'nominal' => 600913,
        'status' => 'menunggu',
        'kedaluwarsa_pada' => now()->subHour(),
    ]);

    Volt::test('public.open-trip.pembayaran-selesai', ['invoice' => $mati->invoice])->call('periksa');

    // Bertanya tentang tagihan yang jawabannya tidak akan berubah lagi cuma
    // menghabiskan kuota panggilan yang dibutuhkan tagihan yang masih hidup.
    Http::assertNothingSent();
});

test('DOKU yang tidak menjawab tidak mengubah apa pun', function () {
    $bayar = pembayaranMenunggu($this->pendaftaran);

    Http::fake(['api-sandbox.doku.com/*' => Http::response(['error' => 'not found'], 404)]);

    Volt::test('public.open-trip.pembayaran-selesai', ['invoice' => $bayar->invoice])
        ->call('periksa')
        ->assertSee('Sedang memastikan pembayaran Anda');

    expect($bayar->fresh()->status)->toBe('menunggu')
        ->and(KonfirmasiPembayaran::count())->toBe(0);
});

/* -------------------------------------------------------------------------
 | Catatan admin: siapa yang boleh membacanya
 * ------------------------------------------------------------------------- */

test('catatan admin tidak ikut ke kwitansi pelanggan', function () {
    /*
     | Layar admin menjanjikan kolom itu internal — kalimat yang mengundang
     | orang menuliskan dugaan, pengingat, atau nama rekan yang harus mengecek.
     | Selama isinya diteruskan ke kwitansi, janji itu bohong, dan yang
     | menanggung akibatnya adalah pelanggan yang membaca catatan yang tidak
     | pernah ditujukan padanya.
     |
     | Untuk pembayaran gerbang, catatannya bahkan tidak berguna baginya: ia
     | berisi nomor tagihan dan keterangan kode unik untuk rekonsiliasi admin.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();

    $konfirmasi = KonfirmasiPembayaran::firstOrFail();

    expect($konfirmasi->status)->toBe('diterima')
        /*
         | Catatannya ADA, dan memang harus ada — tetapi PENDEK.
         |
         | Versi panjangnya dulu mengulang nominal, kode unik, dan nomor
         | tagihan, padahal ketiganya sudah tampil terstruktur di layar tepat
         | di atasnya. Satu baris pembayaran memuat fakta yang sama tiga kali,
         | dan yang membacanya berhenti sebelum sampai ke yang penting.
         */
        ->and($konfirmasi->catatan_admin)->toContain('Masuk sendiri lewat gerbang')
        ->and($konfirmasi->catatan_admin)->not->toContain('Nomor tagihan')
        // Tetapi tidak diteruskan ke berkas yang dibaca pelanggan.
        ->and(App\Support\KabarPembayaran::catatanUntukPelanggan($konfirmasi))->toBeNull();
});

test('alasan penolakan tetap sampai ke pelanggan', function () {
    /*
     | Satu-satunya pengecualian, dan sengaja: di sini catatannya JUSTRU alasan
     | yang harus ia baca supaya tahu apa yang perlu diperbaiki. Menutupnya
     | rapat berarti mengirim penolakan tanpa sebab.
     */
    $ditolak = KonfirmasiPembayaran::create([
        'kode' => $this->pendaftaran->kode,
        'jenis' => 'dp',
        'nominal' => 600000,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso',
        'status' => 'ditolak',
        'catatan_admin' => 'Nominalnya kurang Rp 50.000 dari uang muka.',
    ]);

    expect(App\Support\KabarPembayaran::catatanUntukPelanggan($ditolak))
        ->toBe('Nominalnya kurang Rp 50.000 dari uang muka.');
});

test('detail pendaftaran ikut mengirim kanal pembayarannya', function () {
    /*
     | Dua tempat menyusun daftar pembayaran — PembayaranResource untuk layar
     | bukti, dan endpoint detail pendaftaran untuk kartu di halaman pesanan.
     | Keduanya sempat menyusunnya sendiri-sendiri, dan kanal tidak ikut
     | terkirim di salah satunya.
     |
     | Akibatnya layar pesanan tetap memajang kotak bukti untuk pembayaran yang
     | memang tidak punya bukti — tanpa satu pun galat, tanpa satu pun tes
     | merah. Ia hanya menampilkan hal yang salah, diam-diam.
     */
    config()->set('orcha.api.kunci', 'kunci-uji-kanal');

    $bayar = pembayaranMenunggu($this->pendaftaran);
    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();

    $pembayaran = test()->getJson(
        '/api/v1/pendaftaran/'.$this->pendaftaran->id,
        ['X-Orcha-Key' => 'kunci-uji-kanal', 'Accept' => 'application/json'],
    )->assertOk()->json('data.pembayaran');

    expect($pembayaran)->toHaveCount(1)
        ->and($pembayaran[0]['kanal'])->toBe('doku')
        ->and($pembayaran[0]['rincian']['kode_unik'])->toBe(913)
        ->and($pembayaran[0]['rincian']['invoice'])->toBe($bayar->invoice);
});

test('bukti transfer manual tetap dikirim tanpa rincian gerbang', function () {
    config()->set('orcha.api.kunci', 'kunci-uji-kanal');

    KonfirmasiPembayaran::create([
        'kode' => $this->pendaftaran->kode,
        'jenis' => 'dp',
        'nominal' => 600000,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso',
        'status' => 'diterima',
    ]);

    $pembayaran = test()->getJson(
        '/api/v1/pendaftaran/'.$this->pendaftaran->id,
        ['X-Orcha-Key' => 'kunci-uji-kanal', 'Accept' => 'application/json'],
    )->json('data.pembayaran');

    expect($pembayaran[0]['kanal'])->toBe('transfer')
        ->and($pembayaran[0]['rincian'])->toBeNull();
});

/* -------------------------------------------------------------------------
 | Kwitansi: dua jalur, dua kalimat
 * ------------------------------------------------------------------------- */

/** Isi kwitansi sebagai teks polos, supaya kalimatnya bisa diperiksa. */
function kwitansiTeks(bool $lewatGerbang): string
{
    $html = Illuminate\Support\Facades\Blade::render(
        file_get_contents(resource_path('views/pdf/kwitansi.blade.php')),
        [
            'judul' => 'Tanda Terima Pembayaran',
            'kode' => 'OT-1608-UJI',
            'rincian' => ['Kode pesanan' => 'OT-1608-UJI'],
            'catatan' => null,
            'jumlah' => 'Rp 2.003.378',
            'jumlahLabel' => 'Nominal diterima',
            'capStatus' => 'Diterima',
            'biaya' => [],
            'tagihan' => [
                'total_teks' => 'Rp 2.860.000',
                'sudah_teks' => 'Rp 2.860.000',
                'sisa_teks' => 'Rp 0',
                'lunas' => true,
            ],
            'nota' => [],
            'keadaan' => [],
            'caraBayar' => true,
            'lewatGerbang' => $lewatGerbang,
        ],
    );

    return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
}

test('kwitansi gerbang tidak menyuruh mencocokkan nama penerima', function () {
    /*
     | Pelanggan gerbang TIDAK PERNAH mentransfer ke rekening mana pun — ia
     | membayar lewat virtual account, QRIS, atau dompet digital. Menyuruhnya
     | mencocokkan "satu-satunya nama penerima yang sah" berarti menyuruh
     | memeriksa sesuatu yang tidak pernah ia lihat, dan lebih buruk lagi
     | mengesankan transfer manual sebagai jalur yang berlaku.
     */
    $teks = kwitansiTeks(lewatGerbang: true);

    expect($teks)
        ->not->toContain('Satu-satunya nama penerima yang sah')
        ->not->toContain('Pembayaran hanya sah ke nama di atas')
        ->not->toContain('Nama penerima selain itu')
        // Peringatan penipuannya tetap ada, menunjuk bahaya yang benar-benar
        // mengintai pelanggan gerbang.
        ->toContain('tidak pernah meminta PIN, OTP')
        ->toContain('halaman pembayaran resmi kami');
});

test('kwitansi gerbang tidak menanam ragu yang tidak berdasar', function () {
    /*
     | "Sebelum dicek tim" jujur untuk bukti unggahan: angkanya memuat klaim
     | yang belum diperiksa siapa pun. Uang gerbang sudah dipastikan sebelum
     | barisnya lahir, jadi peringatan yang sama di sana justru membuat
     | pelanggan mengira pembayarannya masih bisa ditolak.
     */
    expect(kwitansiTeks(lewatGerbang: true))
        ->toContain('Sudah dibayar')
        ->not->toContain('sebelum dicek tim');
});

test('kwitansi transfer manual tetap memajang nama penerima', function () {
    // Di jalur itu nama penerima adalah satu-satunya hal yang bisa dicocokkan
    // pelanggan di layar ATM sebelum uangnya berpindah.
    expect(kwitansiTeks(lewatGerbang: false))
        ->toContain('Satu-satunya nama penerima yang sah')
        ->toContain(config('orcha.pembayaran.atas_nama'))
        ->toContain('sebelum dicek tim');
});

/* -------------------------------------------------------------------------
 | Rincian untuk admin
 * ------------------------------------------------------------------------- */

test('admin menerima pecahan nominalnya, bukan satu angka gelondongan', function () {
    /*
     | "Rp 858.889 ini berapa DP-nya?" adalah pertanyaan yang muncul persis
     | saat admin menghitung sisa tagihan pelanggan. Menerkanya menghasilkan
     | sisa yang meleset beberapa ratus rupiah — cukup untuk membuat dua orang
     | berdebat tentang siapa yang salah hitung.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();

    $data = (new App\Http\Resources\OpenTrip\PembayaranResource(
        KonfirmasiPembayaran::firstOrFail()
    ))->resolve();

    expect($data['kanal'])->toBe('doku')
        ->and($data['rincian']['pokok'])->toBe(600000)
        ->and($data['rincian']['kode_unik'])->toBe(913)
        ->and($data['rincian']['total'])->toBe(600913)
        ->and($data['rincian']['invoice'])->toBe($bayar->invoice)
        ->and($data['rincian']['channel'])->toBe('VIRTUAL_ACCOUNT_BCA');
});

test('bukti transfer manual tidak dikarang pecahannya', function () {
    /*
     | Di jalur manual memang tidak ada pemecahan yang bisa
     | dipertanggungjawabkan: nominalnya apa adanya seperti yang tertera di
     | mutasi. Mengarangnya berarti menampilkan angka yang tidak pernah ada
     | di transaksi mana pun.
     */
    $manual = KonfirmasiPembayaran::create([
        'kode' => $this->pendaftaran->kode,
        'jenis' => 'dp',
        'nominal' => 600000,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso',
        'status' => 'diterima',
    ]);

    $data = (new App\Http\Resources\OpenTrip\PembayaranResource($manual))->resolve();

    expect($data['kanal'])->toBe('transfer')
        ->and($data['rincian'])->toBeNull();
});

/* -------------------------------------------------------------------------
 | Halaman hasil
 * ------------------------------------------------------------------------- */

test('halaman hasil melaporkan status, tidak menentukannya', function () {
    $bayar = pembayaranMenunggu($this->pendaftaran);

    $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Sedang memastikan pembayaran Anda');

    // Membuka halamannya tidak mengubah apa pun.
    expect($bayar->fresh()->status)->toBe('menunggu')
        ->and(KonfirmasiPembayaran::count())->toBe(0);
});

test('halaman hasil menampilkan keberhasilan setelah notifikasi masuk', function () {
    $bayar = pembayaranMenunggu($this->pendaftaran);

    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();

    $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Pembayaran berhasil');
});

test('tagihan yang lewat batas waktu terbaca kedaluwarsa tanpa menunggu DOKU', function () {
    /*
     | Statusnya baru berubah kalau DOKU mengirimkan notifikasi kedaluwarsa,
     | dan notifikasi itu punya sakelarnya sendiri di dashboard yang bisa mati
     | tanpa ada yang menyadarinya.
     |
     | Tanpa hitungan jam, halaman ini akan menyatakan "menunggu pembayaran
     | Anda" untuk tagihan yang sudah mati — lengkap dengan tombol menuju
     | halaman DOKU yang tidak lagi menerima apa-apa. Menggantungkan kebenaran
     | layar pada sakelar di sistem orang lain adalah cacat yang baru
     | ketahuan saat ada pelanggan menekan tombol itu.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['kedaluwarsa_pada' => now()->subMinute(), 'url' => 'https://sandbox.doku.com/mati']);

    // Statusnya sengaja DIBIARKAN 'menunggu' — persis keadaan saat notifikasi
    // kedaluwarsa DOKU dimatikan di dashboard.
    expect($bayar->fresh()->status)->toBe('menunggu')
        ->and($bayar->fresh()->sudah_kedaluwarsa)->toBeTrue();

    $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Tautan pembayaran sudah kedaluwarsa')
        ->assertSee('Buat Tautan Baru')
        // Ketenangan didahulukan: yang membaca judulnya menduga pesanannya
        // ikut hangus, dan dugaan itu harus dibantah sebelum mengendap.
        ->assertSee('tidak berubah')
        // Tautan DOKU yang sudah mati tidak boleh ditawarkan lagi.
        ->assertDontSee('https://sandbox.doku.com/mati', false)
        ->assertDontSee('Menunggu pembayaran Anda');
});

test('tagihan yang masih berlaku tetap punya jalan lanjut membayar', function () {
    /*
     | Tautannya tetap ada, tetapi TIDAK di layar pertama.
     |
     | Yang dilihat pelanggan begitu kembali dari DOKU adalah jendela
     | pemastian; tautan bayar baru muncul sesudahnya, di bawah peringatan
     | jangan-membayar-lagi. Uji ini menjaga bahwa jalannya tidak ikut hilang
     | saat ajakannya dihapus — orang yang benar-benar belum sempat membayar
     | tetap bisa menyelesaikannya sendiri.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['kedaluwarsa_pada' => now()->addHours(6), 'url' => 'https://sandbox.doku.com/hidup']);

    $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Sedang memastikan pembayaran Anda')
        ->assertSee('https://sandbox.doku.com/hidup', false);
});

test('kedaluwarsa tidak mengurangi tagihan pesanannya', function () {
    // Batas waktu DOKU dan penahanan kursi adalah dua jam yang berbeda.
    // Yang habis di sini cuma tagihannya; kursinya diurus orcha:lepas-kursi.
    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['kedaluwarsa_pada' => now()->subDay()]);

    $tagihan = TagihanPesanan::untuk($this->pendaftaran->fresh());

    expect($tagihan['sudah'])->toBe(0)
        ->and($tagihan['sisa'])->toBe(2000000)
        ->and($this->pendaftaran->fresh()->status)->not->toBe('batal');
});

test('nomor tagihan asing tidak membocorkan apa pun', function () {
    $this->get(route('pembayaran-selesai', ['invoice' => 'OT-0000-XXXX-DP-AAAA']))
        ->assertOk()
        ->assertSee('Pembayaran tidak ditemukan')
        ->assertDontSee($this->pendaftaran->nama);
});

test('tombol bayar tidak ditawarkan selama jendela pemastian', function () {
    /*
     | Uji yang paling mahal ketiadaannya di seluruh berkas ini.
     |
     | Pelanggan sampai di halaman ini karena DOKU yang mengembalikannya —
     | artinya ia baru saja menyelesaikan sesuatu di sana. Notifikasi yang
     | mencatat pembayarannya tiba beberapa detik kemudian, dan pada detik-detik
     | itu halaman dulu menawarkan "Lanjutkan Pembayaran".
     |
     | Itu bukan sekadar membingungkan: itu mengundang orang yang uangnya sudah
     | berpindah untuk membayar untuk kedua kalinya. Uang kedua itu nyata, dan
     | mengembalikannya pekerjaan manual yang tidak seorang pun minta.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['url' => 'https://sandbox.doku.com/bayar/abc']);

    $halaman = $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk();

    $halaman->assertSee('Sedang memastikan pembayaran Anda')
        ->assertSee('jangan membayar lagi')
        // Ajakan lamanya harus benar-benar hilang, bukan sekadar dipindah.
        ->assertDontSee('Lanjutkan Pembayaran')
        ->assertDontSee('Belum membayar? Halaman pembayarannya masih berlaku');
});

test('jalan bagi yang belum membayar tetap ada, di fase kedua', function () {
    /*
     | Penjaga arah sebaliknya. Menghilangkan tautan bayar sepenuhnya membuat
     | orang yang benar-benar belum sempat membayar terjebak — dan halaman yang
     | tidak punya jalan keluar mengirimkannya ke WhatsApp untuk sesuatu yang
     | seharusnya bisa ia kerjakan sendiri.
     |
     | Ia ada, tetapi di bawah peringatan "jangan membayar lagi" dan bukan
     | sebagai tombol — urutan itulah yang membedakan menawarkan jalan dari
     | mengundang pembayaran kedua.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['url' => 'https://sandbox.doku.com/bayar/abc']);

    $isi = $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Pembayaran belum tercatat')
        ->assertSee('Uang Anda sudah terpotong?')
        ->assertSee('Buka lagi halaman pembayarannya')
        ->getContent();

    // Peringatannya mendahului tautannya di dalam halaman, bukan sesudahnya.
    expect(strpos($isi, 'Jangan membayar lagi'))
        ->toBeLessThan(strpos($isi, 'Buka lagi halaman pembayarannya'));
});

test('hitungan mundur menyebut lamanya, bukan sekadar menyuruh menunggu', function () {
    /*
     | Menunggu tanpa tahu sampai kapan adalah alasan orang menutup tab — dan
     | tab yang ditutup pada detik keempat adalah pelanggan yang tidak pernah
     | melihat bahwa pembayarannya berhasil, lalu menghubungi WhatsApp untuk
     | sesuatu yang sudah beres.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);

    $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        // Angka detiknya digambar Alpine dari x-text, jadi yang dijaga di sini
        // keberadaan hitungannya — bukan angka yang hanya ada setelah JS jalan.
        ->assertSee('x-text="sisa"', escape: false)
        ->assertSee('total: 90', escape: false)
        // Waktu mulainya dititipkan ke sessionStorage. Tanpa itu tiap denyut
        // wire:poll mengembalikan hitungan ke 90, dan mundurnya tidak pernah
        // sampai nol.
        ->assertSee('sessionStorage', escape: false);
});

test('halaman yang sudah berhasil tidak lagi menawarkan apa pun untuk dibayar', function () {
    // Penjaga terakhir terhadap pembayaran kedua: sesudah berhasil, tidak ada
    // satu pun jalan menuju halaman bayar yang tersisa di layar.
    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['url' => 'https://sandbox.doku.com/bayar/abc']);

    kirimNotifikasi(badanSukses($bayar->invoice))->assertOk();

    $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Pembayaran berhasil')
        ->assertDontSee('Buka lagi halaman pembayarannya')
        ->assertDontSee('Lanjutkan Pembayaran')
        ->assertDontSee('Sedang memastikan pembayaran Anda');
});

/* -------------------------------------------------------------------------
 | Jendela bayar pendek, dan tenggang pemeriksaannya
 * ------------------------------------------------------------------------- */

test('umur tagihan yang dikirim ke DOKU mengikuti config', function () {
    /*
     | Dipendekkan jadi tiga puluh menit demi kerahasiaan, bukan demi
     | pembayaran: halaman DOKU menampilkan nama dan nomor telepon pemesan,
     | dan tautannya berpindah tangan lewat email dan WhatsApp. Umur 24 jam
     | berarti sehari penuh jendela terbuka untuk satu pesanan.
     */
    config()->set('doku.batas_bayar_menit', 30);

    Http::fake(['api-sandbox.doku.com/*' => Http::response([
        'response' => ['payment' => ['url' => 'https://sandbox.doku.com/x', 'token_id' => 't']],
    ], 200)]);

    app(DokuCheckout::class)->buatHalamanBayar(
        'OT-UJI-1', 600000, 'Uang Muka', [], ['nama' => 'Siti'],
        ['success' => 'https://orcha.test/kembali']
    );

    Http::assertSent(fn ($p) => $p['payment']['payment_due_date'] === 30);
});

test('pembayaran yang baru lewat batas masih ditanyakan ke DOKU', function () {
    /*
     | Bahaya yang lahir justru karena jendelanya dipendekkan.
     |
     | Jam kedaluwarsa itu MILIK KAMI. Yang membayar pada menit ke-29
     | menyelesaikan transaksinya beberapa detik sesudah jam kami menyatakan
     | mati — dan berhenti bertanya tepat pada detik itu berarti uangnya sudah
     | berpindah sementara layar kami menyatakan batas waktu habis. Bahayanya
     | kecil saat batasnya 24 jam; sejak setengah jam, menit terakhir jadi
     | tempat yang ramai.
     */
    config()->set('doku.tenggang_periksa_menit', 15);

    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['kedaluwarsa_pada' => now()->subMinutes(2)]);

    expect($bayar->fresh()->sudah_kedaluwarsa)->toBeTrue()
        // Sudah mati menurut layar, tetapi masih pantas ditanyakan.
        ->and($bayar->fresh()->masih_pantas_diperiksa)->toBeTrue();

    Http::fake(['api-sandbox.doku.com/*' => Http::response(badanSukses($bayar->invoice), 200)]);

    Volt::test('public.open-trip.pembayaran-selesai', ['invoice' => $bayar->invoice])
        ->call('periksa');

    // Uangnya masuk, dan layarnya berubah sendiri.
    expect($bayar->fresh()->status)->toBe('berhasil')
        ->and(KonfirmasiPembayaran::where('kode', $this->pendaftaran->kode)->count())->toBe(1);
});

test('tenggang pemeriksaan ada batasnya, bukan selamanya', function () {
    // Penjaga arah sebaliknya: bertanya terus-menerus untuk tagihan yang mati
    // berjam-jam lalu cuma membebani DOKU dan kuota data pelanggan.
    config()->set('doku.tenggang_periksa_menit', 15);

    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['kedaluwarsa_pada' => now()->subMinutes(30)]);

    expect($bayar->fresh()->masih_pantas_diperiksa)->toBeFalse();

    Http::fake();

    Volt::test('public.open-trip.pembayaran-selesai', ['invoice' => $bayar->invoice])
        ->call('periksa');

    Http::assertNothingSent();
});

test('layar menyerah lebih dulu daripada jalur pemeriksaannya', function () {
    /*
     | Dua keputusan yang berbeda dan sempat disatukan keliru: apa yang
     | TERTULIS di layar, dan apakah kami MASIH BERTANYA.
     |
     | Begitu jamnya lewat, pelanggan harus dibilangi batas waktunya habis —
     | menyuruhnya menunggu tagihan yang tidak lagi menerima apa-apa adalah
     | membuang waktunya. Tetapi denyut pemeriksaannya tetap berjalan diam-diam
     | di belakang layar itu, dan halamannya berubah sendiri bila uangnya
     | ternyata masuk.
     */
    config()->set('doku.tenggang_periksa_menit', 15);

    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['kedaluwarsa_pada' => now()->subMinutes(2)]);

    $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Tautan pembayaran sudah kedaluwarsa')
        // Tetap berdenyut meski layarnya sudah menyerah.
        ->assertSee('wire:poll.10s', escape: false);
});

test('layar kedaluwarsa tidak lagi menjanjikan uang tidak terpotong', function () {
    /*
     | Janji itu benar selama jendelanya 24 jam: yang sampai di layar ini
     | hampir pasti tidak pernah membayar. Sejak setengah jam, ia bisa saja
     | orang yang uangnya baru berpindah beberapa detik lalu — dan kepadanya
     | kalimat itu bukan menenangkan melainkan salah. Yang mempercayainya
     | berhenti mengejar uangnya sendiri.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['kedaluwarsa_pada' => now()->subHours(2)]);

    $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Tautan pembayaran sudah kedaluwarsa')
        ->assertSee('Sudah terlanjur membayar?')
        // Peringatannya MENDAHULUI tombolnya: tombol itu membuat tagihan
        // baru, dan bagi yang uangnya sudah terpotong beberapa detik sebelum
        // tautannya mati, menekannya berarti membayar dua kali.
        ->assertDontSee('Tidak ada uang yang terpotong, dan pesanan');
});

/* -------------------------------------------------------------------------
 | Membuat ulang tautan setelah kedaluwarsa
 * ------------------------------------------------------------------------- */

/**
 * Jawaban DOKU berurutan, satu URL berbeda tiap panggilan.
 *
 * Http::fake() yang dipanggil dua kali TIDAK mengganti stub sebelumnya untuk
 * pola yang sama, jadi panggilan kedua tetap menerima jawaban pertama —
 * dan uji yang membedakan tagihan lama dari yang baru lewat URL-nya lolos
 * karena alasan yang salah.
 */
function palsukanDokuBerurutan(string ...$url): void
{
    $urutan = Http::sequence();

    foreach ($url as $satu) {
        $urutan->push([
            'response' => ['payment' => ['url' => $satu, 'token_id' => 'tok-'.md5($satu)]],
        ], 200);
    }

    Http::fake(['api-sandbox.doku.com/*' => $urutan]);
}

test('menekan bayar dua kali dalam jendelanya tidak melahirkan dua tautan', function () {
    /*
     | Sudah terjadi di basis data sungguhan: satu pesanan meninggalkan TIGA
     | baris "menunggu" sekaligus, karena tiap penekanan tombol membuat
     | tagihan baru tanpa menutup yang lama.
     |
     | Dengan jendela 24 jam itu cuma berantakan. Sejak setengah jam —
     | membuat ulang jadi alur biasa — akibatnya jadi uang: dua Virtual
     | Account hidup untuk satu tagihan berarti keduanya bisa dibayar, dan
     | yang membayar dua kali bukan kesalahan yang bisa kita bantah.
     |
     | Nominalnya pun berbeda di tiap tagihan karena kode uniknya diacak
     | ulang. Pelanggan yang sudah menyalin angka ke m-banking lalu
     | menyegarkan halaman kami menemukan angka lain, tanpa satu pun
     | keterangan kenapa.
     */
    Mail::fake();
    palsukanDokuBerurutan('https://sandbox.doku.com/bayar/a', 'https://sandbox.doku.com/bayar/b');

    $pertama = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran, 'dp');
    $kedua = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran->fresh(), 'dp');

    expect($kedua->id)->toBe($pertama->id)
        ->and(PembayaranDoku::where('kode', $this->pendaftaran->kode)->count())->toBe(1)
        // Angka yang sudah disalin pelanggan ke m-banking tidak berubah.
        ->and($kedua->nominal)->toBe($pertama->nominal);

    // Fakturnya pun tidak dikirim dua kali untuk tagihan yang sama.
    Mail::assertSent(TagihanPembayaran::class, 1);
});

test('setelah kedaluwarsa, halaman pembayaran memberi tautan yang benar-benar baru', function () {
    /*
     | Inilah alur yang diandalkan sejak jendelanya dipendekkan: tautan mati,
     | pelanggan mulai lagi dari halaman pembayaran, dan mendapat tautan baru.
     | Kalau yang mati dipakai ulang, batas waktu itu tidak berarti apa-apa.
     */
    Mail::fake();
    palsukanDokuBerurutan('https://sandbox.doku.com/bayar/a', 'https://sandbox.doku.com/bayar/b');

    $lama = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran, 'dp');
    $lama->update(['kedaluwarsa_pada' => now()->subMinute()]);

    $baru = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran->fresh(), 'dp');

    expect($baru->id)->not->toBe($lama->id)
        ->and($baru->invoice)->not->toBe($lama->invoice)
        ->and($baru->url)->toBe('https://sandbox.doku.com/bayar/b')
        ->and($baru->sudah_kedaluwarsa)->toBeFalse();
});

test('tagihan yang nominalnya sudah basi tidak disodorkan lagi', function () {
    /*
     | Pokoknya berubah bila admin mencatat pembayaran lain di tengah — lewat
     | unggahan bukti transfer, misalnya. Menyodorkan tagihan lama berarti
     | menagih uang yang sebagiannya sudah masuk.
     */
    Mail::fake();
    palsukanDokuBerurutan('https://sandbox.doku.com/bayar/a', 'https://sandbox.doku.com/bayar/c');

    $lama = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran, 'dp');

    KonfirmasiPembayaran::create([
        'kode' => $this->pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 200_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'diterima',
    ]);

    $baru = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran->fresh(), 'dp');

    expect($baru->id)->not->toBe($lama->id)
        ->and($baru->nominal_pokok)->not->toBe($lama->nominal_pokok);
});

test('tagihan tanpa url tidak pernah dipakai ulang', function () {
    /*
     | Baris "menunggu" tanpa URL adalah sisa dari panggilan DOKU yang gagal
     | di tengah — barisnya sudah ditulis, jawabannya tidak pernah datang.
     | Memakainya ulang berarti menyodorkan tagihan yang tidak punya halaman
     | bayar sama sekali.
     */
    Mail::fake();

    $rusak = PembayaranDoku::create([
        'invoice' => $this->pendaftaran->kode.'-DP-RUSAK',
        'kode' => $this->pendaftaran->kode,
        'jenis' => 'dp',
        'nominal_pokok' => 600_000,
        'kode_unik' => 500,
        'nominal' => 600_500,
        'status' => 'menunggu',
        'kedaluwarsa_pada' => now()->addMinutes(20),
    ]);

    palsukanDokuBerurutan('https://sandbox.doku.com/bayar/d');
    $baru = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran->fresh(), 'dp');

    expect($baru->id)->not->toBe($rusak->id)
        ->and($baru->url)->toBe('https://sandbox.doku.com/bayar/d');
});

test('layar kedaluwarsa menyebutkan yang ikut berpindah ke tautan baru', function () {
    /*
     | Angka, bukan janji. Orang yang baru saja kehilangan satu tautan lebih
     | percaya pada daftar yang bisa ia cocokkan sendiri daripada pada kalimat
     | "kursi Anda aman" — dan yang tidak percaya menghubungi WhatsApp untuk
     | memastikan sesuatu yang sudah tertulis di layarnya.
     */
    $bayar = pembayaranMenunggu($this->pendaftaran);
    $bayar->update(['kedaluwarsa_pada' => now()->subHours(2)]);

    $isi = $this->get(route('pembayaran-selesai', ['invoice' => $bayar->invoice]))
        ->assertOk()
        ->assertSee('Kode pesanan')
        ->assertSee($this->pendaftaran->kode)
        ->assertSee('Rp 600.000')
        ->getContent();

    // Nomor tagihan lama TIDAK lagi jadi hal pertama yang dibaca — ia cuma
    // berguna saat pelanggan menyebutkannya ke kami.
    expect(strpos($isi, 'Kursi dan tagihan Anda'))
        ->toBeLessThan(strpos($isi, 'Tautan lama:'));
});

test('tautan berumur lebih panjang daripada aturan hari ini tidak dipakai ulang', function () {
    /*
     | Tagihan membawa umurnya sejak lahir. Yang dibuat saat batasnya masih 24
     | jam tetap hidup 24 jam meski config sudah dipendekkan jadi 30 menit —
     | dan tanpa penjagaan ini, memakainya ulang berarti menyerahkan kembali
     | tautan berumur sehari sesudah kita memutuskan tautan tidak boleh hidup
     | lebih dari setengah jam.
     |
     | Yang paling berbahaya bukan tautannya, melainkan diamnya: aturan baru
     | seolah tidak berlaku, dan tidak ada satu pun galat yang menjelaskan
     | kenapa. Terukur pada pesanan sungguhan yang masih memegang tautan 1440
     | menit lahir semenit sebelum config diubah.
     */
    Mail::fake();
    config()->set('doku.batas_bayar_menit', 30);

    palsukanDokuBerurutan('https://sandbox.doku.com/lama', 'https://sandbox.doku.com/baru');

    $lama = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran, 'dp');

    // Umur lama: masih hidup, tetapi jauh melewati aturan hari ini.
    $lama->update(['kedaluwarsa_pada' => now()->addHours(23)]);

    $baru = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran->fresh(), 'dp');

    expect($baru->id)->not->toBe($lama->id)
        ->and($baru->url)->toBe('https://sandbox.doku.com/baru')
        // Yang baru benar-benar tunduk pada aturan hari ini.
        ->and(round($baru->created_at->diffInMinutes($baru->kedaluwarsa_pada)))->toBe(30.0);
});

test('tautan yang umurnya sesuai aturan tetap dipakai ulang', function () {
    // Penjaga arah sebaliknya: penjagaan umur tidak boleh mematikan
    // pemakaian ulang yang justru mencegah dua VA hidup bersamaan.
    Mail::fake();
    config()->set('doku.batas_bayar_menit', 30);

    palsukanDokuBerurutan('https://sandbox.doku.com/lama', 'https://sandbox.doku.com/baru');

    $pertama = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran, 'dp');
    $kedua = App\Support\MulaiPembayaranDoku::untuk($this->pendaftaran->fresh(), 'dp');

    expect($kedua->id)->toBe($pertama->id);
});

/* -------------------------------------------------------------------------
 | Pemutaran ulang notifikasi
 * ------------------------------------------------------------------------- */

/** Seperti kirimNotifikasi(), tetapi cap waktunya boleh digeser. */
function kirimNotifikasiBercap(array $badan, string $waktu)
{
    $mentah = json_encode($badan);
    $requestId = 'notif-'.uniqid();

    return test()->call('POST', '/pembayaran/doku/notifikasi', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_CLIENT_ID' => 'BRN-0227-UJI',
        'HTTP_REQUEST_ID' => $requestId,
        'HTTP_REQUEST_TIMESTAMP' => $waktu,
        'HTTP_SIGNATURE' => app(DokuCheckout::class)
            ->tandaTangan('/pembayaran/doku/notifikasi', $requestId, $waktu, $mentah),
    ], $mentah);
}

test('notifikasi bertanda tangan sah tetapi kedaluwarsa ditolak', function () {
    /*
     | Tanda tangan membuktikan notifikasi PERNAH dibuat DOKU; ia tidak
     | membuktikan notifikasi itu baru datang. Satu notifikasi sah yang direkam
     | — dari log perantara, dari server yang pernah bocor — bisa dikirim ulang
     | bertahun-tahun kemudian dan tetap lolos tanpa penjagaan ini.
     |
     | Tanda tangannya di sini BENAR-BENAR SAH: dihitung dengan kunci yang
     | sama, atas badan yang sama. Yang salah cuma umurnya.
     */
    config()->set('doku.jendela_notifikasi_menit', 30);

    $bayar = pembayaranMenunggu($this->pendaftaran);

    kirimNotifikasiBercap(
        badanSukses($bayar->invoice),
        gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
    )->assertStatus(401);

    // Tidak ada yang tercatat: pembayarannya tetap menunggu.
    expect($bayar->fresh()->status)->toBe('menunggu')
        ->and(KonfirmasiPembayaran::count())->toBe(0);
});

test('jam yang sedikit berbeda tidak menolak pembayaran sungguhan', function () {
    /*
     | Penjaga arah sebaliknya, dan yang lebih mahal salahnya. Jam server kita
     | dan jam DOKU tidak pernah persis sama, dan notifikasi yang gagal diulang
     | beberapa menit kemudian harus tetap diterima. Menolak pembayaran
     | sungguhan demi menutup pemutaran ulang adalah pertukaran yang salah
     | arah.
     */
    config()->set('doku.jendela_notifikasi_menit', 30);

    $bayar = pembayaranMenunggu($this->pendaftaran);

    // Lima menit di DEPAN jam kita — jam DOKU sedikit maju.
    kirimNotifikasiBercap(
        badanSukses($bayar->invoice),
        gmdate('Y-m-d\TH:i:s\Z', time() + 300),
    )->assertOk();

    expect($bayar->fresh()->status)->toBe('berhasil');
});

test('cap waktu yang tidak bisa dibaca ditolak', function () {
    // Ia bagian dari bahan tanda tangan, jadi bentuk yang aneh berarti sesuatu
    // yang tidak kita kenali — dan menebak maksudnya bukan pekerjaan pemeriksa
    // keamanan.
    $bayar = pembayaranMenunggu($this->pendaftaran);

    kirimNotifikasiBercap(badanSukses($bayar->invoice), 'bukan-waktu')
        ->assertStatus(401);

    expect($bayar->fresh()->status)->toBe('menunggu');
});
