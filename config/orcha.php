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
    |--------------------------------------------------------------------------
    | Kategori Paket Wisata
    |--------------------------------------------------------------------------
    |
    | Dipakai admin (dropdown form paket) dan landing page (tab filter paket).
    |
    */
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
            'Agya' => 5,
            'Alphard' => 7,
            'Avanza' => 7,
            'Calya' => 7,
            'Camry' => 5,
            'C-HR' => 5,
            'Corolla Altis' => 5,
            'Corolla Cross' => 5,
            'Fortuner' => 7,
            'HiAce Commuter' => 15,
            'HiAce Premio' => 14,
            'Hilux' => 5,
            'Innova Reborn' => 7,
            'Innova Venturer' => 7,
            'Innova Zenix' => 7,
            'Kijang Kapsul' => 7,
            'Land Cruiser' => 7,
            'Raize' => 5,
            'Rush' => 7,
            'Sienta' => 6,
            'Vellfire' => 7,
            'Veloz' => 7,
            'Voxy' => 8,
            'Yaris' => 5,
            'Yaris Cross' => 5,
        ],
        'Daihatsu' => [
            'Ayla' => 5,
            'Gran Max Blind Van' => 2,
            'Gran Max Minibus' => 8,
            'Gran Max Pick Up' => 2,
            'Luxio' => 8,
            'Rocky' => 5,
            'Sigra' => 7,
            'Sirion' => 5,
            'Terios' => 7,
            'Xenia' => 7,
        ],
        'Honda' => [
            'BR-V' => 7,
            'Brio RS' => 5,
            'Brio Satya' => 5,
            'CR-V' => 5,
            'City' => 5,
            'City Hatchback' => 5,
            'Civic' => 5,
            'Freed' => 6,
            'HR-V' => 5,
            'Jazz' => 5,
            'Mobilio' => 7,
            'Odyssey' => 7,
            'WR-V' => 5,
        ],
        'Suzuki' => [
            'APV Arena' => 8,
            'APV Luxury' => 7,
            'Baleno' => 5,
            'Carry Pick Up' => 2,
            'Ertiga' => 7,
            'Ertiga Hybrid' => 7,
            'Grand Vitara' => 5,
            'Ignis' => 5,
            'Jimny' => 4,
            'Karimun Wagon R' => 7,
            'S-Presso' => 5,
            'XL7' => 7,
        ],
        'Mitsubishi' => [
            'Colt Diesel' => 3,
            'Fuso Canter' => 3,
            'L300 Minibus' => 9,
            'L300 Pick Up' => 3,
            'Mirage' => 5,
            'Outlander PHEV' => 5,
            'Pajero Sport' => 7,
            'Triton' => 5,
            'Xforce' => 5,
            'Xpander' => 7,
            'Xpander Cross' => 7,
        ],
        'Nissan' => [
            'Almera' => 5,
            'Evalia' => 7,
            'Grand Livina' => 7,
            'Kicks' => 5,
            'Livina' => 7,
            'Magnite' => 5,
            'Navara' => 5,
            'Serena' => 8,
            'Terra' => 7,
            'X-Trail' => 7,
        ],
        'Hyundai' => [
            'Creta' => 5,
            'H-1' => 11,
            'Ioniq 5' => 5,
            'Ioniq 6' => 5,
            'Palisade' => 7,
            'Santa Fe' => 7,
            'Stargazer' => 7,
            'Stargazer X' => 7,
            'Staria' => 11,
            'Tucson' => 5,
        ],
        'Wuling' => [
            'Air ev' => 4,
            'Almaz' => 7,
            'Almaz RS' => 7,
            'Alvez' => 5,
            'Binguo EV' => 4,
            'Confero' => 8,
            'Confero S' => 8,
            'Cortez' => 7,
            'Cortez CT' => 7,
            'Formo' => 8,
        ],
        'Isuzu' => [
            'Elf Microbus' => 16,
            'Elf NLR' => 3,
            'Elf NMR' => 3,
            'MU-X' => 7,
            'Panther' => 7,
            'Traga' => 3,
        ],
        'Kia' => [
            'Carens' => 7,
            'Carnival' => 11,
            'Grand Sedona' => 11,
            'Picanto' => 5,
            'Rio' => 5,
            'Seltos' => 5,
            'Sonet' => 5,
            'Sportage' => 5,
        ],
        'Mazda' => [
            'Biante' => 8,
            'CX-3' => 5,
            'CX-30' => 5,
            'CX-5' => 5,
            'CX-60' => 5,
            'CX-8' => 7,
            'Mazda2' => 5,
            'Mazda6' => 5,
        ],
        'Chery' => [
            'Omoda 5' => 5,
            'Tiggo 5X' => 5,
            'Tiggo 7 Pro' => 5,
            'Tiggo 8 Pro' => 7,
            'Tiggo Cross' => 5,
        ],
        'BYD' => [
            'Atto 3' => 5,
            'Dolphin' => 5,
            'M6' => 7,
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
            'Gelora E' => 7,
            'Glory 560' => 7,
            'Super Cab' => 2,
        ],
        'Volkswagen' => [
            'Caravelle' => 8,
            'Polo' => 5,
            'Tiguan' => 5,
            'Transporter' => 8,
        ],
        'Mercedes-Benz' => [
            'Bus OH 1526' => 59,
            'Bus OH 1626' => 59,
            'E-Class' => 5,
            'S-Class' => 5,
            'Sprinter' => 15,
            'Vito' => 8,
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
            'LM 350' => 7,
            'RX 300' => 5,
            'UX 300e' => 5,
        ],
        'Hino' => [
            'Bus RK' => 59,
            'Bus RN' => 59,
            'Dutro' => 3,
            'Ranger' => 3,
        ],
        'Golden Dragon' => [
            'Bus Pariwisata' => 45,
            'Minibus' => 19,
        ],
        'Zhongtong' => [
            'Bus Pariwisata' => 45,
        ],
        'Scania' => [
            'Bus K360' => 59,
            'Bus K410' => 59,
        ],
        'Ford' => [
            'Everest' => 7,
            'Ranger' => 5,
        ],
        'Chevrolet' => [
            'Captiva' => 7,
            'Spin' => 7,
            'Trax' => 5,
        ],
        'Datsun' => [
            'Cross' => 5,
            'GO+ Panca' => 7,
        ],
        'Peugeot' => [
            '3008' => 5,
            '5008' => 7,
        ],
        'Renault' => [
            'Kwid' => 5,
            'Triber' => 7,
        ],
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
        'bali_nusa' => 'Bali & Nusa Tenggara',
        'kalimantan' => 'Kalimantan',
        'sulawesi' => 'Sulawesi',
        'maluku_papua' => 'Maluku & Papua',
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

    'api' => [
        'kunci' => env('ORCHA_API_KEY'),
        'ip_diizinkan' => array_filter(array_map('trim', explode(',', (string) env('ORCHA_API_IP', '')))),
        'per_halaman' => 25,
        'per_halaman_maks' => 100,
    ],
];
