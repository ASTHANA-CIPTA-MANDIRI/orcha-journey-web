import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { Observer } from "gsap/Observer";
import SplitType from "split-type";
gsap.registerPlugin(ScrollTrigger, Observer);

document.addEventListener("DOMContentLoaded", function () {
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
       2. ANIMASI BUNDLING
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
       3. ANIMASI DESTINASI
       ========================================== */
    const destWrapper = document.querySelector(".dest-wrapper");
    if (destWrapper) {
        let destItems = gsap.utils.toArray(".dest-item"),
            backgrounds = gsap.utils.toArray(".image-bg"),
            itemsInner = gsap.utils.toArray(".dest-inner"),
            listImages = gsap.utils.toArray(".list-image"),
            titleHeading = gsap.utils.toArray(".content h2");

        let currentIndex = 0;
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

        // Fungsi Animasi (Tetap menggunakan efek premium kita)
        function slideIn(index, direction) {
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

            const isMobile = window.innerWidth < 992;
            if (isMobile) {
                tlSlideIn.fromTo(
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
                tlSlideIn.fromTo(
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

            tlSlideIn.from(
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

            const totalTimeline = tlSlideIn.duration() * 1.5;
            tlSlideIn.fromTo(
                backgrounds[index],
                { scale: 1 },
                { scale: 1.3, duration: totalTimeline, ease: "expo.out" },
                "startTl",
            );

            currentIndex = index;
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
                    slideIn(currentIndex - 1, -1);
                } else {
                    intentObserver.disable(); // Sudah di awal, lepaskan scroll native
                }
            },
            onDown: () => {
                if (!allowScroll) return; // Jika animasi masih jalan, abaikan scroll
                if (currentIndex < maxIndex) {
                    slideIn(currentIndex + 1, 1);
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
            start: "top top",
            end: "+=200", // Buffer area agar tidak mudah lompat
            onEnter: (self) => {
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
       4. ANIMASI MOBIL CARD
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
       5. ANIMASI GALERI (Infinite Horizontal Scroll)
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
});
