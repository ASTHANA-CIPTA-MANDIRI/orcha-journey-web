<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Kebijakan Privasi — Orcha Journey')] class extends Component {
    public function with(): array
    {
        $email = config('orcha.email');

        return [
            'sections' => [
                [
                    'slug' => 'data-yang-dikumpulkan',
                    'judul' => '1. Data yang Kami Kumpulkan',
                    'isi' => '
                        <p>Kami hanya mengumpulkan data yang diperlukan untuk melayani perjalanan Anda:</p>
                        <ul>
                            <li><strong>Data pemesan:</strong> nama, nomor WhatsApp, dan alamat surel.</li>
                            <li><strong>Data peserta:</strong> nama peserta, dan bila diperlukan nomor identitas untuk asuransi, perizinan, atau pemesanan akomodasi.</li>
                            <li><strong>Data perjalanan:</strong> tanggal, tujuan, titik jemput, serta catatan kebutuhan khusus seperti alergi makanan.</li>
                            <li><strong>Bukti pembayaran</strong> yang Anda kirimkan kepada kami.</li>
                            <li><strong>Dokumentasi perjalanan</strong> berupa foto dan video kegiatan.</li>
                        </ul>
                        <p>Kami tidak pernah meminta PIN, kata sandi, maupun kode OTP perbankan Anda.</p>
                    ',
                ],
                [
                    'slug' => 'penggunaan',
                    'judul' => '2. Cara Kami Menggunakan Data',
                    'isi' => '
                        <ul>
                            <li>Menyusun itinerary, memesan armada, akomodasi, dan tiket masuk.</li>
                            <li>Menghubungi Anda terkait jadwal, titik jemput, dan perubahan mendadak.</li>
                            <li>Menerbitkan kuitansi serta dokumen administrasi untuk sekolah dan instansi.</li>
                            <li>Menjawab pertanyaan dan menangani keluhan.</li>
                            <li>Menayangkan testimoni di situs ini, hanya bila Anda mengirimkannya kepada kami.</li>
                        </ul>
                        <p>Kami tidak memakai data Anda untuk keperluan di luar hal-hal tersebut tanpa izin.</p>
                    ',
                ],
                [
                    'slug' => 'berbagi-data',
                    'judul' => '3. Pembagian Data ke Pihak Ketiga',
                    'isi' => '
                        <p>Data dibagikan seperlunya hanya kepada pihak yang terlibat langsung dalam perjalanan Anda:</p>
                        <ul>
                            <li>Penyedia armada dan sopir, untuk titik jemput dan daftar peserta.</li>
                            <li>Penginapan dan penyedia tiket, untuk keperluan pemesanan atas nama peserta.</li>
                            <li>Penyedia asuransi perjalanan, bila paket Anda menyertakan asuransi.</li>
                            <li>Aparat berwenang, bila diminta berdasarkan peraturan yang berlaku.</li>
                        </ul>
                        <p><strong>Kami tidak menjual, menyewakan, atau menukarkan data Anda kepada pihak mana pun untuk keperluan pemasaran.</strong></p>
                    ',
                ],
                [
                    'slug' => 'dokumentasi',
                    'judul' => '4. Foto & Video Perjalanan',
                    'isi' => '
                        <p>Dokumentasi kegiatan dapat kami gunakan untuk media sosial dan galeri di situs ini. Bila Anda atau peserta tidak bersedia wajahnya ditampilkan, sampaikan kepada pemandu saat perjalanan atau hubungi kami setelahnya — foto terkait akan kami turunkan.</p>
                        <p>Untuk rombongan sekolah, izin publikasi dokumentasi kami mintakan melalui penanggung jawab rombongan.</p>
                    ',
                ],
                [
                    'slug' => 'penyimpanan',
                    'judul' => '5. Penyimpanan & Keamanan',
                    'isi' => '
                        <ul>
                            <li>Data pemesanan disimpan selama diperlukan untuk keperluan administrasi dan pembukuan.</li>
                            <li>Akses ke data pemesanan dibatasi hanya untuk tim yang menangani perjalanan Anda.</li>
                            <li>Situs ini memakai koneksi terenkripsi dan panel pengelolaan yang dilindungi kata sandi.</li>
                        </ul>
                        <p>Meski kami berusaha menjaganya, tidak ada sistem yang sepenuhnya bebas risiko. Bila terjadi insiden yang berdampak pada data Anda, kami akan memberi tahu secepatnya.</p>
                    ',
                ],
                [
                    'slug' => 'cookie',
                    'judul' => '6. Cookie',
                    'isi' => '
                        <p>Situs ini memakai cookie seperlunya untuk menjaga sesi masuk panel admin dan keamanan formulir. Kami tidak memasang pelacak iklan pihak ketiga. Anda dapat menghapus cookie kapan saja lewat pengaturan peramban; halaman publik tetap dapat diakses tanpa cookie.</p>
                    ',
                ],
                [
                    'slug' => 'hak-anda',
                    'judul' => '7. Hak Anda atas Data',
                    'isi' => "
                        <p>Anda berhak meminta:</p>
                        <ul>
                            <li>Salinan data yang kami simpan tentang Anda.</li>
                            <li>Perbaikan data yang keliru.</li>
                            <li>Penghapusan data, sepanjang tidak bertentangan dengan kewajiban pembukuan kami.</li>
                            <li>Penarikan izin publikasi dokumentasi perjalanan.</li>
                        </ul>
                        <p>Ajukan permintaan tersebut ke <a href=\"mailto:{$email}\">{$email}</a> atau nomor WhatsApp resmi kami. Permintaan diproses paling lama 14 hari kerja.</p>
                    ",
                ],
                [
                    'slug' => 'anak',
                    'judul' => '8. Data Peserta Anak',
                    'isi' => '
                        <p>Untuk peserta di bawah 17 tahun, data dikumpulkan melalui orang tua, wali, atau pihak sekolah sebagai penanggung jawab rombongan. Kami tidak mengumpulkan data anak secara langsung tanpa sepengetahuan mereka.</p>
                    ',
                ],
                [
                    'slug' => 'perubahan',
                    'judul' => '9. Perubahan Kebijakan',
                    'isi' => '
                        <p>Kebijakan ini dapat diperbarui mengikuti perubahan layanan atau ketentuan hukum. Tanggal pembaruan terakhir selalu tercantum di halaman ini, dan versi yang berlaku adalah versi yang tayang saat Anda menggunakan layanan kami.</p>
                    ',
                ],
            ],
        ];
    }
}; ?>

<x-halaman-ketentuan title="Kebijakan Privasi" eyebrow="Privasi"
    subtitle="Data apa yang kami kumpulkan, untuk apa dipakai, dan hak Anda atas data tersebut."
    image="images/pantai-atas.jpg" diperbarui="14 Agustus 2026" :sections="$sections">
    <p>
        Orcha Journey hanya meminta data yang benar-benar diperlukan untuk menjalankan perjalanan Anda,
        dan tidak membagikannya kepada pihak yang tidak berkepentingan.
    </p>
</x-halaman-ketentuan>
