<?php

namespace App\Support;

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

/**
 * Posisi tagihan sebuah pesanan: berapa totalnya, berapa yang sudah
 * dilaporkan masuk, dan berapa sisanya.
 *
 * Dipakai formulir konfirmasi pembayaran untuk mengisikan nominalnya
 * sendiri. Sebelumnya pelanggan mengetik angkanya dari ingatan, dan
 * salah ketik satu digit membuat pembayaran tidak cocok dengan mutasi
 * rekening — pekerjaan yang berakhir di WhatsApp admin.
 *
 * Yang dihitung "sudah dilaporkan" adalah bukti yang belum ditolak,
 * termasuk yang masih menunggu dicek. Alasannya sederhana: pelanggan
 * yang baru mengirim bukti DP satu jam lalu memang sedang menunggu, dan
 * kalau ia kembali untuk melunasi, yang ditawarkan seharusnya sisanya —
 * bukan DP yang sama untuk kedua kalinya.
 */
class TagihanPesanan
{
    /**
     * @return array<string, mixed> kosong bila pesanannya tidak dikenal
     *                              atau harganya memang belum ada
     */
    public static function untuk(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan, bool $hanyaDiterima = false): array
    {
        if (! $pesanan) {
            return [];
        }

        $total = self::total($pesanan);

        if ($total <= 0) {
            return [];
        }

        // Dua pertanyaan berbeda, dua hitungan berbeda.
        //
        // Untuk mengisikan nominal di formulir pelanggan, bukti yang masih
        // menunggu dicek ikut dihitung — orang yang baru mengirim bukti DP satu
        // jam lalu memang sedang menunggu.
        //
        // Untuk memajukan STATUS pesanan, hanya yang sudah diterima yang boleh
        // dihitung. Status "DP Masuk" berarti uangnya sudah ada, bukan sudah
        // diklaim; kalau tidak, siapa pun bisa memajukan statusnya sendiri
        // hanya dengan mengunggah gambar.
        $sudah = (int) KonfirmasiPembayaran::where('kode', $pesanan->kode)
            ->when($hanyaDiterima,
                fn ($q) => $q->where('status', 'diterima'),
                fn ($q) => $q->where('status', '!=', 'ditolak'),
            )
            ->sum('nominal');

        $sisa = max(0, $total - $sudah);
        $persenDp = self::persenDp($pesanan);
        $dp = (int) round($total * $persenDp / 100);

        // Belum ada yang masuk → yang ditagih uang mukanya. Sudah ada →
        // yang ditagih sisanya, berapa pun yang sudah terkirim sebelumnya.
        $jenis = $sudah <= 0 ? 'dp' : 'pelunasan';
        $nominal = $sudah <= 0 ? $dp : $sisa;

        /*
         | Kode unik ditambahkan ke nominal yang HARUS DITRANSFER.
         |
         | Tagihan bulat (Rp 750.000) memaksa admin mencocokkan tangkapan layar
         | dengan mutasi rekening satu per satu — dan sejak kursi dilepas
         | otomatis dalam 72 jam, verifikasi yang lambat langsung berbiaya
         | kursi.
         |
         | Dengan tiga digit unik, satu nominal di mutasi menunjuk tepat satu
         | pemesanan: Rp 750.013 bukan Rp 750.047. Admin cukup mencocokkan
         | angkanya. Pola ini sudah lazim di Indonesia, jadi pelanggan pun tidak
         | perlu dijelaskan panjang.
         |
         | Angka totalnya sendiri TIDAK berubah — yang bertambah hanya nominal
         | transfernya. Kelebihan bayarnya diserap seperti pada umumnya kode
         | unik, dan tidak pernah lebih dari Rp 999.
         */
        $kodeUnik = self::kodeUnik($pesanan);
        $nominalTransfer = $nominal > 0 ? $nominal + $kodeUnik : 0;

        return [
            'total' => $total,
            'total_teks' => RincianBiaya::rupiah($total),
            'sudah' => $sudah,
            'sudah_teks' => RincianBiaya::rupiah($sudah),
            'sisa' => $sisa,
            'sisa_teks' => RincianBiaya::rupiah($sisa),
            'dp' => $dp,
            'dp_teks' => RincianBiaya::rupiah($dp),
            'dp_persen' => $persenDp,
            'lunas' => $sisa <= 0,
            'jenis_disarankan' => $jenis,

            /*
             | nominal_disarankan adalah yang DITRANSFER — sudah termasuk kode
             | uniknya. Itu yang mengisi sendiri isian nominal di formulir dan
             | yang dibaca pelanggan, jadi angka yang dilihatnya sama persis
             | dengan yang harus ia ketik di aplikasi bank.
             */
            'nominal_disarankan' => $sisa <= 0 ? 0 : $nominalTransfer,
            'nominal_disarankan_teks' => RincianBiaya::rupiah($sisa <= 0 ? 0 : $nominalTransfer),

            // Dipisah supaya tampilan bisa menerangkan angkanya: "Rp 750.000
            // + kode unik 13". Tanpa itu pelanggan mengira harganya naik.
            'kode_unik' => $sisa <= 0 ? 0 : $kodeUnik,
            'nominal_pokok' => $sisa <= 0 ? 0 : $nominal,
        ];
    }

    /**
     * Tiga digit unik yang ditempelkan ke nominal transfer.
     *
     * Diturunkan dari KODE PEMESANAN, bukan diacak dan disimpan.
     *
     * Alasannya kestabilan: pelanggan sering membuka halaman pembayaran
     * berkali-kali — melihat nominalnya, menutup, membuka lagi saat sudah di
     * depan aplikasi bank. Angka yang berubah tiap muat akan membuatnya
     * mentransfer jumlah yang tidak kita tunggu, dan justru merusak hal yang
     * hendak diperbaiki. Turunan dari kode pemesanan selalu menghasilkan angka
     * yang sama tanpa perlu satu kolom pun di basis data.
     *
     * Rentangnya 1–999: tidak pernah nol (nol tidak membedakan apa pun) dan
     * tidak pernah mencapai seribu, sesuai batas yang ditetapkan.
     *
     * Tabrakan mungkin terjadi bila dua pesanan kebetulan bernominal pokok
     * SAMA dan berkode unik sama — peluangnya satu per 999. Yang terjadi bila
     * itu muncul: admin kembali mencocokkan manual untuk dua baris itu saja,
     * persis seperti sebelum ada kode unik. Itu sebabnya tabrakan tidak
     * dianggap sepadan dengan tambahan satu kolom dan satu migrasi.
     */
    public static function kodeUnik(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): int
    {
        if (! $pesanan || blank($pesanan->kode)) {
            return 0;
        }

        return (crc32($pesanan->kode) % 999) + 1;
    }

    /**
     * Bukti yang sudah dikirim penyewa tetapi belum dicek admin.
     *
     * Dipisah dari posisi tagihan karena artinya berbeda, dan perbedaan itu
     * yang sering salah dibaca: uangnya mungkin memang sudah masuk rekening,
     * tetapi selama buktinya belum diterima, sistem tidak boleh menganggapnya
     * ada. Kalau boleh, siapa pun bisa memajukan status pesanannya sendiri
     * hanya dengan mengunggah gambar.
     *
     * Yang perlu tahu justru admin di loket: unit hendak diserahkan, statusnya
     * masih "Baru", dan sebabnya bukan karena penyewa belum membayar melainkan
     * karena buktinya belum sempat dibuka.
     *
     * @return array{nominal: int, berkas: int}
     */
    public static function menungguDicek(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): array
    {
        if (! $pesanan) {
            return ['nominal' => 0, 'berkas' => 0];
        }

        $bukti = KonfirmasiPembayaran::where('kode', $pesanan->kode)->menunggu();

        return [
            'nominal' => (int) (clone $bukti)->sum('nominal'),
            'berkas' => (int) $bukti->count(),
        ];
    }

    /**
     * Uang yang sudah DITERIMA, dipecah menurut jenis pembayarannya.
     *
     * Satu baris "sudah dibayar" menjawab berapa, tetapi tidak menjawab yang
     * ditanyakan berikutnya: itu uang mukanya atau pelunasannya. Pertanyaan itu
     * muncul persis saat menagih sisa, dan jawabannya menentukan kalimat yang
     * dipakai admin.
     *
     * Hanya yang berstatus diterima. Yang masih menunggu dicek belum uang, dan
     * memasukkannya ke daftar pengurang berarti mengurangi tagihan berdasarkan
     * gambar yang belum diperiksa siapa pun.
     *
     * @return array<int, array{jenis: string, label: string, nominal: int, berkas: int}>
     */
    public static function diterimaPerJenis(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): array
    {
        if (! $pesanan) {
            return [];
        }

        $label = (array) config('orcha.jenis_pembayaran', []);

        return KonfirmasiPembayaran::where('kode', $pesanan->kode)
            ->where('status', 'diterima')
            ->get()
            ->groupBy('jenis')
            ->map(fn ($bukti, $jenis) => [
                'jenis' => (string) $jenis,
                'label' => $label[$jenis] ?? 'Pembayaran',
                'nominal' => (int) $bukti->sum('nominal'),
                'berkas' => $bukti->count(),
            ])
            ->values()
            ->all();
    }

    /** Nominal yang pantas ditawarkan untuk satu jenis pembayaran. */
    public static function nominalUntukJenis(array $tagihan, string $jenis): ?int
    {
        if ($tagihan === [] || $tagihan['lunas']) {
            return null;
        }

        return match ($jenis) {
            // DP yang sudah pernah dilaporkan tidak ditawarkan dua kali.
            'dp' => $tagihan['sudah'] > 0 ? $tagihan['sisa'] : $tagihan['dp'],
            'pelunasan', 'sewa' => $tagihan['sisa'],
            default => null,
        };
    }

    private static function total(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan): int
    {
        if ($pesanan instanceof PenyewaanKendaraan) {
            /*
             | Termasuk dendanya, bukan biaya sewanya saja.
             |
             | Denda keterlambatan dan kerusakan sama-sama ditagihkan ke penyewa
             | dan sama-sama harus ia bayar. Selama yang dihitung hanya biaya
             | sewa, halaman serah terima menyebut "Total tagihan Rp 2.150.000"
             | sementara "Sisa tagihan" di kartu pembayaran menyebut Rp 210.000 —
             | dua angka untuk satu tagihan yang sama, di layar yang sama.
             |
             | Yang dibaca penyewa saat menagih adalah yang lebih besar, jadi
             | itulah yang harus dipakai menghitung sisanya.
             */
            return (int) $pesanan->total_tagihan;
        }

        $total = (int) (RincianBiaya::untuk($pesanan->paket, (int) $pesanan->jumlah_peserta)['total'] ?? 0);

        /*
         | Potongan rujukan dikurangkan dari angka yang DIBEKUKAN di
         | pendaftarannya, bukan dihitung ulang dari config.
         |
         | Angka rujukan berubah sepanjang tahun. Menghitungnya ulang di sini
         | berarti tagihan orang yang mendaftar bulan lalu ikut bergeser saat
         | seseorang menyunting angkanya hari ini — dan pergeserannya baru
         | ketahuan ketika ia sudah mentransfer jumlah yang lama.
         */
        return max(0, $total - (int) ($pesanan->potongan_rujukan ?? 0));
    }

    private static function persenDp(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan): int
    {
        if ($pesanan instanceof PendaftaranOpenTrip && $pesanan->paket?->category === 'study_tour') {
            return (int) config('orcha.pembayaran.dp_persen_study_tour');
        }

        return (int) config('orcha.pembayaran.dp_persen');
    }
}
