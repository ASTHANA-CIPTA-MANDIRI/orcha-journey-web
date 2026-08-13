<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Volt::route('/', 'public.homepage')->name('home');
Volt::route('/home', 'public.home')->name('home.new');
Volt::route('/home2', 'public.home2')->name('home.2');
Volt::route('/home3', 'public.new-landing-page')->name('home.3');

Route::middleware(['auth'])->group(function () {
    Volt::route('/admin/dashboard', 'admin.dashboard')->name('dashboard');
    // testimoni route
    Volt::route('/admin/testimoni', 'admin.testimoni.index');
    Volt::route('/admin/testimoni/create', 'admin.testimoni.create');
    Volt::route('/admin/testimoni/{testimonial}/edit', 'admin.testimoni.edit');
    // partner route
    Volt::route('/admin/partner', 'admin.partner.index');
    // bundling route
    Volt::route('/admin/travel-package', 'admin.travel-package.index');
    Volt::route('/admin/travel-package/create', 'admin.travel-package.create');
    Volt::route('/admin/travel-package/{package}', 'admin.travel-package.detail');
    Volt::route('/admin/travel-package/{package}/edit', 'admin.travel-package.edit');
    // route destinasi populer
    Volt::route('/admin/destinasi-populer', 'admin.destination-populer.index');
    // route mobil rental
    Volt::route('/admin/car', 'admin.car.index');
    Volt::route('/admin/car/create', 'admin.car.create');
    Volt::route('/admin/car/{car}/edit', 'admin.car.edit');

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
