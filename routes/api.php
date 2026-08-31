<?php

use App\Http\Controllers\Api\Blog\ArtikelController;
use App\Http\Controllers\Api\Blog\KategoriArtikelController;
use App\Http\Controllers\Api\Etalase\EtalaseController;
use App\Http\Controllers\Api\Etalase\ProvinsiController;
use App\Http\Controllers\Api\Kontak\PesanController;
use App\Http\Controllers\Api\OpenTrip\PembatalanController;
use App\Http\Controllers\Api\OpenTrip\PembayaranController;
use App\Http\Controllers\Api\OpenTrip\PendaftaranController;
use App\Http\Controllers\Api\PaketWisata\KeuntunganController;
use App\Http\Controllers\Api\PaketWisata\PaketWisataController;
use App\Http\Controllers\Api\SewaKendaraan\BagianPemeriksaanController;
use App\Http\Controllers\Api\SewaKendaraan\KatalogKendaraanController;
use App\Http\Controllers\Api\SewaKendaraan\KendaraanController;
use App\Http\Controllers\Api\SewaKendaraan\PenyewaanController;
use App\Http\Controllers\Api\Umum\DashboardController;
use App\Http\Controllers\Api\Umum\JejakAuditController;
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

        /*
         | Jejak audit — HANYA membaca.
         |
         | Tidak ada endpoint untuk menyunting atau menghapusnya. Catatan audit
         | yang bisa dihapus lewat API bukan catatan audit: ia cuma daftar yang
         | kebetulan berisi kejadian, dan baris yang pertama dihapus justru
         | yang paling perlu dibaca.
         */
        Route::get('/jejak-audit', [JejakAuditController::class, 'index']);
        Route::get('/jejak-audit/admin', [JejakAuditController::class, 'admin']);

        /* ----------------------------- OPEN TRIP ----------------------------- */
        Route::get('/pendaftaran', [PendaftaranController::class, 'index']);
        // Sebelum /{pendaftaran}: tanpa ini "perhatian" terbaca sebagai nomor.
        Route::get('/pendaftaran/perhatian', [PendaftaranController::class, 'perhatian']);
        Route::get('/pendaftaran/{pendaftaran}', [PendaftaranController::class, 'show']);
        Route::get('/pendaftaran/{pendaftaran}/riwayat-kesehatan', [PendaftaranController::class, 'riwayatKesehatan']);
        Route::patch('/pendaftaran/{pendaftaran}/status', [PendaftaranController::class, 'ubahStatus']);
        // Melengkapi nama peserta yang belum didata — dari sisi admin, karena
        // pemesan tidak selalu bisa diminta mengisi ulang lewat website.
        Route::patch('/pendaftaran/{pendaftaran}/peserta', [PendaftaranController::class, 'ubahPeserta']);
        // Surat pernyataan penggantian peserta (DOCX) — masih disunting pemesan
        // sebelum ditandatangani, jadi bukan PDF.
        Route::get('/pendaftaran/{pendaftaran}/surat-penggantian', [PendaftaranController::class, 'suratPenggantian']);
        // Surat yang sudah ditandatangani, kembali ke sistem lewat admin.
        Route::post('/pendaftaran/{pendaftaran}/surat-penggantian-ttd', [PendaftaranController::class, 'unggahSuratPenggantian']);
        Route::delete('/pendaftaran/{pendaftaran}/surat-penggantian-ttd', [PendaftaranController::class, 'hapusSuratPenggantian']);
        Route::get('/pendaftaran/{pendaftaran}/kwitansi', [PendaftaranController::class, 'kwitansi']);

        Route::get('/pembayaran', [PembayaranController::class, 'index']);
        // Sebelum /{pembayaran}: tanpa ini "menunggu" terbaca sebagai nomor.
        Route::get('/pembayaran/menunggu', [PembayaranController::class, 'menunggu']);
        Route::get('/pembayaran/{pembayaran}', [PembayaranController::class, 'show']);
        Route::patch('/pembayaran/{pembayaran}/status', [PembayaranController::class, 'ubahStatus']);

        Route::get('/pembatalan', [PembatalanController::class, 'index']);
        // Sebelum /{pembatalan}: tanpa ini "perhatian" terbaca sebagai nomor.
        Route::get('/pembatalan/perhatian', [PembatalanController::class, 'perhatian']);
        Route::get('/pembatalan/{pembatalan}', [PembatalanController::class, 'show']);
        Route::patch('/pembatalan/{pembatalan}/status', [PembatalanController::class, 'ubahStatus']);

        /* --------------------------- SEWA KENDARAAN --------------------------- */
        Route::get('/penyewaan', [PenyewaanController::class, 'index']);
        // Sebelum /{penyewaan}: tanpa ini "perhatian" terbaca sebagai nomor.
        Route::get('/penyewaan/perhatian', [PenyewaanController::class, 'perhatian']);

        // Bagian kendaraan yang diperiksa saat serah terima — dulu dipatok di
        // config, sekarang dikelola admin sendiri.
        Route::get('/bagian-pemeriksaan', [BagianPemeriksaanController::class, 'index']);
        Route::post('/bagian-pemeriksaan', [BagianPemeriksaanController::class, 'store']);
        Route::patch('/bagian-pemeriksaan/{bagian}', [BagianPemeriksaanController::class, 'update']);
        Route::delete('/bagian-pemeriksaan/{bagian}', [BagianPemeriksaanController::class, 'destroy']);
        Route::get('/penyewaan/{penyewaan}', [PenyewaanController::class, 'show']);
        Route::patch('/penyewaan/{penyewaan}/status', [PenyewaanController::class, 'ubahStatus']);
        Route::patch('/penyewaan/{penyewaan}/serah-terima', [PenyewaanController::class, 'serahTerima']);
        Route::get('/penyewaan/{penyewaan}/kwitansi', [PenyewaanController::class, 'kwitansi']);
        Route::post('/penyewaan/{penyewaan}/berkas-jaminan', [PenyewaanController::class, 'berkasJaminan']);

        Route::get('/kendaraan', [KendaraanController::class, 'index']);
        Route::get('/kendaraan/{kendaraan}', [KendaraanController::class, 'show']);
        Route::post('/kendaraan', [KendaraanController::class, 'store']);
        Route::match(['put', 'post'], '/kendaraan/{kendaraan}', [KendaraanController::class, 'update']);
        // Pemeriksaan mandiri sesudah perbaikan — terpisah dari serah terima,
        // karena yang mencatatnya pemilik unit, bukan penyewa yang mengembalikan.
        Route::patch('/kendaraan/{kendaraan}/kondisi', [KendaraanController::class, 'ubahKondisi']);
        Route::delete('/kendaraan/{kendaraan}', [KendaraanController::class, 'destroy']);

        // Katalog merek & model: tambahan admin sendiri. Menghapus entri di sini
        // tidak menghapus kendaraan apa pun.
        Route::post('/katalog-kendaraan', [KatalogKendaraanController::class, 'store']);
        Route::delete('/katalog-kendaraan/{katalog}', [KatalogKendaraanController::class, 'destroy']);

        /* ------------------------------ KONTAK ------------------------------ */
        Route::get('/pesan', [PesanController::class, 'index']);
        // Sebelum /{pesan}: tanpa ini "perhatian" terbaca sebagai nomor.
        Route::get('/pesan/perhatian', [PesanController::class, 'perhatian']);
        Route::get('/pesan/{pesan}', [PesanController::class, 'show']);
        Route::patch('/pesan/{pesan}/dibaca', [PesanController::class, 'tandaiDibaca']);

        /* ---------------------------- PAKET WISATA ----------------------------
         | Gambar ikut sebagai multipart pada permintaan yang sama. Pembaruan
         | memakai POST + _method=PUT karena PHP tidak menguraikan multipart
         | pada permintaan PUT.
         */
        // Keuntungan: rekapnya utuh, rinciannya berhalaman.
        Route::get('/keuntungan', [KeuntunganController::class, 'index']);
        Route::get('/keuntungan/rincian', [KeuntunganController::class, 'rincian']);

        Route::get('/saran', [PaketWisataController::class, 'saran']);
        Route::post('/saran', [PaketWisataController::class, 'simpanSaran']);
        Route::delete('/saran/{saran}', [PaketWisataController::class, 'hapusSaran']);

        Route::get('/paket-wisata', [PaketWisataController::class, 'index']);
        Route::get('/paket-wisata/{paket}', [PaketWisataController::class, 'show']);
        Route::post('/paket-wisata', [PaketWisataController::class, 'store']);
        Route::match(['put', 'post'], '/paket-wisata/{paket}', [PaketWisataController::class, 'update']);
        Route::delete('/paket-wisata/{paket}', [PaketWisataController::class, 'destroy']);

        /* -------------------------------- BLOG --------------------------------
         | Dipakai layar Blog di mode Orcha pada lemon.
         |
         | Daftarnya sengaja memuat draf juga — admin justru sedang mengerjakan
         | itu. Penyaring status disediakan sebagai pilihan, bukan penyaring
         | bawaan yang tersembunyi.
         */
        // Rubrik dikelola admin sendiri, seperti kategori blog Phoenix.
        Route::get('/kategori-artikel', [KategoriArtikelController::class, 'index']);
        Route::post('/kategori-artikel', [KategoriArtikelController::class, 'store']);
        Route::delete('/kategori-artikel/{kategori}', [KategoriArtikelController::class, 'destroy']);

        // Sebelum /{artikel}: tanpa ini "slug" terbaca sebagai nomor artikel.
        Route::get('/artikel/slug', [ArtikelController::class, 'slug']);
        Route::get('/artikel', [ArtikelController::class, 'index']);
        /*
         | {artikel:id}, bukan {artikel}.
         |
         | Artikel::getRouteKeyName() mengembalikan 'slug' demi alamat publik
         | yang enak dibaca. Untuk jalur admin itu justru berbahaya: slug ikut
         | berubah begitu judulnya disunting, sehingga lemon yang menyimpan slug
         | akan menjawab 404 pada permintaan berikutnya. Nomor id tidak pernah
         | berubah.
         */
        Route::get('/artikel/{artikel:id}', [ArtikelController::class, 'show']);
        Route::post('/artikel', [ArtikelController::class, 'store']);
        Route::match(['put', 'post'], '/artikel/{artikel:id}', [ArtikelController::class, 'update']);
        Route::delete('/artikel/{artikel:id}', [ArtikelController::class, 'destroy']);

        /* ------------------------------ ETALASE ------------------------------ */
        /*
         | Kuncinya {destinasi:id}, BUKAN slug.
         |
         | Halaman publik memakai slug supaya alamatnya terbaca manusia dan
         | mesin pencari, jadi getRouteKeyName() model ini mengembalikan 'slug'.
         | Tetapi lemon memanggil API ini dengan id — itu yang ia simpan dan
         | tampilkan di daftarnya — sehingga tanpa :id di sini seluruh
         | penyuntingan destinasi dari lemon menjawab "tidak ditemukan".
         |
         | Pola yang sama dipakai artikel blog, dengan alasan yang bersaudara:
         | yang dipegang admin bukan slug.
         */
        Route::get('/destinasi', [EtalaseController::class, 'destinasi']);
        Route::get('/destinasi/{destinasi:id}', [EtalaseController::class, 'satuDestinasi']);
        // Provinsi tambahan: daftar bawaan boleh dilengkapi tanpa menunggu rilis.
        // Usulan provinsi dari nama destinasi — hasilnya boleh kosong.
        Route::get('/cari-lokasi', [ProvinsiController::class, 'cariLokasi']);
        Route::post('/daerah', [ProvinsiController::class, 'simpanDaerah']);
        Route::delete('/daerah/{daerah}', [ProvinsiController::class, 'hapusDaerah']);
        Route::post('/katalog-destinasi', [ProvinsiController::class, 'simpanKatalogDestinasi']);
        Route::delete('/katalog-destinasi/{katalog}', [ProvinsiController::class, 'hapusKatalogDestinasi']);
        Route::post('/wilayah', [ProvinsiController::class, 'simpanWilayah']);
        Route::delete('/wilayah/{wilayah}', [ProvinsiController::class, 'hapusWilayah']);
        Route::post('/provinsi', [ProvinsiController::class, 'store']);
        Route::delete('/provinsi/{provinsi}', [ProvinsiController::class, 'destroy']);
        Route::post('/destinasi', [EtalaseController::class, 'simpanDestinasi']);
        Route::match(['put', 'post'], '/destinasi/{destinasi:id}', [EtalaseController::class, 'perbaruiDestinasi']);
        Route::delete('/destinasi/{destinasi:id}', [EtalaseController::class, 'hapusDestinasi']);

        Route::get('/testimoni', [EtalaseController::class, 'testimoni']);
        Route::post('/testimoni', [EtalaseController::class, 'simpanTestimoni']);
        Route::match(['put', 'post'], '/testimoni/{testimoni}', [EtalaseController::class, 'perbaruiTestimoni']);
        Route::delete('/testimoni/{testimoni}', [EtalaseController::class, 'hapusTestimoni']);

        /*
         | Menyetujui atau menolak testimoni yang dikirim pelanggan.
         |
         | Terpisah dari perbaruiTestimoni: yang ini keputusan tayang/tidak,
         | bukan penyuntingan isi. Menggabungkannya berarti tiap persetujuan
         | ikut mengirim seluruh isi testimoni kembali ke server — dan isi yang
         | ikut terkirim adalah isi yang bisa berubah tanpa disengaja.
         */
        Route::patch('/testimoni/{testimoni}/status', [EtalaseController::class, 'ubahStatusTestimoni']);

        Route::get('/partner', [EtalaseController::class, 'partner']);
        Route::post('/partner', [EtalaseController::class, 'simpanPartner']);
        Route::match(['put', 'post'], '/partner/{partner}', [EtalaseController::class, 'perbaruiPartner']);
        Route::delete('/partner/{partner}', [EtalaseController::class, 'hapusPartner']);

        Route::get('/galeri', [EtalaseController::class, 'galeri']);
        Route::post('/galeri', [EtalaseController::class, 'simpanGaleri']);
        Route::match(['put', 'post'], '/galeri/{galeri}', [EtalaseController::class, 'perbaruiGaleri']);
        Route::delete('/galeri/{galeri}', [EtalaseController::class, 'hapusGaleri']);
    });
