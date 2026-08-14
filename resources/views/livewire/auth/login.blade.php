<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.empty')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $user = $this->validateCredentials();

        if (Features::canManageTwoFactorAuthentication() && $user->hasEnabledTwoFactorAuthentication()) {
            Session::put([
                'login.id' => $user->getKey(),
                'login.remember' => $this->remember,
            ]);

            $this->redirect(route('two-factor.login'), navigate: true);

            return;
        }

        Auth::login($user, $this->remember);

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Validate the user's credentials.
     */
    protected function validateCredentials(): User
    {
        $user = Auth::getProvider()->retrieveByCredentials(['email' => $this->email, 'password' => $this->password]);

        if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $user;
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold admin-title">Masuk Panel Admin</h1>
        <p class="mt-1 text-sm text-base-content/60">Kelola paket wisata, armada, dan isi website Orcha Journey.</p>
    </div>

    <x-mary-form method="POST" wire:submit="login" class="flex flex-col gap-5">
        <x-mary-input wire:model="email" label="Email" placeholder="admin@orchajourney.com" icon="o-envelope" />

        <div>
            <x-mary-password wire:model="password" label="Kata Sandi" type="password" placeholder="••••••••" right />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                    class="inline-block mt-2 text-xs font-semibold text-primary hover:underline">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <x-mary-checkbox wire:model="remember" label="Ingat saya" />

        <x-mary-button class="w-full btn-primary" type="submit" spinner="login" label="Masuk" />
    </x-mary-form>

    @if (Route::has('register'))
        <p class="text-sm text-center text-base-content/60">
            Belum punya akun?
            <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">Daftar</a>
        </p>
    @endif
</div>