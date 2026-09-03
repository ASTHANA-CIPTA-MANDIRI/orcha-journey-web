<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kontak Orcha Journey
    |--------------------------------------------------------------------------
    |
    | Dipakai tombol WhatsApp melayang, tombol "Pesan Sekarang" tiap kartu,
    | dan blok kontak di footer landing page.
    |
    */
    'whatsapp' => env('ORCHA_WHATSAPP', '62895630279695'),
    'email' => env('ORCHA_EMAIL', 'halo@orchajourney.com'),
    'instagram' => env('ORCHA_INSTAGRAM', 'orcha_journey'),
    'alamat' => env('ORCHA_ALAMAT', 'Perumahan GWI, Jl. Durian No. 115, Banguntapan, Bantul, Yogyakarta'),

    /*
    | Badan hukum di balik merek Orcha Journey, dipakai di baris hak cipta.
    |
    | Sengaja TERPISAH dari 'pembayaran.atas_nama' yang mengeja nama yang sama.
    | Yang itu harus persis seperti tertulis di rekening — huruf besar semua —
    | karena tugasnya jadi patokan pelanggan memeriksa penerima transfer.
    | Menyatukan keduanya berarti sekali seseorang merapikan huruf di footer,
    | nama di halaman pembayaran ikut berubah dan patokannya tidak lagi cocok
    | dengan yang tertera di aplikasi bank.
    */
    'perusahaan' => env('ORCHA_PERUSAHAAN', 'PT Asthana Cipta Mandiri'),
    // Ditautkan dari halaman Tentang Kami: badan hukum yang bisa ditelusuri
    // sendiri jauh lebih menenangkan daripada nama yang cuma tertulis.
    'perusahaan_situs' => env('ORCHA_PERUSAHAAN_SITUS', 'https://asthanaciptamandiri.com/'),

    /*
    |--------------------------------------------------------------------------
    | Verifikasi Kepemilikan Situs
    |--------------------------------------------------------------------------
    |
    | Token dari Google Search Console. BUKAN rahasia — token verifikasi memang
    | dirancang untuk tampil di sumber halaman, dan Google membacanya dari
    | sana. Yang dijaga bukan kerahasiaannya melainkan JANGAN SAMPAI HILANG:
    | begitu tokennya lenyap dari halaman, Google mencabut verifikasinya dan
    | seluruh laporan Search Console ikut tertutup.
    |
    | Boleh dikosongkan bila verifikasinya ditempuh lewat catatan TXT di DNS —
    | dua-duanya sah, dan memasang keduanya sekaligus juga tidak masalah.
    |
    */
    'verifikasi_google' => env('ORCHA_VERIFIKASI_GOOGLE', 'RpB6fBxOB5bVDAfIyP89lUjwcsFmEGSn0vJJvg5L1Ts'),

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4
    |--------------------------------------------------------------------------
    |
    | ID pengukuran aliran data web. Seperti token verifikasi, ini BUKAN
    | rahasia — ia memang tampil di sumber halaman.
    |
    | Yang perlu diingat justru di mana ia TIDAK boleh dijalankan; lihat
    | catatan di layout publik.
    |
    */
    'analitik_google' => env('ORCHA_ANALITIK_GOOGLE', 'G-9MHGYZHE0Z'),

    /*
    |--------------------------------------------------------------------------
    | Meta Pixel
    |--------------------------------------------------------------------------
    |
    | ID set data dari Meta Events Manager. Sama seperti ID Google Analytics,
    | ini bukan rahasia.
    |
    | Pixel dijalankan dengan penjagaan yang SAMA dengan Google Analytics —
    | dan untuk Pixel penjagaannya lebih penting lagi. Selain alamat halaman,
    | Meta menyalakan "Otomatis sertakan info halaman dan produk yang lebih
    | detail" secara bawaan: fitur itu MEMBACA ISI HALAMAN dengan AI — judul,
    | ulasan, harga, nama entitas — lalu mengirimkannya ke Meta.
    |
    | Di halaman riwayat kesehatan, yang dibacanya adalah jawaban pertanyaan
    | medis peserta.
    |
    */
    'meta_pixel' => env('ORCHA_META_PIXEL', '25511443571868907'),

    /*
    |--------------------------------------------------------------------------
    | Kategori Paket Wisata
    |--------------------------------------------------------------------------
    |
    | Dipakai admin (dropdown form paket) dan landing page (tab filter paket).
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Kategori Artikel Blog
    |--------------------------------------------------------------------------
    |
    | Dipakai tab penyaring di /blog dan label di tiap kartu. Kuncinya ikut di
    | alamat halaman (/blog?kategori=panduan), jadi mengganti kunci berarti
    | mematikan tautan yang sudah beredar — ganti labelnya saja bila yang
    | dimaksud cuma penyebutan di layar.
    |
    */
    'kategori_artikel' => [
        'panduan' => 'Panduan Perjalanan',
        'destinasi' => 'Cerita Destinasi',
        'tips' => 'Tips & Persiapan',
        'kabar' => 'Kabar Orcha',
    ],

    /*
    | Berapa artikel per halaman di /blog.
    |
    | Sembilan, sama dengan halaman destinasi: kartunya tiga kolom di layar
    | lebar, jadi angka kelipatan tiga tidak meninggalkan baris terakhir yang
    | timpang.
    */
    'artikel_per_halaman' => 9,

    'kategori_paket' => [
        'open_trip' => 'Open Trip',
        'private_trip' => 'Private Trip',
        'study_tour' => 'Study Tour',
    ],

    /*
    |--------------------------------------------------------------------------
    | Jenis Kendaraan Sewa
    |--------------------------------------------------------------------------
    |
    | Dipakai admin (dropdown form mobil) dan landing page (tab filter armada).
    |
    */
    'jenis_kendaraan' => [
        'mobil' => 'Mobil',
        'hiace' => 'HiAce',
        'bus' => 'Bus',
    ],

    /*
    |--------------------------------------------------------------------------
    | Katalog Merek & Model Kendaraan (pasar Indonesia)
    |--------------------------------------------------------------------------
    |
    | Dipakai dropdown "Merek" dan "Nama unit" di formulir armada, supaya admin
    | memilih alih-alih mengetik. Mengetik bebas menghasilkan ejaan yang
    | berbeda-beda untuk unit yang sama — "Avanza", "avanza", "All New Avanza",
    | "Toyota Avanza" — dan penyaringan di halaman publik jadi tidak dapat
    | diandalkan.
    |
    | Isinya ditulis di sini, BUKAN diambil dari API pihak ketiga. Sudah dicari
    | dan diuji: tidak ada API JSON gratis yang memuat katalog merek + model
    | pasar Indonesia. NOPOL API hanya mencari berdasarkan nomor polisi (SOAP,
    | gratis 10 pencarian), dan vPIC milik NHTSA memang gratis tetapi berisi
    | data registrasi Amerika — dari 58 model Toyota di sana, tidak satu pun
    | Avanza, Rush, Calya, Innova, Agya, atau Veloz. Untuk armada sewa di
    | Yogyakarta, daftar itu tidak berguna.
    |
    | Daftarnya sengaja dibatasi pada unit yang lazim disewakan: MPV, city car,
    | SUV, minibus, dan bus. Bukan seluruh model yang pernah dijual di
    | Indonesia — daftar yang terlalu panjang justru memperlambat memilih.
    |
    | Menambah unit di luar daftar ini tetap bisa: formulirnya menyediakan
    | pilihan "isi manual", dan merek/model milik armada sendiri otomatis ikut
    | tercantum lewat App\Support\SewaKendaraan\KatalogKendaraan.
    |
    | Angka kursi di sini adalah KURSI TOTAL termasuk kursi sopir — sesuai
    | spesifikasi pabrik. Isian Kapasitas di formulir menyimpan kursi PENUMPANG,
    | jadi untuk unit yang selalu dengan sopir angka ini dikurangi satu saat
    | mengisi. HiAce Commuter 15 kursi menjadi 14 penumpang.
    |
    | Bentuknya merek => [model => jumlah kursi]. Kursinya disimpan menyatu di
    | sini, BUKAN di daftar terpisah, supaya tidak mungkin ada model yang
    | tercantum di satu daftar tetapi hilang di daftar lainnya. Angkanya dipakai
    | mengisi kapasitas secara otomatis saat modelnya dipilih — tetap bisa diubah
    | admin, karena unit yang sama bisa dipasangi kursi berbeda.
    |
    | null berarti kursinya belum dipastikan: isian kapasitas dibiarkan apa
    | adanya, bukan diisi angka karangan.
    |
    */

    'katalog_kendaraan' => [
        'Toyota' => [
            'Agya' => ['kursi' => 5, 'cc' => 1200, 'varian' => ['E', 'G', 'GR Sport']],
            'Alphard' => ['kursi' => 7, 'cc' => 2500, 'varian' => ['G', 'X']],
            'Avanza' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['E', 'G', 'Veloz']],
            'Calya' => ['kursi' => 7, 'cc' => 1200, 'varian' => ['E', 'G']],
            'Camry' => 5,
            'C-HR' => 5,
            'Corolla Altis' => 5,
            'Corolla Cross' => ['kursi' => 5, 'varian' => ['Standard', 'Hybrid']],
            'Fortuner' => ['kursi' => 7, 'cc' => 2400, 'varian' => ['G', 'VRZ', 'GR Sport']],
            'HiAce Commuter' => ['kursi' => 15, 'cc' => 2500, 'varian' => ['Standar', 'Kursi Kulit']],
            'HiAce Premio' => ['kursi' => 14, 'cc' => 2800, 'varian' => ['Standar', 'Luxury']],
            'Hilux' => ['kursi' => 5, 'varian' => ['Single Cabin', 'Double Cabin', 'Rangga']],
            'Innova Reborn' => ['kursi' => 7, 'cc' => 2400, 'varian' => ['G', 'V', 'Q']],
            'Innova Venturer' => ['kursi' => 7, 'varian' => ['Diesel', 'Bensin']],
            'Innova Zenix' => ['kursi' => 7, 'cc' => 2000, 'varian' => ['G', 'V', 'Q']],
            'Kijang Kapsul' => ['kursi' => 7, 'varian' => ['LGX', 'LSX', 'SSX']],
            'Land Cruiser' => ['kursi' => 7, 'varian' => ['VX-R', 'ZX']],
            'Raize' => ['kursi' => 5, 'varian' => ['G', 'GR Sport']],
            'Rush' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['G', 'S GR Sport']],
            'Sienta' => ['kursi' => 6, 'varian' => ['G', 'V', 'Q']],
            'Vellfire' => ['kursi' => 7, 'cc' => 2500],
            'Veloz' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['Q', 'Q TSS']],
            'Voxy' => ['kursi' => 8, 'varian' => ['Standard']],
            'Yaris' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['E', 'G', 'S GR Sport']],
            'Yaris Cross' => ['kursi' => 5, 'varian' => ['S', 'S HEV']],
        ],
        'Daihatsu' => [
            'Ayla' => ['kursi' => 5, 'cc' => 1200, 'varian' => ['M', 'X', 'R']],
            'Gran Max Blind Van' => ['kursi' => 2, 'cc' => 1500],
            'Gran Max Minibus' => ['kursi' => 8, 'cc' => 1500, 'varian' => ['D', 'Deluxe']],
            'Gran Max Pick Up' => ['kursi' => 2, 'cc' => 1500],
            'Luxio' => ['kursi' => 8, 'cc' => 1500, 'varian' => ['D', 'M', 'X']],
            'Rocky' => ['kursi' => 5, 'cc' => 1200, 'varian' => ['M', 'X', 'R']],
            'Sigra' => ['kursi' => 7, 'cc' => 1200, 'varian' => ['M', 'X', 'R']],
            'Sirion' => ['kursi' => 5, 'cc' => 1300, 'varian' => ['M', 'R']],
            'Terios' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['X', 'R']],
            'Xenia' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['M', 'X', 'R']],
        ],
        'Honda' => [
            'BR-V' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['S', 'E', 'Prestige']],
            'Brio RS' => ['kursi' => 5, 'cc' => 1200],
            'Brio Satya' => ['kursi' => 5, 'cc' => 1200, 'varian' => ['S', 'E']],
            'CR-V' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['Turbo', 'Prestige']],
            'City' => ['kursi' => 5, 'cc' => 1500],
            'City Hatchback' => ['kursi' => 5, 'varian' => ['RS']],
            'Civic' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['RS', 'Type R']],
            'Freed' => ['kursi' => 6, 'cc' => 1500],
            'HR-V' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['S', 'E', 'SE', 'RS']],
            'Jazz' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['S', 'E', 'RS']],
            'Mobilio' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['S', 'E', 'RS']],
            'Odyssey' => ['kursi' => 7, 'varian' => ['Prestige']],
            'WR-V' => ['kursi' => 5, 'cc' => 1200, 'varian' => ['E', 'RS']],
        ],
        'Suzuki' => [
            'APV Arena' => ['kursi' => 8, 'cc' => 1500],
            'APV Luxury' => ['kursi' => 7, 'cc' => 1500],
            'Baleno' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['GL', 'GX']],
            'Carry Pick Up' => ['kursi' => 2, 'cc' => 1500],
            'Ertiga' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['GA', 'GL', 'GX']],
            'Ertiga Hybrid' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['GL', 'GX', 'SS']],
            'Grand Vitara' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['GX', 'GX Hybrid']],
            'Ignis' => ['kursi' => 5, 'cc' => 1200, 'varian' => ['GL', 'GX']],
            'Jimny' => ['kursi' => 4, 'cc' => 1500, 'varian' => ['3 Pintu', '5 Pintu']],
            'Karimun Wagon R' => ['kursi' => 7, 'cc' => 1000, 'varian' => ['GL', 'GS']],
            'S-Presso' => 5,
            'XL7' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['Zeta', 'Beta', 'Alpha']],
        ],
        'Mitsubishi' => [
            'Colt Diesel' => ['kursi' => 3, 'varian' => ['FE 71', 'FE 74']],
            'Fuso Canter' => ['kursi' => 3, 'varian' => ['Standar']],
            'L300 Minibus' => ['kursi' => 9, 'cc' => 2500, 'varian' => ['Standar']],
            'L300 Pick Up' => ['kursi' => 3, 'cc' => 2500],
            'Mirage' => ['kursi' => 5, 'cc' => 1200, 'varian' => ['GLS', 'Exceed']],
            'Outlander PHEV' => 5,
            'Pajero Sport' => ['kursi' => 7, 'cc' => 2400, 'varian' => ['Exceed', 'Dakar', 'Dakar Ultimate']],
            'Triton' => ['kursi' => 5, 'cc' => 2400],
            'Xforce' => ['kursi' => 5, 'cc' => 1500],
            'Xpander' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['GLS', 'Exceed', 'Sport', 'Ultimate']],
            'Xpander Cross' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['Premium', 'Ultimate']],
        ],
        'Nissan' => [
            'Almera' => 5,
            'Evalia' => ['kursi' => 7, 'cc' => 1500],
            'Grand Livina' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['SV', 'XV']],
            'Kicks' => ['kursi' => 5, 'varian' => ['e-Power']],
            'Livina' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['EL', 'VL']],
            'Magnite' => ['kursi' => 5, 'cc' => 1000],
            'Navara' => 5,
            'Serena' => ['kursi' => 8, 'cc' => 2000],
            'Terra' => ['kursi' => 7, 'cc' => 2500],
            'X-Trail' => ['kursi' => 7, 'cc' => 2500, 'varian' => ['2.5 CVT']],
        ],
        'Hyundai' => [
            'Creta' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['Active', 'Trend', 'Style', 'Prime']],
            'H-1' => ['kursi' => 11, 'cc' => 2500, 'varian' => ['XG', 'Royale']],
            'Ioniq 5' => 5,
            'Ioniq 6' => 5,
            'Palisade' => ['kursi' => 7, 'cc' => 2200],
            'Santa Fe' => ['kursi' => 7, 'cc' => 2200],
            'Stargazer' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['Active', 'Trend', 'Style', 'Prime']],
            'Stargazer X' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['Style', 'Prime']],
            'Staria' => ['kursi' => 11, 'cc' => 2200, 'varian' => ['Signature 7', 'Signature 9', 'Trend 11']],
            'Tucson' => 5,
        ],
        'Wuling' => [
            'Air ev' => ['kursi' => 4, 'varian' => ['Lite', 'Standard Range', 'Long Range']],
            'Almaz' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['Exclusive', 'Luxury']],
            'Almaz RS' => ['kursi' => 7, 'cc' => 1500],
            'Alvez' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['CE', 'EX', 'SE']],
            'Binguo EV' => ['kursi' => 4, 'varian' => ['Premium']],
            'Confero' => ['kursi' => 8, 'cc' => 1500],
            'Confero S' => ['kursi' => 8, 'cc' => 1500, 'varian' => ['L', 'C', 'ACT']],
            'Cortez' => ['kursi' => 7, 'cc' => 1500, 'varian' => ['C', 'L', 'S']],
            'Cortez CT' => ['kursi' => 7, 'cc' => 1500],
            'Formo' => ['kursi' => 8, 'cc' => 1200],
        ],
        'Isuzu' => [
            'Elf Microbus' => ['kursi' => 16, 'cc' => 2800, 'varian' => ['NLR Short', 'NMR Long', 'Long 20 Kursi']],
            'Elf NLR' => ['kursi' => 3, 'cc' => 2800],
            'Elf NMR' => ['kursi' => 3, 'cc' => 2800],
            'MU-X' => ['kursi' => 7, 'cc' => 3000, 'varian' => ['Standar']],
            'Panther' => ['kursi' => 7, 'cc' => 2500, 'varian' => ['LS', 'Grand Touring']],
            'Traga' => ['kursi' => 3, 'cc' => 2500],
        ],
        'Kia' => [
            'Carens' => ['kursi' => 7, 'cc' => 1500],
            'Carnival' => ['kursi' => 11, 'cc' => 2200, 'varian' => ['Premiere', 'Signature']],
            'Grand Sedona' => ['kursi' => 11, 'cc' => 2200, 'varian' => ['Ultimate']],
            'Picanto' => ['kursi' => 5, 'cc' => 1200],
            'Rio' => 5,
            'Seltos' => ['kursi' => 5, 'cc' => 1500],
            'Sonet' => ['kursi' => 5, 'cc' => 1500],
            'Sportage' => 5,
        ],
        'Mazda' => [
            'Biante' => ['kursi' => 8, 'cc' => 2000, 'varian' => ['Skyactiv']],
            'CX-3' => 5,
            'CX-30' => 5,
            'CX-5' => ['kursi' => 5, 'cc' => 2500, 'varian' => ['Elite', 'Kuro']],
            'CX-60' => 5,
            'CX-8' => ['kursi' => 7, 'cc' => 2500],
            'Mazda2' => ['kursi' => 5, 'cc' => 1500],
            'Mazda6' => 5,
        ],
        'Chery' => [
            'Omoda 5' => ['kursi' => 5, 'cc' => 1500, 'varian' => ['RZ', 'GT']],
            'Tiggo 5X' => ['kursi' => 5, 'cc' => 1500],
            'Tiggo 7 Pro' => ['kursi' => 5, 'cc' => 1500],
            'Tiggo 8 Pro' => ['kursi' => 7, 'cc' => 1600, 'varian' => ['Premium', 'Max']],
            'Tiggo Cross' => 5,
        ],
        'BYD' => [
            'Atto 3' => 5,
            'Dolphin' => 5,
            'M6' => ['kursi' => 7, 'varian' => ['Standard Range', 'Superior Captain']],
            'Seal' => 5,
            'Sealion 6' => 5,
        ],
        'MG' => [
            'HS' => 5,
            'MG 4 EV' => 5,
            'MG 5 GT' => 5,
            'MG ZS' => 5,
            'VS HEV' => 5,
        ],
        'DFSK' => [
            'Gelora E' => ['kursi' => 7, 'varian' => ['Blind Van', 'Minibus']],
            'Glory 560' => ['kursi' => 7, 'cc' => 1500],
            'Super Cab' => ['kursi' => 2, 'cc' => 1300],
        ],
        'Volkswagen' => [
            'Caravelle' => ['kursi' => 8, 'cc' => 2000, 'varian' => ['Tourer']],
            'Polo' => 5,
            'Tiguan' => 5,
            'Transporter' => ['kursi' => 8, 'cc' => 2000, 'varian' => ['Standar']],
        ],
        'Mercedes-Benz' => [
            'Bus OH 1526' => ['kursi' => 59, 'cc' => 6400, 'varian' => ['Big Bus 59', 'Big Bus 47']],
            'Bus OH 1626' => ['kursi' => 59, 'cc' => 7700, 'varian' => ['Big Bus 59', 'Big Bus 47']],
            'E-Class' => 5,
            'S-Class' => 5,
            'Sprinter' => ['kursi' => 15, 'cc' => 2100, 'varian' => ['Standar', 'VIP']],
            'Vito' => ['kursi' => 8, 'cc' => 2100, 'varian' => ['Tourer']],
        ],
        'BMW' => [
            'Seri 3' => 5,
            'Seri 5' => 5,
            'Seri 7' => 5,
            'X1' => 5,
            'X3' => 5,
            'X5' => 7,
        ],
        'Lexus' => [
            'LM 350' => ['kursi' => 7, 'cc' => 3500],
            'RX 300' => 5,
            'UX 300e' => 5,
        ],
        'Hino' => [
            'Bus RK' => ['kursi' => 59, 'cc' => 7700, 'varian' => ['Big Bus 59', 'Big Bus 47']],
            'Bus RN' => ['kursi' => 59, 'cc' => 7700, 'varian' => ['Big Bus 59']],
            'Dutro' => ['kursi' => 3, 'cc' => 4000, 'varian' => ['Standar']],
            'Ranger' => 3,
        ],
        'Golden Dragon' => [
            'Bus Pariwisata' => ['kursi' => 45, 'cc' => 6700, 'varian' => ['Big Bus 45', 'Medium Bus 31']],
            'Minibus' => ['kursi' => 19, 'cc' => 3800, 'varian' => ['Standar']],
        ],
        'Zhongtong' => [
            'Bus Pariwisata' => ['kursi' => 45, 'cc' => 6700, 'varian' => ['Big Bus 45', 'Medium Bus 31']],
        ],
        'Scania' => [
            'Bus K360' => ['kursi' => 59, 'cc' => 9300, 'varian' => ['Big Bus 59']],
            'Bus K410' => ['kursi' => 59, 'cc' => 12700, 'varian' => ['Big Bus 59', 'Double Decker']],
        ],
        'Ford' => [
            'Everest' => ['kursi' => 7, 'cc' => 2000, 'varian' => ['Trend', 'Titanium']],
            'Ranger' => ['kursi' => 5, 'cc' => 2000, 'varian' => ['XLS', 'Wildtrak']],
        ],
        'Chevrolet' => [
            'Captiva' => ['kursi' => 7, 'cc' => 1500],
            'Spin' => ['kursi' => 7, 'cc' => 1500],
            'Trax' => 5,
        ],
        'Datsun' => [
            'Cross' => ['kursi' => 5, 'cc' => 1200],
            'GO+ Panca' => ['kursi' => 7, 'cc' => 1200],
        ],
        'Peugeot' => [
            '3008' => ['kursi' => 5, 'cc' => 1600],
            '5008' => ['kursi' => 7, 'cc' => 1600],
        ],
        'Renault' => [
            'Kwid' => ['kursi' => 5, 'cc' => 1000],
            'Triber' => ['kursi' => 7, 'cc' => 1000],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas Wilayah Sewa
    |--------------------------------------------------------------------------
    |
    | Menentukan apa yang dihitung "dalam kota" — dan karenanya tarif mana yang
    | berlaku. Wajib ditulis di formulir, karena penyewa memilih sendiri
    | wilayahnya: tanpa batasan yang jelas, penyewa yang hendak ke Borobudur
    | tidak punya cara tahu harus memilih yang mana, dan selisih tarifnya baru
    | dipersoalkan saat menagih.
    |
    | NILAI DI BAWAH INI ADALAH DUGAAN yang lazim untuk agen di Yogyakarta —
    | seluruh DIY dihitung dalam kota. Sesuaikan dengan aturan yang benar-benar
    | kalian pakai; ini satu-satunya tempat yang perlu diubah, dan kalimatnya
    | langsung tampil di halaman pemesanan.
    |
    */

    'wilayah_sewa' => [
        'dalam_kota' => 'Dalam kota mencakup Kota Yogyakarta, Sleman, Bantul, '
            .'Kulon Progo, dan Gunungkidul. Tujuan di luar itu dihitung luar kota.',
        'catatan' => 'Bila ragu, pilih yang paling mendekati — kami mengabari '
            .'lewat WhatsApp bila wilayahnya perlu disesuaikan sebelum tarifnya dikunci.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pos Biaya Operasional Sewa
    |--------------------------------------------------------------------------
    |
    | BBM, tol, dan parkir: masing-masing bisa ditanggung penyewa atau termasuk
    | dengan biayanya sendiri. Dipisah karena keadaannya memang berbeda — ada
    | unit yang BBM-nya ditanggung pemilik tetapi tolnya tidak, dan parkir hampir
    | selalu urusan tersendiri.
    |
    | Daftar ini satu sumber untuk urutan, label, dan nama kolomnya, dipakai
    | formulir admin maupun keterangan di halaman publik. Menuliskan ketiganya
    | berulang di tiap tempat berarti suatu saat ada yang tertinggal saat berubah.
    |
    */

    'pos_operasional' => [
        'bbm' => 'BBM',
        'tol' => 'Tol',
        'parkir' => 'Parkir',
    ],

    /*
    |--------------------------------------------------------------------------
    | Wilayah Destinasi
    |--------------------------------------------------------------------------
    |
    | Dipakai dropdown wilayah di admin dan tab filter di halaman Destinasi.
    |
    */
    'wilayah' => [
        'sumatera' => 'Sumatera',
        'jawa' => 'Jawa',
        'bali' => 'Bali',
        'nusa_tenggara' => 'Nusa Tenggara',
        'kalimantan' => 'Kalimantan',
        'sulawesi' => 'Sulawesi',
        'maluku' => 'Maluku',
        'papua' => 'Papua',
    ],

    /*
    |--------------------------------------------------------------------------
    | Provinsi dan Wilayahnya
    |--------------------------------------------------------------------------
    |
    | Ditulis di sini, BUKAN diambil dari API wilayah gratis saat halaman
    | dibuka. Daftarnya 38 baris dan berubah beberapa tahun sekali — terakhir
    | 2022, saat Papua dimekarkan jadi enam. Menggantungkannya pada layanan
    | pihak ketiga berarti formulir admin ikut mati setiap layanan itu mati atau
    | berganti alamat, demi data yang justru hampir tidak pernah berubah.
    |
    | Pemetaannya ke wilayah yang dipakai penyaring di halaman publik: provinsi
    | yang dipilih menentukan wilayahnya sendiri, jadi admin cukup memilih satu
    | hal. Sebelumnya keduanya diketik terpisah, dan "Jawa Timur" yang tercatat
    | di wilayah "Bali & Nusa Tenggara" tidak akan pernah ketahuan sampai ada
    | pengunjung yang menyaring dan tidak menemukannya.
    |
    */

    'provinsi_wilayah' => [
        'Aceh' => 'sumatera',
        'Sumatera Utara' => 'sumatera',
        'Sumatera Barat' => 'sumatera',
        'Riau' => 'sumatera',
        'Kepulauan Riau' => 'sumatera',
        'Jambi' => 'sumatera',
        'Sumatera Selatan' => 'sumatera',
        'Kepulauan Bangka Belitung' => 'sumatera',
        'Bengkulu' => 'sumatera',
        'Lampung' => 'sumatera',

        'DKI Jakarta' => 'jawa',
        'Jawa Barat' => 'jawa',
        'Banten' => 'jawa',
        'Jawa Tengah' => 'jawa',
        'DI Yogyakarta' => 'jawa',
        'Jawa Timur' => 'jawa',

        'Bali' => 'bali',

        'Nusa Tenggara Barat' => 'nusa_tenggara',
        'Nusa Tenggara Timur' => 'nusa_tenggara',

        'Kalimantan Barat' => 'kalimantan',
        'Kalimantan Tengah' => 'kalimantan',
        'Kalimantan Selatan' => 'kalimantan',
        'Kalimantan Timur' => 'kalimantan',
        'Kalimantan Utara' => 'kalimantan',

        'Sulawesi Utara' => 'sulawesi',
        'Gorontalo' => 'sulawesi',
        'Sulawesi Tengah' => 'sulawesi',
        'Sulawesi Barat' => 'sulawesi',
        'Sulawesi Selatan' => 'sulawesi',
        'Sulawesi Tenggara' => 'sulawesi',

        'Maluku' => 'maluku',
        'Maluku Utara' => 'maluku',

        'Papua' => 'papua',
        'Papua Barat' => 'papua',
        'Papua Barat Daya' => 'papua',
        'Papua Selatan' => 'papua',
        'Papua Tengah' => 'papua',
        'Papua Pegunungan' => 'papua',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pilihan pada Formulir
    |--------------------------------------------------------------------------
    |
    | Keperluan pada form kontak dan status pendaftaran open trip di admin.
    |
    */
    'keperluan_kontak' => [
        'open_trip' => 'Tanya Open Trip',
        'private_trip' => 'Tanya Private Trip',
        'study_tour' => 'Tanya Study Tour',
        'sewa_kendaraan' => 'Sewa Kendaraan',
        'kerja_sama' => 'Kerja Sama / Partner',
        'lainnya' => 'Lainnya',
    ],

    'kondisi_kesehatan' => [
        'hipertensi' => 'Hipertensi / darah tinggi',
        'jantung' => 'Gangguan jantung',
        'asma' => 'Asma',
        'diabetes' => 'Diabetes',
        'maag' => 'Maag / GERD',
        'vertigo' => 'Vertigo',
        'epilepsi' => 'Epilepsi / kejang',
        'asam_urat' => 'Asam urat / rematik',
        'mabuk_perjalanan' => 'Mudah mabuk perjalanan',
        'gangguan_penglihatan' => 'Gangguan penglihatan',
        'gangguan_pendengaran' => 'Gangguan pendengaran',
        'hamil' => 'Sedang hamil',
    ],

    /*
    | Kondisi yang benar-benar mengubah cara tim menangani peserta di lapangan:
    | bisa kambuh mendadak, atau membatasi kegiatan berat dan air. Dipakai
    | memilah mana peserta yang perlu perhatian khusus.
    |
    | Yang TIDAK masuk sini bukan berarti sepele — maag, asam urat, mabuk
    | perjalanan, gangguan penglihatan dan pendengaran tetap dicatat dan tetap
    | ditampilkan, hanya saja tidak menuntut kesiapan khusus sebelum berangkat.
    | Kalau semuanya ditandai merah, penandanya berhenti berarti.
    */
    'kondisi_berisiko' => [
        'jantung',
        'epilepsi',
        'asma',
        'diabetes',
        'hipertensi',
        'vertigo',
        'hamil',
    ],

    'kemampuan_renang' => [
        'tidak_bisa' => 'Tidak bisa berenang',
        'sedikit' => 'Bisa sedikit',
        'lancar' => 'Lancar berenang',
    ],

    /*
    |--------------------------------------------------------------------------
    | Denda Sewa Kendaraan
    |--------------------------------------------------------------------------
    |
    | ANGKANYA USULAN AWAL — sesuaikan dengan aturan Orcha yang sebenarnya.
    |
    | Keterlambatan dihitung per jam dari tarif hariannya, bukan nominal tetap,
    | supaya adil untuk unit murah maupun mahal. Ada tenggang beberapa menit
    | karena macet di jalan bukan hal yang pantas didendakan, dan ada batas
    | atas per hari supaya denda tidak melampaui harga sewa harian itu sendiri.
    |
    */
    'denda_sewa' => [
        'tenggang_menit' => 30,
        'persen_tarif_harian_per_jam' => 10,
        // Lewat batas ini, satu hari keterlambatan dihitung satu hari sewa penuh.
        'maksimal_persen_per_hari' => 100,
    ],

    /*
    | Bagian kendaraan yang diperiksa saat serah terima dan saat kembali.
    | Daftarnya sengaja pendek dan seragam: yang dibandingkan nanti adalah
    | bagian yang sama, bukan kalimat bebas yang tidak pernah bisa dicocokkan.
    */
    'pemeriksaan_kendaraan' => [
        'bodi_depan' => 'Bodi depan & bemper',
        'bodi_belakang' => 'Bodi belakang & bemper',
        'bodi_kanan' => 'Bodi samping kanan',
        'bodi_kiri' => 'Bodi samping kiri',
        'kaca' => 'Kaca & spion',
        'lampu' => 'Lampu depan, belakang, sein',
        'ban' => 'Ban & pelek (termasuk ban serep)',
        'interior' => 'Interior & jok',
        'ac' => 'AC & kelistrikan',
        'mesin' => 'Mesin & rem',
        'kelengkapan' => 'Dongkrak, kunci roda, segitiga, P3K',
        'surat' => 'STNK & buku servis',
    ],

    'kondisi_pemeriksaan' => [
        'baik' => 'Baik',
        'lecet' => 'Lecet / minor',
        'rusak' => 'Rusak',
        'hilang' => 'Hilang',
    ],

    /*
    | Perkiraan biaya perbaikan per bagian, dipakai MENGUSULKAN denda kerusakan
    | supaya admin tidak menaksir dari nol setiap kali. Angkanya usulan, bukan
    | tagihan: nota bengkel yang sebenarnya selalu menang, dan admin bebas
    | mengubahnya.
    |
    | ANGKA DI BAWAH INI PERKIRAAN AWAL — sesuaikan dengan harga bengkel
    | langganan Orcha. Yang penting bentuknya: tiap bagian punya tarif untuk
    | lecet, rusak, dan hilang, jadi satu ceklis langsung jadi satu angka.
    */
    'biaya_kerusakan' => [
        'bodi_depan' => ['lecet' => 250000, 'rusak' => 1500000, 'hilang' => 3000000],
        'bodi_belakang' => ['lecet' => 250000, 'rusak' => 1500000, 'hilang' => 3000000],
        'bodi_kanan' => ['lecet' => 200000, 'rusak' => 1200000, 'hilang' => 2500000],
        'bodi_kiri' => ['lecet' => 200000, 'rusak' => 1200000, 'hilang' => 2500000],
        'kaca' => ['lecet' => 150000, 'rusak' => 900000, 'hilang' => 1200000],
        'lampu' => ['lecet' => 100000, 'rusak' => 600000, 'hilang' => 800000],
        'ban' => ['lecet' => 150000, 'rusak' => 700000, 'hilang' => 1000000],
        'interior' => ['lecet' => 100000, 'rusak' => 500000, 'hilang' => 750000],
        'ac' => ['lecet' => 150000, 'rusak' => 900000, 'hilang' => 1500000],
        'mesin' => ['lecet' => 300000, 'rusak' => 2500000, 'hilang' => 5000000],
        'kelengkapan' => ['lecet' => 50000, 'rusak' => 200000, 'hilang' => 350000],
        'surat' => ['lecet' => 0, 'rusak' => 250000, 'hilang' => 1000000],
    ],

    'satuan_sewa' => [
        'jam' => ['label' => 'Per jam', 'satuan' => 'jam', 'maks' => 23],
        '12jam' => ['label' => 'Paket 12 jam', 'satuan' => '× 12 jam', 'maks' => 2],
        'hari' => ['label' => 'Per hari (24 jam)', 'satuan' => 'hari', 'maks' => 30],
    ],

    'status_penyewaan' => [
        'baru' => 'Baru',
        'dikonfirmasi' => 'Dikonfirmasi',
        'dp_masuk' => 'DP Masuk',
        'berjalan' => 'Sedang Berjalan',
        'selesai' => 'Selesai',
        'batal' => 'Batal',
    ],

    'alasan_pembatalan' => [
        'jadwal_bentrok' => 'Jadwal bentrok',
        'kondisi_kesehatan' => 'Kondisi kesehatan',
        'kendala_biaya' => 'Kendala biaya',
        'keluarga' => 'Urusan keluarga',
        'cuaca' => 'Cuaca / kondisi lokasi',
        'lainnya' => 'Lainnya',
    ],

    /*
    | Jenis pembayaran yang bisa dikonfirmasi pelanggan lewat formulir.
    */
    'jenis_pembayaran' => [
        'dp' => 'Uang Muka (DP)',
        'pelunasan' => 'Pelunasan',
        'sewa' => 'Sewa Kendaraan',
        'lainnya' => 'Lainnya',
    ],

    'status_pembayaran' => [
        'menunggu' => 'Menunggu Dicek',
        'diterima' => 'Diterima',
        'ditolak' => 'Ditolak',
    ],

    'status_pembatalan' => [
        'diajukan' => 'Diajukan',
        'diproses' => 'Sedang Diproses',
        'disetujui' => 'Disetujui',
        'dana_dikirim' => 'Dana Dikirim',
        'ditolak' => 'Ditolak',
    ],

    /*
    | Keterangan penayangan paket. Dihitung dari status + jadwal, tidak
    | disimpan — lihat TravelPackage::getStatusTayangAttribute().
    */
    'status_tayang' => [
        'tayang' => 'Tayang',
        'terjadwal' => 'Terjadwal',
        'berakhir' => 'Berakhir',
        'draf' => 'Draf',
        'arsip' => 'Arsip',
    ],

    // Yang bisa dipilih admin. 'terjadwal' dan 'berakhir' tidak masuk sini
    // karena keduanya hasil hitungan, bukan pilihan.
    'status_paket' => [
        'terbit' => 'Terbit',
        'draf' => 'Draf',
        'arsip' => 'Arsip',
    ],

    'status_pendaftaran' => [
        'baru' => 'Baru',
        'dihubungi' => 'Sudah Dihubungi',
        'dp_masuk' => 'DP Masuk',
        'lunas' => 'Lunas',
        'batal' => 'Batal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Slogan
    |--------------------------------------------------------------------------
    */
    'slogan' => 'Teman Setia Perjalanan Anda!',

    /*
    |--------------------------------------------------------------------------
    | Berapa lama data kesehatan peserta disimpan
    |--------------------------------------------------------------------------
    |
    | Formulir kesehatan menyimpan riwayat penyakit, riwayat operasi, alergi,
    | obat rutin, dan golongan darah. Itu data pribadi bersifat spesifik: bocor
    | sekali, akibatnya melekat pada orangnya seumur hidup, dan tidak ada cara
    | menariknya kembali.
    |
    | Sebelum ada setelan ini, tidak ada batasnya sama sekali — data medis
    | peserta yang sudah pulang berbulan-bulan lalu masih tersimpan utuh dan
    | akan terus tersimpan.
    |
    | Dihitung dari TANGGAL KEBERANGKATAN, bukan tanggal pengisian: gunanya
    | data ini di hari perjalanan, dan yang menentukan kapan ia tidak
    | diperlukan lagi adalah kapan perjalanannya selesai.
    |
    | 90 hari dipilih supaya masih menutupi klaim asuransi atau pertanyaan
    | susulan yang muncul setelah trip, lalu berhenti. Kalau aturan Anda
    | berbeda, ubah angkanya di sini — seluruh sistem membacanya dari satu
    | tempat ini.
    */
    /*
    |--------------------------------------------------------------------------
    | Promo rombongan
    |--------------------------------------------------------------------------
    |
    | Berlaku DI ATAS harga paket yang sudah berlaku — termasuk harga early
    | bird. Jadi kalau paket sudah turun dari 1.700.000 ke 1.430.000, promo
    | rombongan dihitung dari 1.430.000, bukan dari harga normalnya.
    |
    | TINGKAT TERBAIK YANG MENANG, TIDAK BERTUMPUK. Rombongan 12 orang mendapat
    | tingkat 10, bukan tingkat 5 ditambah tingkat 10. Bertumpuk terdengar lebih
    | murah hati, tetapi angkanya jadi sulit dijelaskan di WhatsApp dan lebih
    | sulit lagi diperiksa saat ada yang protes.
    |
    | Dua bentuk keuntungan, dan bedanya disengaja:
    |
    |   potongan_persen — potongan biasa, dipakai untuk tingkat menengah
    |   gratis_orang    — sejumlah orang tidak dibayar
    |
    | "Gratis 1 dari 10" secara hitungan sama dengan potongan 10%, tetapi
    | DISEBUT sebagai gratis satu orang — itu yang dipahami dan diceritakan
    | ulang orang ke temannya. Karena itu keduanya dihitung berbeda, bukan
    | disamakan jadi persen.
    |
    | 'min' MENGHITUNG SELURUH PESERTA, PEMESANNYA IKUT. Tingkat 6 berarti si
    | pemesan mengajak lima rekan; tingkat 11 berarti ia mengajak sepuluh, dan
    | yang kesebelas tidak dibayar.
    |
    | Yang membuat ini mudah salah: angka yang diucapkan orang adalah jumlah
    | REKAN ("ajak 5 dapat diskon"), sedangkan angka yang dibandingkan sistem
    | adalah jumlah peserta pendaftaran. Kalimat promonya dirakit sendiri dari
    | 'min' dikurangi satu, jadi cukup satu tempat ini yang perlu benar.
    |
    | ANGKA DI BAWAH INI KEPUTUSAN BISNIS, bukan teknis — ubah di sini bila
    | berbeda. Yang berlaku sehari-hari datang dari tabel tbl_promo_rombongan
    | yang diatur admin; daftar ini cadangan saat tabelnya masih kosong.
    */
    'promo_rombongan' => [
        [
            'min' => 6,
            'potongan_persen' => 5,
            'label' => 'Ajak 5 rekan — potongan 5% untuk pemesan',
            'ajakan' => 'Ajak 5 rekan, Anda dapat potongan 5%.',
        ],
        [
            'min' => 11,
            'gratis_orang' => 1,
            'label' => 'Ajak 10 rekan — gratis 1 orang',
            'ajakan' => 'Ajak 10 rekan, 1 orang gratis.',
        ],
    ],

    'kesehatan' => [
        'simpan_hari' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kode rujukan
    |--------------------------------------------------------------------------
    |
    | Alumni trip yang membawa pendaftaran baru. Bedanya dengan promo rombongan
    | tegas: promo rombongan berlaku dalam SATU pendaftaran — ramai orang
    | berangkat bersama di tanggal yang sama. Kode rujukan berlaku LINTAS
    | pendaftaran — orang yang sudah pulang mengajak temannya ikut trip
    | berikutnya, di tanggal yang berbeda.
    |
    | DUA ANGKA, DAN BESARNYA BOLEH BERBEDA:
    |
    |   potongan — untuk yang MEMAKAI kodenya, dipotong dari tagihannya
    |   imbalan  — untuk yang MEMILIKI kodenya, dibayarkan terpisah
    |
    | Menyamakannya terlihat rapi tetapi salah arah. Potongan harus cukup
    | terasa supaya orang mau mengetik kodenya alih-alih melewatinya; imbalan
    | harus sepadan dengan usaha membujuk seseorang berangkat, dan itu usaha
    | yang jauh lebih besar.
    |
    | Keduanya per PENDAFTARAN, bukan per orang. Rujukan yang dihitung per
    | kepala membuat satu kode yang dipakai rombongan dua puluh orang menagih
    | imbalan dua puluh kali — dan itu angka yang tidak pernah dimaksudkan
    | siapa pun saat menetapkannya.
    |
    | ANGKA DI BAWAH INI KEPUTUSAN BISNIS. Diisi sebagai titik awal yang masuk
    | akal terhadap harga trip sekarang; ubah di sini bila berbeda.
    */
    'rujukan' => [
        'aktif' => env('ORCHA_RUJUKAN_AKTIF', true),
        'potongan' => 50000,
        'imbalan' => 75000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cadangan basis data ke Google Drive
    |--------------------------------------------------------------------------
    |
    | Dumpnya ditulis murni PHP — hosting mematikan proc_open, jadi mysqldump
    | dan seluruh paket cadangan yang bersandar padanya tidak bisa dipakai.
    |
    | Unggahannya lewat OAuth ATAS NAMA PEMILIK AKUN, bukan service account.
    | Service account tidak punya kuota penyimpanan sendiri: cara yang paling
    | sering ditulis orang — buat service account, bagikan foldernya — gagal
    | dengan "storage quota exceeded" pada unggahan pertama kecuali tujuannya
    | Shared Drive milik Google Workspace.
    |
    | Penyiapan sekali jalan:
    |
    |   1. console.cloud.google.com → buat proyek → aktifkan Google Drive API.
    |   2. OAuth consent screen → External → tambahkan akun Gmail Anda sendiri
    |      sebagai Test user. (Tanpa langkah ini, refresh token-nya kedaluwarsa
    |      dalam tujuh hari dan cadangannya berhenti diam-diam.)
    |   3. Credentials → OAuth client ID → Desktop app. Catat client id dan
    |      secret-nya.
    |   4. Jalankan: php artisan orcha:drive-izin — ia mencetak tautan
    |      persetujuan dan menukar kodenya jadi refresh token.
    |   5. Buat folder di Drive, salin id-nya dari alamat peramban
    |      (drive.google.com/drive/folders/<INI>), taruh di ORCHA_DRIVE_FOLDER.
    |
    | Cadangan lokal SELALU dibuat lebih dulu, apa pun keadaan Drive-nya. Kalau
    | tidak, hari saat Google sedang bermasalah adalah hari kita tidak punya
    | cadangan sama sekali — dan itu tidak akan diketahui siapa pun sampai
    | dibutuhkan.
    */
    'drive' => [
        'client_id' => env('ORCHA_DRIVE_CLIENT_ID'),
        'client_secret' => env('ORCHA_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('ORCHA_DRIVE_REFRESH_TOKEN'),
        'folder_id' => env('ORCHA_DRIVE_FOLDER'),
    ],

    'cadangan' => [
        /*
         | Berapa cadangan disimpan, di kedua tempat.
         |
         | Empat belas hari, bukan tiga: kerusakan data yang paling mahal
         | bukan yang langsung terlihat — satu kolom yang salah terhapus baru
         | disadari seminggu kemudian, dan pada saat itu cadangan tiga hari
         | sudah ikut memuat kerusakannya.
         */
        'sisakan' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengingat
    |--------------------------------------------------------------------------
    |
    | Dua surat yang selama ini tidak pernah dikirim siapa pun.
    |
    | 'pelunasan_hari_sebelum_batas' — berapa hari SEBELUM batas pelunasan
    | pengingatnya berangkat. Bukan tepat di hari batasnya: orang perlu waktu
    | untuk ke bank, dan transfer akhir pekan baru masuk Senin. Tiga hari
    | memberi ruang tanpa terlalu jauh sehingga terlupakan lagi.
    |
    | 'briefing_hari_sebelum' — berapa hari sebelum berangkat briefingnya
    | dikirim. Satu hari, dan itu disengaja: dikirim seminggu sebelumnya, isinya
    | dibaca lalu dilupakan justru pada malam yang menentukan.
    |
    | 'bawaan' — daftar yang masuk ke surat briefing. Berlaku untuk semua trip;
    | yang khas satu paket ditulis di keterangan paketnya sendiri.
    */
    'pengingat' => [
        'pelunasan_hari_sebelum_batas' => 3,
        'briefing_hari_sebelum' => 1,

        'bawaan' => [
            'Kartu identitas (KTP/SIM/kartu pelajar)',
            'Obat pribadi bila ada',
            'Jaket atau pakaian hangat',
            'Alas kaki yang nyaman untuk berjalan',
            'Uang tunai secukupnya',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ketentuan Pembayaran & DP
    |--------------------------------------------------------------------------
    |
    | ANGKA DI BAWAH INI ADALAH USULAN AWAL — sesuaikan dengan aturan bisnis
    | Orcha Journey yang sebenarnya. Semua halaman ketentuan membacanya dari
    | sini, jadi cukup ubah di berkas ini tanpa menyentuh tampilan.
    |
    */
    'pembayaran' => [
        'dp_persen' => 30,
        'dp_persen_study_tour' => 25,
        // Uang muka mengunci kursi, dibayar segera setelah konfirmasi.
        'dp_batas_jam' => 24,

        /*
         | Berapa jam sebelum kursinya DILEPAS sistem — sengaja lebih longgar
         | daripada dp_batas_jam di atas.
         |
         | Yang 24 jam itu janji kepada pelanggan; yang ini tindakan sistem.
         | Keduanya memang tidak sama, dan bedanya disengaja: orang mentransfer
         | di akhir pekan, bank sedang gangguan, bukti tertahan di ponsel yang
         | lowbat. Melepas kursi tepat di jam ke-24 membuang pemesanan yang
         | sebenarnya masih hidup, dan yang hilang bukan cuma satu kursi
         | melainkan orang yang sudah niat berangkat.
         |
         | Tiga hari cukup panjang untuk semua itu, dan masih cukup pendek
         | supaya kursinya tidak mati sebulan seperti sebelum ini ada.
         */
        'dp_lepas_jam' => 72,
        // Pelunasan paling lambat H-5 sebelum keberangkatan.
        'pelunasan_hari_sebelum' => 5,
        'pelunasan_sewa_kendaraan' => 'saat unit diserahkan',
        /*
         | SATU-SATUNYA nama penerima yang sah. Dipajang di semua halaman yang
         | menyinggung pembayaran supaya pelanggan punya patokan memeriksa
         | sebelum mentransfer — penipu biasanya memakai rekening pribadi.
         */
        'atas_nama' => 'PT ASTHANA CIPTA MANDIRI',

        /*
         | Nomor rekening sengaja TIDAK dipajang di website. Nomor yang
         | terpampang gampang disalin penipu untuk membuat halaman tiruan yang
         | tampak meyakinkan; yang dicek pelanggan cukup NAMA penerimanya.
         | Nomornya dikirim admin lewat WhatsApp setelah pesanan dipastikan.
         */
        'rekening' => [
            // ['bank' => 'BCA', 'nomor' => '0000000000'],
        ],
        // Hanya transfer bank. QRIS dan tunai sempat tercantum padahal tidak
        // dilayani — dan cara bayar yang dijanjikan di situs tapi ditolak saat
        // pemesanan justru bikin pelanggan ragu.
        'metode' => ['Transfer bank'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kebijakan Pembatalan & Pengembalian Dana
    |--------------------------------------------------------------------------
    |
    | Dipakai tabel di halaman Kebijakan Pengembalian. Sesuaikan bila perlu.
    |
    */
    'pengembalian' => [
        'proses_hari_kerja' => 14,
        /*
         * Potongan dihitung dari TOTAL BIAYA, bukan dari uang muka.
         *
         * Sebelumnya semua dihitung dari uang muka, dan sisa pembayaran di
         * luar itu selalu dikembalikan penuh. Akibatnya pelanggan yang
         * melunasi di awal lalu membatalkan H-3 hanya kehilangan uang mukanya
         * — 30% — padahal pada hari itu biaya kami sudah keluar hampir
         * seluruhnya: kursi, kamar, tiket masuk, tim. Selisihnya kami yang
         * menanggung, dan justru pelanggan yang membayar paling awal yang
         * paling terlindungi. Itu terbalik.
         *
         * Dihitung dari total, waktu pembatalan yang menentukan potongannya —
         * bukan seberapa banyak yang kebetulan sudah dibayar.
         */
        'tangga' => [
            ['batas' => 'Lebih dari 30 hari sebelum keberangkatan', 'kembali' => 'Seluruh pembayaran', 'potongan' => 'Tanpa potongan', 'persen' => 0, 'jam_min' => 720],
            ['batas' => '15 – 30 hari sebelum keberangkatan', 'kembali' => 'Pembayaran dikurangi potongan', 'potongan' => '25% dari total biaya', 'persen' => 25, 'jam_min' => 360],
            ['batas' => '7 – 14 hari sebelum keberangkatan', 'kembali' => 'Pembayaran dikurangi potongan', 'potongan' => '50% dari total biaya', 'persen' => 50, 'jam_min' => 168],
            ['batas' => 'Kurang dari 7 hari sebelum keberangkatan', 'kembali' => 'Tidak ada', 'potongan' => '100% dari total biaya', 'persen' => 100, 'jam_min' => 0],
        ],

        /*
         * Sewa kendaraan punya tangganya sendiri, dan sengaja lebih longgar.
         *
         * Kursi open trip yang batal H-5 hampir mustahil dijual lagi, dan
         * biayanya sudah keluar: hotel, kursi bus, guide — semuanya per kepala
         * dan dipesan jauh hari. Unit kendaraan tidak begitu; mobil yang batal
         * hari ini masih bisa disewakan besok, kadang hari itu juga. Menahan
         * seluruh uang muka untuk pembatalan H-5 sulit dipertanggungjawabkan
         * kalau unitnya ternyata tetap tersewa.
         *
         * Satuannya pun berbeda: penyewa berpikir dalam hari dan jam menjelang
         * pengambilan, bukan dalam minggu. Tidak ada yang memesan mobil 30 hari
         * di muka lalu menghitung "H-30".
         */
        'tangga_sewa' => [
            ['batas' => 'Lebih dari 7 hari sebelum mulai sewa', 'kembali' => 'Seluruh pembayaran', 'potongan' => 'Tanpa potongan', 'persen' => 0, 'jam_min' => 168],
            ['batas' => '3 – 7 hari sebelum mulai sewa', 'kembali' => 'Pembayaran dikurangi potongan', 'potongan' => '25% dari total biaya', 'persen' => 25, 'jam_min' => 72],
            ['batas' => '24 jam – 3 hari sebelum mulai sewa', 'kembali' => 'Pembayaran dikurangi potongan', 'potongan' => '50% dari total biaya', 'persen' => 50, 'jam_min' => 24],
            ['batas' => 'Kurang dari 24 jam sebelum mulai sewa', 'kembali' => 'Tidak ada', 'potongan' => '100% dari total biaya', 'persen' => 100, 'jam_min' => 0],
            ['batas' => 'Tidak datang tanpa kabar', 'kembali' => 'Tidak ada', 'potongan' => '100% dari total biaya', 'persen' => 100, 'jam_min' => null],
        ],

        /*
         * Dua aturan yang mengikat kedua tangga di atas.
         *
         * Yang kedua penting untuk dua arah sekaligus: pelanggan yang baru
         * membayar uang muka tidak tiba-tiba berutang saat membatalkan di
         * menit akhir, dan kami tidak perlu menagih orang yang sudah batal —
         * pekerjaan yang hampir tidak pernah sepadan hasilnya.
         */
        'aturan_dasar' => [
            'Potongan dihitung dari total biaya pemesanan, bukan dari uang muka. '
                .'Melunasi lebih awal tidak menghapus potongan; yang menentukan besarnya '
                .'adalah kapan pembatalan diajukan.',
            'Potongan tidak pernah melebihi jumlah yang sudah Anda bayarkan. '
                .'Bila potongannya lebih besar dari pembayaran Anda, sisanya tidak ditagihkan.',
        ],

        /*
         * Tiga hal yang khas sewa dan tidak muat di dalam tabel.
         *
         * Yang ketiga paling sering terlupakan di kebijakan mana pun, dan
         * paling merusak kepercayaan bila tidak tertulis.
         */
        'catatan_sewa' => [
            'Sewa dengan sopir yang dibatalkan kurang dari 24 jam sebelum pengambilan dikenakan '
                .'biaya sopir satu hari sesuai tarif yang berlaku, di luar potongan pada tabel.',
            'Unit yang sudah dilunasi tetap dikenakan potongan sesuai tabel. Melunasi lebih '
                .'awal mengunci jadwalnya, dan jadwal yang terkunci itulah yang hilang saat '
                .'pembatalan mendadak.',
            'Bila pembatalan datang dari pihak kami (unit rusak dan tidak ada penggantinya), '
                .'seluruh pembayaran dikembalikan penuh tanpa potongan apa pun.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Dashboard
    |--------------------------------------------------------------------------
    |
    | Dipakai dashboard admin Phoenix ("lemon by acm") supaya admin cukup satu
    | kali login. Phoenix memanggil API ini dari server ke server, jadi kuncinya
    | tidak pernah sampai ke browser.
    |
    | - kunci        : rahasia bersama. Wajib sama persis di kedua aplikasi.
    | - ip_diizinkan : batasi ke IP server Phoenix. Kosong = tidak dibatasi.
    | - per_halaman  : jumlah baris bawaan tiap permintaan daftar.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Fasilitas yang Sering Dipakai
    |--------------------------------------------------------------------------
    |
    | Dipakai formulir paket di dashboard supaya admin tinggal mencentang,
    | bukan mengetik ulang tiap kali. Bukan daftar tertutup — paket tetap boleh
    | punya fasilitas di luar daftar ini.
    |
    */
    'fasilitas_umum' => [
        'Transportasi AC',
        'BBM & Tol',
        'Sopir berpengalaman',
        'Homestay / penginapan',
        'Makan sesuai itinerary',
        'Tiket masuk wisata',
        'Dokumentasi foto',
        'Tour leader',
        'Asuransi perjalanan',
        'Air mineral',
        'Perlengkapan snorkeling',
        'P3K',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Admin Bawaan Orcha
    |--------------------------------------------------------------------------
    |
    | Pengelolaan Orcha sudah pindah ke dashboard lemon supaya admin cukup satu
    | akun. Karena itu halaman login, daftar, dan /admin/* di aplikasi ini
    | DIMATIKAN secara bawaan — tidak ada lagi akun kedua yang perlu diingat.
    |
    | Berkasnya sengaja tidak dihapus. Bila suatu saat lemon bermasalah dan
    | Orcha perlu diurus sendiri untuk sementara, cukup setel:
    |
    |     ORCHA_ADMIN_BAWAAN=true
    |
    | lalu jalankan `php artisan optimize:clear`.
    |
    */
    'admin_bawaan' => (bool) env('ORCHA_ADMIN_BAWAAN', false),

    /*
    |--------------------------------------------------------------------------
    | Pemberitahuan Email
    |--------------------------------------------------------------------------
    |
    | Setiap formulir yang berhasil dikirim pelanggan (pendaftaran, bukti
    | pembayaran, riwayat kesehatan, pembatalan) ikut dikirimkan ke alamat ini
    | supaya ada salinan di luar aplikasi.
    |
    | Kosong berarti pemberitahuan dimatikan — dipakai saat pengembangan dan
    | pengujian supaya tidak ada surat yang benar-benar terkirim.
    |
    */
    'email_pemberitahuan' => env('ORCHA_EMAIL_PEMBERITAHUAN'),

    /*
    | Salinan untuk pelanggan.
    |
    | Selain kotak kantor, pengisi formulir ikut menerima suratnya sendiri —
    | berisi kode, rincian, dan kwitansi PDF. Tanpa ini satu-satunya bukti
    | pelanggan hanyalah tulisan di layar yang hilang begitu halaman ditutup,
    | dan kode pendaftaran yang telanjur ditutup tidak bisa dipulihkan.
    |
    | Bisa dimatikan tanpa mengubah kode bila suatu saat perlu.
    */
    'email_salinan_pelanggan' => (bool) env('ORCHA_EMAIL_SALINAN_PELANGGAN', true),

    /*
    |--------------------------------------------------------------------------
    | Katalog Nama Destinasi
    |--------------------------------------------------------------------------
    |
    | Tempat-tempat yang paling sering diminta, beserta provinsi DAN daerahnya.
    | Dipakai sebagai PILIHAN saat admin menambah destinasi: sekali pilih, empat
    | isian terisi sekaligus — nama, daerah, provinsi, dan wilayah (dua terakhir
    | mengikuti, karena provinsi menentukan wilayah).
    |
    | Ini daftar awal, bukan daftar tertutup: nama yang belum ada bisa ditulis
    | admin dan langsung masuk daftar. Provinsinya pun boleh dibetulkan lewat
    | isian provinsi — daftar ini hanya mengisi, tidak mengunci.
    |
    | Beberapa tempat berada di perbatasan dan penyebutannya bisa berbeda-beda
    | (Prambanan sering disebut Yogyakarta walau candinya di perbatasan Klaten).
    | Yang dipakai di sini penyebutan yang paling lazim di penawaran wisata.
    |
    */

    'katalog_destinasi' => [

        'Banyuwangi' => ['provinsi' => 'Jawa Timur', 'daerah' => 'Banyuwangi'],
        'Kawah Ijen' => ['provinsi' => 'Jawa Timur', 'daerah' => 'Banyuwangi'],
        'Bromo Tengger Semeru' => ['provinsi' => 'Jawa Timur', 'daerah' => 'Probolinggo'],
        'Tumpak Sewu' => ['provinsi' => 'Jawa Timur', 'daerah' => 'Lumajang'],
        'Madakaripura' => ['provinsi' => 'Jawa Timur', 'daerah' => 'Probolinggo'],
        'Malang' => ['provinsi' => 'Jawa Timur', 'daerah' => 'Malang'],
        'Batu' => ['provinsi' => 'Jawa Timur', 'daerah' => 'Kota Batu'],
        'Pulau Merah' => ['provinsi' => 'Jawa Timur', 'daerah' => 'Banyuwangi'],

        'Borobudur' => ['provinsi' => 'Jawa Tengah', 'daerah' => 'Magelang'],
        'Dieng' => ['provinsi' => 'Jawa Tengah', 'daerah' => 'Wonosobo'],
        'Karimunjawa' => ['provinsi' => 'Jawa Tengah', 'daerah' => 'Jepara'],
        'Bukit Sikunir' => ['provinsi' => 'Jawa Tengah', 'daerah' => 'Wonosobo'],
        'Solo' => ['provinsi' => 'Jawa Tengah', 'daerah' => 'Surakarta'],
        'Semarang' => ['provinsi' => 'Jawa Tengah', 'daerah' => 'Semarang'],

        'Candi Prambanan' => ['provinsi' => 'DI Yogyakarta', 'daerah' => 'Sleman'],
        'Malioboro' => ['provinsi' => 'DI Yogyakarta', 'daerah' => 'Kota Yogyakarta'],
        'Pantai Parangtritis' => ['provinsi' => 'DI Yogyakarta', 'daerah' => 'Bantul'],
        'Goa Jomblang' => ['provinsi' => 'DI Yogyakarta', 'daerah' => 'Gunungkidul'],
        'Gunung Merapi' => ['provinsi' => 'DI Yogyakarta', 'daerah' => 'Sleman'],
        'Pantai Timang' => ['provinsi' => 'DI Yogyakarta', 'daerah' => 'Gunungkidul'],
        'Heha Sky View' => ['provinsi' => 'DI Yogyakarta', 'daerah' => 'Gunungkidul'],

        'Kawah Putih' => ['provinsi' => 'Jawa Barat', 'daerah' => 'Bandung'],
        'Tangkuban Perahu' => ['provinsi' => 'Jawa Barat', 'daerah' => 'Bandung'],
        'Bandung' => ['provinsi' => 'Jawa Barat', 'daerah' => 'Bandung'],
        'Pangandaran' => ['provinsi' => 'Jawa Barat', 'daerah' => 'Pangandaran'],

        'Ujung Kulon' => ['provinsi' => 'Banten', 'daerah' => 'Pandeglang'],
        'Tanjung Lesung' => ['provinsi' => 'Banten', 'daerah' => 'Pandeglang'],

        'Kepulauan Seribu' => ['provinsi' => 'DKI Jakarta', 'daerah' => 'Kepulauan Seribu'],
        'Kota Tua Jakarta' => ['provinsi' => 'DKI Jakarta', 'daerah' => 'Jakarta Pusat'],

        'Pantai Kuta Bali' => ['provinsi' => 'Bali', 'daerah' => 'Badung'],
        'Ubud' => ['provinsi' => 'Bali', 'daerah' => 'Gianyar'],
        'Nusa Penida' => ['provinsi' => 'Bali', 'daerah' => 'Nusa Penida'],
        'Tanah Lot' => ['provinsi' => 'Bali', 'daerah' => 'Tabanan'],
        'Uluwatu' => ['provinsi' => 'Bali', 'daerah' => 'Badung'],
        'Kintamani' => ['provinsi' => 'Bali', 'daerah' => 'Bangli'],
        'Bedugul' => ['provinsi' => 'Bali', 'daerah' => 'Tabanan'],

        'Gili Trawangan' => ['provinsi' => 'Nusa Tenggara Barat', 'daerah' => 'Lombok Utara'],
        'Gunung Rinjani' => ['provinsi' => 'Nusa Tenggara Barat', 'daerah' => 'Lombok Timur'],
        'Mandalika' => ['provinsi' => 'Nusa Tenggara Barat', 'daerah' => 'Lombok Tengah'],

        'Labuan Bajo' => ['provinsi' => 'Nusa Tenggara Timur', 'daerah' => 'Manggarai Barat'],
        'Pulau Komodo' => ['provinsi' => 'Nusa Tenggara Timur', 'daerah' => 'Manggarai Barat'],
        'Kelimutu' => ['provinsi' => 'Nusa Tenggara Timur', 'daerah' => 'Ende'],
        'Sumba' => ['provinsi' => 'Nusa Tenggara Timur', 'daerah' => 'Sumba Barat'],

        'Danau Toba' => ['provinsi' => 'Sumatera Utara', 'daerah' => 'Toba'],
        'Pulau Samosir' => ['provinsi' => 'Sumatera Utara', 'daerah' => 'Samosir'],
        'Bukit Lawang' => ['provinsi' => 'Sumatera Utara', 'daerah' => 'Langkat'],
        'Berastagi' => ['provinsi' => 'Sumatera Utara', 'daerah' => 'Karo'],

        'Bukittinggi' => ['provinsi' => 'Sumatera Barat', 'daerah' => 'Agam'],
        'Kepulauan Mentawai' => ['provinsi' => 'Sumatera Barat', 'daerah' => 'Kepulauan Mentawai'],
        'Lembah Harau' => ['provinsi' => 'Sumatera Barat', 'daerah' => 'Lima Puluh Kota'],

        'Sabang' => ['provinsi' => 'Aceh', 'daerah' => 'Sabang'],
        'Pulau Weh' => ['provinsi' => 'Aceh', 'daerah' => 'Sabang'],

        'Pulau Belitung' => ['provinsi' => 'Kepulauan Bangka Belitung', 'daerah' => 'Belitung'],

        'Pahawang' => ['provinsi' => 'Lampung', 'daerah' => 'Pesawaran'],
        'Way Kambas' => ['provinsi' => 'Lampung', 'daerah' => 'Lampung Timur'],

        'Gunung Kerinci' => ['provinsi' => 'Jambi', 'daerah' => 'Kerinci'],

        'Bintan' => ['provinsi' => 'Kepulauan Riau', 'daerah' => 'Bintan'],

        'Kepulauan Derawan' => ['provinsi' => 'Kalimantan Timur', 'daerah' => 'Berau'],
        'Maratua' => ['provinsi' => 'Kalimantan Timur', 'daerah' => 'Berau'],

        'Tanjung Puting' => ['provinsi' => 'Kalimantan Tengah', 'daerah' => 'Kotawaringin Barat'],

        'Loksado' => ['provinsi' => 'Kalimantan Selatan', 'daerah' => 'Hulu Sungai Selatan'],

        'Bunaken' => ['provinsi' => 'Sulawesi Utara', 'daerah' => 'Manado'],
        'Likupang' => ['provinsi' => 'Sulawesi Utara', 'daerah' => 'Minahasa Utara'],

        'Tana Toraja' => ['provinsi' => 'Sulawesi Selatan', 'daerah' => 'Toraja Utara'],
        'Rammang-Rammang' => ['provinsi' => 'Sulawesi Selatan', 'daerah' => 'Maros'],

        'Wakatobi' => ['provinsi' => 'Sulawesi Tenggara', 'daerah' => 'Wakatobi'],

        'Kepulauan Togean' => ['provinsi' => 'Sulawesi Tengah', 'daerah' => 'Tojo Una-Una'],

        'Pantai Olele' => ['provinsi' => 'Gorontalo', 'daerah' => 'Bone Bolango'],

        'Banda Neira' => ['provinsi' => 'Maluku', 'daerah' => 'Maluku Tengah'],
        'Ambon' => ['provinsi' => 'Maluku', 'daerah' => 'Ambon'],

        'Morotai' => ['provinsi' => 'Maluku Utara', 'daerah' => 'Pulau Morotai'],
        'Ternate' => ['provinsi' => 'Maluku Utara', 'daerah' => 'Ternate'],

        'Raja Ampat' => ['provinsi' => 'Papua Barat Daya', 'daerah' => 'Raja Ampat'],

        'Lembah Baliem' => ['provinsi' => 'Papua Pegunungan', 'daerah' => 'Jayawijaya'],
    ],
    /*
    |--------------------------------------------------------------------------
    | Katalog Daerah (kabupaten, kota, atau kawasan)
    |--------------------------------------------------------------------------
    |
    | Daerah wisata yang paling sering diminta, beserta provinsinya. Dipakai
    | sebagai pilihan pada isian daerah: daftarnya menyusut mengikuti provinsi
    | yang sudah dipilih, sama seperti provinsi menyusut mengikuti wilayah.
    |
    | Daftar awal, bukan daftar tertutup — Indonesia punya lebih dari lima ratus
    | kabupaten dan kota, dan admin bisa menambah yang belum ada.
    |
    */

    'katalog_daerah' => [
        // Dilengkapi supaya tiap daerah yang dirujuk katalog destinasi
        // benar-benar bisa dipilih pada isian daerahnya.
        'Bangli' => 'Bali',
        'Lima Puluh Kota' => 'Sumatera Barat',

        // Jawa Timur
        'Banyuwangi' => 'Jawa Timur',
        'Malang' => 'Jawa Timur',
        'Kota Batu' => 'Jawa Timur',
        'Probolinggo' => 'Jawa Timur',
        'Lumajang' => 'Jawa Timur',
        'Jember' => 'Jawa Timur',
        'Bondowoso' => 'Jawa Timur',
        'Surabaya' => 'Jawa Timur',
        'Madura' => 'Jawa Timur',

        // Jawa Tengah
        'Magelang' => 'Jawa Tengah',
        'Wonosobo' => 'Jawa Tengah',
        'Banjarnegara' => 'Jawa Tengah',
        'Jepara' => 'Jawa Tengah',
        'Karimunjawa' => 'Jawa Tengah',
        'Semarang' => 'Jawa Tengah',
        'Surakarta' => 'Jawa Tengah',
        'Purwokerto' => 'Jawa Tengah',

        // DI Yogyakarta
        'Sleman' => 'DI Yogyakarta',
        'Bantul' => 'DI Yogyakarta',
        'Gunungkidul' => 'DI Yogyakarta',
        'Kulon Progo' => 'DI Yogyakarta',
        'Kota Yogyakarta' => 'DI Yogyakarta',

        // Jawa Barat & Banten
        'Bandung' => 'Jawa Barat',
        'Bogor' => 'Jawa Barat',
        'Garut' => 'Jawa Barat',
        'Pangandaran' => 'Jawa Barat',
        'Sukabumi' => 'Jawa Barat',
        'Pandeglang' => 'Banten',
        'Serang' => 'Banten',

        // DKI Jakarta
        'Kepulauan Seribu' => 'DKI Jakarta',
        'Jakarta Pusat' => 'DKI Jakarta',

        // Bali
        'Badung' => 'Bali',
        'Gianyar' => 'Bali',
        'Klungkung' => 'Bali',
        'Nusa Penida' => 'Bali',
        'Karangasem' => 'Bali',
        'Buleleng' => 'Bali',
        'Tabanan' => 'Bali',
        'Denpasar' => 'Bali',

        // Nusa Tenggara
        'Lombok Utara' => 'Nusa Tenggara Barat',
        'Lombok Tengah' => 'Nusa Tenggara Barat',
        'Lombok Timur' => 'Nusa Tenggara Barat',
        'Sumbawa' => 'Nusa Tenggara Barat',
        'Manggarai Barat' => 'Nusa Tenggara Timur',
        'Ende' => 'Nusa Tenggara Timur',
        'Sumba Barat' => 'Nusa Tenggara Timur',
        'Kupang' => 'Nusa Tenggara Timur',

        // Sumatera
        'Samosir' => 'Sumatera Utara',
        'Toba' => 'Sumatera Utara',
        'Karo' => 'Sumatera Utara',
        'Langkat' => 'Sumatera Utara',
        'Medan' => 'Sumatera Utara',
        'Agam' => 'Sumatera Barat',
        'Padang' => 'Sumatera Barat',
        'Kepulauan Mentawai' => 'Sumatera Barat',
        'Sabang' => 'Aceh',
        'Banda Aceh' => 'Aceh',
        'Belitung' => 'Kepulauan Bangka Belitung',
        'Pesawaran' => 'Lampung',
        'Lampung Timur' => 'Lampung',
        'Kerinci' => 'Jambi',
        'Bintan' => 'Kepulauan Riau',
        'Batam' => 'Kepulauan Riau',

        // Kalimantan
        'Berau' => 'Kalimantan Timur',
        'Balikpapan' => 'Kalimantan Timur',
        'Kotawaringin Barat' => 'Kalimantan Tengah',
        'Hulu Sungai Selatan' => 'Kalimantan Selatan',
        'Banjarmasin' => 'Kalimantan Selatan',
        'Pontianak' => 'Kalimantan Barat',

        // Sulawesi
        'Manado' => 'Sulawesi Utara',
        'Minahasa Utara' => 'Sulawesi Utara',
        'Toraja Utara' => 'Sulawesi Selatan',
        'Makassar' => 'Sulawesi Selatan',
        'Maros' => 'Sulawesi Selatan',
        'Wakatobi' => 'Sulawesi Tenggara',
        'Tojo Una-Una' => 'Sulawesi Tengah',
        'Bone Bolango' => 'Gorontalo',

        // Maluku & Papua
        'Maluku Tengah' => 'Maluku',
        'Ambon' => 'Maluku',
        'Pulau Morotai' => 'Maluku Utara',
        'Ternate' => 'Maluku Utara',
        'Raja Ampat' => 'Papua Barat Daya',
        'Jayawijaya' => 'Papua Pegunungan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pencarian Lokasi (peta luar)
    |--------------------------------------------------------------------------
    |
    | Dipakai hanya untuk MENGUSULKAN provinsi dari nama destinasi yang diketik
    | admin. Nama tempat itu terbuka — tidak bisa didaftar habis — sehingga peta
    | luar memang alat yang tepat, berbeda dari daftar provinsi yang sengaja
    | disimpan sendiri.
    |
    | Bisa dimatikan tanpa akibat apa pun selain hilangnya usulan: formulir tetap
    | jalan dan admin mengisi manual seperti biasa.
    |
    | 'pengenal' wajib diisi jujur — ketentuan pemakaian Nominatim mengharuskan
    | pemanggil menyebut dirinya, dan yang anonim berhak diblokir.
    |
    */

    'peta' => [
        'aktif' => env('ORCHA_PETA_AKTIF', true),
        'alamat' => env('ORCHA_PETA_ALAMAT', 'https://nominatim.openstreetmap.org/search'),
        'pengenal' => env('ORCHA_PETA_PENGENAL', 'OrchaJourney/1.0 (admin@orchajourney.com)'),

        /*
         * Kotak yang diutamakan saat mencari titik: sekitar Yogyakarta dan
         * daerah sekelilingnya (lon,lat kiri-atas sampai lon,lat kanan-bawah).
         *
         * Hanya MENGUTAMAKAN, tidak membatasi — tujuan di luar kotak ini tetap
         * ketemu. Gunanya menyelesaikan tulisan yang ambigu: "malioboro"
         * sendirian jatuh ke Surabaya tanpa kotak ini.
         */
        'kotak_utama' => env('ORCHA_PETA_KOTAK', '109.9,-7.5,110.9,-8.2'),
    ],

    /*
     * Ensiklopedia — sumber kedua untuk lokasi destinasi.
     *
     * Peta unggul pada koordinat, ensiklopedia unggul pada nama yang dikenal
     * orang. Pulau Menjangan Kecil contohnya: peta hanya tahu ia di Jepara,
     * karena batas Kecamatan Karimunjawa memang belum ada di OpenStreetMap,
     * sedangkan ensiklopedia menyebut Karimunjawa apa adanya — dan
     * "Karimunjawa" itulah yang dicari pengunjung, bukan "Jepara".
     */
    'ensiklopedia' => [
        'aktif' => env('ORCHA_ENSIKLOPEDIA_AKTIF', true),
        'alamat' => env('ORCHA_ENSIKLOPEDIA_ALAMAT', 'https://id.wikipedia.org/w/api.php'),
    ],

    /*
     * Rute perjalanan untuk formulir sewa: mencari koordinat titik jemput dan
     * tujuan, lalu jarak JALAN di antara keduanya.
     *
     * Koordinatnya dari Nominatim (lihat 'peta' di atas). Jaraknya dari
     * OpenRouteService, karena Nominatim tidak menghitung rute.
     *
     * Sengaja BUKAN jarak garis lurus. Jogja ke Borobudur lurus sekitar 40 km,
     * lewat jalan sekitar 60 km — meleset separuh, dan angka yang meleset
     * separuh di halaman pemesanan lebih buruk daripada tidak ada angka.
     *
     * Tanpa kunci, petanya tetap tampil beserta kedua penandanya; yang tidak
     * muncul hanya angka jaraknya.
     */
    'rute' => [
        'aktif' => env('ORCHA_RUTE_AKTIF', true),
        'kunci' => env('ORCHA_RUTE_KUNCI'),
        'alamat' => env('ORCHA_RUTE_ALAMAT', 'https://api.openrouteservice.org/v2/directions/driving-car'),
    ],

    'api' => [
        'kunci' => env('ORCHA_API_KEY'),
        'ip_diizinkan' => array_filter(array_map('trim', explode(',', (string) env('ORCHA_API_IP', '')))),
        'per_halaman' => 25,
        'per_halaman_maks' => 100,
    ],
];
