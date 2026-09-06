<?php

/*
|--------------------------------------------------------------------------
| Payment Gateway DOKU (Checkout)
|--------------------------------------------------------------------------
|
| Dipakai halaman pembayaran publik. Pelanggan tidak lagi mentransfer manual
| lalu mengunggah tangkapan layar; ia diarahkan ke halaman DOKU, membayar di
| sana, dan pembayarannya masuk sendiri lewat HTTP Notification.
|
| Yang dipakai adalah DOKU Checkout (non-SNAP): tanda tangannya HMAC-SHA256
| dengan Secret Key. Merchant Public Key dan Token URL di dashboard DOKU
| TIDAK dipakai di sini — keduanya milik jalur SNAP yang bertanda tangan RSA
| asimetris. Membiarkannya kosong bukan konfigurasi yang belum selesai.
|
| Kredensialnya TIDAK boleh masuk repositori. Semua dibaca dari .env.
|
*/

return [

    /*
     | Sakelar utama.
     |
     | Mati = halaman pembayaran kembali menawarkan transfer manual. Ini bukan
     | kemewahan: bila DOKU bermasalah atau kredensialnya belum terpasang di
     | server, pelanggan yang sedang ingin membayar tidak boleh menemui
     | halaman rusak. Yang paling mahal dari pembayaran bukan biaya
     | gateway-nya, melainkan pelanggan yang batal membayar.
     */
    'aktif' => (bool) env('DOKU_AKTIF', false),

    // sandbox | produksi
    'lingkungan' => env('DOKU_LINGKUNGAN', 'sandbox'),

    'client_id' => env('DOKU_CLIENT_ID'),
    'secret_key' => env('DOKU_SECRET_KEY'),

    'endpoint' => [
        'sandbox' => 'https://api-sandbox.doku.com',
        'produksi' => 'https://api.doku.com',
    ],

    'jalur' => [
        // Request-Target saat kita memanggil DOKU.
        'bayar' => '/checkout/v1/payment',
    ],

    /*
     | Umur halaman pembayaran, dalam menit.
     |
     | Nilai ini MENIMPA setelan Batas Waktu di dashboard DOKU — dokumentasinya
     | menyebut yang dikirim per permintaan yang menang. Setelan dashboard cuma
     | berlaku untuk permintaan yang tidak menyebutkan batasnya sendiri, dan
     | kita selalu menyebutkan. Angka di dashboard yang tidak cocok dengan
     | hitung mundur di halaman bayar bukan kekeliruan; sumbernya di sini.
     |
     | TIGA PULUH MENIT, dan itu keputusan tentang kerahasiaan — bukan tentang
     | pembayaran.
     |
     | Halaman DOKU menampilkan nama dan nomor telepon pemesan di panel
     | Informasi Pelanggan. Tautannya bukan rahasia yang dijaga sandi: ia
     | dikirim lewat email, disalin ke WhatsApp, kadang diteruskan ke orang
     | lain yang ikut membayar. Selama tautan itu hidup, data pelanggan bisa
     | dibaca siapa pun yang memegangnya. Umur 24 jam berarti sehari penuh
     | jendela terbuka untuk satu pesanan; setengah jam menutupnya sebelum
     | tautan itu sempat berpindah tangan.
     |
     | Batas ini dipasangkan dengan dua keputusan lain, dan hanya masuk akal
     | bersama keduanya:
     |
     |   CHANNEL-nya dipersempit ke Virtual Account dan QRIS saja, diatur di
     |   dashboard DOKU — bukan di DOKU_METODE, yang sengaja dibiarkan kosong
     |   supaya daftar di sini tidak basi diam-diam tiap kali ada perubahan.
     |   Minimarket dan gerai retail tidak diaktifkan: yang memilih Alfamart
     |   baru membayar saat kebetulan lewat sana, dan setengah jam tidak
     |   pernah cukup untuk itu.
     |
     |   TAGIHAN YANG MATI DIBUAT ULANG. Pelanggan kembali ke halaman
     |   pembayaran dan mendapat tautan baru berikut nomor VA baru. Itu alur
     |   yang disengaja, bukan jalan darurat — lihat MulaiPembayaranDoku, yang
     |   memakai ulang tagihan selama masih hidup dan hanya membuat yang baru
     |   sesudah mati.
     |
     | Nomor VA yang lama ikut mati bersama tagihannya. Transfer ke nomor itu
     | ditolak bank, jadi pelanggan yang menyimpannya di daftar favorit
     | m-banking tidak kehilangan uang — ia cuma gagal mentransfer, dan
     | kembali ke halaman pembayaran untuk nomor yang baru.
     |
     | Batas atasnya dijaga jauh di bawah 72 jam penahanan kursi
     | (orcha.pembayaran.dp_lepas_jam). Halaman bayar yang masih hidup sesudah
     | kursinya dilepas adalah jalan paling langsung menuju pelanggan yang
     | membayar untuk kursi yang tidak lagi miliknya.
     */
    'batas_bayar_menit' => (int) env('DOKU_BATAS_BAYAR_MENIT', 30),

    /*
     | Berapa lama kita MASIH bertanya ke DOKU sesudah tagihannya kedaluwarsa.
     |
     | Ada khusus karena batasnya dipendekkan. Yang membayar pada menit ke-29
     | menyelesaikan transaksinya beberapa detik sesudah jam kami menyatakan
     | mati — dan bila kami berhenti bertanya tepat pada menit ke-30, uangnya
     | sudah berpindah sementara layar kami menyatakan batas waktu habis.
     |
     | Jam kedaluwarsa itu MILIK KAMI, bukan milik DOKU. DOKU tetap memproses
     | pembayaran yang sudah telanjur masuk, dan notifikasinya tetap kami
     | terima — lihat TerimaNotifikasiDoku, yang memang tidak menolak
     | keberhasilan hanya karena jamnya lewat. Yang perlu dijaga cuma jalur
     | cadangannya: polling di halaman hasil.
     */
    'tenggang_periksa_menit' => (int) env('DOKU_TENGGANG_PERIKSA_MENIT', 15),

    /*
     | Batas waktu memanggil DOKU, dalam detik.
     |
     | Pendek disengaja. Pelanggan sedang menunggu di depan tombol "Bayar
     | Sekarang"; menggantung tiga puluh detik lebih buruk daripada pesan
     | gagal yang jujur berikut tawaran menghubungi WhatsApp.
     */
    'batas_tunggu' => (int) env('DOKU_BATAS_TUNGGU', 15),

    /*
     | Seberapa tua cap waktu notifikasi masih boleh diterima, dalam menit.
     |
     | Tanda tangan membuktikan notifikasi PERNAH dibuat DOKU; ia tidak
     | membuktikan notifikasi itu baru datang. Satu notifikasi sah yang direkam
     | bisa dikirim ulang kapan saja dan tetap lolos tanpa batas ini.
     |
     | Longgar dengan sengaja: jam server kita dan jam DOKU tidak pernah persis
     | sama, dan notifikasi yang gagal diulang DOKU beberapa menit kemudian
     | harus tetap diterima. Menolak pembayaran sungguhan demi menutup
     | pemutaran ulang adalah pertukaran yang salah arah.
     */
    'jendela_notifikasi_menit' => (int) env('DOKU_JENDELA_NOTIFIKASI_MENIT', 30),

    /*
     | Metode pembayaran yang ditampilkan.
     |
     | Kosong = DOKU menampilkan SEMUA yang aktif untuk merchant ini. Itu yang
     | dipakai: daftar yang ditulis di sini akan basi diam-diam setiap kali
     | ada channel baru disetujui, dan yang terjadi bukan channel-nya tidak
     | muncul melainkan tidak ada yang tahu kenapa.
     */
    'metode' => array_filter(explode(',', (string) env('DOKU_METODE', ''))),

];
