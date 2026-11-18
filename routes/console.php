<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Operasi terjadwal
|--------------------------------------------------------------------------
|
| Orcha punya tiga belas perintah pemeliharaan — pengingat pelunasan, pelepas
| kursi tertahan, penyelaras status, cadangan basis data — dan sampai 18 Nov
| 2026 TIDAK SATU PUN pernah dijadwalkan. Semuanya hanya berjalan bila ada
| yang mengetikkannya di terminal, dan itu berarti tidak pernah.
|
| DIBUNGKUS Artisan::call(), BUKAN Schedule::command().
|
| Schedule::command() menjalankan tiap tugas sebagai proses TERPISAH, dan itu
| menuntut proc_open. Hosting ini mematikannya — sama persis dengan yang sudah
| terbukti di Phoenix, yang berjalan di server yang sama. Lewat Artisan::call()
| perintahnya dijalankan di dalam proses yang sedang berjalan, jadi proc_open
| tidak pernah disentuh.
|
| TIAP withoutOverlapping() DIBERI BATAS MENIT YANG EKSPLISIT. Satu run yang
| mati di tengah meninggalkan kuncinya terpegang; dengan bawaan Laravel kunci
| itu bertahan 24 JAM dan tugasnya dilewati DIAM-DIAM selama itu — tanpa galat,
| tanpa gejala. Pelajaran yang sudah dibayar mahal di Phoenix (23 Juli 2026).
|
| MASIH MENUNGGU SATU LANGKAH DI SERVER: entri cron di hPanel yang memanggil
| `artisan schedule:run` tiap menit. Tanpa itu berkas ini tidak menjalankan
| apa pun — persis seperti sebelumnya.
*/

$jadwalkan = fn (string $perintah) => Schedule::call(function () use ($perintah) {
    Artisan::call($perintah);
})->name($perintah);

// Pagi hari, sebelum jam kerja: tiga daftar pekerjaan sewa hari ini.
$jadwalkan('sewa:kabar-harian')->dailyAt('06:30')->withoutOverlapping(30);

/*
 | Status pesanan diselaraskan dengan pembayaran yang sudah diterima.
 |
 | Tiap sepuluh menit, bukan tiap menit: yang diperbaikinya adalah selisih
 | yang muncul saat admin menerima bukti transfer, dan sepuluh menit jeda di
 | situ tidak pernah jadi masalah — sementara menjalankannya tiap menit
 | menyapu seluruh tabel pesanan 1.440 kali sehari.
 */
$jadwalkan('orcha:selaraskan-status')->everyTenMinutes()->withoutOverlapping(10);

// Kursi yang ditahan tanpa dibayar dilepas supaya bisa dijual lagi.
$jadwalkan('orcha:lepas-kursi')->everyFiveMinutes()->withoutOverlapping(5);

// Pengingat pelunasan, angsuran, dan briefing keberangkatan.
$jadwalkan('orcha:pengingat')->dailyAt('08:00')->withoutOverlapping(60);

// Ajakan menulis testimoni untuk yang baru pulang.
$jadwalkan('orcha:ajak-testimoni')->dailyAt('10:00')->withoutOverlapping(60);

// Tanggal paket mengikuti keberangkatan terdekatnya.
$jadwalkan('paket:selaraskan-tanggal')->dailyAt('01:00')->withoutOverlapping(60);

// Data kesehatan peserta dihapus setelah lewat batas simpan.
$jadwalkan('orcha:bersihkan-kesehatan')->dailyAt('02:00')->withoutOverlapping(60);

/*
 | Cadangan basis data, dini hari.
 |
 | Terakhir dalam daftar dan paling jarang: ia yang paling berat, dan
 | menjalankannya bersamaan dengan yang lain pada hosting berbagi membuat
 | keduanya sama-sama lambat.
 */
$jadwalkan('orcha:cadangan')->dailyAt('03:00')->withoutOverlapping(120);
