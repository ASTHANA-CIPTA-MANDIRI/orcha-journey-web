<?php

use App\Http\Controllers\Api\Etalase\EtalaseController;
use App\Http\Controllers\Api\Kontak\PesanController;
use App\Http\Controllers\Api\OpenTrip\PembatalanController;
use App\Http\Controllers\Api\OpenTrip\PembayaranController;
use App\Http\Controllers\Api\OpenTrip\PendaftaranController;
use App\Http\Controllers\Api\PaketWisata\PaketWisataController;
use App\Http\Controllers\Api\SewaKendaraan\KendaraanController;
use App\Http\Controllers\Api\SewaKendaraan\PenyewaanController;
use App\Http\Controllers\Api\Umum\DashboardController;
use App\Http\Controllers\Api\Umum\MetaController;
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
| Controllernya dikelompokkan per fitur, sama seperti berkas Blade.
|
*/

Route::prefix('v1')
    ->middleware(['kunci.orcha', 'throttle:120,1'])
    ->group(function () {
        /* ------------------------------- UMUM ------------------------------- */
        Route::get('/ping', [MetaController::class, 'ping']);
        Route::get('/menu', [MetaController::class, 'menu']);
        Route::get('/rujukan', [MetaController::class, 'rujukan']);
        Route::get('/dashboard', DashboardController::class);

        /* ----------------------------- OPEN TRIP ----------------------------- */
        Route::get('/pendaftaran', [PendaftaranController::class, 'index']);
        Route::get('/pendaftaran/{pendaftaran}', [PendaftaranController::class, 'show']);
        Route::get('/pendaftaran/{pendaftaran}/riwayat-kesehatan', [PendaftaranController::class, 'riwayatKesehatan']);
        Route::patch('/pendaftaran/{pendaftaran}/status', [PendaftaranController::class, 'ubahStatus']);
        Route::get('/pendaftaran/{pendaftaran}/kwitansi', [PendaftaranController::class, 'kwitansi']);

        Route::get('/pembayaran', [PembayaranController::class, 'index']);
        Route::get('/pembayaran/{pembayaran}', [PembayaranController::class, 'show']);
        Route::patch('/pembayaran/{pembayaran}/status', [PembayaranController::class, 'ubahStatus']);

        Route::get('/pembatalan', [PembatalanController::class, 'index']);
        Route::get('/pembatalan/{pembatalan}', [PembatalanController::class, 'show']);
        Route::patch('/pembatalan/{pembatalan}/status', [PembatalanController::class, 'ubahStatus']);

        /* --------------------------- SEWA KENDARAAN --------------------------- */
        Route::get('/penyewaan', [PenyewaanController::class, 'index']);
        Route::get('/penyewaan/{penyewaan}', [PenyewaanController::class, 'show']);
        Route::patch('/penyewaan/{penyewaan}/status', [PenyewaanController::class, 'ubahStatus']);
        Route::patch('/penyewaan/{penyewaan}/serah-terima', [PenyewaanController::class, 'serahTerima']);

        Route::get('/kendaraan', [KendaraanController::class, 'index']);
        Route::get('/kendaraan/{kendaraan}', [KendaraanController::class, 'show']);
        Route::post('/kendaraan', [KendaraanController::class, 'store']);
        Route::match(['put', 'post'], '/kendaraan/{kendaraan}', [KendaraanController::class, 'update']);
        Route::delete('/kendaraan/{kendaraan}', [KendaraanController::class, 'destroy']);

        /* ------------------------------ KONTAK ------------------------------ */
        Route::get('/pesan', [PesanController::class, 'index']);
        Route::get('/pesan/{pesan}', [PesanController::class, 'show']);
        Route::patch('/pesan/{pesan}/dibaca', [PesanController::class, 'tandaiDibaca']);

        /* ---------------------------- PAKET WISATA ----------------------------
         | Gambar ikut sebagai multipart pada permintaan yang sama. Pembaruan
         | memakai POST + _method=PUT karena PHP tidak menguraikan multipart
         | pada permintaan PUT.
         */
        Route::get('/saran', [PaketWisataController::class, 'saran']);
        Route::post('/saran', [PaketWisataController::class, 'simpanSaran']);
        Route::delete('/saran/{saran}', [PaketWisataController::class, 'hapusSaran']);

        Route::get('/paket-wisata', [PaketWisataController::class, 'index']);
        Route::get('/paket-wisata/{paket}', [PaketWisataController::class, 'show']);
        Route::post('/paket-wisata', [PaketWisataController::class, 'store']);
        Route::match(['put', 'post'], '/paket-wisata/{paket}', [PaketWisataController::class, 'update']);
        Route::delete('/paket-wisata/{paket}', [PaketWisataController::class, 'destroy']);

        /* ------------------------------ ETALASE ------------------------------ */
        Route::get('/destinasi', [EtalaseController::class, 'destinasi']);
        Route::post('/destinasi', [EtalaseController::class, 'simpanDestinasi']);
        Route::match(['put', 'post'], '/destinasi/{destinasi}', [EtalaseController::class, 'perbaruiDestinasi']);
        Route::delete('/destinasi/{destinasi}', [EtalaseController::class, 'hapusDestinasi']);

        Route::get('/testimoni', [EtalaseController::class, 'testimoni']);
        Route::post('/testimoni', [EtalaseController::class, 'simpanTestimoni']);
        Route::match(['put', 'post'], '/testimoni/{testimoni}', [EtalaseController::class, 'perbaruiTestimoni']);
        Route::delete('/testimoni/{testimoni}', [EtalaseController::class, 'hapusTestimoni']);

        Route::get('/partner', [EtalaseController::class, 'partner']);
        Route::post('/partner', [EtalaseController::class, 'simpanPartner']);
        Route::match(['put', 'post'], '/partner/{partner}', [EtalaseController::class, 'perbaruiPartner']);
        Route::delete('/partner/{partner}', [EtalaseController::class, 'hapusPartner']);
    });
