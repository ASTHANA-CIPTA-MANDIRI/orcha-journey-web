<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Syarat & Ketentuan — Orcha Journey')] class extends Component {
    public function with(): array
    {
        $dp = config('orcha.pembayaran.dp_persen');
        $pelunasan = config('orcha.pembayaran.pelunasan_hari_sebelum');
        $batasDp = config('orcha.pembayaran.dp_batas_jam');

        return [
            'sections' => [
                [
                    'slug' => 'ketentuan-umum',
                    'judul' => '1. Ketentuan Umum',
                    'isi' => '
                        <p>Syarat dan ketentuan ini mengatur hubungan antara <strong>Orcha Journey</strong> sebagai penyedia jasa perjalanan wisata dan penyewaan kendaraan, dengan pemesan yang menggunakan layanan kami.</p>
                        <p>Dengan melakukan pemesanan — baik melalui WhatsApp, telepon, surel, maupun kanal resmi lainnya — pemesan dianggap telah membaca, memahami, dan menyetujui seluruh ketentuan di halaman ini.</p>
                        <ul>
                            <li>Layanan yang kami sediakan meliputi open trip, private trip, study tour, serta sewa mobil, HiAce, dan bus pariwisata.</li>
                            <li>Pemesan wajib berusia minimal 17 tahun atau diwakili oleh orang tua, wali, maupun penanggung jawab rombongan.</li>
                            <li>Seluruh penawaran harga berlaku selama masa yang tercantum pada penawaran dan dapat berubah bila ketersediaan armada atau akomodasi berubah.</li>
                        </ul>
                    ',
                ],
                [
                    'slug' => 'pemesanan',
                    'judul' => '2. Pemesanan & Konfirmasi',
                    'isi' => "
                        <p>Pemesanan dinyatakan sah dan kursi/armada dikunci setelah uang muka diterima dan dikonfirmasi oleh tim kami.</p>
                        <ol>
                            <li>Pemesan menyampaikan tujuan, tanggal, jumlah peserta, dan kebutuhan khusus.</li>
                            <li>Orcha Journey mengirimkan itinerary beserta rincian biaya.</li>
                            <li>Pemesan membayar uang muka sebesar {$dp}% dari total biaya, paling lambat {$batasDp} jam setelah konfirmasi.</li>
                            <li>Tim kami mengirimkan konfirmasi pemesanan berisi jadwal, titik jemput, dan nomor kontak pendamping.</li>
                        </ol>
                        <p>Data peserta yang diserahkan kepada kami harus benar dan lengkap. Kesalahan data yang berdampak pada tiket, asuransi, atau perizinan menjadi tanggung jawab pemesan.</p>
                    ",
                ],
                [
                    'slug' => 'harga',
                    'judul' => '3. Harga & Cakupan Layanan',
                    'isi' => '
                        <p>Harga paket yang tercantum di situs adalah harga per orang untuk jumlah peserta minimum yang tertulis pada masing-masing paket.</p>
                        <h3>Umumnya sudah termasuk</h3>
                        <ul>
                            <li>Transportasi selama perjalanan sesuai armada yang disepakati.</li>
                            <li>Pemandu atau pendamping perjalanan.</li>
                            <li>Tiket masuk destinasi yang disebutkan pada rincian paket.</li>
                        </ul>
                        <h3>Umumnya belum termasuk</h3>
                        <ul>
                            <li>Pengeluaran pribadi, oleh-oleh, dan keperluan di luar itinerary.</li>
                            <li>Tip untuk sopir dan pemandu.</li>
                            <li>Biaya destinasi tambahan yang diminta saat perjalanan berlangsung.</li>
                        </ul>
                        <p>Cakupan pasti selalu tertulis pada penawaran yang Anda terima. Bila terjadi perbedaan antara halaman ini dan penawaran tertulis, yang berlaku adalah penawaran tertulis.</p>
                    ',
                ],
                [
                    'slug' => 'pembayaran',
                    'judul' => '4. Pembayaran',
                    'isi' => "
                        <p>Uang muka sebesar {$dp}% saat pemesanan, dan pelunasan paling lambat H-{$pelunasan} sebelum keberangkatan. Rincian lengkap, termasuk metode pembayaran dan bukti transfer, dijelaskan pada halaman <a href=\"" . route('ketentuan-pembayaran') . "\">Ketentuan Pembayaran &amp; DP</a>.</p>
                        <p>Keterlambatan pelunasan tanpa pemberitahuan dapat menyebabkan pemesanan dianggap batal dan tunduk pada ketentuan pembatalan.</p>
                    ",
                ],
                [
                    'slug' => 'kewajiban-peserta',
                    'judul' => '5. Kewajiban Peserta',
                    'isi' => '
                        <ul>
                            <li>Hadir tepat waktu di titik jemput yang disepakati. Keterlambatan lebih dari 30 menit dapat membuat rombongan berangkat lebih dulu tanpa pengembalian biaya.</li>
                            <li>Menjaga barang bawaan pribadi. Kehilangan barang di dalam kendaraan atau di lokasi wisata bukan tanggung jawab Orcha Journey.</li>
                            <li>Mematuhi arahan pemandu, aturan lokasi wisata, dan peraturan perundang-undangan yang berlaku.</li>
                            <li>Menyampaikan kondisi kesehatan khusus, alergi makanan, atau kebutuhan aksesibilitas sebelum keberangkatan.</li>
                            <li>Tidak membawa barang terlarang, minuman keras, atau senjata ke dalam armada.</li>
                        </ul>
                        <p>Peserta yang melanggar ketentuan di atas dapat dikeluarkan dari rombongan tanpa pengembalian biaya, dan biaya kepulangan menjadi tanggungan yang bersangkutan.</p>
                    ',
                ],
                [
                    'slug' => 'sewa-kendaraan',
                    'judul' => '6. Ketentuan Khusus Sewa Kendaraan',
                    'isi' => '
                        <ul>
                            <li>Harga sewa adalah harga unit. Sopir, BBM, tol, parkir, dan tiket masuk lokasi dihitung terpisah kecuali dinyatakan lain.</li>
                            <li>Sewa lepas kunci hanya berlaku untuk kategori mobil, dengan jaminan dan verifikasi identitas. HiAce dan bus selalu disertai sopir kami.</li>
                            <li>Kendaraan diserahkan dalam kondisi bersih dan bahan bakar sesuai catatan serah terima, dan dikembalikan dalam kondisi yang sama.</li>
                            <li>Kelebihan jam pemakaian dikenakan biaya tambahan yang dihitung per jam.</li>
                            <li>Kerusakan, kehilangan, atau tilang yang terjadi akibat kelalaian penyewa menjadi tanggung jawab penyewa.</li>
                            <li>Kendaraan dilarang digunakan untuk kegiatan melanggar hukum, balapan, atau dibawa keluar wilayah yang disepakati tanpa izin tertulis.</li>
                        </ul>
                    ',
                ],
                [
                    'slug' => 'perubahan',
                    'judul' => '7. Perubahan Jadwal & Itinerary',
                    'isi' => '
                        <p>Orcha Journey berhak menyesuaikan urutan kunjungan, jam keberangkatan, atau mengganti destinasi dengan destinasi setara apabila terjadi hal di luar kendali kami, seperti cuaca ekstrem, penutupan lokasi, kemacetan luar biasa, atau alasan keselamatan.</p>
                        <p>Perubahan yang diminta pemesan diupayakan sebaik mungkin, tetapi bergantung pada ketersediaan armada, akomodasi, dan kuota destinasi pada tanggal baru.</p>
                    ',
                ],
                [
                    'slug' => 'pembatalan',
                    'judul' => '8. Pembatalan',
                    'isi' => '
                        <p>Ketentuan pembatalan beserta besaran pengembalian dana diatur terpisah pada halaman <a href="' . route('kebijakan-pengembalian') . '">Kebijakan Pembatalan &amp; Pengembalian Dana</a>.</p>
                    ',
                ],
                [
                    'slug' => 'tanggung-jawab',
                    'judul' => '9. Batasan Tanggung Jawab',
                    'isi' => '
                        <p>Orcha Journey bertanggung jawab atas pelaksanaan layanan sesuai kesepakatan. Kami tidak bertanggung jawab atas kerugian yang timbul karena:</p>
                        <ul>
                            <li>Keadaan kahar seperti bencana alam, cuaca ekstrem, kerusuhan, wabah, atau kebijakan pemerintah.</li>
                            <li>Kelalaian peserta sendiri, termasuk kehilangan barang pribadi dan cedera akibat melanggar arahan pemandu.</li>
                            <li>Layanan pihak ketiga di luar kendali kami, seperti penutupan mendadak objek wisata.</li>
                        </ul>
                    ',
                ],
                [
                    'slug' => 'hukum',
                    'judul' => '10. Hukum yang Berlaku',
                    'isi' => '
                        <p>Ketentuan ini tunduk pada hukum Republik Indonesia. Setiap perselisihan diupayakan diselesaikan secara musyawarah terlebih dahulu; bila tidak tercapai kesepakatan, penyelesaian dilakukan melalui jalur hukum yang berlaku di wilayah domisili Orcha Journey.</p>
                        <p>Orcha Journey dapat memperbarui ketentuan ini sewaktu-waktu. Versi yang berlaku adalah versi yang tayang di halaman ini pada saat pemesanan dilakukan.</p>
                    ',
                ],
            ],
        ];
    }
}; ?>

<x-halaman-ketentuan title="Syarat & Ketentuan" eyebrow="Ketentuan Layanan"
    subtitle="Aturan main pemesanan, pelaksanaan perjalanan, dan penyewaan kendaraan di Orcha Journey."
    image="images/pantai-pinggir.webp" diperbarui="14 Agustus 2026" :sections="$sections">
    <p>
        Mohon dibaca sebelum melakukan pemesanan. Ketentuan di halaman ini berlaku untuk seluruh layanan Orcha Journey,
        baik paket wisata maupun sewa kendaraan.
    </p>
</x-halaman-ketentuan>
