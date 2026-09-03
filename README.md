# Orcha Journey

Situs travel agent **Orcha Journey** (Yogyakarta) — open trip, private trip, study tour, dan
sewa kendaraan (mobil, HiAce, bus pariwisata). Berisi halaman publik untuk calon peserta
sekaligus back office untuk mengelola paket, armada, pemesanan, dan pesan yang masuk.

Bahasa aplikasi ini **Bahasa Indonesia** — nama kolom, rute, dan tulisan di layar memakai
istilah Indonesia. Kode baru sebaiknya mengikuti pola yang sama.

---

## Ringkas: menjalankan di komputer sendiri

```bash
composer setup            # install, .env, key, migrate, npm install, build
php artisan migrate --seed
composer dev              # server + queue + vite sekaligus
```

Kalau ingin menjalankan terpisah:

```bash
php artisan serve         # http://127.0.0.1:8000
npm run dev               # Vite (HMR)
```

Kebutuhan: **PHP 8.2+**, **Composer**, **Node 20+**, dan **MySQL** dengan nama basis data `orcha`.

```env
DB_CONNECTION=mysql
DB_DATABASE=orcha
```

Uji otomatis memakai SQLite di memori, jadi tidak menyentuh basis data asli.

---

## Perintah harian

```bash
composer test                  # seluruh uji (php artisan test)
./vendor/bin/pest --compact    # sama, tampilan ringkas
./vendor/bin/pest tests/Feature/SewaKendaraanTest.php   # satu berkas
./vendor/bin/pint              # rapikan gaya penulisan kode
./vendor/bin/pint --test       # periksa saja, tanpa mengubah
npm run build                  # bangun aset produksi
php artisan orcha:sampul-destinasi          # buat ulang sampul destinasi (SVG)
php artisan orcha:sampul-destinasi --force  # timpa yang sudah ada
```

---

## Bentuk aplikasinya

### Rute langsung ke komponen Volt

Tidak ada controller untuk halaman. `routes/web.php` memetakan URL ke komponen
**Livewire Volt** (berkas tunggal: kelas PHP + Blade dalam satu berkas).

```php
Volt::route('/sewa-kendaraan/{jenis?}', 'public.sewa-kendaraan.index')->name('sewa-kendaraan');
```

`routes/web.php` adalah sumber kebenaran soal halaman mana dilayani berkas mana.

### Satu fitur, satu folder

Berlaku juga di `app/` — model, controller API, resource, dan perkakas
dikelompokkan per fitur, bukan hanya per jenis berkas:

```
app/
├── Models/{PaketWisata,SewaKendaraan,OpenTrip,Etalase,Kontak}/
├── Http/Controllers/Api/
│   ├── ApiController.php            (bagian bersama)
│   ├── Concerns/MenyimpanGambar.php (unggahan → WebP)
│   ├── Umum/{Dashboard,Meta}Controller.php
│   ├── PaketWisata/PaketWisataController.php
│   ├── SewaKendaraan/{Kendaraan,Penyewaan}Controller.php
│   ├── OpenTrip/{Pendaftaran,Pembatalan}Controller.php
│   ├── Kontak/PesanController.php
│   └── Etalase/EtalaseController.php
├── Http/Resources/{PaketWisata,SewaKendaraan,OpenTrip,Kontak}/
├── Support/{PaketWisata,Etalase}/ + GambarWebp.php
└── Console/Commands/{Umum,Etalase}/
```


Berkas Blade dikelompokkan per fitur — publik maupun admin:

```
resources/views/livewire/
├── public/
│   ├── beranda/            index
│   ├── tentang-kami/       index
│   ├── kontak/             index
│   ├── destinasi/          index
│   ├── testimoni/          index
│   ├── paket-wisata/       index, detail
│   ├── sewa-kendaraan/     index, pemesanan
│   ├── open-trip/          pendaftaran, riwayat-kesehatan, pembatalan
│   └── informasi/          faq, syarat-ketentuan, ketentuan-pembayaran,
│                           kebijakan-pengembalian, kebijakan-privasi
└── admin/
    ├── dashboard/  paket-wisata/  sewa-kendaraan/  destinasi/  testimoni/
    └── partner/    pesan/  pendaftaran/  penyewaan/  pembatalan/

resources/views/components/
├── paket-wisata/kartu.blade.php     → <x-paket-wisata.kartu>
├── sewa-kendaraan/kartu.blade.php   → <x-sewa-kendaraan.kartu>
└── page-hero, wajib, halaman-ketentuan   (dipakai lintas fitur)
```

Menambah halaman baru berarti menambah folder fitur, bukan menaruh berkas lepas di
`public/`. Ada uji yang menjaga aturan ini (`SewaKendaraanTest`).

### Aturan bisnis terpusat di `config/orcha.php`

Nomor WhatsApp, slogan, kategori paket, jenis kendaraan, satuan sewa, tahapan status,
daftar kondisi kesehatan, alasan pembatalan, sampai tangga potongan pengembalian — semua
di satu berkas. Termasuk aturan pembayaran:

| Kunci | Arti |
| --- | --- |
| `dp_persen` | uang muka open/private trip (30%) |
| `dp_persen_study_tour` | uang muka study tour (25%) |
| `dp_batas_jam` | batas bayar uang muka setelah konfirmasi (24 jam) |
| `pelunasan_hari_sebelum` | batas pelunasan (**H-5** sebelum berangkat) |

Jangan menulis angka ini langsung di Blade — baca dari config supaya sekali ubah berlaku
di semua halaman.

### Tampilan

Tailwind CSS v4 + daisyUI 5 + **Mary UI** (`<x-mary-*>`, khusus halaman admin).
Ragam desain publik ada di `resources/css/new-homepage.css`:

- token warna `--color-orcha-*` (navy, ocean, sky, foam, sun, abyss)
- huruf aksen kaligrafi `.aksen-orcha`, judul `.title-orcha`
- `.card-orcha`, `.btn-orcha-*`, `.tab-orcha`, `.page-orcha` (penomoran halaman)
- `.pilihan-centang` (kotak centang berbentuk kartu), `.isian-orcha`, `.label-orcha`
- `.marquee` (deret partner yang jalan terus), `.ocean` (animasi ombak)

Pakai ulang kelas-kelas ini, jangan membuat gaya baru untuk hal yang sudah ada.

**Layar muat** berdiri sendiri di `resources/views/partials/orcha-loader.blade.php` —
markup, gaya, dan perilakunya satu berkas dengan awalan `.orc-*`, mengikuti pola loader
Phoenix Digital. Ia sengaja **tidak** bergantung pada GSAP atau `new-homepage.js`: kalau
berkas itu gagal dimuat, layar muat tetap tahu cara menyingkir. Tiga jalan keluarnya —
`load`, jaring pengaman 8 detik, dan aturan `.no-js .orc-loader` untuk saat skrip sama
sekali tidak jalan — dijaga `TampilanTest`; menghapus salah satunya berarti satu berkas
gagal dimuat sama dengan situs tampak mati total.

---

## Fitur

### Publik

| Halaman | URL |
| --- | --- |
| Beranda | `/` |
| Tentang kami | `/tentang-kami` |
| Paket wisata (semua / per kategori) | `/paket-wisata/{kategori?}` |
| Detail paket | `/paket/{uuid}` |
| Sewa kendaraan | `/sewa-kendaraan/{jenis?}` |
| Formulir sewa kendaraan | `/sewa-kendaraan/pesan` |
| Pendaftaran open trip | `/pendaftaran-open-trip` |
| Riwayat kesehatan peserta | `/riwayat-kesehatan` |
| Pengajuan pembatalan | `/pembatalan` |
| Destinasi, testimoni, kontak | `/destinasi`, `/testimoni`, `/kontak` |
| Blog (daftar / artikel) | `/blog`, `/blog/{slug}` |
| Informasi | `/faq`, `/syarat-ketentuan`, `/ketentuan-pembayaran`, `/kebijakan-pengembalian`, `/kebijakan-privasi` |

Halaman publik memakai **UUID**, bukan id angka, supaya jumlah data tidak bisa ditebak
dari URL.

### Formulir

Semua formulir divalidasi **di sisi server**, bukan sekadar `required` di HTML. Kolom
wajib ditandai `<x-wajib />` (bintang merah) dan pesan galatnya berbahasa Indonesia
(`lang/id/validation.php`).

- **Pendaftaran open trip** → kode `OT-ddmm-XXXX`. Tanggal berangkat dan titik jemput
  mengikuti paket, peserta tidak memilih sendiri.
- **Riwayat kesehatan** → cukup memasukkan kode pendaftaran, identitas trip langsung
  tampil. Data kesehatan hanya terlihat di admin, ikut terhapus bila pendaftarannya
  dihapus, dan ada uji yang memastikan data itu tidak pernah muncul di halaman publik.
- **Pembatalan** → alasan, jumlah peserta yang dibatalkan, dan rekening pengembalian.
- **Sewa kendaraan** → kode `SK-ddmm-XXXX`, perkiraan biaya terhitung langsung saat
  pilihan diubah.

### Tarif sewa bertingkat

Satu unit punya beberapa tarif sekaligus, karena harga per jam dan per hari memang beda:

| Kolom | Arti |
| --- | --- |
| `harga_per_jam` | tarif per jam (boleh kosong — bus tidak dilepas per jam) |
| `harga_12_jam` | paket setengah hari |
| `price_per_day` | tarif 24 jam |
| `harga_sopir` | tambahan sopir per hari |
| `transmisi_tersedia` | daftar transmisi unit itu, mis. `["Manual","Matic"]` |

```php
$mobil->tarif('jam');                          // 55000
$mobil->estimasiBiaya('jam', 6, true);         // 6 jam + sopir
$mobil->transmisi_label;                       // "Manual & Matic"
```

`tarif()` mengembalikan `null` untuk satuan yang tidak dijual, dan formulir pemesanan
**menolak di server** satuan atau transmisi yang tidak tersedia pada unit terpilih.

### Keuntungan paket

Paket menyimpan **modal per orang** (`harga_modal`) di samping harga jual, jadi marginnya
cukup selisih keduanya — modal 1.400.000 yang dijual 1.430.000 berarti 30.000 per peserta.
Laporannya ada di dashboard lemon (menu *Keuntungan Paket*), datanya lewat
`/api/v1/keuntungan`.

| Aturan | Alasan |
| --- | --- |
| Hanya pendaftaran **lunas** yang dihitung untung | Pesanan ber-DP masih bisa batal; ia dilaporkan terpisah sebagai potensi |
| Modal kosong ≠ nol | Paket yang modalnya belum diisi dihitung "belum lengkap", bukan untung penuh |
| Harga jual & modal **dibekukan** tiap pendaftaran | Modal berubah sepanjang tahun; laporan bulan lalu tidak boleh ikut berubah |

Modal tidak pernah tampil di halaman publik — kolomnya `$hidden`, dan ada uji yang menjaganya.

### Blog

`/blog` menampilkan daftar artikel — tulisan terbaru sebagai sorotan, tab kategori
berikut jumlahnya, pencarian, dan penomoran halaman. Penyaringnya ikut di alamat
(`/blog?kategori=panduan&cari=bromo`) supaya daftar yang tersaring bisa dikirim ke orang
lain apa adanya.

Alamat artikel memakai **slug**, bukan UUID seperti halaman publik lain. Alasan UUID di
tempat lain adalah supaya jumlah data tidak bisa ditebak; untuk artikel itu memang bukan
rahasia, sementara ongkosnya nyata — mesin pencari dan orang yang menempelkan tautan
sama-sama membaca alamatnya. Slug dibuat sendiri dari judul dan dijaga unik
(`judul-yang-sama-2`).

Tayang ditentukan **dua** kolom sekaligus, dan keduanya diperiksa hanya di
`Artikel::scopeTayang()` — halaman daftar, halaman detail, dan peta situs semuanya lewat
sana:

| Kolom | Arti |
| --- | --- |
| `status` | keputusan penulis: `draf` atau `tayang` |
| `terbit_pada` | kapan artikel boleh mulai terlihat (boleh dijadwalkan ke depan) |

Artikel ber-status `tayang` tetapi `terbit_pada` kosong dianggap **belum** tayang.
Draf dan artikel terjadwal menjawab 404 bila alamatnya ditebak, bukan sekadar hilang dari
daftar.

Isi artikel disimpan sebagai HTML dan dicetak apa adanya, digayakan `.isi-artikel` di
`new-homepage.css` — jadi admin bisa menulis judul bagian, daftar, kutipan, dan tabel
tanpa tahu satu pun nama kelas. **Konsekuensinya:** siapa pun yang bisa menyunting artikel
bisa menyisipkan skrip ke halaman publik, jadi formulir admin nanti wajib dijaga izin.

Kategori artikel ada di `config('orcha.kategori_artikel')`. Kuncinya ikut di alamat, jadi
menggantinya mematikan tautan yang sudah beredar — ganti labelnya saja bila yang dimaksud
cuma penyebutan di layar.

### Admin (cadangan, dimatikan)

`/admin/dashboard` beserta pengelolaan paket wisata, armada, destinasi, testimoni,
partner, pesan masuk, pendaftaran open trip, sewa masuk, dan pembatalan.

> **Halaman ini tidak aktif.** Seluruh `/admin/*`, `/login`, dan `/register` menjawab 404;
> pengelolaan sudah pindah ke dashboard lemon lewat API di bawah. Berkas komponennya
> sengaja dibiarkan sebagai cadangan bila lemon bermasalah — cara menyalakannya ada di
> bagian berikutnya. Panel ini belum punya penjagaan peran (hanya `auth`) dan `/register`
> ikut terbuka saat dinyalakan, jadi nyalakan seperlunya lalu matikan lagi.

---

## API dashboard untuk admin Phoenix

Admin Orcha juga bisa dijalankan dari dashboard Phoenix ("lemon by acm") supaya admin
cukup satu kali login. Basis datanya tetap di sini; Phoenix hanya menggambar tampilannya
lewat API `/api/v1/*`.

```bash
php artisan orcha:kunci-api --tulis   # buat kunci, tulis ke .env
php artisan orcha:drive-izin          # sekali: dapatkan refresh token Google Drive
php artisan orcha:cadangan            # salin basis data, unggah ke Drive
php artisan orcha:pengingat --percobaan  # lihat siapa yang akan dikirimi hari ini
curl -H "X-Orcha-Key: $ORCHA_API_KEY" https://orchajourney.com/api/v1/ping
```

Penjagaannya: kunci rahasia bersama di header `X-Orcha-Key`, daftar IP yang diizinkan
(`ORCHA_API_IP`), dan batas 120 permintaan per menit. Riwayat kesehatan peserta tidak ikut
di daftar biasa — hanya keluar lewat jalur khususnya dan setiap pembukaannya dicatat.

Karena seluruh pengelolaan sudah pindah ke lemon, **halaman login dan `/admin/*` di
aplikasi ini dimatikan** (404) — admin tidak perlu akun kedua. Untuk menyalakannya lagi
sementara: `ORCHA_ADMIN_BAWAAN=true` lalu `php artisan optimize:clear`.

Rincian jalur, bentuk balasan, dan rancangan sisi Phoenix (kelas pemanggil, permission
`akses_orcha`, tombol sidebar) ada di **[docs/API-DASHBOARD.md](docs/API-DASHBOARD.md)**.

---

## Gambar

Foto asli disimpan di `public/images/`, video latar beranda di `public/videos/`.

**Semua unggahan dari dashboard otomatis jadi WebP.** Admin boleh mengunggah JPG
atau PNG; `App\Support\GambarWebp` mengubahnya, mengecilkan sisi terpanjang ke
1920px, dan menyimpannya sebagai `.webp` — biasanya sepertiga sampai separuh
ukuran aslinya. Bila server tidak mendukung WebP, berkasnya disimpan apa adanya
supaya unggahan tidak pernah hilang.

Jalurnya **satu** untuk semua unggahan gambar — API dashboard, panel admin bawaan,
maupun bukti transfer dari pengunjung. Jangan memanggil `->store()` langsung atas
berkas unggahan; `GambarWebp::simpan($berkas, $folder)` sudah mengembalikan jalur
`/storage/...` yang siap disimpan ke kolom, jadi tidak perlu merangkai awalannya
sendiri. Ada uji yang menolak `->store()` mentah di komponen Volt
(`GambarRinganTest`), karena pernah terjadi jalur API sudah WebP sementara panel
admin diam-diam masih menyimpan berkas asli.

### Cara memuat gambar di halaman publik

Halaman publik menampilkan berpuluh foto sekaligus (kartu paket, armada,
destinasi, galeri) dan jumlahnya ikut bertambah setiap admin mengunggah. Aturannya
dua baris:

| Letak gambar | Atribut |
| --- | --- |
| Di luar layar pertama — kartu, galeri, avatar, logo kaki halaman | `loading="lazy" decoding="async"` |
| Di dalam layar pertama — hero, preloader, logo bilah atas | `fetchpriority="high"` (**jangan** lazy) |

Baris kedua sama pentingnya dengan yang pertama. Foto hero hampir selalu jadi
elemen yang diukur LCP; menandainya `lazy` membuat peramban menundanya sampai tata
letak selesai dihitung, sehingga halaman justru terasa **lebih lambat** — kebalikan
dari maksudnya. `x-show` juga bukan pengganti lazy: gambar yang disembunyikan
Alpine tetap ikut diunduh kalau tidak ditandai.

`GambarRinganTest` menolak `<img>` yang tidak menyatakan salah satu dari keduanya,
jadi gambar baru tidak bisa lolos tanpa keputusan yang disengaja.

Foto sampul paket dipakai sebagai latar hero di halaman paket. Pita hero itu
lebar dan pendek, jadi bagian atas & bawah foto pasti terpotong — **ukuran yang
pas 1600 × 600** (paling kecil 1200 × 450), dengan bagian penting di tengah.

Destinasi yang belum punya foto memakai sampul **SVG buatan sendiri** (ilustrasi
pemandangan laut bergaya datar, dibuat `App\Support\SampulDestinasi` dan tetap sama
untuk nama yang sama). Ini sengaja berupa ilustrasi, bukan foto tempat asli — jangan
menyajikannya seolah-olah foto lokasi sungguhan. Begitu foto asli tersedia, isi kolom
gambarnya lewat admin dan sampul otomatis tergantikan.

---

## Uji otomatis

```bash
composer test
```

Berkas uji di `tests/Feature/`:

| Berkas | Isi |
| --- | --- |
| `LandingPageTest` | beranda dan bagian-bagiannya |
| `HalamanPublikTest` | seluruh halaman publik bisa dibuka |
| `HalamanInformasiTest` | FAQ dan halaman ketentuan |
| `DestinasiNusantaraTest` | destinasi seluruh Indonesia + sampul |
| `FormulirPublikTest` | kontak, pendaftaran, riwayat kesehatan |
| `PembatalanTest` | pengajuan pembatalan dan tindak lanjut admin |
| `SewaKendaraanTest` | tarif bertingkat, transmisi, pemesanan sewa, penataan folder |
| `KeuntunganPaketTest` | modal paket, margin per orang, pembekuan di pendaftaran, laporan & API keuntungan |
| `OpenTripBanyuwangiTest` | data trip sungguhan dari flyer |
| `TampilanTest` | hal-hal tampilan yang gampang jebol (aset, ikon, tipografi) |
| `AdminPagesTest` | halaman admin |
| `ApiDashboardTest` | API dashboard: penjagaan kunci, daftar, ubah status |
| `GambarRinganTest` | unggahan jadi WebP di semua jalur, dan gambar publik menyatakan cara muatnya |
| `BlogTest` | artikel tayang/draf/terjadwal, slug unik, penyaring, dan peta situs |

---

## Alur cabang

`need` → `dev` → `main`. Pekerjaan masuk ke `need` dulu; setelah seluruh uji hijau baru
dinaikkan ke `dev`, lalu ke `main`. Penggabungan memakai `--ff-only` supaya riwayatnya
tetap lurus. Uji merah berhenti di `need`.


Seluruh perintah berkala dan baris cronnya ada di [docs/CRON.md](docs/CRON.md).
