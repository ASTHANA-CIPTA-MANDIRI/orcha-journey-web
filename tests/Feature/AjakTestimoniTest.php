<?php

use App\Models\Etalase\Testimoni;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use Illuminate\Support\Facades\Mail;

/**
 * Ajakan menulis testimoni, dikirim setelah pesertanya pulang.
 *
 * Sebelum ini trip berakhir lalu senyap. Formulir testimoni untuk pelanggan
 * sudah ada — hanya tidak ada satu pun yang mengajak mengisinya, dan fitur yang
 * menunggu ditemukan sendiri hampir tidak pernah ditemukan.
 */
beforeEach(fn () => Mail::fake());

function pesertaPulang(array $ubah = []): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create(array_merge([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'email' => 'budi@contoh.test',
        'jumlah_peserta' => 2,
        'nama_paket' => 'Open Trip Bromo',
        'status' => 'lunas',
        'tanggal_berangkat' => now()->subDays(2)->toDateString(),
    ], $ubah));
}

test('peserta yang pulang dua hari lalu diajak', function () {
    pesertaPulang();

    $this->artisan('orcha:ajak-testimoni')
        ->expectsOutputToContain('1 peserta diajak')
        ->assertSuccessful();
});

test('yang baru pulang hari ini belum diajak', function () {
    /*
     | Bukan hari-H: orang masih di perjalanan pulang, lelah, dan kesannya
     | belum mengendap.
     */
    pesertaPulang(['tanggal_berangkat' => now()->toDateString()]);

    $this->artisan('orcha:ajak-testimoni')
        ->expectsOutputToContain('Tidak ada yang perlu diajak')
        ->assertSuccessful();
});

test('yang belum lunas tidak diajak', function () {
    /*
     | Mengirim "bagaimana perjalanannya?" kepada orang yang tagihannya belum
     | selesai terbaca sebagai penagihan yang menyamar.
     */
    pesertaPulang(['status' => 'dp_masuk']);

    $this->artisan('orcha:ajak-testimoni')
        ->expectsOutputToContain('Tidak ada yang perlu diajak')
        ->assertSuccessful();
});

test('yang batal tidak diajak', function () {
    pesertaPulang(['status' => 'batal']);

    $this->artisan('orcha:ajak-testimoni')
        ->expectsOutputToContain('Tidak ada yang perlu diajak')
        ->assertSuccessful();
});

test('yang sudah menulis testimoni tidak diajak lagi', function () {
    // Ajakan kedua untuk hal yang sudah dikerjakan terbaca sebagai sistem yang
    // tidak memperhatikan.
    $daftar = pesertaPulang();

    Testimoni::create([
        'customer_name' => 'Budi Santoso', 'rating' => 5,
        'testimonial' => 'Seru sekali.', 'kode_pesanan' => $daftar->kode,
    ]);

    $this->artisan('orcha:ajak-testimoni')
        ->expectsOutputToContain('Tidak ada yang perlu diajak')
        ->assertSuccessful();
});

test('yang tidak mencantumkan email dilewati tanpa menggagalkan yang lain', function () {
    // Email memang boleh kosong di formulir pendaftaran — nomor WhatsApp yang
    // wajib. Yang tanpa email tidak bisa diajak lewat jalur ini, dan itu bukan
    // kegagalan.
    pesertaPulang(['email' => null]);
    pesertaPulang(['nama' => 'Siti', 'email' => 'siti@contoh.test']);

    $this->artisan('orcha:ajak-testimoni')
        ->expectsOutputToContain('1 peserta diajak')
        ->assertSuccessful();
});

test('percobaan tidak mengirim surat apa pun', function () {
    pesertaPulang();

    $this->artisan('orcha:ajak-testimoni', ['--percobaan' => true])->assertSuccessful();

    Mail::assertNothingSent();
});

test('tautannya membawa kode pesanan supaya tinggal menulis', function () {
    $daftar = pesertaPulang();

    $this->artisan('orcha:ajak-testimoni')->assertSuccessful();

    Mail::assertSent(App\Mail\PemberitahuanFormulir::class, function ($surat) use ($daftar) {
        return str_contains($surat->render(), $daftar->kode);
    });
});
