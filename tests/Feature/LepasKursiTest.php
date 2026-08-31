<?php

use App\Models\JejakAudit;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\LepaskanKursiTertahan;
use Illuminate\Support\Facades\Mail;

/**
 * Kursi yang ditahan pemesanan yang tidak pernah membayar.
 *
 * Sebelum ini tidak ada apa pun yang melepasnya: satu pendaftaran di data
 * nyata menahan 46 kursi selama dua minggu tanpa satu rupiah pun masuk. Sejak
 * kuota berlaku, kursi seperti itu membuat admin melihat "penuh" pada trip
 * yang sebenarnya kosong.
 */
beforeEach(function () {
    Mail::fake();

    $this->paket = TravelPackage::create([
        'name' => 'Open Trip Bromo', 'category' => 'open_trip',
        'price' => 750000, 'status' => 'terbit', 'kuota' => 10,
    ]);
});

function pendaftaranUmur(TravelPackage $paket, int $jam, string $status = 'baru', int $peserta = 2): PendaftaranOpenTrip
{
    $daftar = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'email' => 'budi@contoh.test',
        'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name,
        'jumlah_peserta' => $peserta,
        'status' => $status,
    ]);

    // created_at diatur langsung: umur pendaftaranlah yang diuji, bukan
    // kemampuan menunggu tiga hari.
    $daftar->forceFill(['created_at' => now()->subHours($jam)])->save();

    return $daftar->fresh();
}

test('pemesanan tanpa pembayaran lewat 72 jam dilepas', function () {
    $lama = pendaftaranUmur($this->paket, 80);

    LepaskanKursiTertahan::jalankan();

    expect($lama->fresh()->status)->toBe('batal');

    // Dan kursinya benar-benar kembali — itu inti seluruh perubahan ini.
    expect($this->paket->fresh()->sisa_kursi)->toBe(10);
});

test('pemesanan yang belum lewat batas tidak disentuh', function () {
    $baru = pendaftaranUmur($this->paket, 70);

    LepaskanKursiTertahan::jalankan();

    expect($baru->fresh()->status)->toBe('baru');
});

test('yang sudah pernah mengirim bukti tidak dilepas, walau buktinya ditolak', function () {
    /*
     | Orang yang sudah mengunggah bukti sedang berusaha membayar. Buktinya
     | bisa saja salah nominal atau salah rekening, dan jawabannya memperbaiki
     | bukti itu — bukan kehilangan kursinya diam-diam sementara ia menunggu
     | kabar.
     */
    $daftar = pendaftaranUmur($this->paket, 200);

    KonfirmasiPembayaran::create([
        'kode' => $daftar->kode,
        'jenis' => 'dp',
        'nominal' => 500000,
        'tanggal_transfer' => now()->subDays(5)->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso',
        'status' => 'ditolak',
    ]);

    LepaskanKursiTertahan::jalankan();

    expect($daftar->fresh()->status)->toBe('baru');
});

test('yang sudah dp atau lunas tidak pernah dilepas', function () {
    $dp = pendaftaranUmur($this->paket, 500, 'dp_masuk');
    $lunas = pendaftaranUmur($this->paket, 500, 'lunas');

    LepaskanKursiTertahan::jalankan();

    expect($dp->fresh()->status)->toBe('dp_masuk')
        ->and($lunas->fresh()->status)->toBe('lunas');
});

test('yang sudah dihubungi tetapi belum membayar ikut dilepas', function () {
    /*
     | 'dihubungi' berarti admin sudah bicara dengan orangnya — pemesanan yang
     | paling hidup di antara yang belum bayar. Tetapi tanpa uang masuk,
     | kursinya tetap kursi yang tidak bisa dijual kepada orang lain.
     */
    $daftar = pendaftaranUmur($this->paket, 100, 'dihubungi');

    LepaskanKursiTertahan::jalankan();

    expect($daftar->fresh()->status)->toBe('batal');
});

test('percobaan tidak mengubah apa pun', function () {
    $daftar = pendaftaranUmur($this->paket, 100);

    $hasil = LepaskanKursiTertahan::jalankan(percobaan: true);

    expect($hasil['dilepas'])->toBe(1)
        ->and($daftar->fresh()->status)->toBe('baru');
});

test('pelepasan tercatat di jejak audit atas nama Sistem', function () {
    /*
     | Pelakunya ditulis "Sistem", bukan dikosongkan: yang membaca jejak nanti
     | perlu tahu bahwa pembatalan ini bukan keputusan seseorang — kalau tidak,
     | ia akan mencari admin yang melakukannya dan tidak menemukan siapa pun.
     */
    $daftar = pendaftaranUmur($this->paket, 100);

    LepaskanKursiTertahan::jalankan();

    $jejak = JejakAudit::where('kode', $daftar->kode)->first();

    expect($jejak)->not->toBeNull()
        ->and($jejak->admin)->toBe('Sistem')
        ->and($jejak->sesudah)->toBe('batal');
});

test('alasannya ikut tertulis di catatan pemesanan', function () {
    // Admin yang membuka pemesanan itu besok harus bisa tahu kenapa statusnya
    // batal tanpa perlu membuka halaman jejak audit.
    $daftar = pendaftaranUmur($this->paket, 100);

    LepaskanKursiTertahan::jalankan();

    expect($daftar->fresh()->catatan)
        ->toContain('dilepas otomatis')
        ->toContain('72 jam');
});

test('catatan yang sudah ada tidak tertimpa', function () {
    $daftar = pendaftaranUmur($this->paket, 100);
    $daftar->update(['catatan' => 'Minta kursi dekat jendela.']);

    LepaskanKursiTertahan::jalankan();

    expect($daftar->fresh()->catatan)
        ->toContain('Minta kursi dekat jendela.')
        ->toContain('dilepas otomatis');
});

test('perintah artisan melaporkan yang dilepas', function () {
    pendaftaranUmur($this->paket, 100);

    $this->artisan('orcha:lepas-kursi')
        ->expectsOutputToContain('1 pemesanan dilepas')
        ->assertSuccessful();
});

test('perintah artisan tetap berhasil saat tidak ada yang perlu dilepas', function () {
    // Cron menjalankannya tiap jam; sebagian besar jalannya memang tidak
    // menemukan apa-apa, dan itu bukan kegagalan.
    $this->artisan('orcha:lepas-kursi')
        ->expectsOutputToContain('Tidak ada kursi yang perlu dilepas')
        ->assertSuccessful();
});
