{{-- Perapi isian sambil diketik: uang bertitik dan nomor telepon bertanda
     hubung.

     Server tetap yang memegang nilainya — wire:model.blur merapikan ulang
     dengan aturan yang sama, jadi hasil akhirnya tidak bergantung skrip ini.
     Gunanya cuma supaya pengguna tidak menunggu pindah kolom untuk melihat
     bentuk yang benar.

     Ditulis inline karena berkas Vite tidak ikut ter-deploy, dan tidak lewat
     @push('scripts') karena layout guest tidak menyediakan @stack. --}}
<script>
    (function () {
        if (window.__orchaFormatIsian) return;
        window.__orchaFormatIsian = true;

        const titik = (angka) => angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        // 0812-3456-7890 — dipenggal 4-4-sisanya, cara orang Indonesia membaca
        // nomor ponsel.
        const telepon = (angka) => {
            if (angka.startsWith('62')) angka = '0' + angka.slice(2);
            else if (angka.startsWith('8')) angka = '0' + angka;

            const bagian = [angka.slice(0, 4), angka.slice(4, 8), angka.slice(8, 17)];
            return bagian.filter(Boolean).join('-');
        };

        document.addEventListener('input', (e) => {
            const el = e.target;
            if (!el.classList) return;

            const uang = el.classList.contains('orcha-uang');
            const telp = el.classList.contains('orcha-telp');
            if (!uang && !telp) return;

            const angka = el.value.replace(/\D/g, '');
            const baru = angka === '' ? '' : (uang ? titik(angka) : telepon(angka));
            if (baru === el.value) return;

            // Jaga posisi kursor: hitung digit sebelum kursor, lalu taruh
            // kursor setelah digit ke-sekian pada teks yang baru.
            const sebelum = el.value.slice(0, el.selectionStart).replace(/\D/g, '').length;
            el.value = baru;

            let posisi = 0, terhitung = 0;
            while (posisi < baru.length && terhitung < sebelum) {
                if (/\d/.test(baru[posisi])) terhitung++;
                posisi++;
            }
            el.setSelectionRange(posisi, posisi);
        });
    })();
</script>
