<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Orcha tidak lagi punya halaman login sendiri — pengelolaannya ada di
        // dashboard lemon, dengan satu akun saja. Fortify mendaftarkan rute
        // login/two-factor miliknya sendiri, jadi harus diminta berhenti di
        // sini, bukan cukup dari routes/web.php.
        if (! config('orcha.admin_bawaan')) {
            Fortify::ignoreRoutes();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
