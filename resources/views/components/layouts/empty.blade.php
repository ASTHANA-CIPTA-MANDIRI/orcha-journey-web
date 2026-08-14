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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen antialiased">
    {{-- Latar bertema laut supaya halaman masuk/daftar tetap terasa Orcha --}}
    <div class="relative flex items-center justify-center min-h-screen px-4 py-10 overflow-hidden bg-orcha-navy">
        <img src="{{ asset('images/laut.jpg') }}" alt=""
            class="absolute inset-0 object-cover w-full h-full opacity-25">

        <div class="relative w-full max-w-md">
            <a href="/" class="flex flex-col items-center gap-3 mb-8">
                <img src="{{ asset('orcha-logo-only.png') }}" alt="Orcha Journey" width="64" height="64"
                    class="object-contain w-16 h-16">
                <span class="text-xl font-black tracking-tight text-white uppercase font-heading">
                    Orcha <span class="text-orcha-sky">Journey</span>
                </span>
            </a>

            <div class="p-6 shadow-2xl bg-base-100 rounded-2xl sm:p-8">
                {{ $slot }}
            </div>

            <p class="mt-6 text-xs text-center text-white/60">
                &copy; {{ date('Y') }} Orcha Journey
            </p>
        </div>
    </div>

    <x-mary-toast />
</body>

</html>
