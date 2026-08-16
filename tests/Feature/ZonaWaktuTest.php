<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use Carbon\Carbon;

/**
 * Waktu aplikasi mengikuti jam dinding pembacanya.
 *
 * Selama zona waktunya masih UTC, surat pemberitahuan dan kwitansi tertulis
 * tujuh jam lebih awal — pendaftaran pukul 07.50 pagi tercetak 00.50 — dan
 * batas "hari ini" berpindah pukul tujuh pagi, sehingga aturan penayangan
 * paket serta tanggal keberangkatan ikut meleset sehari.
 */
test('aplikasi memakai waktu Indonesia bagian barat', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(now()->getOffset())->toBe(7 * 3600);
});

test('pergantian hari mengikuti tengah malam WIB, bukan UTC', function () {
    // 20 Agustus pukul 20.00 UTC sama dengan 21 Agustus pukul 03.00 WIB
    Carbon::setTestNow(Carbon::parse('2026-08-20 20:00:00', 'UTC'));

    expect(today()->toDateString())->toBe('2026-08-21')
        ->and(now()->format('H:i'))->toBe('03:00');

    Carbon::setTestNow();
});

test('stempel waktu tersimpan sesuai jam dinding', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-16 07:50:00', 'Asia/Jakarta'));

    $pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Siti Aminah',
        'whatsapp' => '081298765432',
        'jumlah_peserta' => 1,
        'nama_paket' => 'Open Trip Banyuwangi',
    ]);

    // Yang tertulis di surat dan kwitansi berasal dari sini
    expect($pendaftaran->created_at->format('d M Y H:i'))->toBe('16 Aug 2026 07:50');

    Carbon::setTestNow();
});

test('berkas resmi menuliskan jam WIB apa adanya', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-16 07:50:00', 'Asia/Jakarta'));

    $html = view('pdf.kwitansi', [
        'judul' => 'Rincian Biaya Pendaftaran',
        'kode' => 'OT-1608-ABCD',
        'rincian' => ['Pemesan' => 'Siti Aminah'],
        'catatan' => null,
        'jumlah' => null,
        'jumlahLabel' => null,
        'capStatus' => null,
        'biaya' => [],
    ])->render();

    expect($html)->toContain('16 Agustus 2026, 07:50 WIB');

    Carbon::setTestNow();
});
