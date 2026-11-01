<?php

namespace App\Support\Rab;

use App\Models\Rab\HariLibur;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use Illuminate\Support\Carbon;

/**
 * Satu-satunya tempat yang memutuskan TANGGAL MANA menentukan harga.
 *
 * Dipakai dua pihak: RabController saat menarik biaya, dan HitungRab saat
 * memberi tahu bahwa harga beku sebuah baris sudah tidak cocok lagi dengan
 * itinerary-nya. Menyalin aturannya ke dua tempat berarti suatu hari layar
 * mengatakan "harga akhir pekan" untuk baris yang ditarik sebagai hari kerja,
 * dan tidak ada yang bisa menyebut mana yang benar.
 *
 * Dibuat sekali per RAB, lalu ditanya berkali-kali: daftar hari libur dibaca
 * SEKALI, bukan sekali per baris biaya.
 */
class HargaHari
{
    /** @var array<string, string> tanggal 'Y-m-d' => nama liburnya */
    private array $libur;

    /** @var array<string, Carbon> destinasi (huruf kecil) => kunjungan pertama */
    private array $kunjungan;

    public function __construct(private Rab $rab)
    {
        $this->libur = HariLibur::peta();
        $this->kunjungan = $this->petaKunjungan();
    }

    /**
     * Tanggal setiap destinasi DIKUNJUNGI, bukan tanggal berangkat.
     *
     * Perjalanan yang berangkat Jumat dan sampai di Baluran hari Sabtu
     * membayar tarif akhir pekan. Memakai tanggal berangkat untuk seluruh
     * barisnya membuat tiketnya terhitung tarif hari kerja — dan selisihnya
     * baru ketahuan di gerbang, saat rombongannya sudah berdiri di sana.
     *
     * Kunjungan PERTAMA yang dipakai bila satu destinasi muncul beberapa
     * hari. Yang campuran disebut terpisah oleh campuran(), bukan dipilih
     * diam-diam.
     *
     * @return array<string, Carbon>
     */
    private function petaKunjungan(): array
    {
        if (! $this->rab->tanggal_mulai) {
            return [];
        }

        $peta = [];

        /*
         | Relasi yang SUDAH dimuat dipakai apa adanya.
         |
         | HitungRab::ringkas() dipanggil dari bentuk() yang baru saja memuat
         | itinerary-nya; menembak kueri baru di sini berarti satu kueri
         | tambahan tiap kali ringkasan dibaca — termasuk di kedua PDF.
         */
        $kegiatan = $this->rab->relationLoaded('itinerary')
            ? $this->rab->itinerary
            : $this->rab->itinerary()->whereNotNull('destinasi')->get();

        foreach ($kegiatan as $i) {
            $kunci = self::kunci($i->destinasi);

            if ($kunci === '') {
                continue;
            }

            $tanggal = $this->rab->tanggal_mulai->copy()->addDays(max(1, (int) $i->hari_ke) - 1);

            if (! isset($peta[$kunci]) || $tanggal->lt($peta[$kunci])) {
                $peta[$kunci] = $tanggal;
            }
        }

        return $peta;
    }

    public static function kunci(?string $teks): string
    {
        return mb_strtolower(trim((string) $teks));
    }

    /**
     * Tanggal yang menentukan harga sebuah baris.
     *
     * Baris bertiket memakai tanggal destinasinya. Biaya wajib provinsi —
     * armada, pemandu, asuransi — tidak terikat satu hari, jadi memakai
     * tanggal berangkat; yang lintas jenis hari disebut oleh campuran().
     */
    public function tanggal(?string $destinasi): ?Carbon
    {
        return $this->kunjungan[self::kunci($destinasi)] ?? $this->rab->tanggal_mulai;
    }

    /**
     * Harga master yang berlaku untuk RAB ini, berikut sebabnya.
     *
     * @return array{harga: int, sebab: string|null, akhir_pekan: bool}
     */
    public function untuk(MasterHarga $m): array
    {
        return $m->hargaUntuk($this->tanggal($m->destinasi), $this->libur);
    }

    /**
     * Apakah harga beku sebuah baris masih cocok dengan itinerary-nya.
     *
     * Memindahkan Baluran dari hari Sabtu ke hari Senin tidak mengubah harga
     * yang sudah dibekukan — itu memang aturannya — tetapi barisnya jadi
     * memegang tarif akhir pekan untuk kunjungan hari kerja, dan tidak ada
     * apa pun di layar yang mengatakannya. Menarik biaya lagi pun tidak
     * menolong: baris yang sudah ada tidak ditambahkan dua kali.
     *
     * Memulangkan sebab yang SEHARUSNYA, atau null bila sudah cocok.
     */
    public function tidakCocok(RabBiaya $b): ?string
    {
        if (! $b->master || ! $b->master->dua_harga) {
            return null;
        }

        $kini = $this->untuk($b->master);

        if ($kini['akhir_pekan'] === (bool) $b->pakai_akhir_pekan) {
            return null;
        }

        return $kini['akhir_pekan']
            ? $kini['sebab']
            : 'harga hari kerja ('.$this->tanggal($b->master->destinasi)
                ?->locale('id')->translatedFormat('l, j M').')';
    }

    /**
     * Baris yang harinya CAMPURAN — sebagian hari kerja, sebagian akhir pekan.
     *
     * Satu baris biaya hanya bisa memegang satu harga. Perjalanan Jumat–Minggu
     * yang menyewa bus per hari membayar satu hari tarif biasa dan dua hari
     * tarif akhir pekan, dan tidak ada satu angka pun yang benar untuk itu.
     *
     * Karena itu TIDAK dipilih diam-diam: barisnya disebut namanya, dan admin
     * memutuskan sendiri — biasanya dengan menggandakan barisnya. Memilih
     * salah satu tanpa memberi tahu adalah selisih yang tidak pernah terbaca
     * sampai tagihan vendornya datang.
     *
     * @param  iterable<MasterHarga>  $master
     * @return array<int, string>
     */
    public function campuran(iterable $master): array
    {
        if (! $this->lintasJenisHari()) {
            return [];
        }

        $nama = [];

        foreach ($master as $m) {
            /*
             | Hanya satuan yang DIKALI HARI. Tiket sekali masuk sudah benar
             | dengan tanggal kunjungannya, dan menyebutnya di sini hanya
             | menambah peringatan yang tidak punya jalan keluar.
             */
            if ($m->dua_harga && in_array($m->satuan, ['orang_hari', 'hari', 'unit_hari', 'kamar_malam'], true)) {
                $nama[] = $m->nama;
            }
        }

        return $nama;
    }

    /** Apakah perjalanannya melewati hari kerja DAN akhir pekan sekaligus. */
    public function lintasJenisHari(): bool
    {
        if (! $this->rab->tanggal_mulai || (int) $this->rab->jumlah_hari < 2) {
            return false;
        }

        $jenis = [];

        for ($h = 0; $h < max(1, (int) $this->rab->jumlah_hari); $h++) {
            $jenis[] = HariLibur::periksa(
                $this->rab->tanggal_mulai->copy()->addDays($h),
                $this->libur,
            )['akhir_pekan'];
        }

        return count(array_unique($jenis)) > 1;
    }
}
