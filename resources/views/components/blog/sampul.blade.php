@props(['artikel', 'prioritas' => false, 'terang' => false])

{{-- Bidang sampul artikel — DUA LAPIS.

     Lapis bawah selalu digambar: bidang berlambang orca. Lapis atas fotonya,
     dan hanya ada bila artikelnya memang punya sampul.

     Kenapa begitu, bukan satu <img> dengan foto cadangan:

     1. Artikel tanpa sampul sebelumnya memakai foto hero /blog sebagai
        cadangan. Di satu kartu itu tidak kentara; di daftar yang sebagian
        besar belum bersampul, SELURUH kartunya memakai foto pantai senja yang
        sama persis — lima kartu kembar yang terbaca seperti satu artikel
        dicetak berulang. Bidang berlambang justru mengaku apa adanya: belum
        ada fotonya.

     2. Berkas sampulnya bisa hilang walau kolomnya terisi (sudah terjadi pada
        dua artikel). onerror MENCOPOT lapis atas, dan yang tersingkap lapis
        bawah — jadi satu susunan ini menangani kedua keadaan sekaligus, tanpa
        kotak gelap berisi tulisan alt seperti sebelumnya. --}}
@php
    /*
     | Tiga ragam bidang, dipilih dari id artikel.
     |
     | Satu ragam saja memindahkan masalahnya, tidak menyelesaikannya: lima
     | foto kembar berganti jadi lima petak kembar. Ragamnya tetap satu
     | keluarga warna — hijau-toska logo — jadi yang berubah arah gradasi dan
     | kedalamannya saja, bukan warnanya.
     |
     | Dari ID, bukan acak: kartu yang sama harus tampil sama setiap kali
     | halamannya dibuka, termasuk saat Livewire menggambar ulang daftarnya
     | setelah penyaring diubah.
     */
    $ragam = [
        'bg-gradient-to-br from-orcha-toska via-[#1b5c54] to-[#183030]',
        'bg-gradient-to-tr from-[#132a2a] via-[#1b5c54] to-[#2a8d7c]',
        'bg-gradient-to-r from-[#17403b] via-[#20665c] to-[#143030]',
    ][(int) $artikel->id % 3];

    // Di dalam panel gelap, bidang hijau gelap nyaris tidak terlihat — ia
    // melebur dengan panelnya. Di sana yang dipakai krem, dengan lambang
    // toska di atasnya.
    $ragamTerang = [
        'bg-gradient-to-br from-orcha-foam via-[#f7e9d2] to-[#efdcc0]',
        'bg-gradient-to-tr from-[#efdcc0] via-orcha-foam to-[#f9efdf]',
        'bg-gradient-to-r from-[#f6e6cd] via-orcha-foam to-[#f1e0c6]',
    ][(int) $artikel->id % 3];
@endphp

<span {{ $attributes->merge(['class' => 'relative block overflow-hidden']) }}>
    <span class="absolute inset-0 flex items-center justify-center {{ $terang ? $ragamTerang : $ragam }}">
        {{-- aria-hidden + alt kosong: ini hiasan. Judul artikelnya ada tepat
             di bawah atau di sebelah bidang ini.

             Ukurannya DIPAKSA. Kartu "Bacaan Lainnya" di halaman artikel
             menyetel `.orc-samping-pos .gambar img { width:100%; height:100% }`
             — pemilih berkekhususan (0,2,1) yang mengalahkan utilitas
             Tailwind, sehingga lambang ini ikut diregangkan memenuhi
             bingkainya. --}}
        <img src="{{ asset('orcha-logo-only.png') }}" alt="" aria-hidden="true" loading="lazy"
            width="512" height="512"
            class="!w-2/5 !h-auto max-w-[7.5rem] {{ $terang ? 'opacity-30' : 'opacity-25' }}">
    </span>

    @if (filled($artikel->sampul))
        <img src="{{ $artikel->sampul }}" alt="{{ $artikel->judul }}"
            @if ($prioritas) fetchpriority="high" @else loading="lazy" @endif
            decoding="async" onerror="this.remove();"
            class="relative object-cover w-full h-full transition-transform duration-700 group-hover:scale-105">
    @endif
</span>
