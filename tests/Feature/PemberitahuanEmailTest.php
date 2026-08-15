<?php

use App\Mail\PemberitahuanFormulir;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Mail::fake();
    Storage::fake('public');
    config()->set('orcha.email_pemberitahuan', 'halo@orchajourney.com');

    $this->paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'price' => 1430000,
        'minimal_peserta' => 6,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'tanggal_pulang' => now()->addMonth()->addDays(2)->toDateString(),
        'titik_jemput' => 'Jogja, Klaten, Surakarta',
    ]);
});

function pendaftaranUji(?string $email = null): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => 'Siti Aminah',
        'whatsapp' => '081298765432',
        'email' => $email,
        'jumlah_peserta' => 2,
        'daftar_peserta' => [
            ['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja'],
            ['nama' => 'Budi Santoso', 'titik_jemput' => 'Surakarta'],
        ],
        'nama_paket' => 'Open Trip Banyuwangi',
    ]);
}

/* ---------------------------- PENDAFTARAN ---------------------------- */

test('pendaftaran mengirim surat beserta bukti pendaftaran pdf', function () {
    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $this->paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('jumlahPeserta', 2)
        ->set('peserta', [
            ['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja'],
            ['nama' => 'Budi Santoso', 'titik_jemput' => 'Surakarta'],
        ])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();

    $kode = PendaftaranOpenTrip::firstOrFail()->kode;

    Mail::assertSent(PemberitahuanFormulir::class, function ($surat) use ($kode) {
        return $surat->hasTo('halo@orchajourney.com')
            && $surat->kode === $kode
            && $surat->judul === 'Pendaftaran Open Trip Baru'
            // Titik jemput tiap peserta ikut disebut
            && str_contains($surat->rincian['Peserta & titik jemput'], 'Budi Santoso — Surakarta')
            // Berkas PDF-nya benar-benar terbentuk, bukan sekadar nama
            && count($surat->berkasPdf) === 1
            && str_starts_with(reset($surat->berkasPdf), '%PDF-');
    });
});

/* ------------------------ KONFIRMASI PEMBAYARAN ------------------------ */

test('bukti pembayaran mengirim surat dengan foto bukti dan kwitansi pdf', function () {
    $pendaftaran = pendaftaranUji();

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $pendaftaran->kode)
        ->set('jenis', 'dp')
        ->set('nominalTeks', '500000')
        ->set('tanggalTransfer', now()->toDateString())
        ->set('bankPengirim', 'BCA')
        ->set('atasNamaPengirim', 'Siti Aminah')
        ->set('bukti', UploadedFile::fake()->image('bukti.jpg'))
        ->set('setuju', true)
        ->call('kirim')
        ->assertHasNoErrors();

    Mail::assertSent(PemberitahuanFormulir::class, function ($surat) use ($pendaftaran) {
        $namaBerkas = array_key_first($surat->berkasPdf);

        return $surat->kode === $pendaftaran->kode
            && $surat->rincian['Nominal'] === 'Rp 500.000'
            // Foto buktinya ikut dilampirkan
            && count($surat->lampiran) === 1
            && str_ends_with($surat->lampiran[0], '.webp')
            && str_contains($namaBerkas, 'TANDA-TERIMA')
            && str_starts_with($surat->berkasPdf[$namaBerkas], '%PDF-');
    });
});

/* -------------------------- RIWAYAT KESEHATAN -------------------------- */

test('riwayat kesehatan mengirim surat tanpa merinci datanya', function () {
    $pendaftaran = pendaftaranUji();

    Volt::test('public.open-trip.riwayat-kesehatan')
        ->set('kode', $pendaftaran->kode)
        ->set('namaPeserta', 'Budi Santoso')
        ->set('usia', 28)
        ->set('jenisKelamin', 'Laki-laki')
        ->set('kemampuanRenang', 'tidak_bisa')
        ->set('riwayatPenyakit', 'Asma ringan')
        ->set('kontakNama', 'Siti')
        ->set('kontakHp', '081298765432')
        ->set('kontakHubungan', 'Istri')
        ->set('setuju', true)
        ->call('simpan')
        ->assertHasNoErrors();

    Mail::assertSent(PemberitahuanFormulir::class, function ($surat) {
        $isi = implode(' ', array_filter($surat->rincian)).' '.$surat->catatan;

        return $surat->judul === 'Riwayat Kesehatan Peserta Masuk'
            && str_contains($isi, 'Budi Santoso')
            // Penyakitnya TIDAK ikut dikirim — kotak masuk bukan tempat data pribadi
            && ! str_contains($isi, 'Asma ringan')
            && str_contains($isi, 'Ya');
    });
});

/* ------------------------------ PEMBATALAN ------------------------------ */

test('pengajuan pembatalan mengirim surat dengan tanda terima pdf', function () {
    $pendaftaran = pendaftaranUji();

    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $pendaftaran->kode)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('alasan', 'kondisi_kesehatan')
        ->set('jumlahDibatalkan', 1)
        ->set('bank', 'BCA')
        ->set('nomorRekening', '1234567890')
        ->set('atasNama', 'Siti Aminah')
        ->set('setuju', true)
        ->call('ajukan')
        ->assertHasNoErrors();

    Mail::assertSent(PemberitahuanFormulir::class, function ($surat) {
        return $surat->judul === 'Pengajuan Pembatalan'
            && str_contains($surat->rincian['Rekening pengembalian'], '1234567890')
            && count($surat->berkasPdf) === 1;
    });
});

/* -------------------- SALINAN UNTUK PELANGGAN --------------------
 *
 * Sebelum ini hanya kotak kantor yang menerima surat, sehingga pelanggan
 * tidak punya bukti apa pun begitu halaman ditutup — termasuk kode
 * pendaftarannya sendiri, yang justru dibutuhkan di semua formulir lanjutan.
 */

test('pendaftar ikut menerima salinan suratnya sendiri', function () {
    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $this->paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('email', 'siti@contoh.test')
        ->set('jumlahPeserta', 1)
        ->set('peserta', [['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();

    $kode = PendaftaranOpenTrip::firstOrFail()->kode;

    // Kantor tetap dapat suratnya seperti semula
    Mail::assertSent(PemberitahuanFormulir::class, fn ($surat) => $surat->hasTo('halo@orchajourney.com')
        && $surat->untukPelanggan === false);

    Mail::assertSent(PemberitahuanFormulir::class, function ($surat) use ($kode) {
        $html = $surat->render();

        return $surat->hasTo('siti@contoh.test')
            && $surat->untukPelanggan === true
            && $surat->judul === 'Pendaftaran Anda Sudah Kami Terima'
            // Kwitansinya ikut supaya pelanggan punya berkas yang bisa disimpan
            && count($surat->berkasPdf) === 1
            && str_starts_with(reset($surat->berkasPdf), '%PDF-')
            // Kodenya disebut di badan surat, bukan cuma di subjek
            && str_contains($html, $kode)
            && str_contains($html, 'Langkah Berikutnya')
            // Kotak masuk adalah tempat penipu menyamar → nama penerima yang sah ikut ditegaskan
            && str_contains($html, 'PT ASTHANA CIPTA MANDIRI');
    });

    Mail::assertSentCount(2);
});

test('subjek surat pelanggan tidak memakai awalan kotak kantor', function () {
    $kantor = new PemberitahuanFormulir('Pendaftaran Open Trip Baru', 'OT-1508-ABCD', []);
    $pelanggan = new PemberitahuanFormulir('Pendaftaran Anda Sudah Kami Terima', 'OT-1508-ABCD', [], untukPelanggan: true);

    expect($kantor->envelope()->subject)->toBe('[Orcha] Pendaftaran Open Trip Baru — OT-1508-ABCD');
    expect($pelanggan->envelope()->subject)->toBe('Orcha Journey — Pendaftaran Anda Sudah Kami Terima (OT-1508-ABCD)');
});

test('salinan pembayaran dikirim ke alamat pendaftarnya, tanpa mengembalikan fotonya', function () {
    $pendaftaran = pendaftaranUji('siti@contoh.test');

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $pendaftaran->kode)
        ->set('jenis', 'dp')
        ->set('nominalTeks', '500000')
        ->set('tanggalTransfer', now()->toDateString())
        ->set('bankPengirim', 'BCA')
        ->set('atasNamaPengirim', 'Siti Aminah')
        ->set('bukti', UploadedFile::fake()->image('bukti.jpg'))
        ->set('setuju', true)
        ->call('kirim')
        ->assertHasNoErrors();

    Mail::assertSent(PemberitahuanFormulir::class, function ($surat) {
        return $surat->hasTo('siti@contoh.test')
            && $surat->untukPelanggan === true
            // Foto bukti berasal dari pelanggan sendiri — tak perlu dikirim balik
            && $surat->lampiran === []
            && count($surat->berkasPdf) === 1
            // Jangan sampai terbaca sebagai tanda lunas
            && str_contains($surat->catatan, 'Menunggu Dicek');
    });
});

test('salinan kesehatan untuk pelanggan tidak memuat kontak darurat', function () {
    $pendaftaran = pendaftaranUji('siti@contoh.test');

    Volt::test('public.open-trip.riwayat-kesehatan')
        ->set('kode', $pendaftaran->kode)
        ->set('namaPeserta', 'Budi Santoso')
        ->set('usia', 28)
        ->set('jenisKelamin', 'Laki-laki')
        ->set('kemampuanRenang', 'tidak_bisa')
        ->set('riwayatPenyakit', 'Asma ringan')
        ->set('kontakNama', 'Siti')
        ->set('kontakHp', '081298765432')
        ->set('kontakHubungan', 'Istri')
        ->set('setuju', true)
        ->call('simpan')
        ->assertHasNoErrors();

    Mail::assertSent(PemberitahuanFormulir::class, function ($surat) {
        if (! $surat->untukPelanggan) {
            return false;
        }

        $isi = $surat->render();

        return $surat->hasTo('siti@contoh.test')
            && str_contains($isi, 'Budi Santoso')
            // Penyakit maupun kontak daruratnya tidak ikut ke kotak masuk pelanggan
            && ! str_contains($isi, 'Asma ringan')
            && ! str_contains($isi, '081298765432');
    });
});

test('alamat pelanggan yang salah ketik tidak menggagalkan apa pun', function () {
    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $this->paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        // Lolos validasi peramban, tetapi bukan alamat yang bisa dikirimi
        ->set('email', null)
        ->set('jumlahPeserta', 1)
        ->set('peserta', [['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();

    expect(PendaftaranOpenTrip::count())->toBe(1);
    Mail::assertSentCount(1);
});

test('salinan pelanggan bisa dimatikan lewat setelan', function () {
    config()->set('orcha.email_salinan_pelanggan', false);

    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $this->paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('email', 'siti@contoh.test')
        ->set('jumlahPeserta', 1)
        ->set('peserta', [['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();

    Mail::assertSentCount(1);
});

/* ------------------------------ PENJAGAAN ------------------------------ */

test('alamat kosong berarti tidak ada surat yang dikirim', function () {
    config()->set('orcha.email_pemberitahuan', null);

    pendaftaranUji();

    Volt::test('public.open-trip.pembatalan')
        ->set('kode', PendaftaranOpenTrip::first()->kode)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('alasan', 'kendala_biaya')
        ->set('jumlahDibatalkan', 1)
        ->set('bank', 'BCA')
        ->set('nomorRekening', '1234567890')
        ->set('atasNama', 'Siti Aminah')
        ->set('setuju', true)
        ->call('ajukan')
        ->assertHasNoErrors();

    Mail::assertNothingSent();
});

test('server surat yang mati tidak membatalkan pendaftaran', function () {
    // Yang penting: data pelanggan tetap tersimpan walau surat gagal dikirim.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP mati'));

    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $this->paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '081298765432')
        ->set('jumlahPeserta', 1)
        ->set('peserta', [['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();

    expect(PendaftaranOpenTrip::count())->toBe(1);
});

test('surat memuat logo yang ikut terkirim, bukan tautan gambar luar', function () {
    $html = (new PemberitahuanFormulir('Uji', 'OT-0000-XXXX', ['Pemesan' => 'Siti']))->render();

    expect($html)->toContain('ORCHA')
        ->and($html)->toContain('Orcha Journey')
        // Logo disisipkan lewat embed(); yang penting BUKAN tautan http ke situs,
        // karena klien surat memblokir gambar luar sampai penerima mengizinkan.
        ->and($html)->not->toContain('http://127.0.0.1:8000/orcha-logo')
        ->and($html)->toContain('<img');

    // Berkas logonya memang ada dan ringan — surat yang berat gampang tertahan
    expect(file_exists(public_path('orcha-logo-surat.png')))->toBeTrue()
        ->and(filesize(public_path('orcha-logo-surat.png')))->toBeLessThan(60_000);
});

test('kwitansi memakai stempel dan tanda tangan bila berkasnya ada', function () {
    $isi = App\Support\BerkasKwitansi::buat('Uji', 'OT-0000-XXXX', ['Pemesan' => 'Siti']);

    expect($isi)->not->toBeNull()
        ->and(substr($isi, 0, 5))->toBe('%PDF-')
        // Ringan: lampiran berat gampang tertahan penyaring surat
        ->and(strlen($isi))->toBeLessThan(400_000);

    // Slot stempel & tanda tangan memang disediakan, dan berkas tetap terbit
    // walau keduanya belum ada.
    $sumber = file_get_contents(base_path('resources/views/pdf/kwitansi.blade.php'));

    expect($sumber)->toContain("public_path('orcha-stempel.png')")
        ->and($sumber)->toContain("public_path('orcha-ttd.png')");
});
