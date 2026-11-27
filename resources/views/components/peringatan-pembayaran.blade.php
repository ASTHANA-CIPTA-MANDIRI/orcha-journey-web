@props(['ringkas' => false])

{{-- Patokan tetap agar pelanggan bisa memeriksa sendiri sebelum mentransfer.

     Nomor rekening sengaja tidak dipajang: nomor yang terpampang gampang
     disalin penipu untuk membuat halaman tiruan. Yang perlu dicek pelanggan
     cukup NAMA penerimanya, dan nama itu tidak bisa dipalsukan di mesin bank. --}}
@php
    $atasNama = config('orcha.pembayaran.atas_nama');
    $atasNamaGerbang = config('orcha.pembayaran.atas_nama_gerbang');
    $waLink = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp');

    /*
     | Isi peringatannya berbeda sejak pembayaran publik lewat gerbang DOKU.
     |
     | Kalimat lama menjanjikan nomor rekening yang dikirim admin lewat
     | WhatsApp. Sejak pembayaran diselesaikan di halaman DOKU, janji itu tidak
     | lagi benar — dan peringatan penipuan yang isinya tidak lagi benar justru
     | melatih pelanggan mengabaikan peringatan berikutnya.
     |
     | Dibaca dari layanannya, bukan dari config mentah, supaya server yang
     | sakelarnya menyala tetapi kredensialnya belum terpasang tetap menampilkan
     | kalimat transfer manual — sebab itulah yang benar-benar berlaku di sana.
     */
    $gerbang = app(App\Services\DokuCheckout::class)->aktif();
@endphp

{{-- Latarnya PADAT (.peringatan-bayar), bukan bg-orcha-sun/10.

     Emas 10% di atas putih memang terlihat krem — tetapi di halaman
     pendaftaran kotak ini naik menindih foto kepala halaman, dan di sana
     fotonya tembus lewat latar yang cuma 10% itu: peringatan penipuan jadi
     tidak terbaca sama sekali. Tidak ada galat, kotaknya tetap ada, hanya
     tulisannya yang hilang ke dalam gambar.

     Warnanya dipatok sama dengan hasil emas 10% di atas putih, jadi di
     halaman lain tidak ada yang berubah — yang berubah cuma: ia tidak bisa
     lagi ditembus apa pun yang ada di belakangnya. Peringatan yang bisa
     hilang bukan peringatan. --}}
<div {{ $attributes->merge(['class' => 'p-4 border rounded-2xl peringatan-bayar sm:p-5']) }}>
    <div class="flex items-start gap-3">
        <x-heroicon-s-shield-check class="w-6 h-6 shrink-0 text-orcha-sun" />

        <div class="text-sm text-slate-700">
            {{-- DUA nama, masing-masing dengan jalurnya.

                 Lewat halaman pembayaran, yang dilihat pembayar di nama virtual
                 account dan di mutasi banknya adalah nama merchant yang
                 terdaftar di DOKU — merek, bukan badan hukumnya. Menyebut badan
                 hukumnya saja membuat peringatan ini MENUDUH pembayaran yang
                 sah, dan peringatan yang bertentangan dengan kenyataan melatih
                 orang mengabaikan peringatan berikutnya. --}}
            <p class="font-bold text-orcha-navy">
                @if ($gerbang)
                    Pembayaran hanya sah atas nama
                    <span class="text-orcha-toska">{{ $atasNamaGerbang }}</span> di halaman pembayaran kami,
                    atau <span class="text-orcha-toska">{{ $atasNama }}</span> bila Anda transfer ke rekening kami
                @else
                    Pembayaran hanya sah atas nama <span class="text-orcha-toska">{{ $atasNama }}</span>
                @endif
            </p>

            @unless ($ringkas)
                @if ($gerbang)
                    <p class="mt-1.5">
                        Pembayaran diselesaikan di <strong>halaman pembayaran resmi kami</strong>, lewat
                        bank, QRIS, atau dompet digital. Di aplikasi bank Anda, penerimanya tertulis
                        <strong>{{ $atasNamaGerbang }}</strong> — itu kami. Kami <strong>tidak pernah</strong>
                        meminta transfer ke rekening pribadi atas nama perorangan, dan tidak pernah meminta
                        PIN, OTP, atau nomor kartu Anda lewat pesan apa pun.
                    </p>
                    <p class="mt-1.5">
                        Ragu dengan pesan yang mengaku dari kami? Tanyakan dulu lewat
                        <a href="{{ $waLink }}" target="_blank" rel="noopener"
                            class="font-semibold text-orcha-ocean hover:underline">WhatsApp resmi</a>.
                    </p>
                    <a href="{{ route('konfirmasi-pembayaran') }}"
                        class="inline-block mt-2 text-sm font-bold text-orcha-ocean hover:underline">
                        Bayar pesanan Anda di sini →
                    </a>
                @else
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
                    <a href="{{ route('konfirmasi-pembayaran') }}"
                        class="inline-block mt-2 text-sm font-bold text-orcha-ocean hover:underline">
                        Sudah transfer? Kirim buktinya di sini →
                    </a>
                @endif
            @endunless
        </div>
    </div>
</div>
