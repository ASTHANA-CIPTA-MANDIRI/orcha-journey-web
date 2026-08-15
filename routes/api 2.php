<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KatalogController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\PembatalanController;
use App\Http\Controllers\Api\PendaftaranController;
use App\Http\Controllers\Api\PenyewaanController;
use App\Http\Controllers\Api\PesanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Dashboard Orcha
|--------------------------------------------------------------------------
|
| Dipakai dashboard admin Phoenix supaya admin cukup satu kali login. Basis
| datanya tetap di sini; Phoenix hanya menggambar tampilannya.
|
| Semua jalur dijaga kunci rahasia (header X-Orcha-Key) dan dibatasi 120
| permintaan per menit per IP. Hak akses per admin tetap urusan Phoenix —
| di sisi Orcha, siapa pun yang memegang kunci dianggap sudah berwenang.
|
*/

Route::prefix('v1')
    ->middleware(['kunci.orcha', 'throttle:120,1'])
    ->group(function () {
        // Keterangan sistem
        Route::get('/ping', [MetaController::class, 'ping']);
        Route::get('/menu', [MetaController::class, 'menu']);
        Route::get('/rujukan', [MetaController::class, 'rujukan']);

        // Dashboard
        Route::get('/dashboard', DashboardController::class);

        // Pendaftaran open trip
        Route::get('/pendaftaran', [PendaftaranController::class, 'index']);
        Route::get('/pendaftaran/{pendaftaran}', [PendaftaranController::class, 'show']);
        Route::get('/pendaftaran/{pendaftaran}/riwayat-kesehatan', [PendaftaranController::class, 'riwayatKesehatan']);
        Route::patch('/pendaftaran/{pendaftaran}/status', [PendaftaranController::class, 'ubahStatus']);

        // Sewa kendaraan yang masuk
        Route::get('/penyewaan', [PenyewaanController::class, 'index']);
        Route::get('/penyewaan/{penyewaan}', [PenyewaanController::class, 'show']);
        Route::patch('/penyewaan/{penyewaan}/status', [PenyewaanController::class, 'ubahStatus']);

        // Pembatalan
        Route::get('/pembatalan', [PembatalanController::class, 'index']);
        Route::get('/pembatalan/{pembatalan}', [PembatalanController::class, 'show']);
        Route::patch('/pembatalan/{pembatalan}/status', [PembatalanController::class, 'ubahStatus']);

        // Pesan kontak
        Route::get('/pesan', [PesanController::class, 'index']);
        Route::get('/pesan/{pesan}', [PesanController::class, 'show']);
        Route::patch('/pesan/{pesan}/dibaca', [PesanController::class, 'tandaiDibaca']);

        // Etalase (baca saja)
        Route::get('/paket-wisata', [KatalogController::class, 'paket']);
        Route::get('/paket-wisata/{paket}', [KatalogController::class, 'paketDetail']);
        Route::get('/kendaraan', [KatalogController::class, 'kendaraan']);
        Route::get('/kendaraan/{kendaraan}', [KatalogController::class, 'kendaraanDetail']);
        Route::get('/destinasi', [KatalogController::class, 'destinasi']);
        Route::get('/testimoni', [KatalogController::class, 'testimoni']);
        Route::get('/partner', [KatalogController::class, 'partner']);
    });
