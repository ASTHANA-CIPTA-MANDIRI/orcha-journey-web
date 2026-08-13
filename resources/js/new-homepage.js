import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { Observer } from "gsap/Observer";
import SplitType from "split-type";
gsap.registerPlugin(ScrollTrigger, Observer);

document.addEventListener("DOMContentLoaded", function () {
    /* ==========================================
       0. PRELOADER (persentase loading asset + transisi GSAP)
       ========================================== */
    const preloader = document.getElementById("preloader");
    if (preloader) {
        const percentageEl = document.getElementById("preloader-percentage");
        const barEl = document.getElementById("preloader-bar");
        const contentEl = preloader.querySelector(".preloader-content");
        document.body.classList.add("overflow-hidden");

        // Minimal 2 detik preloader tetap tampil, walau aset sudah selesai lebih cepat
        const MIN_DISPLAY_MS = 2000;
        const startedAt = performance.now();

        const heroTargets = gsap.utils.toArray(
            ".intro-section .hero-title, .intro-section .hero-title-outline, .intro-section .hero-img, .intro-section a",
        );
        if (heroTargets.length) gsap.set(heroTargets, { opacity: 0, y: 40 });

        const progress = { value: 0 };
        let assetsReady = false;
        let exitStarted = false;

        const renderProgress = () => {
            const value = Math.round(progress.value);
            if (percentageEl) percentageEl.textContent = value;
            if (barEl) barEl.style.width = `${value}%`;
        };

        const animateTo = (target, duration) => {
            gsap.to(progress, {
                value: target,
                duration,
                ease: "power1.out",
                overwrite: true,
                onUpdate: renderProgress,
                onComplete: () => {
                    if (target >= 100) startExit();
                },
            });
        };

        const startExit = () => {
            if (exitStarted) return;
            exitStarted = true;

            const tl = gsap.timeline({
                defaults: { ease: "power4.inOut" },
                onComplete: () => {
                    document.body.classList.remove("overflow-hidden");
                    preloader.remove();
                },
            });

            tl.to(contentEl, { opacity: 0, y: -20, duration: 0.45, ease: "power2.in" }).to(
                preloader,
                { yPercent: -100, duration: 1.1 },
                "-=0.15",
            );

            if (heroTargets.length) {
                tl.to(
                    heroTargets,
                    { opacity: 1, y: 0, duration: 1, stagger: 0.12, ease: "power3.out" },
                    "-=0.9",
                );
            }
        };

        // Begitu aset siap, hitung sisa waktu agar total tampilan preloader minimal 2 detik
        const checkReady = () => {
            if (!assetsReady) return;
            const elapsed = performance.now() - startedAt;
            const remaining = Math.max(0, MIN_DISPLAY_MS - elapsed);
            animateTo(100, Math.max(remaining / 1000, 0.3));
        };

        const images = Array.from(document.images);
        const total = images.length;

        if (total === 0) {
            assetsReady = true;
            checkReady();
        } else {
            let loaded = 0;
            images.forEach((img) => {
                const onDone = () => {
                    loaded += 1;
                    animateTo((loaded / total) * 100, 0.4);
                    if (loaded === total) {
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

        // Jaga-jaga: pastikan preloader tetap tertutup walau ada aset lain yang lambat
        window.addEventListener("load", () => {
            assetsReady = true;
            checkReady();
        });
        setTimeout(() => {
            assetsReady = true;
            checkReady();
        }, 10000);
    }

    /* ==========================================
       1. ANIMASI HERO
       ========================================== */
    const introSection = document.querySelector(".intro-section");
    if (introSection) {
        let heroTl = gsap.timeline({
            scrollTrigger: {
                trigger: introSection,
                start: "top top",
                end: "bottom top",
                scrub: 1,
            },
        });
        heroTl.to(
            ".hero-title",
            { scale: 1.5, y: "50%", ease: "power1.inOut" },
            0,
        );
        heroTl.to(
            ".hero-title-outline",
            { scale: 1.5, y: "50%", ease: "power1.inOut" },
            0,
        );
        gsap.fromTo(
            ".hero-img",
            { x: "30vw", y: "20vh", rotate: -15 },
            {
                x: "-30vw",
                y: "-20vh",
                rotate: 15,
                ease: "none",
                scrollTrigger: {
                    trigger: introSection,
                    start: "top top",
                    end: "bottom top",
                    scrub: 2,
                },
            },
        );
    }

    /* ==========================================
       2. ANIMASI JUDUL SECTION (konsisten di semua section)
       ========================================== */
    gsap.utils.toArray(".section-heading").forEach((heading) => {
        gsap.from(heading, {
            scrollTrigger: {
                trigger: heading,
                start: "top 85%",
                toggleActions: "play none none reverse",
            },
            y: 30,
            opacity: 0,
            duration: 0.9,
            ease: "power3.out",
        });
    });

    /* ==========================================
       3. ANIMASI BUNDLING
       ========================================== */
    gsap.from(".bundle-card", {
        scrollTrigger: {
            trigger: "#bundling",
            start: "top 75%",
            toggleActions: "play none none reverse",
        },
        y: 80,
        scale: 0.9,
        opacity: 0,
        duration: 0.8,
        stagger: 0.2,
        ease: "back.out(1.5)",
    });

    /* ==========================================
       4. ANIMASI DESTINASI
       ========================================== */
    const destWrapper = document.querySelector(".dest-wrapper");
    if (destWrapper) {
        let destItems = gsap.utils.toArray(".dest-item"),
            backgrounds = gsap.utils.toArray(".image-bg"),
            itemsInner = gsap.utils.toArray(".dest-inner"),
            listImages = gsap.utils.toArray(".list-image"),
            titleHeading = gsap.utils.toArray(".content h2");

        let currentIndex = 0;
        let introPlayed = false;
        const maxIndex = destItems.length - 1;

        let titleSplit = titleHeading.map(
            (title) =>
                new SplitType(title, {
                    types: "words, chars",
                }),
        );

        // Variabel Kontrol Scroll (Sesuai Referensi 2024)
        let allowScroll = true;
        // Jeda 1.5 detik agar animasi selesai sebelum menerima perintah scroll baru
        let scrollTimeout = gsap
            .delayedCall(1.5, () => (allowScroll = true))
            .pause();

        // Bagian reveal (thumbnail + judul + zoom background) dipakai bersama
        // oleh transisi antar slide maupun reveal slide pertama, supaya semua
        // slide (termasuk yang pertama) masuk dengan gerakan yang sama halusnya.
        function appendReveal(tl, index) {
            const isMobile = window.innerWidth < 992;
            if (isMobile) {
                tl.fromTo(
                    listImages[index].querySelectorAll("img"),
                    { yPercent: 130, opacity: 0 },
                    {
                        yPercent: 0,
                        opacity: 1,
                        stagger: 0.1,
                        duration: 2,
                        ease: "expo.inOut",
                    },
                    "<",
                );
            } else {
                tl.fromTo(
                    listImages[index].querySelectorAll("img"),
                    { xPercent: 120, opacity: 0 },
                    {
                        xPercent: 0,
                        opacity: 1,
                        stagger: 0.1,
                        duration: 2,
                        ease: "expo.inOut",
                    },
                    "<",
                );
            }

            tl.from(
                titleSplit[index].chars,
                {
                    yPercent: 100,
                    opacity: 0,
                    stagger: { each: 0.03, from: "start" },
                    ease: "expo.inOut",
                    duration: 1.5,
                },
                "<",
            );

            const totalTimeline = tl.duration() * 1.5;
            tl.fromTo(
                backgrounds[index],
                { scale: 1 },
                { scale: 1.3, duration: totalTimeline, ease: "expo.out" },
                "startTl",
            );
        }

        // Transisi antar slide (menyembunyikan slide lama, memunculkan slide baru)
        function slideIn(index) {
            // Kunci scroll sementara animasi berjalan
            allowScroll = false;
            scrollTimeout.restart(true);

            let tlSlideIn = gsap.timeline({
                defaults: { duration: 1, ease: "power1.inOut" },
            });

            // Hilangkan item saat ini
            gsap.set(destItems[currentIndex], { zIndex: 0 });
            tlSlideIn.to(itemsInner[currentIndex], { autoAlpha: 0 });

            // Munculkan item baru
            gsap.set(destItems[index], { zIndex: 1 });
            tlSlideIn.to(itemsInner[index], { autoAlpha: 1 }, "<");
            tlSlideIn.addLabel("startTl", "<");

            appendReveal(tlSlideIn, index);

            currentIndex = index;
        }

        // Reveal slide pertama saat section baru terkunci (pin) di layar, agar
        // tidak ada "lompatan" dari state statis ke slideshow yang animatif.
        function playIntro(index) {
            let tlIntro = gsap.timeline({
                defaults: { duration: 1, ease: "power1.inOut" },
            });

            gsap.set(destItems[index], { zIndex: 1 });
            tlIntro.to(itemsInner[index], { autoAlpha: 1 });
            tlIntro.addLabel("startTl", "<");

            appendReveal(tlIntro, index);
        }

        // --- OBSERVER 2024: MENCEGAH MOMENTUM SCROLL BROWSER ---
        const isTouch = ScrollTrigger.isTouch;
        if (isTouch === 1) {
            ScrollTrigger.normalizeScroll(true);
        }

        let intentObserver = ScrollTrigger.observe({
            type: "wheel,touch,pointer",
            tolerance: 10,
            preventDefault: true,
            onUp: () => {
                if (!allowScroll) return; // Jika animasi masih jalan, abaikan scroll
                if (currentIndex > 0) {
                    slideIn(currentIndex - 1);
                } else {
                    intentObserver.disable(); // Sudah di awal, lepaskan scroll native
                }
            },
            onDown: () => {
                if (!allowScroll) return; // Jika animasi masih jalan, abaikan scroll
                if (currentIndex < maxIndex) {
                    slideIn(currentIndex + 1);
                } else {
                    intentObserver.disable(); // Sudah di akhir, lepaskan scroll native
                }
            },
            onEnable(self) {
                allowScroll = false;
                scrollTimeout.restart(true);
                // Bekukan posisi scroll native (Sangat ampuh untuk pengguna Mac/Trackpad)
                let savedScroll = self.scrollY();
                self._restoreScroll = () => self.scrollY(savedScroll);
                document.addEventListener("scroll", self._restoreScroll, {
                    passive: false,
                });
            },
            onDisable: (self) => {
                document.removeEventListener("scroll", self._restoreScroll);
            },
        });

        intentObserver.disable(); // Matikan di awal

        // --- PINNING & TRIGGER SECTION ---
        ScrollTrigger.create({
            trigger: ".dest-wrapper",
            pin: true,
            anticipatePin: 1, // Hindari efek "lompat" saat section mulai terkunci
            start: "top top",
            end: "+=200", // Buffer area agar tidak mudah lompat
            onEnter: (self) => {
                if (!introPlayed) {
                    introPlayed = true;
                    playIntro(currentIndex);
                }
                if (intentObserver.isEnabled) return;
                self.scroll(self.start + 1); // Lompat 1px ke dalam section agar tertahan
                intentObserver.enable();
            },
            onEnterBack: (self) => {
                if (intentObserver.isEnabled) return;
                self.scroll(self.end - 1); // Lompat 1px sebelum end agar tertahan
                intentObserver.enable();
            },
        });
    }

    /* ==========================================
       5. ANIMASI MOBIL CARD
       ========================================== */
    gsap.from(".car-card", {
        scrollTrigger: {
            trigger: ".car-section",
            start: "top 75%",
            toggleActions: "play none none reverse",
        },
        y: 100,
        rotationX: 15,
        transformPerspective: 1000,
        opacity: 0, // Menggunakan opacity 0 agar tersembunyi di awal
        duration: 1,
        stagger: 0.15,
        ease: "power3.out",
    });

    /* ==========================================
       6. ANIMASI GALERI (Infinite Horizontal Scroll)
       ========================================== */
    const gallerySpeed = 25;

    // Track 1: Bergeser ke Kiri (Dari x: 0% menuju x: -50%)
    gsap.to(".gallery-track-left", {
        xPercent: -50,
        ease: "none", // Harus 'none' agar kecepatan stabil dan mulus tanpa melambat
        duration: gallerySpeed,
        repeat: -1, // -1 artinya mengulang tanpa batas (infinite loop)
    });

    // Track 2: Bergeser ke Kanan (Dari x: -50% menuju x: 0%)
    gsap.fromTo(
        ".gallery-track-right",
        { xPercent: -50 }, // Mulai dari tengah wadah yang digandakan
        {
            xPercent: 0, // Berjalan mundur ke posisi 0
            ease: "none",
            duration: gallerySpeed,
            repeat: -1,
        },
    );

    /* ==========================================
       7. ANIMASI TESTIMONI
       ========================================== */
    gsap.from(".testimonial-card", {
        scrollTrigger: {
            trigger: "#reviews",
            start: "top 70%",
            toggleActions: "play none none reverse",
        },
        y: 40,
        opacity: 0,
        duration: 0.8,
        stagger: 0.15,
        ease: "power3.out",
    });

    /* ==========================================
       8. ANIMASI PARTNER
       ========================================== */
    gsap.from(".partner-card", {
        scrollTrigger: {
            trigger: ".partners-grid",
            start: "top 85%",
            toggleActions: "play none none reverse",
        },
        y: 30,
        opacity: 0,
        scale: 0.9,
        duration: 0.6,
        stagger: 0.08,
        ease: "back.out(1.4)",
    });
});
