/**
 * Bundel JS untuk halaman admin & auth.
 *
 * Sengaja ringan: maryUI/daisyUI dan Livewire sudah membawa skripnya sendiri,
 * jadi di sini hanya perilaku kecil yang dipakai lintas halaman admin.
 */

document.addEventListener("DOMContentLoaded", () => {
    // Tutup drawer sidebar mobile setelah salah satu menu diklik,
    // supaya isi halaman langsung terlihat di layar kecil.
    const drawer = document.getElementById("main-drawer");
    if (!drawer) return;

    document.querySelectorAll("aside a").forEach((link) => {
        link.addEventListener("click", () => {
            drawer.checked = false;
        });
    });
});
