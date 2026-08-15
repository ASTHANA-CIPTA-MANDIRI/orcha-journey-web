<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Halaman Publik
|--------------------------------------------------------------------------
|
| Berkas Blade-nya dikelompokkan per fitur di resources/views/livewire/public,
| jadi nama komponen Volt mengikuti nama foldernya.
|
*/
Volt::route('/', 'public.beranda.index')->name('home');
Volt::route('/tentang-kami', 'public.tentang-kami.index')->name('tentang-kami');

// Paket wisata
Volt::route('/paket/{paket:uuid}', 'public.paket-wisata.detail')->name('paket-detail');
Volt::route('/paket-wisata/{kategori?}', 'public.paket-wisata.index')->name('paket-wisata');

// Sewa kendaraan — rute /pesan didaftarkan lebih dulu agar tidak tertangkap
// oleh parameter {jenis} di bawahnya.
Volt::route('/sewa-kendaraan/pesan', 'public.sewa-kendaraan.pemesanan')->name('sewa-kendaraan.pesan');
Volt::route('/sewa-kendaraan/{jenis?}', 'public.sewa-kendaraan.index')->name('sewa-kendaraan');

// Destinasi, testimoni, kontak
Volt::route('/destinasi', 'public.destinasi.index')->name('destinasi');
Volt::route('/testimoni', 'public.testimoni.index')->name('testimoni');
Volt::route('/kontak', 'public.kontak.index')->name('kontak');

// Open trip: pendaftaran, riwayat kesehatan, pembatalan
Volt::route('/pendaftaran-open-trip', 'public.open-trip.pendaftaran')->name('pendaftaran-open-trip');
Volt::route('/riwayat-kesehatan', 'public.open-trip.riwayat-kesehatan')->name('riwayat-kesehatan');
Volt::route('/pembatalan', 'public.open-trip.pembatalan')->name('pembatalan');

// Halaman informasi & ketentuan
Volt::route('/faq', 'public.informasi.faq')->name('faq');
Volt::route('/syarat-ketentuan', 'public.informasi.syarat-ketentuan')->name('syarat-ketentuan');
Volt::route('/ketentuan-pembayaran', 'public.informasi.ketentuan-pembayaran')->name('ketentuan-pembayaran');
Volt::route('/kebijakan-pengembalian', 'public.informasi.kebijakan-pengembalian')->name('kebijakan-pengembalian');
Volt::route('/kebijakan-privasi', 'public.informasi.kebijakan-privasi')->name('kebijakan-privasi');

/*
|--------------------------------------------------------------------------
| Panel Admin Bawaan (DIMATIKAN)
|--------------------------------------------------------------------------
|
| Pengelolaan Orcha sudah pindah ke dashboard lemon (Phoenix) supaya admin
| cukup satu akun. Halaman login, daftar, dan /admin/* di sini tidak lagi
| tersedia — termasuk /login yang dulu ada di alamat ini.
|
| Berkas komponennya sengaja dibiarkan. Untuk menyalakannya kembali sementara
| (mis. lemon sedang bermasalah), setel ORCHA_ADMIN_BAWAAN=true di .env lalu
| jalankan `php artisan optimize:clear`.
|
*/
if (config('orcha.admin_bawaan')) {
    Route::middleware(['auth'])->group(function () {
        Volt::route('/admin/dashboard', 'admin.dashboard.index')->name('dashboard');

        // Pesanan yang masuk dari website
        Volt::route('/admin/pendaftaran', 'admin.pendaftaran.index')->name('admin.pendaftaran');
        Volt::route('/admin/penyewaan', 'admin.penyewaan.index')->name('admin.penyewaan');
        Volt::route('/admin/pembatalan', 'admin.pembatalan.index')->name('admin.pembatalan');
        Volt::route('/admin/pesan', 'admin.pesan.index')->name('admin.pesan');

        // Paket wisata
        Volt::route('/admin/paket-wisata', 'admin.paket-wisata.index');
        Volt::route('/admin/paket-wisata/create', 'admin.paket-wisata.create');
        Volt::route('/admin/paket-wisata/{package}/edit', 'admin.paket-wisata.edit');

        // Sewa kendaraan (tambah/ubah lewat modal di halaman index)
        Volt::route('/admin/sewa-kendaraan', 'admin.sewa-kendaraan.index');

        // Destinasi populer
        Volt::route('/admin/destinasi', 'admin.destinasi.index');

        // Testimoni & partner
        Volt::route('/admin/testimoni', 'admin.testimoni.index');
        Volt::route('/admin/testimoni/create', 'admin.testimoni.create');
        Volt::route('/admin/testimoni/{testimonial}/edit', 'admin.testimoni.edit');
        Volt::route('/admin/partner', 'admin.partner.index');

        Route::redirect('settings', 'settings/profile');

        Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
        Volt::route('settings/password', 'settings.password')->name('password.edit');
        Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

        Volt::route('settings/two-factor', 'settings.two-factor')
            ->middleware(
                when(
                    Features::canManageTwoFactorAuthentication()
                        && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                    ['password.confirm'],
                    [],
                ),
            )
            ->name('two-factor.show');
    });

    require __DIR__.'/auth.php';
}
