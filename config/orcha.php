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
    'whatsapp' => env('ORCHA_WHATSAPP', '6289509882219'),
    'email' => env('ORCHA_EMAIL', 'info@orchajourney.com'),
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

    'kemampuan_renang' => [
        'tidak_bisa' => 'Tidak bisa berenang',
        'sedikit' => 'Bisa sedikit',
        'lancar' => 'Lancar berenang',
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

    'status_pembatalan' => [
        'diajukan' => 'Diajukan',
        'diproses' => 'Sedang Diproses',
        'disetujui' => 'Disetujui',
        'dana_dikirim' => 'Dana Dikirim',
        'ditolak' => 'Ditolak',
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
        // Nomor rekening sengaja dikosongkan. Isi lewat berkas ini bila ingin
        // ditampilkan di halaman Ketentuan Pembayaran; selama kosong, halaman
        // mengarahkan pelanggan meminta nomor rekening lewat WhatsApp.
        'rekening' => [
            // ['bank' => 'BCA', 'nomor' => '0000000000', 'atas_nama' => 'Orcha Journey'],
        ],
        'metode' => ['Transfer bank', 'QRIS', 'Tunai di kantor'],
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
        'tangga' => [
            ['batas' => 'Lebih dari 30 hari sebelum keberangkatan', 'kembali' => '100% dari DP', 'potongan' => 'Tanpa potongan'],
            ['batas' => '15 – 30 hari sebelum keberangkatan', 'kembali' => '50% dari DP', 'potongan' => '50% dari DP'],
            ['batas' => '8 – 14 hari sebelum keberangkatan', 'kembali' => '25% dari DP', 'potongan' => '75% dari DP'],
            ['batas' => 'Kurang dari 7 hari sebelum keberangkatan', 'kembali' => 'Tidak ada', 'potongan' => '100% dari DP'],
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

    'api' => [
        'kunci' => env('ORCHA_API_KEY'),
        'ip_diizinkan' => array_filter(array_map('trim', explode(',', (string) env('ORCHA_API_IP', '')))),
        'per_halaman' => 25,
        'per_halaman_maks' => 100,
    ],
];
