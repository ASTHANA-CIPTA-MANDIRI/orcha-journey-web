<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Kebijakan Pembatalan & Pengembalian Dana — Orcha Journey')] class extends Component {
    public function with(): array
    {
        $tangga = config('orcha.pengembalian.tangga');
        $prosesHari = config('orcha.pengembalian.proses_hari_kerja');
        $atasNama = config('orcha.pembayaran.atas_nama');
        $pelunasan = config('orcha.pembayaran.pelunasan_hari_sebelum');

        $baris = fn (array $tangga) => collect($tangga)
            ->map(fn ($b) => "<tr><td>{$b['batas']}</td><td><strong>{$b['kembali']}</strong></td><td>{$b['potongan']}</td></tr>")
            ->implode('');

        $barisTangga = $baris($tangga);

        // Aturan sewa dibaca dari config yang sama dengan yang dipakai formulir
        // pembatalan. Sebelumnya angkanya diketik langsung di halaman ini, dan
        // dua tempat yang mengeja aturan yang sama akan berbeda cepat atau
        // lambat — biasanya diketahui setelah ada pelanggan yang membacanya.
        $barisTanggaSewa = $baris(config('orcha.pengembalian.tangga_sewa', []));

        $catatanSewa = collect(config('orcha.pengembalian.catatan_sewa', []))
            ->map(fn ($c) => "<li>{$c}</li>")
            ->implode('');

        // Dua aturan yang mengikat kedua tangga. Ditulis sekali di config lalu
        // dipakai halaman ini dan formulir pembatalan — aturan uang tidak
        // boleh punya dua salinan yang bisa berbeda.
        $aturanDasar = collect(config('orcha.pengembalian.aturan_dasar', []))
            ->map(fn ($a) => "<li>{$a}</li>")
            ->implode('');

        return [
            'sections' => [
                [
                    'slug' => 'dasar',
                    'judul' => '1. Dasar Kebijakan',
                    'isi' => "
                        <p>Begitu pemesanan dikunci, Orcha Journey langsung mengeluarkan biaya di muka: uang muka armada, pemesanan akomodasi, tiket masuk rombongan, dan penjadwalan tim. Karena itu pembatalan yang semakin dekat dengan tanggal keberangkatan menanggung potongan yang semakin besar.</p>
                        <p>Potongan dihitung dari <strong>total biaya pemesanan</strong>, bukan dari uang muka. Yang dikembalikan adalah pembayaran Anda dikurangi potongan itu.</p>
                        <ul>{$aturanDasar}</ul>
                        <p>Contoh: pemesanan Rp 2.000.000 yang sudah dilunasi lalu dibatalkan 3 hari sebelum berangkat menanggung potongan 100% — tidak ada pengembalian. Pemesanan yang sama, dibatalkan 20 hari sebelum berangkat, menanggung potongan 25% (Rp 500.000) dan mengembalikan Rp 1.500.000.</p>
                    ",
                ],
                [
                    'slug' => 'tangga-pengembalian',
                    'judul' => '2. Besaran Pengembalian Dana',
                    'isi' => "
                        <div class=\"table-wrap\"><table class=\"table-orcha\">
                            <thead><tr><th>Waktu Pembatalan</th><th>Dana Kembali</th><th>Potongan</th></tr></thead>
                            <tbody>{$barisTangga}</tbody>
                        </table></div>
                        <p>Perhitungan hari dihitung dari tanggal pemberitahuan pembatalan diterima tim kami, bukan tanggal pengajuan lisan.</p>
                    ",
                ],
                [
                    'slug' => 'cara-mengajukan',
                    'judul' => '3. Cara Mengajukan Pembatalan',
                    'isi' => "
                        <p>Cara paling cepat: isi <a href=\"" . route('pembatalan') . "\">formulir pengajuan pembatalan</a>. Bisa juga lewat WhatsApp dengan langkah berikut.</p>
                        <ol>
                            <li>Kirim pemberitahuan tertulis lewat WhatsApp resmi kami, sebutkan nama pemesan, tanggal keberangkatan, dan alasan pembatalan.</li>
                            <li>Lampirkan tanda terima pembayaran yang Anda simpan.</li>
                            <li>Tim kami mengirimkan perhitungan pengembalian untuk disetujui.</li>
                            <li>Setelah disetujui, dana dikirim ke rekening atas nama pemesan dalam {$prosesHari} hari kerja.</li>
                        </ol>
                        <p>Pengembalian hanya dikirim ke rekening atas nama pemesan yang sama dengan yang melakukan pembayaran, dan hanya dikirim dari rekening kami atas nama <strong>{$atasNama}</strong>.</p>
                    ",
                ],
                [
                    'slug' => 'pembatalan-kami',
                    'judul' => '4. Pembatalan dari Pihak Kami',
                    'isi' => '
                        <p>Bila Orcha Journey membatalkan keberangkatan — misalnya kuota open trip tidak terpenuhi atau armada tidak dapat dioperasikan — pemesan berhak memilih:</p>
                        <ul>
                            <li>Penjadwalan ulang ke tanggal lain tanpa biaya tambahan, atau</li>
                            <li>Pengembalian dana <strong>100% tanpa potongan apa pun</strong>.</li>
                        </ul>
                        <p>Untuk open trip, pemberitahuan pembatalan karena kuota kurang kami sampaikan paling lambat 3 hari sebelum keberangkatan.</p>
                    ',
                ],
                [
                    'slug' => 'keadaan-kahar',
                    'judul' => '5. Keadaan Kahar (Force Majeure)',
                    'isi' => '
                        <p>Pada kondisi di luar kendali kedua pihak — bencana alam, cuaca ekstrem, penutupan jalur, wabah, atau kebijakan pemerintah — keselamatan menjadi pertimbangan utama.</p>
                        <ul>
                            <li>Kami tawarkan penjadwalan ulang atau destinasi pengganti yang setara.</li>
                            <li>Biaya yang telanjur dibayarkan ke pihak ketiga dan tidak dapat ditarik kembali akan kami rinci secara terbuka dan dikurangkan dari pengembalian.</li>
                            <li>Sisa dana di luar biaya tersebut dikembalikan penuh.</li>
                        </ul>
                    ',
                ],
                [
                    'slug' => 'perubahan-peserta',
                    'judul' => '6. Pengurangan Peserta & Penggantian Nama',
                    'isi' => "
                        <ul>
                            <li>Pengurangan jumlah peserta lebih dari H-{$pelunasan} umumnya dapat disesuaikan tanpa potongan, sepanjang jumlah minimum paket tetap terpenuhi.</li>
                            <li>Pengurangan peserta kurang dari H-{$pelunasan} mengikuti tabel pengembalian di atas untuk porsi peserta yang batal.</li>
                            <li>Penggantian nama peserta (bukan pembatalan) tidak dikenakan biaya sepanjang jumlah peserta tetap sama.</li>
                        </ul>
                    ",
                ],
                [
                    'slug' => 'tidak-dikembalikan',
                    'judul' => '7. Hal yang Tidak Dapat Dikembalikan',
                    'isi' => '
                        <ul>
                            <li>Peserta yang tidak hadir di titik jemput tanpa pemberitahuan (<em>no show</em>).</li>
                            <li>Peserta yang mengundurkan diri di tengah perjalanan.</li>
                            <li>Peserta yang dikeluarkan dari rombongan karena melanggar ketentuan layanan.</li>
                            <li>Layanan yang sudah terpakai sebagian, dihitung berdasarkan porsi yang sudah berjalan.</li>
                        </ul>
                    ',
                ],
                [
                    'slug' => 'sewa-kendaraan',
                    'judul' => '8. Pembatalan Sewa Kendaraan',
                    'isi' => "
                        <p>Sewa kendaraan memakai tangga tersendiri, dan sengaja lebih longgar daripada
                        open trip. Kursi trip yang batal beberapa hari sebelum berangkat hampir mustahil
                        terjual lagi, sedangkan unit kendaraan yang batal hari ini masih mungkin tersewa
                        besok. Jaraknya dihitung ke <strong>waktu mulai sewa</strong>, bukan tanggal
                        keberangkatan rombongan.</p>
                        <div class=\"table-wrap\"><table class=\"table-orcha\">
                            <thead><tr><th>Waktu Pembatalan</th><th>Dana Kembali</th><th>Potongan</th></tr></thead>
                            <tbody>{$barisTanggaSewa}</tbody>
                        </table></div>
                        <ul>{$catatanSewa}
                            <li>Bila unit tidak dapat kami sediakan, kami mengganti dengan unit setara
                            atau mengembalikan dana 100%.</li>
                        </ul>
                    ",
                ],
            ],
        ];
    }
}; ?>

<x-halaman-ketentuan title="Kebijakan Pembatalan & Pengembalian Dana" eyebrow="Refund"
    subtitle="Besaran pengembalian dana, cara mengajukan, dan berapa lama prosesnya."
    image="images/pantai-pinggir-laut.jpg" diperbarui="14 Agustus 2026" :sections="$sections">
    <p>
        Kami berusaha membuat aturan pembatalan sejelas mungkin supaya tidak ada kejutan di kemudian hari.
        Semua angka di halaman ini berlaku untuk pemesanan yang dilakukan setelah tanggal pembaruan terakhir.
    </p>
</x-halaman-ketentuan>
