<?php

use App\Mail\PemberitahuanFormulir;
use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\PengingatPesanan;
use App\Support\RencanaAngsuran;
use Illuminate\Support\Facades\Mail;

/**
 * Pengingat angsuran.
 *
 * Yang dijaga di sini bukan isi suratnya melainkan siapa yang menerimanya, dan
 * berapa kali. Surat tagihan yang datang kepada orang yang sudah membayar
 * adalah cara tercepat membuatnya berhenti membuka surel dari kami.
 */
beforeEach(function () {
    Mail::fake();

    $this->pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Siti Aminah',
        'whatsapp' => '081298765432',
        'email' => 'siti@contoh.test',
        'jumlah_peserta' => 2,
        'harga_jual' => 1_430_000,
        'nama_paket' => 'Study Tour Bromo',
        'tanggal_berangkat' => now()->addDays(90)->toDateString(),
    ])->fresh();

    $this->rencana = Angsuran::create([
        'kode' => $this->pendaftaran->kode,
        'jumlah_termin' => 3,
        'total' => $this->pendaftaran->omzet,
    ]);

    foreach (RencanaAngsuran::susun($this->pendaftaran, 3) as $baris) {
        $this->rencana->termin()->create([
            'urutan' => $baris['urutan'],
            'nominal' => $baris['nominal'],
            'jatuh_tempo' => $baris['jatuh_tempo'],
        ]);
    }
});

test('termin diingatkan menjelang jatuh temponya', function () {
    // Termin pertama jatuh tempo besok, jadi sudah masuk jendela tiga hari.
    $hasil = PengingatPesanan::jalankan();

    expect($hasil['angsuran'])->toHaveCount(1);

    Mail::assertSent(PemberitahuanFormulir::class, fn ($surat) => $surat->untukPelanggan
        && $surat->hasTo('siti@contoh.test')
        && $surat->rincian['Jumlah'] === 'Rp 858.000');
});

test('satu termin hanya diingatkan sekali', function () {
    /*
     | Penandanya di TERMIN, bukan di rencananya. Penanda di tingkat rencana
     | hanya bisa menjawab "sudah pernah diingatkan" — dan pelanggan lalu tidak
     | pernah diingatkan lagi sampai lunas, padahal justru jatuh tempo kedua
     | dan ketiga yang paling mudah terlupa.
     */
    PengingatPesanan::jalankan();

    expect(PengingatPesanan::jalankan()['angsuran'])->toBe([]);
});

test('termin yang sudah tertutup pembayaran tidak ditagih', function () {
    // Yang membayar lebih awal justru yang paling tidak pantas menerima surat
    // tagihan.
    KonfirmasiPembayaran::create([
        'kode' => $this->pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 858_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    expect(PengingatPesanan::jalankan()['angsuran'])->toBe([]);
});

test('rencana yang dibatalkan tidak lagi menagih', function () {
    $this->rencana->update(['dibatalkan_pada' => now()]);

    expect(PengingatPesanan::jalankan()['angsuran'])->toBe([]);
});

test('termin yang lewat jatuh tempo dilaporkan ke kantor, bukan ke pelanggan', function () {
    /*
     | Yang telat sudah menerima pengingatnya sebelum jatuh tempo. Surat kedua
     | yang isinya menagih lagi tidak menambah kemampuannya membayar — yang
     | belum tahu justru admin.
     */
    $this->travel(3)->days();

    $hasil = PengingatPesanan::jalankan();

    expect($hasil['angsuran_telat'])->toHaveCount(1);

    Mail::assertSent(PemberitahuanFormulir::class, fn ($surat) => ! $surat->untukPelanggan
        && $surat->judul === 'Angsuran Lewat Jatuh Tempo'
        && $surat->rincian['Masih kurang'] === 'Rp 858.000');
});

test('keterlambatan dilaporkan sekali, bukan tiap hari', function () {
    $this->travel(3)->days();
    PengingatPesanan::jalankan();

    $this->travel(1)->days();

    expect(PengingatPesanan::jalankan()['angsuran_telat'])->toBe([]);
});
