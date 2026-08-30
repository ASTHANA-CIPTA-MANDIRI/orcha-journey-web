{{-- ============================================================
     Orcha Journey — Loader
     Lencana muncul sementara satu cincin cahaya menggambar penuh
     mengelilinginya, lalu kilau menyapu.
     Self-contained: style + script inline, prefix .orc-*.

     Bentuknya mengikuti loader Phoenix Digital (lapisan layar penuh,
     cahaya di belakang logo, partikel, wordmark dua baris, batang
     kemajuan, mode gelap, prefers-reduced-motion). Yang BERBEDA hanya
     cara logonya muncul: logo Phoenix tersusun dari empat bilah yang
     merekah dari satu pivot, dan irisan clip-path-nya digarap khusus
     untuk bentuk itu. Logo Orcha lencana bundar — irisan yang sama akan
     memotong orca-nya jadi juring, kepala dan ekor di potongan berbeda.
     Jadi iramanya dipertahankan (tumbuh, overshoot, kilau, denyut),
     mekanismenya yang menyesuaikan bentuk bundar.
     ============================================================ --}}
<div id="orcha-loader" class="orc-loader" role="status" aria-live="polite" aria-label="Memuat Orcha Journey">
    <div class="orc-stage">
        <div class="orc-glow" aria-hidden="true"></div>

        {{-- Gelembung naik — padanan laut untuk bara api di loader Phoenix --}}
        <div class="orc-bubbles" aria-hidden="true">
            <span class="orc-bubble b1"></span>
            <span class="orc-bubble b2"></span>
            <span class="orc-bubble b3"></span>
            <span class="orc-bubble b4"></span>
            <span class="orc-bubble b5"></span>
            <span class="orc-bubble b6"></span>
        </div>

        <div class="orc-mark-wrap">
            <div class="orc-mark-img">
                {{-- Cincin digambar dengan conic-gradient yang disapu lewat
                     properti bernama (@property --orc-sudut). Tanpa pendaftaran
                     itu peramban memperlakukan sudutnya sebagai kata, bukan
                     angka, sehingga tidak ada yang bisa dianimasikan — cincinnya
                     akan meloncat 0→360 tanpa gerak di antaranya. --}}
                <span class="orc-ring" aria-hidden="true"></span>

                {{-- Cakram pengurung. Kilau di bawah harus TERKURUNG di dalam
                     lencana: kalau ia bebas, sapuannya melayang di sebelah logo
                     sebagai bidang pucat yang terbaca sebagai cacat gambar,
                     bukan sebagai kilau. Cincin sengaja di LUAR cakram ini —
                     ia digambar di inset -9px dan akan ikut terpotong habis
                     kalau ditaruh di dalam. --}}
                <span class="orc-disc">
                    <img class="orc-mark" src="{{ asset('orcha-logo-only.png') }}" alt="Orcha Journey"
                        width="128" height="128" fetchpriority="high" decoding="async">
                    <span class="orc-sheen" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="orc-word">
            <span class="orc-word-top">Orcha</span>
            <span class="orc-word-bot">Journey</span>
        </div>

        <div class="orc-bar" aria-hidden="true"><span></span></div>
    </div>
</div>

<style>
    @property --orc-sudut {
        syntax: '<angle>';
        inherits: false;
        initial-value: 0deg;
    }

    .orc-loader {
        position: fixed;
        inset: 0;
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(120% 90% at 50% 38%, #04223a 0%, #001a2e 55%, #001220 100%);
        opacity: 1;
        visibility: visible;
        transition: opacity .55s ease, visibility 0s linear .55s;
    }
    .orc-loader.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .55s ease, visibility 0s linear .55s;
    }

    /* Skrip gagal dimuat -> layar muat tidak boleh menutupi halaman selamanya.
       Jaring pengaman 8 detik di bawah tidak menolong di sini: kalau skripnya
       memang tidak pernah jalan, tidak ada yang menyalakan penghitungnya. */
    .no-js .orc-loader {
        display: none;
    }

    .orc-stage {
        position: relative;
        width: 240px;
        max-width: 78vw;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    /* Cahaya sejuk di belakang lencana */
    .orc-glow {
        position: absolute;
        top: -14px;
        left: 50%;
        width: 240px;
        height: 240px;
        transform: translateX(-50%);
        border-radius: 50%;
        background: radial-gradient(circle, rgba(26,176,226,.40) 0%, rgba(0,109,168,.16) 42%, rgba(0,109,168,0) 70%);
        filter: blur(4px);
        animation: orcGlow 2.6s ease-in-out infinite;
        pointer-events: none;
    }
    @keyframes orcGlow {
        0%, 100% { opacity: .55; transform: translateX(-50%) scale(.86); }
        45%      { opacity: 1;   transform: translateX(-50%) scale(1.06); }
    }

    .orc-mark-wrap { position: relative; z-index: 2; }
    /* Lencana Orcha bujur sangkar 128x128 */
    .orc-mark-img {
        position: relative;
        width: 132px;
        height: 132px;
        filter: drop-shadow(0 12px 26px rgba(0,109,168,.42));
    }

    /* Lencananya sendiri: tumbuh dari kecil dengan sedikit overshoot,
       irama yang sama dengan irisan logo Phoenix.

       Yang dianimasikan cakramnya, bukan gambarnya — supaya kilau di dalamnya
       ikut tumbuh bersama lencana alih-alih menyapu bidang yang belum ada. */
    .orc-disc {
        position: absolute;
        inset: 0;
        display: block;
        border-radius: 50%;
        overflow: hidden;
        opacity: 0;
        animation: orcMark 2.6s cubic-bezier(.22,.9,.24,1) infinite;
        will-change: transform, opacity;
    }
    .orc-mark {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    @keyframes orcMark {
        0%   { opacity: 0; transform: scale(.34); }
        12%  { opacity: 1; }
        22%  { transform: scale(1.04); }        /* sedikit overshoot */
        30%  { transform: scale(1); }
        68%  { opacity: 1; transform: none; }
        84%  { opacity: 0; transform: scale(.34); }
        100% { opacity: 0; transform: scale(.34); }
    }

    /* Cincin yang menggambar penuh mengelilingi lencana.
       conic-gradient dipotong jadi cincin oleh mask, jadi yang terlihat hanya
       tepinya — bukan cakram penuh. */
    .orc-ring {
        position: absolute;
        inset: -9px;
        border-radius: 50%;
        background: conic-gradient(from -90deg,
            #1ab0e2 0deg,
            #7fd4f3 var(--orc-sudut),
            rgba(127,212,243,.10) var(--orc-sudut));
        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 3px));
                mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 3px));
        opacity: 0;
        animation: orcRing 2.6s cubic-bezier(.4,0,.2,1) infinite;
        pointer-events: none;
    }
    @keyframes orcRing {
        0%       { --orc-sudut: 0deg;   opacity: 0; }
        10%      { opacity: 1; }
        55%      { --orc-sudut: 360deg; opacity: 1; }
        76%      { --orc-sudut: 360deg; opacity: 1; }
        90%, 100%{ --orc-sudut: 360deg; opacity: 0; }
    }

    /* Sapuan cahaya melintasi lencana saat utuh */
    .orc-sheen {
        position: absolute;
        top: -12%;
        left: 0;
        width: 26px;
        height: 124%;
        background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.6), rgba(255,255,255,0));
        transform: translateX(-40px) rotate(20deg);
        opacity: 0;
        /* screen, bukan overlay. Latar Orcha laut dalam, dan overlay atas warna
           gelap justru MENGGELAPKAN — kilaunya terbaca sebagai noda, bukan
           cahaya. screen selalu menerangkan. */
        mix-blend-mode: screen;
        pointer-events: none;
        animation: orcSheen 2.6s ease-in-out infinite;
    }
    @keyframes orcSheen {
        0%, 32%   { opacity: 0; transform: translateX(-40px) rotate(20deg); }
        44%       { opacity: .85; }
        60%       { opacity: 0; transform: translateX(150px) rotate(20deg); }
        100%      { opacity: 0; transform: translateX(150px) rotate(20deg); }
    }

    /* Gelembung naik */
    .orc-bubbles {
        position: absolute;
        top: 8px;
        left: 50%;
        width: 150px;
        height: 150px;
        transform: translateX(-50%);
        z-index: 1;
        pointer-events: none;
    }
    .orc-bubble {
        position: absolute;
        bottom: 34px;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: radial-gradient(circle at 35% 30%, #ffffff 0%, #7fd4f3 45%, rgba(26,176,226,0) 72%);
        opacity: 0;
        animation: orcBubble 2.8s ease-in infinite;
    }
    .orc-bubble.b1 { left: 40%; animation-delay: .1s; }
    .orc-bubble.b2 { left: 54%; width: 4px; height: 4px; animation-delay: .7s; }
    .orc-bubble.b3 { left: 62%; animation-delay: 1.3s; }
    .orc-bubble.b4 { left: 48%; width: 5px; height: 5px; animation-delay: 1.9s; }
    .orc-bubble.b5 { left: 58%; width: 3px; height: 3px; animation-delay: 1s; }
    .orc-bubble.b6 { left: 44%; width: 4px; height: 4px; animation-delay: 2.3s; }
    @keyframes orcBubble {
        0%   { opacity: 0; transform: translate(0, 0) scale(.6); }
        14%  { opacity: 1; }
        70%  { opacity: .9; }
        100% { opacity: 0; transform: translate(10px, -68px) scale(.3); }
    }

    /* Wordmark */
    .orc-word {
        margin-top: 18px;
        text-align: center;
        line-height: 1;
        opacity: 0;
        transform: translateY(8px);
        animation: orcWord 2.6s ease-in-out infinite;
    }
    .orc-word-top {
        display: block;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-weight: 800;
        font-size: 1.5rem;
        letter-spacing: .5px;
        background: linear-gradient(90deg, #1ab0e2, #ffc74e);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .orc-word-bot {
        display: block;
        margin-top: 3px;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-weight: 600;
        font-size: .72rem;
        letter-spacing: 4px;
        text-transform: uppercase;
        color: #7fd4f3;
    }
    @keyframes orcWord {
        0%, 22%  { opacity: 0; transform: translateY(8px); }
        40%, 74% { opacity: 1; transform: translateY(0); }
        92%,100% { opacity: 0; transform: translateY(8px); }
    }

    /* Batang kemajuan tak tentu */
    .orc-bar {
        margin-top: 20px;
        width: 148px;
        height: 4px;
        border-radius: 99px;
        background: rgba(26,176,226,.18);
        overflow: hidden;
    }
    .orc-bar span {
        display: block;
        width: 42%;
        height: 100%;
        border-radius: 99px;
        background: linear-gradient(90deg, #1ab0e2, #ffc74e);
        animation: orcBar 1.25s cubic-bezier(.65,.05,.35,1) infinite;
    }
    @keyframes orcBar {
        0%   { transform: translateX(-120%); }
        100% { transform: translateX(360%); }
    }

    /* Mode terang: situsnya berlatar putih, jadi layar muat ikut terang.
       Ditulis sebagai media query terang (bukan gelap seperti Phoenix) karena
       nada dasar Orcha memang laut dalam. */
    @media (prefers-color-scheme: light) {
        .orc-loader {
            background: radial-gradient(120% 90% at 50% 38%, #ffffff 0%, #f2fbff 55%, #e8f7fd 100%);
        }
        .orc-word-bot { color: #006da8; }
        .orc-bar { background: rgba(0,109,168,.16); }
        .orc-glow {
            background: radial-gradient(circle, rgba(26,176,226,.30) 0%, rgba(0,109,168,.12) 42%, rgba(0,109,168,0) 70%);
        }
        .orc-sheen { mix-blend-mode: soft-light; }
    }

    /* Hormati pengguna yang mengurangi animasi: tampilkan lencana utuh,
       denyut lembut, cincin diam penuh. */
    @media (prefers-reduced-motion: reduce) {
        .orc-disc { opacity: 1 !important; transform: none !important; animation: orcSoftPulse 2s ease-in-out infinite; }
        .orc-ring { opacity: 1 !important; --orc-sudut: 360deg; animation: none; }
        .orc-word { opacity: 1 !important; transform: none !important; animation: none; }
        .orc-glow, .orc-bubbles { animation: none; }
        .orc-sheen { display: none; }
        @keyframes orcSoftPulse { 0%,100% { opacity: .85; } 50% { opacity: 1; } }
    }
</style>

<script>
    (function () {
        var el = document.getElementById('orcha-loader');
        if (!el) return;

        var MIN_MS = 650;           // tampil minimal supaya tidak berkedip
        var shownAt = Date.now();
        var hideTimer = null;

        // Halaman tidak boleh bisa digulung di balik layar muat.
        document.body.classList.add('orc-terkunci');

        function hide() {
            var wait = Math.max(0, MIN_MS - (Date.now() - shownAt));
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                el.classList.add('is-hidden');
                document.body.classList.remove('orc-terkunci');
                // GSAP menghitung posisi pemicu gulir saat halaman masih
                // tertutup layar muat; tingginya berubah begitu layar ini
                // pergi, jadi perhitungannya perlu diulang.
                window.dispatchEvent(new CustomEvent('orcha:loader-selesai'));
            }, wait);
        }
        function show() {
            clearTimeout(hideTimer);
            shownAt = Date.now();
            document.body.classList.add('orc-terkunci');
            el.classList.remove('is-hidden');
        }

        // Muat awal
        if (document.readyState === 'complete') hide();
        else window.addEventListener('load', hide);

        // Navigasi SPA Livewire (wire:navigate)
        document.addEventListener('livewire:navigate', show);
        document.addEventListener('livewire:navigated', hide);

        // Jaring pengaman: jangan sampai loader tersangkut
        setTimeout(function () {
            el.classList.add('is-hidden');
            document.body.classList.remove('orc-terkunci');
        }, 8000);
    })();
</script>
