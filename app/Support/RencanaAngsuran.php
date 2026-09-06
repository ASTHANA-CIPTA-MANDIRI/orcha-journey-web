<?php

namespace App\Support;

use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Support\Carbon;

/**
 * Aturan angsuran: berapa kali boleh, berapa nominalnya, dan kapan jatuh
 * temponya.
 *
 * BERAPA KALI DITENTUKAN SISTEM, bukan diketik siapa pun — admin hanya memilih
 * dari yang diizinkan. Nominal bebas pernah dipertimbangkan dan ditolak:
 * pelanggan yang membayar Rp 10.000 tiga kali merasa sudah mencicil, padahal
 * pada H-5 ia masih harus melunasi hampir seluruhnya. Yang dipindahkan bukan
 * bebannya, melainkan waktu kejutannya.
 *
 * Dua hal membatasi, dan YANG LEBIH KECIL yang berlaku:
 *
 *   HARGA — tangga di config. Pesanan kecil tidak bisa diangsur sama sekali;
 *   memecah Rp 1,5 juta jadi tiga kali menambah dua kali pekerjaan menagih
 *   tanpa meringankan siapa pun.
 *
 *   KALENDER — tenggat pelunasan TIDAK ikut mundur. Biaya operasional sudah
 *   keluar sebelum berangkat, jadi rencananya harus habis sebelum H-5. Ini
 *   batasan yang paling mudah terlupa, dan akibat melupakannya bukan
 *   pelanggan tertolong melainkan jadwal yang gagal sejak hari pertama.
 */
class RencanaAngsuran
{
    /**
     * Berapa kali pesanan ini boleh diangsur. 1 berarti tidak boleh.
     *
     * Termin pertama selalu terhitung: rencana "3x" berarti uang muka plus dua
     * angsuran, bukan uang muka plus tiga.
     */
    public static function maksTermin(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): int
    {
        $tagihan = TagihanPesanan::untuk($pesanan);

        if ($pesanan === null || $tagihan === [] || $tagihan['lunas']) {
            return 1;
        }

        return min(self::maksMenurutHarga($tagihan['total']), self::maksMenurutKalender($pesanan));
    }

    /** Tangga harga; ambang pertama yang terpenuhi yang berlaku. */
    private static function maksMenurutHarga(int $total): int
    {
        foreach ((array) config('orcha.pembayaran.angsuran.tangga', []) as $jenjang) {
            if ($total >= (int) $jenjang['min_total']) {
                return (int) $jenjang['maks_termin'];
            }
        }

        return 1;
    }

    /**
     * Berapa termin yang MUAT sampai tenggat pelunasan.
     *
     * Termin pertama jatuh tempo besok — pelanggan yang meminta keringanan
     * tetap harus menahan kursinya. Sisanya berjarak minimal jarak_hari, dan
     * yang terakhir mendarat tepat di tenggat. Jadi untuk n termin dibutuhkan
     * (n-1) jarak.
     */
    private static function maksMenurutKalender(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan): int
    {
        $tenggat = self::tenggat($pesanan);

        if (! $tenggat) {
            return 1;
        }

        $jarak = max(1, (int) config('orcha.pembayaran.angsuran.jarak_hari', 14));
        $tersedia = self::mulai()->diffInDays($tenggat, false);

        if ($tersedia < 0) {
            return 1;
        }

        return intdiv((int) $tersedia, $jarak) + 1;
    }

    /**
     * Jadwal yang akan terbit bila rencana ini dibuat — belum disimpan.
     *
     * Dipakai layar admin untuk memperlihatkan angkanya SEBELUM disepakati.
     * Menjanjikan "3x angsuran" tanpa menyebut nominalnya adalah janji yang
     * tidak bisa dinilai pelanggan.
     *
     * @return array<int, array{urutan: int, nominal: int, jatuh_tempo: Carbon, label: string}>
     */
    public static function susun(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan, int $jumlahTermin): array
    {
        $tagihan = TagihanPesanan::untuk($pesanan);
        $maks = self::maksTermin($pesanan);

        if ($tagihan === [] || $jumlahTermin < 2 || $jumlahTermin > $maks) {
            return [];
        }

        $mulai = self::mulai();
        $tenggat = self::tenggat($pesanan);

        /*
         | Termin pertama sebesar UANG MUKA, bukan sepertiga rata.
         |
         | Kursi ditahan setelah uang muka masuk (orcha.pembayaran.dp_lepas_jam).
         | Termin pertama yang lebih kecil berarti kursi tertahan berhari-hari
         | oleh pembayaran seuprit — persis hal yang membuat nominal bebas
         | ditolak, cuma pindah tempat.
         */
        $jadwal = [[
            'urutan' => 1,
            'nominal' => $tagihan['dp'],
            'jatuh_tempo' => $mulai,
            'label' => 'Uang muka '.$tagihan['dp_persen'].'%',
        ]];

        $sisa = $tagihan['total'] - $tagihan['dp'];
        $banyak = $jumlahTermin - 1;

        // Dibulatkan ke ribuan supaya angkanya enak dibaca dan diketik; sisa
        // pembulatannya diserap termin terakhir, jadi jumlahnya selalu pas.
        $per = (int) (floor($sisa / $banyak / 1000) * 1000);

        // Jaraknya dibagi rata antara termin pertama dan tenggat, jadi yang
        // terakhir mendarat TEPAT di tenggat pelunasan.
        $rentang = $mulai->diffInDays($tenggat, false);

        for ($i = 1; $i <= $banyak; $i++) {
            $terakhir = $i === $banyak;

            $jadwal[] = [
                'urutan' => $i + 1,
                'nominal' => $terakhir ? $sisa - ($per * ($banyak - 1)) : $per,
                'jatuh_tempo' => $mulai->copy()->addDays((int) round($rentang * $i / $banyak)),
                'label' => 'Angsuran ke-'.$i,
            ];
        }

        return $jadwal;
    }

    /**
     * Posisi tiap termin terhadap uang yang sudah masuk.
     *
     * Status DITURUNKAN, bukan disimpan: termin ke-n lunas bila total
     * pembayaran sudah menutup jumlah termin 1..n. Tidak ada alokasi, tidak
     * ada kasus khusus untuk yang membayar lebih, kurang, atau dua termin
     * sekaligus — dan karena itu mustahil desinkron dengan tagihan.
     *
     * @return array<int, array{urutan: int, nominal: int, jatuh_tempo: Carbon, kurang: int, status: string}>
     */
    public static function posisi(?Angsuran $rencana): array
    {
        if (! $rencana) {
            return [];
        }

        $sudah = (int) (TagihanPesanan::untuk($rencana->pesanan(), hanyaDiterima: true)['sudah'] ?? 0);

        $kumulatif = 0;
        $hasil = [];

        foreach ($rencana->termin as $termin) {
            $kumulatif += $termin->nominal;
            $kurang = max(0, $kumulatif - $sudah);

            $hasil[] = [
                'urutan' => $termin->urutan,
                'nominal' => $termin->nominal,
                'jatuh_tempo' => $termin->jatuh_tempo,
                'kurang' => $kurang,
                'status' => match (true) {
                    $kurang === 0 => 'lunas',
                    $termin->jatuh_tempo->isPast() => 'telat',
                    default => 'menunggu',
                },
            ];
        }

        return $hasil;
    }

    /**
     * Nama termin sebagaimana dibaca pelanggan.
     *
     * Termin pertama SELALU uang muka — bukan "Angsuran ke-1". Rencana 3x
     * berarti uang muka plus dua angsuran, dan menyebut yang pertama sebagai
     * angsuran membuat pelanggan mengira ada empat pembayaran.
     *
     * Satu tempat, dipakai halaman pembayaran maupun surat tanda terima.
     * Sebelumnya kalimatnya dirakit ulang di tiap layar, dan penamaan yang
     * dirakit dua kali akan berbeda suatu saat — pelanggan lalu membaca
     * "Angsuran ke-1" di email untuk baris yang di layar bernama "Uang muka".
     */
    public static function labelTermin(int $urutan): string
    {
        return $urutan === 1 ? 'Uang muka' : 'Angsuran ke-'.($urutan - 1);
    }

    /**
     * Fakta angsuran yang perlu diketahui pelanggan, siap dirangkai jadi
     * kalimat.
     *
     * Mengembalikan FAKTA, bukan kalimat jadi, karena tempat yang memakainya
     * berbeda mediumnya: surat tanda terima menulis teks biasa, sedangkan
     * berkas pendaftaran menulis HTML bertebal. Yang tidak boleh berbeda
     * angkanya — dan angka yang dihitung di dua tempat akan berbeda suatu
     * hari.
     *
     * null berarti pesanan ini tidak berangsur, atau seluruh terminnya sudah
     * tertutup. Pemanggil lalu memakai kalimat pelunasan biasa.
     *
     * @return array{lunas:int, jumlah:int, label:string, kurang:int, kurang_teks:string, jatuh_tempo_teks:string, telat:bool}|null
     */
    public static function ringkasUntukPelanggan(?string $kode): ?array
    {
        $rencana = Angsuran::aktifUntuk($kode);

        if (! $rencana) {
            return null;
        }

        $posisi = self::posisi($rencana);
        $berikutnya = collect($posisi)->firstWhere('status', '!=', 'lunas');

        if (! $berikutnya) {
            return null;
        }

        return [
            'lunas' => collect($posisi)->where('status', 'lunas')->count(),
            'jumlah' => count($posisi),
            'label' => self::labelTermin((int) $berikutnya['urutan']),
            'kurang' => (int) $berikutnya['kurang'],
            'kurang_teks' => RincianBiaya::rupiah((int) $berikutnya['kurang']),
            'jatuh_tempo_teks' => $berikutnya['jatuh_tempo']->locale('id')->translatedFormat('j F Y'),
            'telat' => $berikutnya['status'] === 'telat',
        ];
    }

    /** Termin terdekat yang belum tertutup pembayaran. */
    public static function terminBerikutnya(?Angsuran $rencana): ?array
    {
        foreach (self::posisi($rencana) as $termin) {
            if ($termin['status'] !== 'lunas') {
                return $termin;
            }
        }

        return null;
    }

    /** Batas akhir pelunasan: H-5 sebelum berangkat, atau mulai sewa. */
    private static function tenggat(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan): ?Carbon
    {
        $mulai = $pesanan instanceof PenyewaanKendaraan
            ? $pesanan->tanggal_mulai
            : $pesanan->tanggal_berangkat;

        if (! $mulai) {
            return null;
        }

        return $mulai->copy()->startOfDay()
            ->subDays((int) config('orcha.pembayaran.pelunasan_hari_sebelum', 5));
    }

    /** Jatuh tempo termin pertama: besok, sesuai batas uang muka. */
    private static function mulai(): Carbon
    {
        return now()->startOfDay()
            ->addDays((int) ceil(config('orcha.pembayaran.dp_batas_jam', 24) / 24));
    }
}
