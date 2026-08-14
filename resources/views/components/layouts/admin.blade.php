<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap"
        rel="stylesheet">

    <script type="text/javascript"
        src="https://cdn.jsdelivr.net/gh/robsontenorio/mary@0.44.2/libs/currency/currency.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
    $menu = [
        ['title' => 'Dashboard', 'icon' => 'o-squares-2x2', 'link' => '/admin/dashboard'],
        ['title' => 'Pendaftaran', 'icon' => 'o-clipboard-document-list', 'link' => '/admin/pendaftaran'],
        ['title' => 'Sewa Masuk', 'icon' => 'o-truck', 'link' => '/admin/penyewaan'],
        ['title' => 'Pembatalan', 'icon' => 'o-arrow-uturn-left', 'link' => '/admin/pembatalan'],
        ['title' => 'Pesan Masuk', 'icon' => 'o-inbox', 'link' => '/admin/pesan'],
        ['title' => 'Paket Wisata', 'icon' => 'o-map', 'link' => '/admin/paket-wisata'],
        ['title' => 'Sewa Kendaraan', 'icon' => 'o-truck', 'link' => '/admin/sewa-kendaraan'],
        ['title' => 'Destinasi Populer', 'icon' => 'o-map-pin', 'link' => '/admin/destinasi'],
        ['title' => 'Testimoni', 'icon' => 'o-chat-bubble-left-right', 'link' => '/admin/testimoni'],
        ['title' => 'Partner', 'icon' => 'o-user-group', 'link' => '/admin/partner'],
    ];
@endphp

<body class="min-h-screen antialiased bg-base-200">

    {{-- NAVBAR — hanya tampil di layar kecil --}}
    <x-mary-nav sticky class="lg:hidden admin-brandbar">
        <x-slot:brand>
            <a href="/admin/dashboard" class="flex items-center gap-2">
                <img src="{{ asset('orcha-logo-only.png') }}" alt="Orcha Journey" width="32" height="32"
                    class="object-contain w-8 h-8">
                <span class="text-base font-black tracking-tight uppercase font-heading">Orcha <span
                        class="text-orcha-sky">Journey</span></span>
            </a>
        </x-slot:brand>
        <x-slot:actions>
            <label for="main-drawer" class="cursor-pointer lg:hidden">
                <x-mary-icon name="o-bars-3" class="w-6 h-6" />
            </label>
        </x-slot:actions>
    </x-mary-nav>

    <x-mary-main full-width>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-base-100">

            <div class="flex flex-col items-center gap-2 px-4 py-6 border-b border-base-300">
                <img src="{{ asset('orcha-logo-only.png') }}" alt="Logo Orcha Journey" width="64" height="64"
                    class="object-contain w-16 h-16">
                <p class="text-sm font-black tracking-tight text-center uppercase font-heading text-orcha-navy">
                    Orcha <span class="text-orcha-wave">Journey</span>
                </p>
                <p class="text-[0.65rem] tracking-[0.2em] uppercase text-base-content/50">Panel Admin</p>
            </div>

            <x-mary-menu activate-by-route>
                @foreach ($menu as $item)
                    <x-mary-menu-item :title="$item['title']" :icon="$item['icon']" :link="$item['link']" />
                @endforeach
            </x-mary-menu>

            <div class="px-4 mt-auto mb-4">
                <a href="/" target="_blank" rel="noopener"
                    class="flex items-center justify-center gap-2 text-xs font-semibold btn btn-sm btn-outline btn-primary">
                    <x-mary-icon name="o-arrow-top-right-on-square" class="w-4 h-4" />
                    Lihat Website
                </a>
            </div>
        </x-slot:sidebar>

        <x-slot:content>
            <div class="admin-shell">
                {{-- Bar atas: identitas user + logout --}}
                @if ($user = auth()->user())
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 mb-6 bg-base-100 rounded-2xl shadow-sm sm:px-6">
                        <div class="min-w-0">
                            <p class="text-xs tracking-wider uppercase text-base-content/50">Selamat datang</p>
                            <p class="text-sm font-bold truncate sm:text-base text-orcha-navy">{{ $user->name }}</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="hidden text-xs sm:inline text-base-content/60">{{ $user->email }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-mary-button type="submit" label="Keluar" icon="o-power" class="btn-sm btn-error"
                                    responsive data-test="logout-button" />
                            </form>
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </x-slot:content>
    </x-mary-main>

    {{-- TOAST area --}}
    <x-mary-toast />
</body>

</html>
