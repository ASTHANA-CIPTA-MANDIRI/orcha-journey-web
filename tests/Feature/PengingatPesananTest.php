<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\PengingatPesanan;
use Illuminate\Support\Facades\Mail;

/**
 * Dua surat yang selama ini tidak pernah dikirim siapa pun.
 *
 * Batas pelunasan H-5 tertulis di enam halaman publik dan di syarat &
 * ketentuan; sistem menghitung tanggalnya, menampilkannya, lalu tidak
 * melakukan apa pun saat tanggal itu tiba. Yang sudah membayar uang muka tidak
 * pernah ditagih sisanya — dan kursinya tidak bisa dilepas juga, karena
 * orangnya memang sudah membayar.
 */
function paketPengingat(int $mulaiHariLagi): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Uji',
        'category' => 'open_trip',
        'price' => 1000000,
        'status' => 'terbit',
        'tanggal_berangkat' => now()->addDays($mulaiHariLagi)->toDateString(),
    ]);
}

function daftarPengingat(TravelPackage $paket, string $status, array $ubah = []): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create(array_merge([
        'nama' => 'Pemesan',
        'whatsapp' => '081234567890',
        'email' => 'pemesan@contoh.test',
        'jumlah_peserta' => 2,
        'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name,
        'tanggal_berangkat' => $paket->tanggal_berangkat,
        'titik_jemput' => 'Terminal Bungurasih',
        'status' => $status,
    ], $ubah))->fresh();
}

beforeEach(function () {
    Mail::fake();
    config()->set('orcha.pembayaran.pelunasan_hari_sebelum', 5);
    config()->set('orcha.pengingat.pelunasan_hari_sebelum_batas', 3);
    config()->set('orcha.pengingat.briefing_hari_sebelum', 1);
});

/* ----------------------------- PELUNASAN ----------------------------- */

test('yang sudah DP ditagih menjelang batas pelunasan', function () {
    // Batas pelunasan H-5, pengingatnya tiga hari sebelum batas itu — jadi
    // jendelanya sampai H-8.
    $daftar = daftarPengingat(paketPengingat(8), 'dp_masuk');

    $hasil = PengingatPesanan::jalankan();

    expect($hasil['pelunasan'])->toContain($daftar->kode)
        ->and($daftar->fresh()->pengingat_pelunasan_pada)->not->toBeNull();
});

test('yang belum membayar sama sekali TIDAK ditagih pelunasan', function () {
    /*
     | Kursinya sudah diurus LepaskanKursiTertahan. Menagih pelunasan kepada
     | orang yang belum membayar sepeser pun terbaca seperti sistem yang tidak
     | tahu apa-apa soal pesanannya sendiri.
     */
    $daftar = daftarPengingat(paketPengingat(8), 'baru');

    expect(PengingatPesanan::jalankan()['pelunasan'])->not->toContain($daftar->kode);
});

test('yang sudah lunas tidak ditagih lagi', function () {
    $daftar = daftarPengingat(paketPengingat(8), 'lunas');

    expect(PengingatPesanan::jalankan()['pelunasan'])->not->toContain($daftar->kode);
});

test('yang batal tidak ditagih', function () {
    $daftar = daftarPengingat(paketPengingat(8), 'batal');

    expect(PengingatPesanan::jalankan()['pelunasan'])->not->toContain($daftar->kode);
});

test('tagihannya dikirim SEKALI meski perintahnya diulang', function () {
    /*
     | Inti penjagaannya. Cron gagal lalu diulang, jam server bergeser, atau
     | seseorang menjalankan perintahnya manual untuk memeriksa.
     |
     | Untuk penagihan, kiriman ganda lebih buruk daripada tidak terkirim:
     | surat yang menagih hal yang sudah dibayar membuat orang berhenti
     | membaca surat kita sama sekali.
     */
    $daftar = daftarPengingat(paketPengingat(8), 'dp_masuk');

    PengingatPesanan::jalankan();
    $kedua = PengingatPesanan::jalankan();

    expect($kedua['pelunasan'])->not->toContain($daftar->kode);
});

test('yang tanggalnya masih jauh belum ditagih', function () {
    $daftar = daftarPengingat(paketPengingat(30), 'dp_masuk');

    expect(PengingatPesanan::jalankan()['pelunasan'])->not->toContain($daftar->kode);
});

test('yang terlewat sehari tetap tertagih, bukan hilang selamanya', function () {
    /*
     | Jendela, bukan tanggal persis. Mencocokkan satu tanggal berarti
     | pendaftaran yang tanggalnya terlewat — cron mati sehari, hosting sedang
     | bermasalah — tidak pernah ditagih sama sekali.
     */
    $daftar = daftarPengingat(paketPengingat(6), 'dp_masuk');

    expect(PengingatPesanan::jalankan()['pelunasan'])->toContain($daftar->kode);
});

test('yang tanggal berangkatnya sudah lewat tidak ditagih lagi', function () {
    $daftar = daftarPengingat(paketPengingat(-3), 'dp_masuk');

    expect(PengingatPesanan::jalankan()['pelunasan'])->not->toContain($daftar->kode);
});

/* ------------------------------ BRIEFING ------------------------------ */

test('yang berangkat besok menerima briefing', function () {
    $daftar = daftarPengingat(paketPengingat(1), 'lunas');

    $hasil = PengingatPesanan::jalankan();

    expect($hasil['briefing'])->toContain($daftar->kode)
        ->and($daftar->fresh()->briefing_pada)->not->toBeNull();
});

test('yang belum membayar sama sekali tidak dibriefing', function () {
    /*
     | Ia belum punya kursi. Surat berisi "sampai jumpa besok" akan
     | membuatnya datang ke titik kumpul untuk sesuatu yang tidak dipesannya —
     | dan itu percakapan yang jauh lebih buruk daripada tidak berkirim surat.
     */
    $daftar = daftarPengingat(paketPengingat(1), 'baru');

    expect(PengingatPesanan::jalankan()['briefing'])->not->toContain($daftar->kode);
});

test('yang batal tidak dibriefing', function () {
    $daftar = daftarPengingat(paketPengingat(1), 'batal');

    expect(PengingatPesanan::jalankan()['briefing'])->not->toContain($daftar->kode);
});

test('briefingnya juga sekali saja', function () {
    $daftar = daftarPengingat(paketPengingat(1), 'lunas');

    PengingatPesanan::jalankan();

    expect(PengingatPesanan::jalankan()['briefing'])->not->toContain($daftar->kode);
});

test('yang berangkat lusa belum dibriefing', function () {
    $daftar = daftarPengingat(paketPengingat(2), 'lunas');

    expect(PengingatPesanan::jalankan()['briefing'])->not->toContain($daftar->kode);
});

/* ---------------------------- PERCOBAAN ---------------------------- */

test('percobaan menyebut siapa saja tanpa menandai apa pun', function () {
    /*
     | Kalau percobaan ikut menandai, sekali memeriksa berarti pengingatnya
     | tidak pernah benar-benar dikirim — dan tidak ada yang menyadarinya
     | karena perintahnya melaporkan sukses.
     */
    $daftar = daftarPengingat(paketPengingat(8), 'dp_masuk');

    $hasil = PengingatPesanan::jalankan(percobaan: true);

    expect($hasil['pelunasan'])->toContain($daftar->kode)
        ->and($daftar->fresh()->pengingat_pelunasan_pada)->toBeNull();
});

test('perintahnya jalan dan melaporkan jumlahnya', function () {
    daftarPengingat(paketPengingat(8), 'dp_masuk');

    $this->artisan('orcha:pengingat')
        ->expectsOutputToContain('1 pengingat dikirim.')
        ->assertSuccessful();
});

/* ------------------------- ISI SURATNYA ------------------------- */

test('surat pelunasan benar-benar tergambar dan menyebut angkanya', function () {
    /*
     | Mail::fake() menerima apa pun tanpa merender templatnya — variabel yang
     | tidak ada, tautan yang salah nama rute, semuanya lolos. Yang menemukan
     | galatnya nanti pelanggan, dalam bentuk surat yang tidak pernah sampai.
     |
     | Di sini surat pelanggannya benar-benar dirender jadi HTML.
     */
    $paket = paketPengingat(8);
    $daftar = daftarPengingat($paket, 'dp_masuk');

    Mail::assertNothingSent();
    PengingatPesanan::jalankan();

    Mail::assertSent(\App\Mail\PemberitahuanFormulir::class, function ($surat) use ($daftar) {
        if (! $surat->hasTo($daftar->email)) {
            return false;
        }

        $html = $surat->render();

        expect($html)->toContain($daftar->kode)
            // Nominal sisanya harus tertulis. "Segera lakukan pelunasan" tidak
            // menggerakkan siapa pun: yang membacanya tidak tahu berapa.
            ->and($html)->toContain('Rp')
            ->and($html)->toContain('Sisa pembayaran');

        return true;
    });
});

test('surat briefing memuat titik jemput tiap orang', function () {
    /*
     | Titik jemput sudah tersimpan per peserta sejak mereka mendaftar, tetapi
     | tidak pernah dikirim balik. Itu yang membuat WhatsApp penuh pertanyaan
     | yang sama pada H-1 malam.
     */
    $paket = paketPengingat(1);
    $daftar = daftarPengingat($paket, 'lunas', [
        'jumlah_peserta' => 3,
        'daftar_peserta' => [
            ['nama' => 'Budi', 'titik_jemput' => 'Terminal Bungurasih'],
            ['nama' => 'Sari', 'titik_jemput' => 'Stasiun Gubeng'],
            ['nama' => 'Rian', 'titik_jemput' => 'Stasiun Gubeng'],
        ],
    ]);

    PengingatPesanan::jalankan();

    Mail::assertSent(\App\Mail\PemberitahuanFormulir::class, function ($surat) use ($daftar) {
        if (! $surat->hasTo($daftar->email)) {
            return false;
        }

        $html = $surat->render();

        // Disusun per TITIK, bukan per orang: yang membacanya si pemesan, yang
        // perlu memastikan tiap temannya menunggu di tempat yang benar.
        expect($html)->toContain('Terminal Bungurasih')
            ->and($html)->toContain('Stasiun Gubeng')
            ->and($html)->toContain('Sari, Rian');

        return true;
    });
});

test('yang alamat surelnya kosong tetap ditandai, bukan dicoba terus', function () {
    /*
     | Surel opsional di formulir kami — nomor WhatsApp yang wajib. Tanpa
     | penandaan, orang tanpa surel akan terus masuk daftar tiap hari dan
     | menutupi yang benar-benar perlu dikirimi.
     */
    $daftar = daftarPengingat(paketPengingat(1), 'lunas', ['email' => null]);

    PengingatPesanan::jalankan();

    expect($daftar->fresh()->briefing_pada)->not->toBeNull();
    Mail::assertNotSent(\App\Mail\PemberitahuanFormulir::class,
        fn ($surat) => $surat->hasTo('pemesan@contoh.test'));
});
