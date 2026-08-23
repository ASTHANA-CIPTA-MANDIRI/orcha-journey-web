/**
 * Peta rute di formulir sewa kendaraan.
 *
 * Dimuat HANYA di halaman yang memakainya (lihat pemanggilnya di
 * new-homepage.js): Leaflet beserta gayanya sekitar 45 KB, dan halaman lain
 * tidak perlu membayarnya.
 *
 * Datanya datang dari server lewat peristiwa Livewire 'peta-rute' — koordinat
 * dicari di sana supaya ketentuan pemakaian Nominatim tetap terjaga (satu
 * permintaan per detik, ber-User-Agent, dan hasilnya disimpan).
 */
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const WADAH = ".peta-rute-kanvas";

/** Penanda digambar sendiri: berkas ikon bawaan Leaflet tidak ikut ter-bundle. */
function penanda(warna) {
    return L.divIcon({
        className: "peta-penanda",
        html: `<span class="peta-penanda-bulat" style="--warna:${warna}"></span>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });
}

export function pasangPetaRute() {
    const wadah = document.querySelector(WADAH);
    if (!wadah || wadah.dataset.siap) return;

    const peta = L.map(wadah, {
        zoomControl: false,
        scrollWheelZoom: false, // menggulung halaman tidak boleh tersangkut di peta
        attributionControl: true,
    }).setView([-7.8, 110.37], 10);

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(peta);

    wadah.dataset.siap = "1";

    let lapisan = L.layerGroup().addTo(peta);

    const gambar = (data) => {
        lapisan.clearLayers();

        const titik = [];

        if (data?.jemput) {
            const a = [data.jemput.lat, data.jemput.lon];
            L.marker(a, { icon: penanda("#1d6fa5") }).addTo(lapisan)
                .bindTooltip(data.jemput.nama, { direction: "top", offset: [0, -12] });
            titik.push(a);
        }

        if (data?.tujuan) {
            const b = [data.tujuan.lat, data.tujuan.lon];
            L.marker(b, { icon: penanda("#ffc74e") }).addTo(lapisan)
                .bindTooltip(data.tujuan.nama, { direction: "top", offset: [0, -12] });
            titik.push(b);
        }

        // Garis rute sebenarnya, bila jalannya menyambung.
        if (data?.garis?.length) {
            L.polyline(data.garis, {
                color: "#1ab0e2",
                weight: 5,
                opacity: 0.9,
                lineJoin: "round",
            }).addTo(lapisan);
        } else if (data?.moda === "tak_tersambung" && titik.length === 2) {
            // Tidak tersambung jalan darat: garisnya PUTUS-PUTUS, bukan penuh.
            //
            // Garis penuh di atas peta sungguhan terbaca sebagai rute yang bisa
            // ditempuh, padahal jalannya tidak ada. Putus-putus adalah cara peta
            // pada umumnya mengatakan "penghubungnya bukan jalan" — dan
            // keterangannya ditulis di bawah peta.
            L.polyline(titik, {
                color: "#ffc74e",
                weight: 4,
                opacity: 0.95,
                dashArray: "2 10",
                lineCap: "round",
            }).addTo(lapisan);
        }

        if (titik.length === 1) {
            peta.setView(titik[0], 13, { animate: true });
        } else if (titik.length > 1) {
            peta.flyToBounds(L.latLngBounds(titik).pad(0.25), { duration: 0.9 });
        }
    };

    // Livewire.on, BUKAN window.addEventListener.
    //
    // $this->dispatch() di PHP mengirim peristiwa Livewire, bukan peristiwa DOM
    // di window — itu cara Livewire 2. Menunggunya di window berarti menunggu
    // sesuatu yang tidak pernah datang: petanya tetap di tampilan awal, tanpa
    // penanda, tanpa galat, dan tanpa satu pun tanda bahwa ada yang salah.
    const dengar = () => window.Livewire.on("peta-rute", (data) => {
        gambar(data?.peta ?? data);
    });

    if (window.Livewire) {
        dengar();
    } else {
        document.addEventListener("livewire:init", dengar);
    }

    // Ukuran peta dihitung ulang setelah tampil: Leaflet salah mengukur bila
    // wadahnya sempat tersembunyi atau berubah lebar.
    setTimeout(() => peta.invalidateSize(), 200);
    window.addEventListener("resize", () => peta.invalidateSize());
}

pasangPetaRute();
