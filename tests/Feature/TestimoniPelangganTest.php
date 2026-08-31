<?php

use App\Models\Etalase\Testimoni;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use Livewire\Volt\Volt;

/**
 * Testimoni yang ditulis pelanggannya sendiri.
 *
 * Sebelumnya bagian "Kirim Testimoni" cuma tombol WhatsApp: pelanggan
 * mengetik ceritanya di sana, lalu admin menyalin dan mengetikkannya ulang di
 * panel. Pekerjaan itu selalu kalah prioritas dibanding pesanan yang sedang
 * berjalan, jadi sebagian besar cerita tidak pernah sampai ke halaman ini.
 */
beforeEach(function () {
    $this->pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 2,
        'nama_paket' => 'Open Trip Banyuwangi',
        'status' => 'lunas',
    ]);
});

test('pelanggan dengan pesanan terbukti bisa mengirim testimoni', function () {
    Volt::test('public.testimoni.index')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('nilai', 5)
        ->set('isi', 'Sopirnya sabar dan armadanya bersih. Sunrise di Penanjakan tidak terlupakan.')
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertSet('terkirim', true);

    $testimoni = Testimoni::first();

    // Namanya diambil dari pesanan, bukan diketik sendiri — supaya yang tampil
    // tidak bisa dipakai menyamar sebagai orang lain.
    expect($testimoni->customer_name)->toBe('Budi Santoso')
        ->and($testimoni->kode_pesanan)->toBe($this->pendaftaran->kode)
        ->and($testimoni->terverifikasi)->toBeTrue();
});

test('testimoni baru menunggu disetujui, tidak langsung tayang', function () {
    /*
     | Bukan karena penulisnya diragukan — ia sudah membuktikan pesanannya —
     | melainkan karena halaman ini terbaca sebagai suara perusahaan: satu
     | kalimat kasar yang lolos ke beranda merugikan pembacanya maupun yang
     | menulisnya.
     */
    Volt::test('public.testimoni.index')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('isi', 'Perjalanannya menyenangkan dari awal sampai akhir, terima kasih.')
        ->call('kirim')
        ->assertHasNoErrors();

    expect(Testimoni::first()->status)->toBe('menunggu');

    // Dan benar-benar belum tampil di halaman publik.
    $this->get(route('testimoni'))
        ->assertOk()
        ->assertDontSee('Perjalanannya menyenangkan dari awal sampai akhir');
});

test('yang belum pernah memesan tidak bisa menulis testimoni', function () {
    Volt::test('public.testimoni.index')
        ->set('kode', 'OT-0000-XXXXXX')
        ->set('empatDigit', '7890')
        ->set('isi', 'Bagus sekali pelayanannya, sangat memuaskan sekali.')
        ->call('kirim')
        ->assertHasErrors('kode');

    expect(Testimoni::count())->toBe(0);
});

test('kode yang benar dengan nomor yang salah juga ditolak', function () {
    Volt::test('public.testimoni.index')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '0000')
        ->set('isi', 'Bagus sekali pelayanannya, sangat memuaskan sekali.')
        ->call('kirim')
        ->assertHasErrors('kode');

    expect(Testimoni::count())->toBe(0);
});

test('satu pesanan hanya boleh mengirim satu testimoni', function () {
    /*
     | Tanpa ini satu orang bisa mengirim berkali-kali dan memenuhi halaman
     | dengan suaranya sendiri — dan karena semuanya terverifikasi, tidak ada
     | satu pun tanda bahwa itu orang yang sama.
     */
    $kirim = fn () => Volt::test('public.testimoni.index')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->set('isi', 'Perjalanannya menyenangkan dari awal sampai akhir, terima kasih.')
        ->call('kirim');

    $kirim()->assertHasNoErrors();
    $kirim()->assertHasErrors('kode');

    expect(Testimoni::count())->toBe(1);
});

test('testimoni yang menunggu tidak ikut terhitung di beranda', function () {
    Testimoni::create([
        'customer_name' => 'Menunggu', 'rating' => 5,
        'testimonial' => 'Belum disetujui.', 'status' => 'menunggu',
    ]);

    // Kalau yang menunggu ikut terhitung, angka "sekian ulasan" di beranda
    // menjanjikan lebih banyak daripada yang benar-benar bisa dibaca.
    $this->get(route('home'))->assertOk()->assertDontSee('Belum disetujui.');
});

test('testimoni terverifikasi ditandai di halaman publik', function () {
    Testimoni::create([
        'customer_name' => 'Budi Santoso', 'rating' => 5,
        'testimonial' => 'Sopirnya sabar dan armadanya bersih sekali.',
        'kode_pesanan' => $this->pendaftaran->kode, 'status' => 'tayang',
    ]);

    Testimoni::create([
        'customer_name' => 'Tanpa Kode', 'rating' => 5,
        'testimonial' => 'Diketik admin dari pesan WhatsApp.', 'status' => 'tayang',
    ]);

    $isi = $this->get(route('testimoni'))->assertOk()->getContent();

    // Penandanya muncul sekali saja — hanya untuk yang punya kode pesanan.
    expect(substr_count($isi, 'Terverifikasi'))->toBe(1);
});
