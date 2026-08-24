<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;

/**
 * Kwitansi menyebutkan posisi pembayaran apa adanya.
 *
 * Berkas ini dipegang pelanggan, dan sebelumnya ia selalu mencetak tenggat DP,
 * sisa pelunasan, dan petunjuk transfer — apa pun keadaan pesanannya. Yang
 * sudah melunasi menerima berkas yang tetap menagih; yang pesanannya sudah
 * dibatalkan menerima berkas yang memintanya menyelesaikan sisa pembayaran.
 */
const KUNCI_KWITANSI = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_KWITANSI);
    config()->set('orcha.api.ip_diizinkan', []);
});

function kepalaKwitansi(): array
{
    return ['X-Orcha-Key' => KUNCI_KWITANSI, 'Accept' => 'application/json'];
}

function pendaftaranKwitansi(array $ubah = [], int $dibayar = 0): PendaftaranOpenTrip
{
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip',
        'price' => 1430000, 'minimal_peserta' => 6,
    ]);

    $daftar = PendaftaranOpenTrip::create(array_merge([
        'travel_package_id' => $paket->id, 'nama_paket' => $paket->name,
        'nama' => 'Suparjiman', 'whatsapp' => '081234567890',
        'jumlah_peserta' => 2, 'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ], $ubah));

    if ($dibayar > 0) {
        KonfirmasiPembayaran::create([
            'kode' => $daftar->kode, 'jenis' => 'dp', 'nominal' => $dibayar,
            'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
            'atas_nama_pengirim' => 'Suparjiman', 'status' => 'diterima',
        ]);
    }

    return $daftar->fresh();
}

test('kwitansi keempat keadaan tetap terbit', function (string $keterangan, array $ubah, int $dibayar) {
    // Isi kalimatnya diuji lewat templat; yang dijaga di sini adalah keempat
    // keadaan itu tidak ada yang membuat pembuatan berkasnya gagal — termasuk
    // pesanan batal, yang dulu tidak pernah dilalui jalur ini.
    $daftar = pendaftaranKwitansi($ubah, $dibayar);

    $balasan = $this->get("/api/v1/pendaftaran/{$daftar->id}/kwitansi", kepalaKwitansi());

    $balasan->assertOk();
    expect(substr($balasan->getContent(), 0, 5))->toBe('%PDF-');
})->with([
    ['belum bayar', [], 0],
    ['dp masuk', ['status' => 'dp_masuk'], 858000],
    ['lunas', ['status' => 'lunas'], 2860000],
    ['dibatalkan setelah dp', ['status' => 'batal'], 858000],
]);

test('kalimat keadaan untuk pesanan lunas menyatakan tidak ada sisa', function () {
    $html = view('pdf.kwitansi', [
        'judul' => 'Rincian Biaya Pendaftaran', 'kode' => 'OT-1608-FXYK',
        'rincian' => ['Pemesan' => 'Suparjiman'], 'catatan' => null,
        'jumlah' => 'Rp 2.860.000', 'jumlahLabel' => 'Sudah dibayar penuh', 'capStatus' => 'Lunas',
        'biaya' => ['satuan_teks' => 'Rp 1.430.000', 'orang' => 2, 'total_teks' => 'Rp 2.860.000',
            'dp_persen' => 30, 'dp_teks' => 'Rp 858.000', 'sisa_teks' => 'Rp 2.002.000',
            'dp_batas_jam' => 24, 'pelunasan_hari' => 5, 'tempo' => false],
        'tagihan' => [], 'nota' => [],
        'keadaan' => ['nada' => 'aman', 'kalimat' => 'Pembayaran Anda <strong>sudah lunas</strong>.'],
        'caraBayar' => false,
    ])->render();

    expect($html)
        ->toContain('sudah lunas')
        // Tenggat yang tidak perlu lagi dibayar tidak dicetak.
        ->not->toContain('dibayar sekarang')
        ->not->toContain('Sisa pelunasan')
        // Petunjuk transfer pun tidak: pelanggan yang membacanya mengira masih kurang.
        ->not->toContain('Cara Pembayaran')
        ->not->toContain('Nomor rekening dikirim tim kami')
        // Tanda tangan tetap ada.
        ->toContain('Hormat kami');
});

test('pesanan yang masih menunggu pembayaran tetap memuat cara pembayaran', function () {
    $html = view('pdf.kwitansi', [
        'judul' => 'Rincian Biaya Pendaftaran', 'kode' => 'OT-1608-FXYK',
        'rincian' => ['Pemesan' => 'Suparjiman'], 'catatan' => null,
        'jumlah' => 'Rp 858.000', 'jumlahLabel' => 'Dibayar sekarang · DP 30%',
        'capStatus' => 'Belum Dibayar',
        'biaya' => ['satuan_teks' => 'Rp 1.430.000', 'orang' => 2, 'total_teks' => 'Rp 2.860.000',
            'dp_persen' => 30, 'dp_teks' => 'Rp 858.000', 'sisa_teks' => 'Rp 2.002.000',
            'dp_batas_jam' => 24, 'pelunasan_hari' => 5],
        'tagihan' => [], 'nota' => [],
        'keadaan' => ['nada' => 'netral',
            'kalimat' => '<strong>Belum ada pembayaran yang kami terima</strong> untuk pendaftaran ini.'],
        'caraBayar' => true,
    ])->render();

    expect($html)
        ->toContain('Belum ada pembayaran yang kami terima')
        ->toContain('dibayar sekarang')
        ->toContain('Sisa pelunasan')
        ->toContain('Cara Pembayaran');
});

test('kalimat keadaan untuk pesanan batal tidak meminta transfer lagi', function () {
    $html = view('pdf.kwitansi', [
        'judul' => 'Rincian Biaya Pendaftaran', 'kode' => 'OT-1608-ZT8K',
        'rincian' => ['Pemesan' => 'Sofyan'], 'catatan' => null,
        'jumlah' => 'Rp 570.000', 'jumlahLabel' => 'Sudah dibayar sebelum dibatalkan',
        'capStatus' => 'Dibatalkan',
        'biaya' => ['satuan_teks' => 'Rp 1.900.000', 'orang' => 1, 'total_teks' => 'Rp 1.900.000',
            'dp_persen' => 30, 'dp_teks' => 'Rp 570.000', 'sisa_teks' => 'Rp 1.330.000',
            'dp_batas_jam' => 24, 'pelunasan_hari' => 5, 'tempo' => false],
        'tagihan' => [], 'nota' => [],
        'keadaan' => ['nada' => 'awas',
            'kalimat' => '<strong>Pendaftaran ini dibatalkan.</strong> Pembayaran yang sudah kami terima '
                .'sebesar <strong>Rp 570.000</strong> diproses menurut kebijakan pengembalian. '
                .'Tidak ada lagi yang perlu Anda transfer.'],
        'caraBayar' => false,
    ])->render();

    expect($html)
        ->toContain('Pendaftaran ini dibatalkan')
        ->toContain('Tidak ada lagi yang perlu Anda transfer')
        // Yang paling berbahaya: berkas batal yang tetap menagih sisa pelunasan.
        ->not->toContain('Sisa pelunasan')
        ->not->toContain('Cara Pembayaran');
});

test('kalimat keadaan untuk yang sudah dp menyebut dp masuk dan sisanya', function () {
    $html = view('pdf.kwitansi', [
        'judul' => 'Rincian Biaya Pendaftaran', 'kode' => 'OT-1608-FXYK',
        'rincian' => ['Pemesan' => 'Suparjiman'], 'catatan' => null,
        'jumlah' => 'Rp 2.002.000', 'jumlahLabel' => 'Sisa yang harus dibayar',
        'capStatus' => 'Dibayar Sebagian',
        'biaya' => ['satuan_teks' => 'Rp 1.430.000', 'orang' => 2, 'total_teks' => 'Rp 2.860.000',
            'dp_persen' => 30, 'dp_teks' => 'Rp 858.000', 'sisa_teks' => 'Rp 2.002.000',
            'dp_batas_jam' => 24, 'pelunasan_hari' => 5],
        'tagihan' => [], 'nota' => [],
        'keadaan' => ['nada' => 'awas',
            'kalimat' => 'Uang muka Anda sebesar <strong>Rp 858.000</strong> <strong>sudah kami terima</strong>. '
                .'Sisa yang perlu dilunasi <strong>Rp 2.002.000</strong>, paling lambat '
                .'<strong>14 Oktober 2026</strong> (H-5 sebelum berangkat).'],
        'caraBayar' => true,
    ])->render();

    expect($html)
        ->toContain('sudah kami terima')
        ->toContain('Sisa yang perlu dilunasi')
        ->toContain('14 Oktober 2026')
        // Masih menunggu uang, jadi petunjuk transfernya tetap dicetak.
        ->toContain('Cara Pembayaran');
});
