<?php

namespace App\Support;

use App\Models\SewaKendaraan\BagianPemeriksaan;
use Illuminate\Support\Facades\Schema;

/**
 * Satu-satunya pintu membaca daftar bagian pemeriksaan.
 *
 * Sebelum ini daftarnya dibaca langsung dari config di sepuluh tempat —
 * model, resource, dua controller, dan meta. Begitu daftarnya jadi data,
 * sepuluh tempat itu harus tahu hal yang sama: mana yang masih aktif, mana
 * yang hanya perlu dibaca namanya karena menempel di serah terima lama, dan
 * bagian mana yang berlaku untuk jenis unit yang sedang dibuka.
 *
 * Perbedaan yang paling mudah keliru, dan karenanya dipisah jadi dua metode
 * dengan nama yang tegas:
 *
 *   untuk()  — yang DIISI. Hanya yang aktif, dan hanya yang berlaku untuk
 *              jenis unit itu. Dipakai formulir dan penjagaan masukan.
 *   label()  — yang DIBACA. SEMUA baris, termasuk yang sudah dinonaktifkan,
 *              karena lembar serah terima setahun lalu tetap harus bisa
 *              menyebut nama bagiannya, bukan kunci mentahnya.
 */
class Pemeriksaan
{
    /** @var array<string, mixed>|null */
    private static ?array $memo = null;

    /** Dilupakan tiap barisnya berubah, supaya satu permintaan tidak membaca dua keadaan. */
    public static function lupakan(): void
    {
        self::$memo = null;
    }

    /**
     * Bagian yang diisi untuk satu jenis unit: kunci => label.
     *
     * $jenis null berarti seluruh yang aktif — dipakai layar yang belum tahu
     * unitnya, dan sebagai jaring pengaman bila jenis unitnya kosong.
     *
     * @return array<string, string>
     */
    public static function untuk(?string $jenis = null): array
    {
        $hasil = [];

        foreach (self::baris() as $bagian) {
            if ($bagian['aktif'] && ($jenis === null || in_array($jenis, $bagian['jenis'], true))) {
                $hasil[$bagian['kunci']] = $bagian['label'];
            }
        }

        return $hasil;
    }

    /** Kunci yang boleh dikirim untuk satu jenis unit. @return array<int, string> */
    public static function kunci(?string $jenis = null): array
    {
        return array_keys(self::untuk($jenis));
    }

    /**
     * Nama bagian untuk DIBACA — termasuk yang sudah dinonaktifkan.
     *
     * @return array<string, string>
     */
    public static function label(): array
    {
        $hasil = [];

        foreach (self::baris() as $bagian) {
            $hasil[$bagian['kunci']] = $bagian['label'];
        }

        return $hasil;
    }

    /**
     * Tarif perbaikan per bagian per tingkat kondisi.
     *
     * Ikut memuat yang sudah dinonaktifkan: unit yang terlanjur diserahkan
     * dengan bagian itu masih bisa kembali dalam keadaan rusak, dan dendanya
     * tetap harus terhitung.
     *
     * @return array<string, array<string, int>>
     */
    public static function tarif(): array
    {
        $hasil = [];

        foreach (self::baris() as $bagian) {
            $hasil[$bagian['kunci']] = [
                'lecet' => $bagian['biaya_lecet'],
                'rusak' => $bagian['biaya_rusak'],
                'hilang' => $bagian['biaya_hilang'],
            ];
        }

        return $hasil;
    }

    /** Daftar per jenis kendaraan, untuk dikirim sekali ke lemon. @return array<string, array<string, string>> */
    public static function perJenis(): array
    {
        $hasil = [];

        foreach (array_keys(config('orcha.jenis_kendaraan')) as $jenis) {
            $hasil[$jenis] = self::untuk($jenis);
        }

        return $hasil;
    }

    /**
     * Isi tabelnya, atau isi config bila tabelnya belum ada.
     *
     * Jaring pengaman untuk jeda antara berkas ter-deploy dan migrasi
     * dijalankan: selama beberapa menit itu tabelnya belum ada, dan tanpa
     * jaring ini SELURUH sewa kendaraan — formulir armada, serah terima,
     * usulan denda — roboh berbarengan.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function baris(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        if (! Schema::hasTable('bagian_pemeriksaan')) {
            return self::$memo = self::dariConfig();
        }

        $baris = BagianPemeriksaan::urut()->get()
            ->map(fn (BagianPemeriksaan $b) => [
                'kunci' => $b->kunci,
                'label' => $b->label,
                'jenis' => $b->jenis ?? [],
                'aktif' => $b->aktif,
                'biaya_lecet' => $b->biaya_lecet,
                'biaya_rusak' => $b->biaya_rusak,
                'biaya_hilang' => $b->biaya_hilang,
            ])->all();

        // Tabel kosong diperlakukan sama dengan tabel belum ada: yang dipakai
        // isi config, supaya basis data baru tidak menampilkan ceklis kosong.
        return self::$memo = $baris !== [] ? $baris : self::dariConfig();
    }

    /**
     * Bentuk config lama, dipakai sebagai cadangan DAN sebagai benih.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function dariConfig(): array
    {
        $tarif = config('orcha.biaya_kerusakan', []);
        $jenis = array_keys(config('orcha.jenis_kendaraan', []));
        $urutan = 0;

        $hasil = [];

        foreach (config('orcha.pemeriksaan_kendaraan', []) as $kunci => $label) {
            $hasil[] = [
                'kunci' => $kunci,
                'label' => $label,
                // Bawaannya berlaku untuk semua jenis — persis seperti
                // sebelumnya. Pemilahannya per jenis diserahkan ke admin.
                'jenis' => $jenis,
                'aktif' => true,
                'biaya_lecet' => (int) ($tarif[$kunci]['lecet'] ?? 0),
                'biaya_rusak' => (int) ($tarif[$kunci]['rusak'] ?? 0),
                'biaya_hilang' => (int) ($tarif[$kunci]['hilang'] ?? 0),
                'urutan' => $urutan += 10,
            ];
        }

        return $hasil;
    }
}
