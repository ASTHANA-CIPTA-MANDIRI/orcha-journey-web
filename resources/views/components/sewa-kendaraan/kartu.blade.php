@props(['kendaraan'])

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');

    // Unit belum tentu punya foto. Daripada kotak kosong, tiap jenis dapat
    // latar gradasi sendiri supaya kartunya tetap terbaca dan tidak seragam.
    $latar = match ($kendaraan->type) {
        'bus' => 'from-orcha-navy to-orcha-ocean',
        'hiace' => 'from-orcha-ocean to-orcha-sky',
        default => 'from-orcha-sky to-orcha-ocean',
    };

    // Tarif selain harian. price_per_day selalu terisi (kolomnya wajib), jadi
    // ia yang jadi angka utama; sisanya keterangan tambahan yang tidak semua
    // unit punya.
    $tarifLain = collect([
        ['Per jam', $kendaraan->harga_per_jam],
        ['Paket 12 jam', $kendaraan->harga_12_jam],
    ])->filter(fn ($baris) => (float) $baris[1] > 0);

    $operasionalTermasuk = collect($kendaraan->rincian_operasional)->contains('termasuk', true);
@endphp

<article {{ $attributes->merge(['class' => 'flex flex-col h-full overflow-hidden card-orcha group']) }}>
    <div class="relative overflow-hidden bg-orcha-foam aspect-[16/10]">
        @if ($kendaraan->image)
            <img src="{{ $kendaraan->image }}" alt="{{ $kendaraan->name }}" loading="lazy"
                class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110">
        @else
            <div class="relative flex flex-col items-center justify-center w-full h-full bg-gradient-to-br {{ $latar }}">
                {{-- Unit tanpa foto memenuhi sebagian besar daftar, dan gradasi rata
                     membuat barisnya terbaca seperti bidang kosong yang berulang.
                     Kilau miring ini menegaskan bahwa kotaknya memang gambar
                     pengganti, bukan gambar yang gagal dimuat. --}}
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_28%_18%,rgba(255,255,255,0.32),transparent_58%)]">
                </div>
                <x-heroicon-o-truck
                    class="relative w-20 h-20 text-white/70 transition-transform duration-700 group-hover:scale-110" />
                <span class="relative mt-2 text-xs font-bold tracking-[0.2em] uppercase text-white/75">
                    {{ $kendaraan->brand }}
                </span>
            </div>
        @endif

        {{-- Label ditata dalam satu baris ber-flex, bukan ditempel di empat sudut.
             Sebelumnya jenis, transmisi, dan "selalu dengan sopir" mengambil tiga
             sudut sekaligus sehingga fotonya nyaris tertutup keterangan — padahal
             transmisi sudah disebut lagi di baris spesifikasi di bawah. --}}
        <div class="absolute inset-x-3 top-3 flex flex-wrap items-start justify-between gap-2">
            <span
                class="px-3 py-1 text-xs font-bold tracking-wide uppercase rounded-full bg-white/90 text-orcha-ocean backdrop-blur">
                {{ $kendaraan->type_label }}
            </span>

            @unless ($kendaraan->lepas_kunci)
                {{-- Disebut di kartunya, bukan hanya di formulir pemesanan: penyewa
                     yang mencari unit lepas kunci berhak tahu sebelum mengisi apa pun. --}}
                <span
                    class="px-3 py-1 text-xs font-bold rounded-full bg-orcha-sun/95 text-orcha-navy backdrop-blur">
                    Selalu dengan sopir
                </span>
            @endunless
        </div>
    </div>

    <div class="flex flex-col flex-1 p-5 sm:p-6">
        <h3 class="text-lg font-bold leading-snug font-heading text-orcha-navy">
            {{ $kendaraan->name }}
            @if ($kendaraan->varian)
                <span class="font-semibold text-orcha-ocean">{{ $kendaraan->varian }}</span>
            @endif
        </h3>
        {{-- Tipe, tahun, dan cc: yang ditanyakan penyewa sebelum memesan, dan
             selama ini hanya ada di kepala pemilik. Bagian yang belum diketahui
             dilewati supaya unit lama tetap terbaca wajar. --}}
        <p class="mt-0.5 text-sm text-slate-500">
            {{ $kendaraan->brand }}
            @if ($kendaraan->tahun) · {{ $kendaraan->tahun }} @endif
            @if ($kendaraan->cc) · {{ number_format($kendaraan->cc, 0, ',', '.') }} cc @endif
        </p>

        {{-- my-auto, bukan mt-auto di kotak tarif. Sisa ruang kartu — yang muncul
             karena isi tiap unit tidak sama panjang — dibagi rata ke atas dan ke
             bawah baris ini, jadi terbaca sebagai dua jarak napas, bukan satu
             lubang menganga tepat di atas harga. Tombolnya tetap sebaris karena
             seluruh sisa ruang tetap habis sebelum kotak tarif.

             Penumpang di kiri, transmisi di kanan: tepi kanannya lurus dengan
             angka harga di bawahnya. --}}
        <div class="flex flex-wrap justify-between py-3 my-auto text-sm gap-x-4 gap-y-1 text-slate-600">
            <span class="inline-flex items-center gap-1.5">
                <x-heroicon-o-user-group class="w-4 h-4 shrink-0 text-orcha-sun" />
                {{-- capacity berisi kursi PENUMPANG, jadi inilah angka yang boleh
                     dijanjikan. Kursi totalnya disebut dalam tanda kurung hanya
                     bila berbeda, yaitu saat satu kursi terpakai sopir. --}}
                {{ $kendaraan->capacity }} penumpang
                @unless ($kendaraan->lepas_kunci)
                    <span class="text-xs text-slate-400">({{ $kendaraan->kursi_total }} kursi)</span>
                @endunless
            </span>
            <span class="inline-flex items-center gap-1.5">
                <x-heroicon-o-cog-6-tooth class="w-4 h-4 shrink-0 text-orcha-sun" />
                {{ $kendaraan->transmisi_label }}
            </span>
        </div>

        {{-- Kotak tarif dibaca sebagai daftar harga, bukan sebagai tumpukan
             kalimat: keterangan rata kiri, angkanya rata KANAN. Semua nominal
             jatuh di satu garis tegak — itu yang membuat kartunya terlihat
             tertata, dan mata bisa membandingkan harga antar-baris tanpa
             menyusurinya satu per satu. --}}
        <div class="p-4 rounded-2xl bg-orcha-foam/70">
            {{-- Tarif harian dijadikan angka utama. Sebelumnya ketiga satuan
                 ditulis sederajat sebagai daftar, sehingga mata tidak menemukan
                 harga mana yang harus dibaca lebih dulu. --}}
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-xs font-semibold tracking-wide uppercase text-slate-500">Per hari (24 jam)</span>
                <span class="text-2xl font-black leading-none font-heading text-orcha-navy tabular">
                    {{ $rupiah($kendaraan->price_per_day) }}
                </span>
            </div>

            @if ($tarifLain->isNotEmpty() || $kendaraan->punya_tarif_luar_kota)
                <div class="pt-2.5 mt-2.5 space-y-1.5 border-t border-white">
                    @foreach ($tarifLain as [$label, $harga])
                        <div class="flex items-baseline justify-between gap-2 text-xs">
                            <span class="text-slate-500">{{ $label }}</span>
                            <span class="font-bold text-orcha-ocean tabular">{{ $rupiah($harga) }}</span>
                        </div>
                    @endforeach

                    {{-- Hanya disebut bila memang berbeda: menuliskan "luar kota
                         tarifnya sama" di setiap kartu menambah baris tanpa
                         menambah keterangan. Letaknya di dalam kotak tarif karena
                         ia memang tarif, bukan catatan kaki. --}}
                    @if ($kendaraan->punya_tarif_luar_kota)
                        <div class="flex items-baseline justify-between gap-2 text-xs">
                            {{-- Satuan "per hari" ikut di sisi label, bukan
                                 dilekatkan di belakang angkanya. Sebagai akhiran,
                                 ia mendorong nominalnya ke kiri sehingga digit
                                 baris ini tidak lagi lurus dengan harga di
                                 atasnya — justru merusak barisan yang jadi
                                 tujuan penataan ini. Luar kota memang hanya
                                 dihitung harian, jadi satuannya tetap disebut. --}}
                            <span class="inline-flex items-center gap-1 text-slate-500">
                                <x-heroicon-o-map-pin class="w-3.5 h-3.5 shrink-0 text-orcha-ocean" />
                                Luar kota per hari
                            </span>
                            <span class="font-bold text-orcha-ocean tabular">
                                {{ $rupiah($kendaraan->harga_luar_kota) }}
                            </span>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Keterangan sopir dan BBM/tol/parkir dibaca dari unitnya, bukan ditulis
             tetap. Sebelumnya semua kartu menyatakan "belum termasuk BBM & tol",
             jadi unit all-in pun terbaca sebaliknya — penyewa bertanya ulang, atau
             mengira sudah termasuk padahal belum lalu berselisih saat membayar.

             Ditampilkan sebagai daftar berikon, bukan kalimat berwarna. Kalimat
             biru bertumpuk terbaca seperti tautan yang bisa diklik; ikon centang
             menyampaikan "termasuk" tanpa perlu mewarnai seluruh barisnya. --}}
        {{-- Aturan biaya: berlabel wilayah HANYA bila memang berbeda.

             Sebelumnya dua kalimat tanpa wilayah lalu satu baris "Luar kota: …"
             di bawahnya. Penyewa tidak punya cara tahu bahwa dua kalimat pertama
             ternyata hanya berlaku dalam kota — ia membaca ketiganya sebagai satu
             daftar yang saling bertentangan.

             Bila aturannya sama di kedua wilayah, labelnya justru mengganggu:
             menuliskan "dalam kota" dan "luar kota" untuk hal yang tidak berbeda
             menyuruh penyewa mencari perbedaan yang tidak ada. --}}
        @if ($kendaraan->beda_aturan_luar_kota)
            <ul class="mt-4 space-y-2 text-xs text-slate-500">
                @foreach ([['Dalam kota', $kendaraan->ringkasan_dalam_kota, 'o-building-office-2'], ['Luar kota', $kendaraan->ringkasan_luar_kota, 'o-map-pin']] as [$wilayah, $ringkasan, $ikon])
                    <li class="flex items-start gap-1.5">
                        <x-dynamic-component :component="'heroicon-' . $ikon"
                            class="w-4 h-4 mt-px shrink-0 text-orcha-sun" />
                        <span>
                            <b class="font-bold text-slate-600">{{ $wilayah }}</b>
                            — {{ $ringkasan }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @else
            <ul class="mt-4 space-y-1.5 text-xs text-slate-500">
                <li class="flex items-start gap-1.5">
                    @if ($kendaraan->termasuk_sopir)
                        <x-heroicon-s-check-circle class="w-4 h-4 mt-px shrink-0 text-orcha-ocean" />
                    @else
                        <x-heroicon-o-user class="w-4 h-4 mt-px shrink-0 text-slate-300" />
                    @endif
                    <span class="{{ $kendaraan->termasuk_sopir ? 'font-semibold text-slate-600' : '' }}">
                        {{ $kendaraan->sopir_label }}
                    </span>
                </li>
                <li class="flex items-start gap-1.5">
                    @if ($operasionalTermasuk)
                        <x-heroicon-s-check-circle class="w-4 h-4 mt-px shrink-0 text-orcha-ocean" />
                    @else
                        <x-heroicon-o-banknotes class="w-4 h-4 mt-px shrink-0 text-slate-300" />
                    @endif
                    <span class="{{ $operasionalTermasuk ? 'font-semibold text-slate-600' : '' }}">
                        {{ $kendaraan->operasional_label }}
                    </span>
                </li>
            </ul>
        @endif

        <a href="{{ route('sewa-kendaraan.pesan', ['unit' => $kendaraan->uuid]) }}"
            class="w-full mt-4 btn-orcha btn-orcha-primary !py-2.5 !text-sm">
            <x-heroicon-o-pencil-square class="w-4 h-4" />
            Pesan Unit Ini
        </a>
    </div>
</article>
