{{-- Tanda berhasil: cakram gradasi tosca–hijau, cincin tipis mengorbit, kilau.

     Gerakannya sengaja DITAHAN. Cakram mengembang halus, centangnya digambar,
     cincin berputar sekali pelan, kilaunya berkedip lalu diam. Tidak ada
     letupan: yang perlu dibaca berikutnya — tombol Isi Riwayat Kesehatan —
     ada tepat di bawahnya, dan gerakan yang berlebihan menahan mata di atas.

     Semua berhenti. Animasi yang berulang selamanya menarik pandangan kembali
     terus-menerus ke sesuatu yang sudah selesai.

     CSS-nya menempel di berkas ini, bukan kelas utilitas: animasi bernama
     butuh @keyframes, dan berkas Vite tidak selalu ikut ter-deploy. Awalan
     .ocs- supaya tidak bertabrakan dengan apa pun.

     CATATAN: jangan menaruh komentar DI DALAM @props — Blade tersedak dan
     halamannya berakhir 500 tanpa menyebut sebabnya. --}}
@props([
    'ukuran' => 132,
])

@php
    $ukuran = (int) $ukuran;

    // Kilau: [x, y, besar, tunda]. Sebarannya sengaja tidak simetris —
    // yang simetris terbaca sebagai pola, bukan kilau.
    $kilau = [
        [30, 30, 5.5, 0.62],
        [92, 26, 4, 0.74],
        [98, 74, 3.2, 0.86],
        [24, 78, 3.6, 0.7],
        [60, 14, 2.6, 0.9],
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'ocs-tanda']) }} viewBox="0 0 120 120"
    style="width:{{ $ukuran }}px;height:{{ $ukuran }}px;" role="img" aria-label="Berhasil">

    <defs>
        <linearGradient id="ocs-daun" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#22ddd2" />
            <stop offset="48%" stop-color="#22c58c" />
            <stop offset="100%" stop-color="#46bd35" />
        </linearGradient>

        {{-- Kilau empat sudut, digambar sekali lalu dipakai ulang lewat <use>.
             Titik pusatnya di 0,0 dan jari-jarinya 1, jadi ukurannya diatur
             seluruhnya oleh transform pemakainya. --}}
        <path id="ocs-kilau"
            d="M0-1 Q.2-.2 1 0 Q.2.2 0 1 Q-.2.2-1 0 Q-.2-.2 0-1Z" />
    </defs>

    {{-- Cincin tipis mengorbit. Dua ruas, bukan lingkaran penuh: lingkaran
         penuh kedua di luar cakram terbaca sebagai bingkai, sementara ruas
         yang terputus terbaca sebagai gerak. --}}
    <circle class="ocs-orbit" cx="60" cy="60" r="45" fill="none" />

    <circle class="ocs-cakram" cx="60" cy="60" r="34" fill="url(#ocs-daun)" />

    <path class="ocs-centang" fill="none"
        d="M45.5 60.5 L55.5 70.5 L75 50" />

    {{-- Penempatan dan animasinya dipisah ke dua simpul, dan itu WAJIB.

         Atribut transform pada SVG ditimpa habis oleh properti transform dari
         CSS. Menaruh translate() di elemen yang sama dengan keyframe berarti
         penempatannya lenyap begitu animasinya berjalan — seluruh kilau
         menumpuk mengerut di pojok kiri atas, dan tidak ada pesan galat apa
         pun yang menyebutkannya. Grup luar menempatkan, anak di dalamnya
         berkilau. --}}
    @foreach ($kilau as [$x, $y, $besar, $tunda])
        <g transform="translate({{ $x }} {{ $y }}) scale({{ $besar }})">
            <use class="ocs-kilau" href="#ocs-kilau" style="--tunda:{{ $tunda }}s" />
        </g>
    @endforeach
</svg>

@once
    <style>
        .ocs-tanda {
            display: block;
            overflow: visible;
        }

        .ocs-cakram {
            transform-origin: 60px 60px;
            animation: ocs-mengembang .5s cubic-bezier(.34, 1.4, .64, 1) both;
            filter: drop-shadow(0 6px 16px rgba(38, 191, 143, .32));
        }

        .ocs-centang {
            stroke: #ffffff;
            stroke-width: 7;
            stroke-linecap: round;
            stroke-linejoin: round;
            /* Panjang jalur centangnya ~42 — dilebihkan supaya tidak terpotong. */
            stroke-dasharray: 46;
            stroke-dashoffset: 46;
            animation: ocs-menggores .42s cubic-bezier(.65, 0, .45, 1) .32s forwards;
        }

        .ocs-orbit {
            stroke: #7fe3d2;
            stroke-width: 2;
            stroke-linecap: round;
            /* Keliling r=45 dibulatkan: 2 x pi x 45 = 282,7. Dua ruas panjang
               dipisahkan dua jeda — itulah yang membuatnya terbaca mengorbit. */
            stroke-dasharray: 86 55 46 96;
            transform-origin: 60px 60px;
            opacity: 0;
            animation: ocs-mengorbit 1.4s cubic-bezier(.35, .1, .25, 1) .2s forwards;
        }

        .ocs-kilau {
            fill: #2fcfa6;
            /* Titik pusat jalurnya memang 0,0, jadi transform-origin bawaan
               sudah tepat — tidak perlu transform-box: fill-box. */
            opacity: 0;
            animation: ocs-berkilau .9s ease-out var(--tunda) forwards;
        }

        @keyframes ocs-menggores {
            to {
                stroke-dashoffset: 0;
            }
        }

        @keyframes ocs-mengembang {
            from {
                transform: scale(.45);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes ocs-mengorbit {
            0% {
                opacity: 0;
                transform: rotate(-60deg);
            }

            35% {
                opacity: .9;
            }

            100% {
                opacity: .75;
                transform: rotate(28deg);
            }
        }

        @keyframes ocs-berkilau {
            0% {
                opacity: 0;
                transform: scale(0) rotate(-45deg);
            }

            55% {
                opacity: 1;
                transform: scale(1.15) rotate(0deg);
            }

            100% {
                opacity: .85;
                transform: scale(1) rotate(0deg);
            }
        }

        /* Sebagian orang menyetel sistemnya menahan gerakan — bukan soal
           selera, melainkan mual dan pusing. Yang mereka terima bentuk
           akhirnya, seketika dan utuh, bukan versi yang lebih miskin. */
        @media (prefers-reduced-motion: reduce) {

            .ocs-cakram,
            .ocs-centang,
            .ocs-orbit,
            .ocs-kilau {
                animation: none;
                opacity: 1;
            }

            .ocs-centang {
                stroke-dashoffset: 0;
            }

            .ocs-orbit {
                opacity: .75;
            }
        }
    </style>
@endonce
