<?php

use App\Services\DokuCheckout;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Ketentuan Pembayaran & DP — Orcha Journey')] class extends Component {
    public function with(): array
    {
        $dp = config('orcha.pembayaran.dp_persen');
        $dpStudy = config('orcha.pembayaran.dp_persen_study_tour');
        $batasDp = config('orcha.pembayaran.dp_batas_jam');

        /*
         | Batas yang DIJANJIKAN (24 jam) berbeda dari saat kursinya benar-benar
         | dilepas sistem (72 jam), dan keduanya disebut apa adanya di halaman
         | ini.
         |
         | Menyembunyikan tenggang tiga harinya akan membuat kalimatnya lebih
         | tegas tetapi tidak benar — dan yang membacanya justru orang yang
         | sedang menghitung kapan ia harus mentransfer.
         |
         | Kata "Batal" disebut terang-terangan, dan itu perbaikan atas kalimat
         | lama yang cuma menulis "kursinya dilepas". Yang benar-benar terjadi
         | di basis data adalah status pemesanan berubah menjadi batal — lihat
         | LepaskanKursiTertahan — jadi pelanggan yang membuka Lacak Pesanan
         | akan membaca "Batal", kata yang halaman ini tidak pernah
         | mempersiapkannya. Selisih kecil semacam itu berakhir sebagai pesan
         | WhatsApp yang menanyakan apakah uangnya hangus.
         |
         | Penjagaannya juga disebut, dan itu bagian yang paling menenangkan:
         | yang pernah membayar atau pernah mengirim bukti TIDAK pernah ikut
         | dilepas. Tanpa kalimat itu, orang yang mentransfer di jam ke-70
         | menghabiskan tiga hari berikutnya mengira kursinya melayang.
         */
        $lepasJam = config('orcha.pembayaran.dp_lepas_jam');
        $lepasHari = (int) round($lepasJam / 24);
        $pelunasan = config('orcha.pembayaran.pelunasan_hari_sebelum');
        $sewa = config('orcha.pembayaran.pelunasan_sewa_kendaraan');
        $metode = config('orcha.pembayaran.metode');
        $rekening = config('orcha.pembayaran.rekening');

        $atasNama = config('orcha.pembayaran.atas_nama');

        /*
         | Halaman ini menerangkan jalur yang BENAR-BENAR berlaku, bukan yang
         | direncanakan.
         |
         | Cara bayar yang dijanjikan di situs tetapi ditolak saat pemesanan
         | membuat pelanggan ragu — itu sebabnya QRIS dan tunai dulu dicabut
         | dari daftar metode. Kesalahan yang sama akan terulang terbalik bila
         | halaman ini terus menyuruh orang mengirim bukti transfer padahal
         | pembayarannya sudah pindah ke gerbang.
         */
        $gerbang = app(DokuCheckout::class)->aktif();

        /*
         | Channel-nya sengaja TIDAK didaftar satu per satu.
         |
         | Yang aktif di akun DOKU bisa bertambah dan berkurang tanpa satu baris
         | kode pun berubah, dan daftar yang basi di halaman ketentuan adalah
         | persis jenis janji yang dulu bikin repot. Yang disebut cukup: di mana
         | membayarnya. Pilihannya ditampilkan halaman pembayaran itu sendiri,
         | dan di sana daftarnya selalu benar.
         */
        $daftarMetode = $gerbang
            ? '<li>Transfer bank &amp; Virtual Account</li><li>QRIS</li><li>Dompet digital</li>'
            : collect($metode)->map(fn ($m) => "<li>{$m}</li>")->implode('');

        $blokRekening = $gerbang
            ? "<p>Pembayaran diselesaikan di <strong>halaman pembayaran resmi kami</strong>, dan pilihan metode yang tersedia ditampilkan di halaman itu — daftar di atas adalah gambaran umumnya. Penerima dananya tetap satu: <strong>{$atasNama}</strong>.</p>"
                .'<p>Kami <strong>tidak pernah</strong> meminta transfer ke rekening pribadi atas nama perorangan. Bila ada yang mengirimi Anda nomor rekening pribadi mengatasnamakan kami, hentikan dan konfirmasikan dulu ke nomor resmi kami.</p>'
            : (empty($rekening)
            ? "<p>Seluruh pembayaran hanya sah ke rekening atas nama <strong>{$atasNama}</strong>. Nama selain itu <strong>bukan kami</strong> — jangan ditransfer.</p>"
                ."<p>Nomor rekeningnya sengaja tidak kami pajang di situs ini: nomor yang terpampang mudah disalin penipu untuk membuat halaman tiruan. Nomornya dikirim tim kami lewat WhatsApp resmi saat konfirmasi pemesanan. Yang perlu Anda periksa di mesin bank cukup nama penerimanya.</p>"
            : '<div class="table-wrap"><table class="table-orcha"><thead><tr><th>Bank</th><th>Nomor Rekening</th><th>Atas Nama</th></tr></thead><tbody>'
                .collect($rekening)->map(fn ($r) => "<tr><td>{$r['bank']}</td><td>{$r['nomor']}</td><td>{$atasNama}</td></tr>")->implode('')
                .'</tbody></table></div>');

        return [
            'sections' => [
                [
                    'slug' => 'uang-muka',
                    'judul' => '1. Uang Muka (DP)',
                    'isi' => "
                        <p>Pemesanan dikunci setelah uang muka diterima. Besarnya:</p>
                        <div class=\"table-wrap\"><table class=\"table-orcha\">
                            <thead><tr><th>Jenis Layanan</th><th>Uang Muka</th><th>Batas Pembayaran</th></tr></thead>
                            <tbody>
                                <tr><td>Open trip &amp; private trip</td><td>{$dp}% dari total biaya</td><td>{$batasDp} jam setelah konfirmasi</td></tr>
                                <tr><td>Study tour</td><td>{$dpStudy}% dari total biaya</td><td>{$batasDp} jam setelah konfirmasi</td></tr>
                                <tr><td>Sewa kendaraan</td><td>{$dp}% dari total sewa</td><td>{$batasDp} jam setelah konfirmasi</td></tr>
                            </tbody>
                        </table></div>
                        <p>Bila uang muka tidak diterima dalam batas waktu di atas, kami masih menahan kursi Anda hingga <strong>{$lepasJam} jam</strong> ({$lepasHari} hari) sejak pemesanan. Lewat itu, pemesanan Anda <strong>berubah status menjadi Batal</strong> secara otomatis, kursinya dilepas untuk pemesan lain, dan kami kabari lewat email. Kode pemesanan yang lama tidak bisa dipakai lagi — untuk ikut, daftar ulang selama kursinya masih tersedia.</p>
                        <p><strong>Sudah membayar tetapi belum tercatat?</strong> Pemesanan yang pembayarannya sudah masuk — atau yang buktinya sudah Anda kirim dan masih kami periksa, termasuk yang kami tolak — <strong>tidak pernah ikut dilepas</strong>, berapa pun lamanya. Yang dilepas hanya pemesanan yang tidak pernah ada pembayarannya sama sekali.</p>
                    ",
                ],
                [
                    'slug' => 'pelunasan',
                    'judul' => '2. Pelunasan',
                    'isi' => "
                        <ul>
                            <li><strong>Paket wisata:</strong> pelunasan paling lambat <strong>H-{$pelunasan}</strong>, yaitu {$pelunasan} hari sebelum tanggal keberangkatan.</li>
                            <li><strong>Sewa kendaraan:</strong> pelunasan {$sewa}.</li>
                            <li><strong>Pemesanan mendadak</strong> (kurang dari {$pelunasan} hari sebelum berangkat) dibayar lunas di muka, tanpa skema uang muka.</li>
                        </ul>
                        <p>Keterlambatan pelunasan tanpa pemberitahuan membuat pemesanan dianggap batal dan tunduk pada ketentuan pembatalan yang berlaku.</p>
                    ",
                ],
                [
                    'slug' => 'metode',
                    'judul' => '3. Metode Pembayaran',
                    'isi' => "<ul>{$daftarMetode}</ul>{$blokRekening}",
                ],
                [
                    'slug' => 'bukti-bayar',
                    'judul' => $gerbang ? '4. Cara Membayar' : '4. Bukti Pembayaran',
                    'isi' => $gerbang
                        ? '
                        <ol>
                            <li>Buka <a href="'.route('konfirmasi-pembayaran').'">halaman pembayaran</a>, lalu masukkan kode pesanan Anda (mis. OT-1508-A7K3) dan 4 digit terakhir nomor WhatsApp yang Anda pakai saat memesan.</li>
                            <li>Pilih membayar uang muka dulu atau langsung lunas. Nominalnya kami hitungkan, termasuk kode unik pemesanan Anda — angkanya tidak perlu diketik sendiri.</li>
                            <li>Selesaikan pembayaran di halaman yang muncul berikutnya.</li>
                            <li>Pembayaran tercatat sendiri dalam hitungan detik, dan tanda terimanya dikirim ke email Anda. Tidak perlu mengirim bukti apa pun kepada kami.</li>
                        </ol>
                        <p>Simpan tanda terima itu sampai perjalanan selesai; itu dasarnya bila terjadi selisih catatan. Bila pembayaran Anda sudah keluar dari rekening tetapi belum tercatat setelah beberapa menit, hubungi kami lewat WhatsApp resmi dengan menyebutkan kode pesanan — uang yang sudah berpindah tidak hilang.</p>
                    '
                        : '
                        <ol>
                            <li>Kirim bukti transfer lewat <a href="'.route('konfirmasi-pembayaran').'">formulir Konfirmasi Pembayaran</a> paling lambat 1×24 jam setelah pembayaran.</li>
                            <li>Sertakan kode pesanan Anda (mis. OT-1508-A7K3) supaya pembayaran langsung tercocokkan.</li>
                            <li>Tim kami memeriksa lalu mengabari hasilnya lewat WhatsApp.</li>
                            <li>Simpan tanda terima yang Anda peroleh sampai perjalanan selesai; itu dasarnya bila terjadi selisih catatan.</li>
                        </ol>
                        <p>Bukti dikirim lewat formulir, bukan lewat percakapan, supaya tidak tenggelam di antara pesan lain dan pemeriksaannya bisa ditelusuri. Pembayaran tanpa bukti yang terkirim tidak dapat kami verifikasi, dan pemesanan belum dianggap terkunci.</p>
                    ',
                ],
                [
                    'slug' => 'biaya-tambahan',
                    'judul' => '5. Biaya Tambahan',
                    'isi' => '
                        <p>Biaya berikut dihitung di luar harga paket dan ditagihkan setelah perjalanan bila muncul:</p>
                        <ul>
                            <li>Penambahan destinasi atau perpanjangan waktu atas permintaan peserta.</li>
                            <li>Kelebihan jam pemakaian kendaraan.</li>
                            <li>Biaya parkir, tol, atau tiket masuk lokasi yang tidak tercantum pada itinerary.</li>
                            <li>Kerusakan fasilitas armada akibat kelalaian peserta.</li>
                        </ul>
                        <p>Setiap biaya tambahan selalu kami sampaikan dan konfirmasikan lebih dahulu, tidak pernah ditagihkan diam-diam.</p>
                    ',
                ],
                [
                    'slug' => 'kuitansi',
                    'judul' => '6. Kuitansi & Faktur Instansi',
                    'isi' => '
                        <p>Untuk pemesanan sekolah, kampus, atau instansi, kami menyediakan kuitansi bermeterai serta dokumen penagihan sesuai kebutuhan administrasi. Sampaikan permintaan ini di awal agar dokumen dapat disiapkan sebelum keberangkatan.</p>
                    ',
                ],
                [
                    'slug' => 'keamanan',
                    'judul' => '7. Keamanan Pembayaran',
                    'isi' => '
                        <p>Orcha Journey <strong>tidak pernah</strong> meminta pembayaran ke rekening pribadi di luar yang kami konfirmasikan resmi, dan tidak pernah meminta kode OTP, PIN, atau kata sandi mobile banking Anda.</p>
                        <p>Bila menerima permintaan mencurigakan yang mengatasnamakan kami, hentikan transaksi dan konfirmasikan lebih dulu ke nomor resmi yang tertera di situs ini.</p>
                    ',
                ],
            ],
        ];
    }
}; ?>

<x-halaman-ketentuan title="Ketentuan Pembayaran & DP" eyebrow="Pembayaran"
    subtitle="Besaran uang muka, tenggat pelunasan, metode pembayaran, dan cara menyelesaikannya."
    image="images/HERO/ketentuan-pembayaran.webp" posisi="center 80%" diperbarui="14 Agustus 2026" :sections="$sections">
    <p>
        Halaman ini merangkum seluruh aturan pembayaran di Orcha Journey. Bila ada perbedaan dengan penawaran tertulis
        yang Anda terima, yang berlaku adalah penawaran tertulis tersebut.
    </p>
</x-halaman-ketentuan>
