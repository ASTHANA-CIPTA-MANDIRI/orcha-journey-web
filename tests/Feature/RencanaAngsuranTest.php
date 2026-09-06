<?php

use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\RencanaAngsuran;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

/**
 * Aturan angsuran.
 *
 * Yang dijaga berkas ini bukan tampilannya, melainkan dua batasan yang
 * melindungi Orcha dari menjanjikan sesuatu yang tidak bisa ditepati:
 * harga yang terlalu kecil untuk dipecah, dan waktu yang tidak cukup untuk
 * menagihnya.
 */
function pesananAngsuran(int $hargaSatuan, int $peserta, int $berangkatHariLagi): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => 'Siti Aminah',
        'whatsapp' => '081298765432',
        'email' => 'siti@contoh.test',
        'jumlah_peserta' => $peserta,
        'harga_jual' => $hargaSatuan,
        'nama_paket' => 'Study Tour Bromo',
        'tanggal_berangkat' => now()->addDays($berangkatHariLagi)->toDateString(),
    ])->fresh();
}

/* -------------------------------------------------------------------------
 | Berapa kali boleh
 * ------------------------------------------------------------------------- */

test('pesanan kecil tidak boleh diangsur', function () {
    /*
     | Memecah Rp 1,5 juta jadi tiga kali menambah dua kali pekerjaan menagih
     | tanpa meringankan siapa pun. Ambangnya bukan kekikiran, melainkan
     | pengakuan bahwa angsuran punya biayanya sendiri.
     */
    $pesanan = pesananAngsuran(750_000, 2, 90);

    expect($pesanan->omzet)->toBe(1_500_000)
        ->and(RencanaAngsuran::maksTermin($pesanan))->toBe(1);
});

test('tangga harga menentukan batas atasnya', function () {
    // Waktunya sengaja dilonggarkan supaya yang diuji betul-betul harganya.
    expect(RencanaAngsuran::maksTermin(pesananAngsuran(1_430_000, 2, 300)))->toBe(3)
        ->and(RencanaAngsuran::maksTermin(pesananAngsuran(3_000_000, 4, 300)))->toBe(5);
});

test('kalender berhak menolak tangga harga', function () {
    /*
     | Batasan yang paling mudah terlupa. Tenggat pelunasan TIDAK ikut mundur —
     | biaya operasional sudah keluar sebelum berangkat — jadi rencananya harus
     | habis sebelum H-5.
     |
     | Berangkat 12 hari lagi: tenggatnya 7 hari lagi, termin pertama besok,
     | tersisa 6 hari. Satu jarak butuh 14 hari, jadi tidak ada angsuran yang
     | muat betapa pun mahalnya pesanan itu.
     */
    $mahal = pesananAngsuran(3_000_000, 4, 12);

    expect($mahal->omzet)->toBe(12_000_000)
        ->and(RencanaAngsuran::maksTermin($mahal))->toBe(1);
});

test('yang berlaku selalu yang lebih kecil antara harga dan kalender', function () {
    // Harga membolehkan 5x, tetapi waktunya hanya memuat 3x.
    $pesanan = pesananAngsuran(3_000_000, 4, 40);

    expect(RencanaAngsuran::maksTermin($pesanan))->toBe(3);
});

test('pesanan yang sudah lunas tidak ditawari angsuran', function () {
    $pesanan = pesananAngsuran(1_430_000, 2, 300);

    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'pelunasan', 'nominal' => 2_860_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    expect(RencanaAngsuran::maksTermin($pesanan->fresh()))->toBe(1);
});

/* -------------------------------------------------------------------------
 | Jadwalnya
 * ------------------------------------------------------------------------- */

test('termin pertama sebesar uang muka, bukan sepertiga rata', function () {
    /*
     | Kursi ditahan setelah uang muka masuk. Termin pertama yang lebih kecil
     | berarti kursi tertahan berhari-hari oleh pembayaran seuprit — persis
     | hal yang membuat nominal bebas ditolak, cuma pindah tempat.
     */
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $jadwal = RencanaAngsuran::susun($pesanan, 3);

    expect($jadwal)->toHaveCount(3)
        ->and($jadwal[0]['nominal'])->toBe(858_000)
        ->and($jadwal[0]['label'])->toContain('Uang muka');
});

test('jumlah seluruh termin sama persis dengan tagihannya', function () {
    /*
     | Pembulatan ke ribuan membuat angkanya enak dibaca, dan justru di situ
     | selisih receh lahir. Sisanya diserap termin terakhir — jadwal yang
     | jumlahnya meleset seribu rupiah dari tagihan adalah jadwal yang tidak
     | bisa dipertanggungjawabkan.
     */
    foreach ([[1_430_000, 2, 3], [3_000_000, 4, 5], [1_111_111, 3, 3]] as [$harga, $peserta, $termin]) {
        $pesanan = pesananAngsuran($harga, $peserta, 300);
        $jadwal = RencanaAngsuran::susun($pesanan, $termin);

        expect(array_sum(array_column($jadwal, 'nominal')))->toBe($pesanan->omzet);
    }
});

test('termin terakhir mendarat tepat di tenggat pelunasan', function () {
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $jadwal = RencanaAngsuran::susun($pesanan, 3);

    $tenggat = now()->addDays(90)->startOfDay()->subDays(5);

    expect(end($jadwal)['jatuh_tempo']->toDateString())->toBe($tenggat->toDateString());
});

test('jumlah termin di luar yang diizinkan ditolak', function () {
    $pesanan = pesananAngsuran(1_430_000, 2, 90);

    // Harga membolehkan 3x; 5x tidak boleh diselundupkan lewat pemanggilan.
    expect(RencanaAngsuran::susun($pesanan, 5))->toBe([])
        ->and(RencanaAngsuran::susun($pesanan, 1))->toBe([]);
});

/* -------------------------------------------------------------------------
 | Status termin, diturunkan bukan disimpan
 * ------------------------------------------------------------------------- */

function rencanaUji(PendaftaranOpenTrip $pesanan, int $jumlah): Angsuran
{
    $rencana = Angsuran::create([
        'kode' => $pesanan->kode,
        'jumlah_termin' => $jumlah,
        'total' => $pesanan->omzet,
    ]);

    foreach (RencanaAngsuran::susun($pesanan, $jumlah) as $baris) {
        $rencana->termin()->create([
            'urutan' => $baris['urutan'],
            'nominal' => $baris['nominal'],
            'jatuh_tempo' => $baris['jatuh_tempo'],
        ]);
    }

    return $rencana->fresh('termin');
}

test('termin lunas begitu pembayaran kumulatif menutupinya', function () {
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $rencana = rencanaUji($pesanan, 3);

    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'dp', 'nominal' => 858_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    $posisi = RencanaAngsuran::posisi($rencana);

    expect($posisi[0]['status'])->toBe('lunas')
        ->and($posisi[1]['status'])->toBe('menunggu')
        ->and($posisi[1]['kurang'])->toBe($posisi[1]['nominal']);
});

test('bayar dua termin sekaligus tidak butuh kasus khusus', function () {
    /*
     | Inilah keuntungan menurunkan status alih-alih mengalokasikan. Orang yang
     | membayar sekaligus, membayar lebih, atau membayar dengan angka yang
     | tidak pas semuanya terhitung wajar — tanpa satu pun cabang tambahan.
     */
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $rencana = rencanaUji($pesanan, 3);

    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'dp', 'nominal' => 1_900_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    $posisi = RencanaAngsuran::posisi($rencana);

    expect($posisi[0]['status'])->toBe('lunas')
        ->and($posisi[1]['status'])->toBe('lunas')
        ->and($posisi[2]['status'])->toBe('menunggu');
});

test('termin yang lewat jatuh tempo ditandai telat', function () {
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $rencana = rencanaUji($pesanan, 3);

    // Tanpa satu rupiah pun masuk, termin pertama lewat besok.
    $this->travel(3)->days();

    expect(RencanaAngsuran::posisi($rencana->fresh('termin'))[0]['status'])->toBe('telat');
});

test('termin berikutnya adalah yang terdekat belum tertutup', function () {
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $rencana = rencanaUji($pesanan, 3);

    expect(RencanaAngsuran::terminBerikutnya($rencana)['urutan'])->toBe(1);

    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'dp', 'nominal' => 858_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    expect(RencanaAngsuran::terminBerikutnya($rencana->fresh('termin'))['urutan'])->toBe(2);
});

test('bukti yang belum diterima belum menutup termin', function () {
    /*
     | Status termin dihitung dari uang yang DITERIMA, bukan yang diklaim.
     | Kalau tidak, siapa pun bisa menyatakan terminnya lunas hanya dengan
     | mengunggah gambar.
     */
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $rencana = rencanaUji($pesanan, 3);

    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'dp', 'nominal' => 858_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'menunggu',
    ]);

    expect(RencanaAngsuran::posisi($rencana)[0]['status'])->toBe('menunggu');
});

/* -------------------------------------------------------------------------
 | Yang dilihat pelanggan
 * ------------------------------------------------------------------------- */

test('jadwal tampil seluruhnya di halaman pembayaran', function () {
    /*
     | SELURUHNYA, bukan hanya termin berikutnya. Yang sedang kesulitan
     | keuangan perlu melihat seluruh kewajibannya untuk merencanakan;
     | disodori satu per satu ia tidak pernah tahu kapan ini berakhir.
     */
    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');

    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $pesanan->update(['whatsapp' => '081234567890']);
    rencanaUji($pesanan->fresh(), 3);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $pesanan->kode)
        ->set('empatDigit', '7890')
        ->assertSee('Jadwal Angsuran Anda')
        ->assertSee('Uang muka')
        ->assertSee('Angsuran ke-1')
        ->assertSee('Angsuran ke-2')
        ->assertSee('Rp 858.000')
        // Satu tombol saja: termin berikutnya. "Bayar lunas" di sebelahnya
        // membatalkan gunanya keringanan.
        ->assertSee('Bayar Termin Berikutnya')
        ->assertDontSee('Bayar Lunas');
});

test('yang ditagih kekurangan termin, bukan nominal penuhnya', function () {
    /*
     | Pelanggan yang sempat membayar sebagian termin ini tidak boleh diminta
     | membayar penuh lagi — menagih nominal penuh berarti menagih uang yang
     | sudah masuk untuk kedua kalinya.
     */
    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');

    Http::fake(['api-sandbox.doku.com/*' => Http::response([
        'response' => ['payment' => ['url' => 'https://sandbox.doku.com/x', 'token_id' => 't']],
    ], 200)]);

    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $pesanan->update(['whatsapp' => '081234567890']);
    rencanaUji($pesanan->fresh(), 3);

    // Uang muka Rp 858.000, baru masuk Rp 500.000.
    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'dp', 'nominal' => 500_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $pesanan->kode)
        ->set('empatDigit', '7890')
        ->set('jenis', 'angsuran')
        ->call('bayar')
        ->assertHasNoErrors();

    // 858.000 - 500.000 = 358.000, plus kode uniknya.
    expect(App\Models\OpenTrip\PembayaranDoku::firstOrFail()->nominal_pokok)->toBe(358_000);
});

test('tiap termin menyebut sendiri sudah dibayar atau belum', function () {
    /*
     | Sebelum ini keadaannya cuma tulisan abu-abu kecil di pojok kanan, dan
     | yang membuka halaman ini sedang cemas soal uang — pojok kanan justru
     | bagian yang paling mudah terlewat. Sekarang tiap termin menyebutkannya
     | dengan kata, bukan hanya dengan warna: yang tidak bisa membedakan hijau
     | dan merah tetap harus bisa membaca jadwalnya.
     */
    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');

    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $pesanan->update(['whatsapp' => '081234567890']);
    rencanaUji($pesanan->fresh(), 3);

    // Uang muka lunas, dua termin sisanya belum.
    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'dp', 'nominal' => 858_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $pesanan->kode)
        ->set('empatDigit', '7890')
        ->assertSee('Sudah dibayar')
        ->assertSee('Belum dibayar')
        // Ringkasannya menjawab pertanyaan yang sedang dipikirkan tanpa
        // menyuruhnya menjumlahkan tiga baris sendiri.
        ->assertSee('1 dari 3')
        // Uang muka Rp 858.000 dari total Rp 2.860.000, jadi sisanya
        // Rp 2.002.000 — BUKAN Rp 3.003.000. 'kurang' sudah kumulatif, dan
        // menjumlahkannya menghitung uang yang sama dua kali. Angka sisa yang
        // lebih besar daripada tagihannya membuat orang berhenti mempercayai
        // seluruh halaman.
        ->assertSee('Rp 2.002.000')
        ->assertDontSee('Rp 3.003.000');
});

test('termin yang lewat jatuh tempo dikatakan, bukan disamarkan', function () {
    /*
     | Nadanya tenang dengan sengaja. Yang menunggak sudah tahu ia menunggak,
     | dan kalimat yang menghakimi membuatnya menghindari kami — persis
     | kebalikan dari yang kita butuhkan.
     */
    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');

    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $pesanan->update(['whatsapp' => '081234567890']);
    $rencana = rencanaUji($pesanan->fresh(), 3);

    $rencana->termin()->where('urutan', 1)
        ->update(['jatuh_tempo' => now()->subDays(3)->toDateString()]);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $pesanan->kode)
        ->set('empatDigit', '7890')
        ->assertSee('Lewat jatuh tempo')
        ->assertSee('Ada termin yang sudah lewat jatuh tempo.');
});

test('pesanan yang sudah lunas tidak lagi disodori jadwalnya', function () {
    /*
     | Jadwalnya BERHENTI ditampilkan begitu seluruhnya terbayar, dan itu
     | disengaja — halaman ini adalah halaman membayar, bukan arsip. Yang
     | sudah selesai tidak perlu disodori daftar kewajiban yang tidak lagi
     | menuntut apa pun darinya.
     |
     | Diuji supaya perubahan pada blok jadwal tidak diam-diam menghidupkannya
     | kembali di keadaan yang tidak seharusnya.
     */
    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');

    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $pesanan->update(['whatsapp' => '081234567890']);
    rencanaUji($pesanan->fresh(), 3);

    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'pelunasan', 'nominal' => 2_860_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $pesanan->kode)
        ->set('empatDigit', '7890')
        ->assertDontSee('Jadwal Angsuran Anda')
        ->assertDontSee('Belum dibayar')
        ->assertDontSee('sisa Rp');
});

/* -------------------------------------------------------------------------
 | Surat yang dikirim ke pelanggan
 * ------------------------------------------------------------------------- */

test('tanda terima menyebut jadwal angsuran, bukan tenggat pelunasan umum', function () {
    /*
     | Pernah salah, dan salahnya jenis yang paling merusak kepercayaan: surat
     | tanda terima menulis "Sisa yang perlu dilunasi Rp 4.004.000, paling
     | lambat H-5 sebelum berangkat" kepada pelanggan yang layar
     | pembayarannya justru menampilkan tiga termin dengan tanggalnya
     | masing-masing.
     |
     | Dua janji yang bertentangan, dari sistem yang sama, kepada orang yang
     | baru saja kami beri keringanan karena sedang kesulitan keuangan. Yang
     | mana yang ia percaya tidak bisa ditebak — dan keduanya membuatnya
     | menghubungi kami untuk bertanya mana yang benar.
     */
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $pesanan->update(['email' => 'siti@contoh.test', 'whatsapp' => '081234567890']);
    rencanaUji($pesanan->fresh(), 3);

    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'dp', 'nominal' => 858_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    $langkah = (function () use ($pesanan) {
        $m = new ReflectionMethod(App\Support\KabarPembayaran::class, 'langkahDiterima');
        $m->setAccessible(true);

        return $m->invoke(null, App\Support\TagihanPesanan::untuk($pesanan->fresh()), $pesanan->fresh());
    })();

    expect($langkah)->toContain('1 dari 3 termin lunas')
        ->and($langkah)->toContain('jatuh tempo')
        // Tenggat umum TIDAK boleh muncul: ia menagih seluruh sisanya pada
        // tanggal yang bukan tanggal termin mana pun.
        ->and($langkah)->not->toContain('paling lambat H-');
});

test('pesanan biasa tetap memakai tenggat pelunasan seperti sebelumnya', function () {
    // Penjaga arah sebaliknya. Mayoritas pesanan tidak berangsur, dan
    // kalimatnya tidak boleh ikut berubah hanya karena fitur angsuran ada.
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $pesanan->update(['email' => 'budi@contoh.test']);

    KonfirmasiPembayaran::create([
        'kode' => $pesanan->kode, 'jenis' => 'dp', 'nominal' => 858_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'diterima',
    ]);

    $m = new ReflectionMethod(App\Support\KabarPembayaran::class, 'langkahDiterima');
    $m->setAccessible(true);
    $langkah = $m->invoke(null, App\Support\TagihanPesanan::untuk($pesanan->fresh()), $pesanan->fresh());

    expect($langkah)->toContain('paling lambat H-')
        ->and($langkah)->not->toContain('termin lunas');
});

test('termin yang lewat jatuh tempo disebut di surat, tanpa menghakimi', function () {
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    $pesanan->update(['email' => 'siti@contoh.test']);
    $rencana = rencanaUji($pesanan->fresh(), 3);

    $rencana->termin()->where('urutan', 1)
        ->update(['jatuh_tempo' => now()->subDays(3)->toDateString()]);

    $m = new ReflectionMethod(App\Support\KabarPembayaran::class, 'langkahDiterima');
    $m->setAccessible(true);
    $langkah = $m->invoke(null, App\Support\TagihanPesanan::untuk($pesanan->fresh()), $pesanan->fresh());

    expect($langkah)->toContain('sudah lewat jatuh temponya')
        ->and($langkah)->toContain('penyesuaian jadwal');
});

test('tabel rincian surat ikut membawa jadwalnya', function () {
    /*
     | Ada di TABEL, bukan cuma di kalimat, karena tabel itulah yang dibuka
     | lagi berminggu-minggu kemudian saat pelanggan lupa kapan termin
     | berikutnya jatuh tempo. Kalimat dibaca sekali; tabel dicari.
     */
    $pesanan = pesananAngsuran(1_430_000, 2, 90);
    rencanaUji($pesanan->fresh(), 3);

    $m = new ReflectionMethod(App\Support\KabarPembayaran::class, 'barisAngsuran');
    $m->setAccessible(true);
    $baris = $m->invoke(null, $pesanan->fresh());

    expect($baris)->toHaveKey('Angsuran')
        ->and($baris['Angsuran'])->toBe('0 dari 3 termin lunas')
        ->and($baris['Termin berikutnya'])->toContain('Uang muka')
        ->and($baris['Termin berikutnya'])->toContain('jatuh tempo');
});

test('pesanan tanpa rencana tidak menambah baris apa pun ke surat', function () {
    $pesanan = pesananAngsuran(1_430_000, 2, 90);

    $m = new ReflectionMethod(App\Support\KabarPembayaran::class, 'barisAngsuran');
    $m->setAccessible(true);

    expect($m->invoke(null, $pesanan->fresh()))->toBe([]);
});

test('termin pertama bernama uang muka di layar maupun di surat', function () {
    /*
     | Satu tempat, dipakai keduanya. Penamaan yang dirakit dua kali akan
     | berbeda suatu saat, dan pelanggan lalu membaca "Angsuran ke-1" di email
     | untuk baris yang di layar bernama "Uang muka" — lalu mengira ada empat
     | pembayaran, bukan tiga.
     */
    expect(RencanaAngsuran::labelTermin(1))->toBe('Uang muka')
        ->and(RencanaAngsuran::labelTermin(2))->toBe('Angsuran ke-1')
        ->and(RencanaAngsuran::labelTermin(3))->toBe('Angsuran ke-2');
});
