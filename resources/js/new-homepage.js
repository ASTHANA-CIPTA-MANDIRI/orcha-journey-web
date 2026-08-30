import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);

/**
 * Halaman ditandai `no-js` dari HTML. Begitu modul ini jalan, tandanya dilepas
 * sehingga animasi masuk aktif. Kalau modul gagal dimuat, `no-js` tetap
 * menempel dan seluruh isi halaman langsung tampil.
 *
 * Layar muat tidak ikut bergantung pada berkas ini — ia punya skripnya sendiri
 * di partials/orcha-loader.blade.php. Tetapi aturan `.no-js .orc-loader` di
 * sana tetap perlu: kalau skrip mana pun gagal, layar muat harus menyingkir
 * sendiri, bukan menutupi halaman selamanya.
 */
document.documentElement.classList.remove("no-js");

const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
).matches;

/* ==========================================================
   0. PETA RUTE — hanya di halaman yang memakainya
   Leaflet beserta gayanya sekitar 45 KB; halaman lain tidak perlu
   membayarnya, jadi modulnya diambil hanya bila wadahnya ada.
========================================================== */
if (document.querySelector(".peta-rute-kanvas")) {
    import("./peta-rute.js");
}

/* ==========================================================
   1. LAYAR MUAT
   Layar muatnya sendiri berdiri sendiri di partials/orcha-loader.blade.php:
   markup, gaya, dan perilakunya satu berkas, tanpa bergantung pada GSAP —
   kalau berkas ini gagal dimuat, layar muat tetap tahu cara pergi.

   Yang tersisa di sini cuma satu sambungan: ScrollTrigger menghitung posisi
   pemicu gulir saat halaman masih tertutup layar muat, dan tinggi halaman
   berubah begitu layar itu pergi. Tanpa perhitungan ulang, animasi masuk di
   bagian bawah halaman terpicu pada posisi yang salah.
========================================================== */
window.addEventListener("orcha:loader-selesai", () => ScrollTrigger.refresh());

/* ==========================================================
   2. REVEAL — elemen ber-class .reveal muncul saat masuk viewport
   Pakai IntersectionObserver (native) agar tetap jalan di layar
   sempit maupun saat GSAP gagal menghitung posisi.
========================================================== */
function initReveal() {
    const targets = document.querySelectorAll(".reveal");
    if (!targets.length) return;

    if (prefersReducedMotion || !("IntersectionObserver" in window)) {
        targets.forEach((el) => el.classList.add("is-visible"));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                // Kartu bersaudara muncul berurutan, bukan serentak
                const siblings = Array.from(el.parentElement?.children ?? []);
                const order = siblings.filter((n) => n.classList.contains("reveal")).indexOf(el);
                el.style.transitionDelay = `${Math.min(Math.max(order, 0), 6) * 80}ms`;
                el.classList.add("is-visible");
                observer.unobserve(el);
            });
        },
        { rootMargin: "0px 0px -8% 0px", threshold: 0.08 },
    );

    targets.forEach((el) => observer.observe(el));
}

/* ==========================================================
   3. PARALAKS HERO — gambar bergerak lebih lambat dari halaman
========================================================== */
function initHeroParallax() {
    const media = document.querySelector(".hero-parallax");
    if (!media || prefersReducedMotion) return;

    gsap.fromTo(
        media,
        { yPercent: -6, scale: 1.12 },
        {
            yPercent: 10,
            scale: 1.12,
            ease: "none",
            scrollTrigger: {
                trigger: media.closest("section"),
                start: "top top",
                end: "bottom top",
                scrub: true,
            },
        },
    );
}

/* ==========================================================
   4. SCROLL HALUS KE ANCHOR (offset navbar sticky)
========================================================== */
function initAnchorOffset() {
    document.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener("click", (event) => {
            const id = link.getAttribute("href");
            if (!id || id === "#") return;
            const target = document.querySelector(id);
            if (!target) return;

            event.preventDefault();
            const top = target.getBoundingClientRect().top + window.scrollY - 72;
            window.scrollTo({
                top,
                behavior: prefersReducedMotion ? "auto" : "smooth",
            });
        });
    });
}

document.addEventListener("DOMContentLoaded", () => {
    initReveal();
    initHeroParallax();
    initAnchorOffset();
});

/* ==========================================================
   5. GAMBAR ULANG SETELAH LIVEWIRE MENYEGARKAN HALAMAN

   .reveal bermula dari opacity 0 dan baru terlihat setelah skrip ini
   menambahkan .is-visible. Livewire mengganti simpul DOM-nya saat komponen
   digambar ulang — misalnya sesudah formulir kontak terkirim — dan simpul
   yang baru datang tanpa .is-visible.

   Pengamatnya sendiri sudah dilepas (unobserve) untuk simpul lama, jadi tidak
   ada yang menyalakan simpul pengganti: isinya tetap ada dan tetap memakan
   ruang, tetapi tidak pernah terlihat. Di layar hasilnya berupa bidang kosong
   besar — persis yang terjadi pada halaman kontak sesudah pesan terkirim.

   Karena itu pengamatnya dipasang ulang tiap kali Livewire selesai menggambar.
   Memasang ulang pada elemen yang sudah terlihat tidak berbahaya: kalau sudah
   ber-.is-visible ia dilewati, dan yang sedang di layar langsung menyala.
========================================================== */
function segarkanReveal() {
    // Yang sedang berada di layar dinyalakan langsung, tidak lewat pengamat.
    //
    // Pengamat bekerja saat elemen MASUK viewport. Simpul pengganti yang lahir
    // sudah berada di dalam viewport belum tentu memicunya, dan kalau itu
    // terjadi elemennya tidak pernah menyala sama sekali — persoalan yang mau
    // diperbaiki di sini justru terulang. Diuji di peramban: tanpa langkah ini
    // kartunya tetap 0 dari 2 yang terlihat.
    //
    // Tanpa jeda transisi, supaya kartu yang sedang dibaca tidak berkedip.
    document.querySelectorAll(".reveal:not(.is-visible)").forEach((el) => {
        const kotak = el.getBoundingClientRect();

        if (kotak.top < window.innerHeight && kotak.bottom > 0) {
            el.style.transitionDelay = "0ms";
            el.classList.add("is-visible");
        }
    });

    // Sisanya — yang masih di bawah layar — diamati seperti biasa.
    initReveal();
}

document.addEventListener("livewire:navigated", segarkanReveal);
document.addEventListener("livewire:initialized", () => {
    if (window.Livewire?.hook) {
        window.Livewire.hook("morphed", segarkanReveal);
    }
});
