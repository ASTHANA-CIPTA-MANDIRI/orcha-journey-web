import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

let items = document.querySelectorAll(".slider .item");
let prevButton = document.getElementById("prev");
let nextButton = document.getElementById("next");
let lastPosition = items.length - 1;
let firstPosition = 0;
let active = 0;

nextButton.onclick = () => {
    active++;
    setSlider();
};
prevButton.onclick = () => {
    active--;
    setSlider();
};

const setSlider = () => {
    const oldActive = document.querySelector(".slider .item.active");
    if (oldActive) oldActive.classList.remove("active");
    items[active].classList.add("active");

    nextButton.classList.remove("d-none");
    prevButton.classList.remove("d-none");
    if (active == lastPosition) nextButton.classList.add("d-none");
    if (active == firstPosition) prevButton.classList.add("d-none");
};

setSlider();

const setDiameter = () => {
    let slider = document.querySelector(".slider");
    let widthSlider = slider.offsetWidth;
    let heightSlider = slider.offsetHeight;

    let diameter = Math.sqrt(
        Math.pow(widthSlider, 2) + Math.pow(heightSlider, 2),
    );

    document.documentElement.style.setProperty("--diameter", diameter + "px");
};

document.addEventListener("resize", setDiameter);

// Mendaftarkan plugin ScrollTrigger ke GSAP
gsap.registerPlugin(ScrollTrigger);
document.addEventListener("DOMContentLoaded", (event) => {
    // 1. Animasi Masuk Hero Section (Berurutan dari bawah ke atas)
    gsap.from(".gsap-hero", {
        y: 50,
        opacity: 0,
        duration: 1,
        stagger: 0.2, // Jarak waktu muncul antar elemen
        ease: "power3.out",
        delay: 0.2,
    });

    // 2. Animasi Scroll (Untuk Bundling, Destinasi, Mobil, Langkah Pesan)
    // Mencari semua bagian dengan class .scroll-section
    const sections = document.querySelectorAll(".scroll-section");

    sections.forEach((section) => {
        // Ambil anak elemen dengan class .gsap-stagger di dalam section ini
        const staggers = section.querySelectorAll(".gsap-stagger");

        if (staggers.length > 0) {
            gsap.from(staggers, {
                scrollTrigger: {
                    trigger: section,
                    start: "top 80%", // Mulai animasi ketika bagian atas section mencapai 80% dari viewport
                    toggleActions: "play none none reverse", // Putar saat terlihat, balikkan saat di-scroll ke atas
                },
                y: 50,
                opacity: 0,
                duration: 0.8,
                stagger: 0.15,
                ease: "power2.out",
            });
        }
    });
});
