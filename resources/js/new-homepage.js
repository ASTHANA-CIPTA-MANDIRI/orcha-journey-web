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

/* ==========================================================
   KORSEL (galeri & testimoni)

   Dua mode, satu mesin: "kipas" (galeri — satu foto besar di tengah,
   tetangganya dimiringkan ke belakang) dan "tumpuk" (testimoni — kartu
   aktif di depan, sisanya mundur seperti setumpuk kartu). Modenya dibaca
   dari atribut data-korsel pada wadahnya.

   Markahnya tanpa kelas .is-3d sampai fungsi ini jalan: kalau berkas ini
   gagal dimuat, galerinya tetap berupa baris bergulir yang bisa digeser
   jari. Itu sebabnya kelasnya dipasang DI SINI, bukan ditulis di Blade.
========================================================== */
function initKorsel() {
    document.querySelectorAll("[data-korsel]").forEach((panggung) => {
        if (panggung.dataset.korselSiap === "1") return;

        const jalur = panggung.querySelector("[data-korsel-jalur]");
        const kartu = Array.from(panggung.querySelectorAll("[data-korsel-kartu]"));
        if (!jalur || kartu.length === 0) return;

        panggung.dataset.korselSiap = "1";
        panggung.classList.add("is-3d");
        const mode = panggung.dataset.korsel || "kipas";

        const wadahTitik = panggung.closest("section")?.querySelector("[data-korsel-titik]");
        // Kendali dan keterangan berada di LUAR panggung (lajur kiri), jadi
        // dicari dari bagian terdekat, bukan dari dalam panggungnya.
        const bagian = panggung.closest("section") || document;
        const wadahHitung = bagian.querySelector("[data-korsel-hitung]");
        const wadahKeterangan = bagian.querySelector("[data-korsel-keterangan]");
        const blokKeterangan = bagian.querySelector("[data-korsel-blok]");
        const duaAngka = (n) => String(n).padStart(2, "0");
        let aktif = 0;
        let jeda = false;
        let pewaktu = null;

        // Jarak terpendek i ke kartu aktif, dihitung melingkar: kartu
        // terakhir berjarak 1 dari kartu pertama, bukan n-1. Tanpa ini,
        // perpindahan dari foto terakhir ke foto pertama terbaca seperti
        // melempar seluruh deretnya balik ke awal.
        const jarak = (i) => {
            const n = kartu.length;
            let d = i - aktif;
            if (d * 2 > n) d -= n;
            if (d * 2 < -n) d += n;
            return d;
        };

        const gambar = () => {
            kartu.forEach((el, i) => {
                const d = jarak(i);
                const jauh = Math.abs(d);
                const terlihat = jauh < 3;

                el.classList.toggle("is-aktif", d === 0);
                el.style.zIndex = String(20 - jauh);
                el.style.opacity = terlihat ? String(1 - jauh * (mode === "tumpuk" ? 0.45 : 0.28)) : "0";
                el.style.pointerEvents = terlihat ? "auto" : "none";

                if (mode === "tumpuk") {
                    // TUMPUKAN: kartu aktif di depan, tetangganya mundur ke
                    // belakang dan sedikit turun — seperti setumpuk kartu.
                    //
                    // Dipakai untuk testimoni, bukan kipas seperti galeri:
                    // kartu kipas dimiringkan 30 derajat, dan tulisan yang
                    // dimiringkan sebanyak itu tidak terbaca. Di sini yang
                    // miring cuma 1,5 derajat, sekadar penanda bahwa ada
                    // kartu lain di belakangnya.
                    el.style.transform =
                        "translateX(" + d * 7 + "%)" +
                        " translateY(" + jauh * 18 + "px)" +
                        " scale(" + (1 - jauh * 0.07) + ")" +
                        " rotate(" + d * 1.5 + "deg)";
                } else {
                    // KIPAS: tetangganya didorong ke tepi dan dimiringkan,
                    // supaya deretnya mengisi lebar panel.
                    el.style.transform =
                        "translateX(" + d * 64 + "%)" +
                        " translateZ(" + jauh * -150 + "px)" +
                        " rotateY(" + d * -30 + "deg)";
                }
                el.setAttribute("aria-hidden", d === 0 ? "false" : "true");
            });

            if (wadahTitik) {
                Array.from(wadahTitik.children).forEach((t, i) => {
                    t.setAttribute("aria-current", i === aktif ? "true" : "false");
                });
            }

            if (wadahHitung) {
                wadahHitung.textContent =
                    "Foto " + duaAngka(aktif + 1) + " dari " + duaAngka(kartu.length);
            }

            if (wadahKeterangan) {
                const ket = kartu[aktif].querySelector("figcaption");
                wadahKeterangan.textContent = ket ? ket.textContent.trim() : "";
            }

            // Bloknya disembunyikan lewat atribut hidden di Blade dan baru
            // dibuka di sini: tanpa JavaScript, blok berisi garis tegak dan
            // label kosong hanya akan terbaca sebagai sisa yang lupa dibuang.
            if (blokKeterangan) blokKeterangan.hidden = false;
        };

        const ke = (i) => {
            aktif = (i + kartu.length) % kartu.length;
            gambar();
        };

        // Titik navigasi dibuat di sini, bukan di Blade: jumlahnya selalu
        // sama dengan jumlah kartu, dan tanpa JS titik itu tidak berguna.
        if (wadahTitik) {
            kartu.forEach((_, i) => {
                const titik = document.createElement("button");
                titik.type = "button";
                titik.setAttribute("aria-label", "Foto ke-" + (i + 1));
                titik.addEventListener("click", () => {
                    ke(i);
                    hidupkanUlang();
                });
                wadahTitik.appendChild(titik);
            });
        }

        kartu.forEach((el, i) => {
            el.addEventListener("click", () => {
                ke(i);
                hidupkanUlang();
            });
        });

        bagian.querySelector("[data-korsel-maju]")?.addEventListener("click", () => {
            ke(aktif + 1);
            hidupkanUlang();
        });
        bagian.querySelector("[data-korsel-mundur]")?.addEventListener("click", () => {
            ke(aktif - 1);
            hidupkanUlang();
        });

        // Jalan sendiri hanya bila penggunanya tidak meminta gerak dikurangi,
        // dan berhenti saat kursor berada di atasnya — galeri yang berganti
        // tepat ketika seseorang sedang memperhatikan satu foto itu
        // menyebalkan, bukan hidup.
        function hidupkanUlang() {
            if (pewaktu) clearInterval(pewaktu);
            if (prefersReducedMotion || kartu.length < 2) return;
            pewaktu = setInterval(() => {
                if (!jeda && !document.hidden) ke(aktif + 1);
            }, mode === "tumpuk" ? 6500 : 4200);
        }

        panggung.addEventListener("mouseenter", () => (jeda = true));
        panggung.addEventListener("mouseleave", () => (jeda = false));

        gambar();
        hidupkanUlang();
    });
}

document.addEventListener("DOMContentLoaded", () => {
    initReveal();
    initHeroParallax();
    initAnchorOffset();
    initKorsel();
    initPitaHalus();
});

// Livewire mengganti simpul DOM saat menggambar ulang; galeri yang baru
// datang tanpa pengait apa pun. Penanda data-galeri-siap mencegah satu
// panggung dipasangi dua kali.
/* ==========================================================
   PITA BERJALAN — rem halus saat kursor masuk
   ==========================================================
   Aturan CSS-nya memakai animation-play-state: paused, dan itu berhenti
   seketika di tengah gerak: pitanya seperti tersentak. Di sini lajunya
   direm bertahap memakai playbackRate animasi yang SAMA — bukan animasi
   baru — jadi posisinya tidak pernah melompat, hanya kecepatannya yang
   turun. Kalau peramban tidak punya updatePlaybackRate, fungsi ini
   menyerah diam-diam dan rem mendadak CSS tetap bekerja. */
function initPitaHalus() {
    document.querySelectorAll(".marquee-wrap").forEach((wadah) => {
        if (wadah.dataset.pitaSiap === "1") return;

        const pita = wadah.querySelector(".marquee");
        if (!pita || typeof pita.getAnimations !== "function") return;

        const animasi = pita.getAnimations()[0];
        if (!animasi || typeof animasi.updatePlaybackRate !== "function") return;

        wadah.dataset.pitaSiap = "1";
        wadah.classList.add("pita-halus");

        // 0.12, bukan 0: pita yang benar-benar beku terasa seperti halaman
        // yang menggantung. Merayap pelan tetap terbaca sebagai "menunggu".
        const LAMBAT = 0.12;
        let tujuan = 1;
        let laju = 1;
        let bingkai = null;

        const langkah = () => {
            laju += (tujuan - laju) * 0.12;
            if (Math.abs(tujuan - laju) < 0.004) laju = tujuan;
            animasi.updatePlaybackRate(laju);
            bingkai = laju === tujuan ? null : requestAnimationFrame(langkah);
        };

        const menuju = (nilai) => {
            tujuan = nilai;
            if (bingkai === null) bingkai = requestAnimationFrame(langkah);
        };

        // pointerenter/leave, bukan mouseenter: ikut bekerja untuk pena
        // digital, dan tidak ikut terpanggil oleh sentuhan layar.
        wadah.addEventListener("pointerenter", () => menuju(LAMBAT));
        wadah.addEventListener("pointerleave", () => menuju(1));
        wadah.addEventListener("focusin", () => menuju(LAMBAT));
        wadah.addEventListener("focusout", () => menuju(1));
    });
}

document.addEventListener("livewire:navigated", initKorsel);
document.addEventListener("livewire:navigated", initPitaHalus);

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
