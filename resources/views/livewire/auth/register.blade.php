<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.empty')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        Session::regenerate();

        $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold admin-title">Buat Akun Admin</h1>
        <p class="mt-1 text-sm text-base-content/60">Akun ini dipakai untuk mengelola isi website.</p>
    </div>

    <x-mary-form method="POST" wire:submit="register" class="flex flex-col gap-5">
        <x-mary-input wire:model="name" label="Nama Lengkap" placeholder="Nama lengkap" icon="o-user" />
        <x-mary-input wire:model="email" label="Email" placeholder="admin@orchajourney.com" icon="o-envelope" />
        <x-mary-password wire:model="password" label="Kata Sandi" placeholder="••••••••" right />
        <x-mary-password wire:model="password_confirmation" label="Ulangi Kata Sandi" placeholder="••••••••" right />

        <x-mary-button class="w-full btn-primary" type="submit" spinner="register" label="Daftar" />
    </x-mary-form>

    <p class="text-sm text-center text-base-content/60">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">Masuk</a>
    </p>
</div>
