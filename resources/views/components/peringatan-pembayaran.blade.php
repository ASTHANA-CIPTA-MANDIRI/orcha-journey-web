@props(['ringkas' => false])

{{-- Patokan tetap agar pelanggan bisa memeriksa sendiri sebelum mentransfer.

     Nomor rekening sengaja tidak dipajang: nomor yang terpampang gampang
     disalin penipu untuk membuat halaman tiruan. Yang perlu dicek pelanggan
     cukup NAMA penerimanya, dan nama itu tidak bisa dipalsukan di mesin bank. --}}
@php
    $atasNama = config('orcha.pembayaran.atas_nama');
    $waLink = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp');
@endphp

<div {{ $attributes->merge(['class' => 'p-4 border rounded-2xl border-orcha-sun/50 bg-orcha-sun/10 sm:p-5']) }}>
    <div class="flex items-start gap-3">
        <x-heroicon-s-shield-check class="w-6 h-6 shrink-0 text-orcha-sun" />

        <div class="text-sm text-slate-700">
            <p class="font-bold text-orcha-navy">
                Pembayaran hanya sah atas nama <span class="text-orcha-ocean">{{ $atasNama }}</span>
            </p>

            @unless ($ringkas)
                <p class="mt-1.5">
                    Selain nama itu, <strong>bukan kami</strong> — jangan ditransfer. Kami tidak pernah
                    meminta transfer ke rekening pribadi atas nama perorangan.
                </p>
                <p class="mt-1.5">
                    Nomor rekening tidak kami pajang di website. Nomornya dikirim admin lewat
                    <a href="{{ $waLink }}" target="_blank" rel="noopener"
                        class="font-semibold text-orcha-ocean hover:underline">WhatsApp resmi</a>
                    setelah pesanan Anda dipastikan. Bila ragu, tanyakan dulu lewat nomor itu.
                </p>
            @endunless
        </div>
    </div>
</div>
