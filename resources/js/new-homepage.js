import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);

/**
 * Halaman ditandai `no-js` dari HTML. Begitu modul ini jalan, tandanya dilepas
 * sehingga preloader & animasi masuk aktif. Kalau modul gagal dimuat, `no-js`
 * tetap menempel: preloader disembunyikan CSS dan semua konten langsung tampil.
 */
document.documentElement.classList.remove("no-js");

const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
).matches;

/* ==========================================================
   1. PRELOADER — progres berdasarkan gambar yang selesai dimuat
========================================================== */
function initPreloader() {
    const preloader = document.getElementById("preloader");
    if (!preloader) return;

    const percentageEl = document.getElementById("preloader-percentage");
    const contentEl = preloader.querySelector(".preloader-content");

    document.body.classList.add("overflow-hidden");

    const MIN_DISPLAY_MS = 1200;
    const HARD_TIMEOUT_MS = 6000;
    const startedAt = performance.now();

    const progress = { value: 0 };
    let assetsReady = false;
    let exitStarted = false;

    // Satu tulisan untuk semuanya: angka persennya, dan --maju yang dibaca CSS
    // untuk jalur, pesawat, dan fajarnya. Kalau masing-masing digerakkan
    // sendiri, ketiganya akan berselisih di jaringan lambat — pesawatnya sampai
    // di ujung sementara angkanya masih 70%.
    const renderProgress = () => {
        const value = progress.value;
        if (percentageEl) percentageEl.textContent = Math.round(value);
        preloader.style.setProperty("--maju", (value / 100).toFixed(4));
    };

    const finish = () => {
        if (exitStarted) return;
        exitStarted = true;
        document.body.classList.remove("overflow-hidden");

        if (prefersReducedMotion) {
            preloader.remove();
            ScrollTrigger.refresh();
            return;
        }

        // Keluarnya dibaca sebagai "berangkat", bukan sebagai panel yang
        // hilang: pesawat dan jalurnya meluncur ke depan lebih dulu, isinya
        // menepi, baru layarnya terangkat.
        const rute = preloader.querySelector(".preloader-rute");

        gsap.timeline({
            defaults: { ease: "power3.inOut" },
            onComplete: () => {
                preloader.remove();
                ScrollTrigger.refresh();
            },
        })
            .to(rute, { xPercent: 18, opacity: 0, duration: 0.5, ease: "power2.in" }, 0)
            .to(contentEl, { opacity: 0, y: -24, duration: 0.45, ease: "power2.in" }, 0.12)
            .to(preloader, { yPercent: -100, duration: 1, ease: "power4.inOut" }, 0.3);
    };

    const animateTo = (target, duration) => {
        gsap.to(progress, {
            value: target,
            duration: prefersReducedMotion ? 0 : duration,
            ease: "power1.out",
            overwrite: true,
            onUpdate: renderProgress,
            onComplete: () => {
                renderProgress();
                if (target >= 100) finish();
            },
        });
    };

    const checkReady = () => {
        if (!assetsReady) return;
        const remaining = Math.max(
            0,
            MIN_DISPLAY_MS - (performance.now() - startedAt),
        );
        animateTo(100, Math.max(remaining / 1000, 0.25));
    };

    // Hanya menunggu gambar yang benar-benar terlihat di layar awal.
    // Gambar bertanda loading="lazy" tidak ikut dihitung supaya preloader
    // tidak menahan halaman karena galeri di bagian bawah.
    const images = Array.from(document.images).filter(
        (img) => img.loading !== "lazy",
    );

    if (images.length === 0) {
        assetsReady = true;
        checkReady();
    } else {
        let loaded = 0;
        images.forEach((img) => {
            const onDone = () => {
                loaded += 1;
                animateTo((loaded / images.length) * 100, 0.35);
                if (loaded === images.length) {
                    assetsReady = true;
                    checkReady();
                }
            };
            if (img.complete) {
                onDone();
            } else {
                img.addEventListener("load", onDone, { once: true });
                img.addEventListener("error", onDone, { once: true });
            }
        });
    }

    window.addEventListener("load", () => {
        assetsReady = true;
        checkReady();
    });

    // Jaring pengaman: apa pun yang terjadi, preloader tidak menahan halaman.
    setTimeout(() => {
        assetsReady = true;
        checkReady();
        finish();
    }, HARD_TIMEOUT_MS);
}

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
    initPreloader();
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
