<?php

namespace App\Support\Rab;

use App\Models\Rab\HariLibur;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\MusimHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /** @var Collection<int, MusimHarga> musim yang berlaku, terurut dari yang menang */
    private Collection $musim;

    /** @var array<string, Carbon> destinasi (huruf kecil) => kunjungan pertama */
    private array $kunjungan;

    public function __construct(private Rab $rab)
    {
        $this->libur = HariLibur::peta();
        // Dibaca SEKALI, bukan sekali per baris biaya. Satu RAB bisa punya 30
        // baris, dan jalur ini ikut dilewati kedua PDF.
        $this->musim = MusimHarga::berlaku();
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
        return $m->hargaUntuk($this->tanggal($m->destinasi), $this->libur, $this->musim);
    }

    /** Musim yang memuat tanggal sebuah destinasi, atau null. */
    public function musimUntuk(?string $destinasi): ?MusimHarga
    {
        return MusimHarga::pada($this->tanggal($destinasi), $this->musim);
    }

    /**
     * Baris master yang jatuh DI DALAM musim tetapi tidak punya harga musimnya.
     *
     * Bukan kesalahan, dan karena itu bukan galat: tidak semua harga naik di
     * Nataru — tiket Candi Arjuna tetap Rp 10.000 pada 25 Desember. Tetapi
     * "memang tidak naik" dan "lupa diisi" terlihat persis sama dari tabel,
     * dan yang kedua baru terbaca di meja resepsionis hotel.
     *
     * Jadi disebut namanya, bukan dipilih diam-diam — alasan yang sama dengan
     * tidakCocok() dan campuran(). Admin yang melihat "Hotel Dieng belum
     * punya harga Nataru" akan tahu dalam dua detik apakah itu benar.
     *
     * @param  iterable<MasterHarga>  $master
     * @return array<int, array{nama: string, musim: string}>
     */
    public function belumPunyaHargaMusim(iterable $master): array
    {
        if ($this->musim->isEmpty()) {
            return [];
        }

        $hasil = [];

        foreach ($master as $m) {
            $musim = $this->musimUntuk($m->destinasi);

            if (! $musim) {
                continue;
            }

            /*
             | Yang SUDAH memakai tarif musim dilewati.
             |
             | Diperiksa lewat hargaUntuk(), bukan dengan mencari musim_id
             | pemenang: bila pemenangnya tidak punya harga di baris ini
             | sementara musim lain yang memuat tanggal yang sama punya, baris
             | itu sudah bertarif musim dan tidak perlu disebut.
             */
            if ($this->untuk($m)['tingkat'] === 'musim') {
                continue;
            }

            $hasil[] = ['nama' => $m->nama, 'musim' => $musim->nama];
        }

        return $hasil;
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
        if (! $b->master) {
            return null;
        }

        /*
         | Baris yang hanya punya SATU harga tidak bisa salah tingkat.
         |
         | Memeriksanya tetap tidak berbahaya, tetapi menghasilkan peringatan
         | yang tidak punya jalan keluar: tidak ada tarif lain untuk dipindah
         | ke sana.
         */
        if (! $b->master->dua_harga && ! $b->master->punya_harga_musim) {
            return null;
        }

        $kini = $this->untuk($b->master);

        /*
         | Dibandingkan TINGKATNYA, bukan lagi benar/salah akhir pekan.
         |
         | Baris bertarif Nataru dan baris bertarif hari kerja keduanya
         | punya pakai_akhir_pekan = false. Membandingkan boolean itu membuat
         | keduanya terbaca cocok, dan RAB yang digeser keluar dari rentang
         | Nataru akan tetap memegang tarif Nataru tanpa satu pun tanda —
         | penawaran yang kemahalan, lalu kalah, dan tidak ada yang tahu
         | sebabnya.
         |
         | musim_id ikut dibandingkan: berpindah dari Nataru ke Lebaran tetap
         | 'musim' di kedua sisi, tetapi angkanya lain.
         */
        $samaTingkat = $kini['tingkat'] === $b->tingkat
            && (int) ($kini['musim_id'] ?? 0) === (int) ($b->musim_id ?? 0);

        if ($samaTingkat) {
            return null;
        }

        if ($kini['tingkat'] !== 'hari_kerja') {
            return $kini['sebab'];
        }

        $tanggal = $this->tanggal($b->master->destinasi);

        /*
         | Tanggal yang DIKOSONGKAN disebut apa adanya.
         |
         | Admin yang menghapus tanggal berangkat sesudah biaya ditarik akan
         | melihat "harga hari kerja ()" — tanda baca tanpa isi, yang terbaca
         | sebagai layar rusak alih-alih sebagai tanggal yang hilang.
         */
        return $tanggal === null
            ? 'tanggal berangkatnya dikosongkan'
            : 'harga hari kerja ('.$tanggal->locale('id')->translatedFormat('l, j M').')';
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
            /*
             | Daftarnya milik HitungRab, bukan salinan di sini.
             |
             | Dua daftar satuan berdurasi akan berselisih begitu satuan baru
             | ditambahkan — dan yang kalah adalah peringatan ini, yang
             | berhenti menyebut satuan yang justru paling baru.
             */
            if (! in_array($m->satuan, HitungRab::SATUAN_BERDURASI, true)) {
                continue;
            }

            /*
             | Yang menentukan bukan "perjalanannya lintas hari", melainkan
             | apakah HARGA BARIS INI benar-benar berbeda di sepanjangnya.
             |
             | Hotel tanpa tarif akhir pekan maupun tarif musim membayar angka
             | yang sama Jumat sampai Minggu; menyebutnya campuran hanya
             | karena perjalanannya melewati Sabtu adalah peringatan yang
             | tidak ada jalan keluarnya — dan peringatan yang selalu muncul
             | adalah peringatan yang berhenti dibaca, termasuk saat ia benar.
             */
            if (count($this->hargaTiapHari($m)) > 1) {
                $nama[] = $m->nama;
            }
        }

        return $nama;
    }

    /**
     * Harga baris ini pada SETIAP hari perjalanan, tanpa kembar.
     *
     * Lebih dari satu angka berarti tidak ada satu pun angka yang benar untuk
     * seluruh barisnya.
     *
     * @return array<int, int>
     */
    private function hargaTiapHari(MasterHarga $m): array
    {
        $harga = [];

        for ($h = 0; $h < max(1, (int) $this->rab->jumlah_hari); $h++) {
            $harga[] = $m->hargaUntuk(
                $this->rab->tanggal_mulai->copy()->addDays($h),
                $this->libur,
                $this->musim,
            )['harga'];
        }

        return array_values(array_unique($harga));
    }

    /**
     * Apakah perjalanannya melewati lebih dari satu JENIS hari.
     *
     * Bukan lagi cuma hari kerja vs akhir pekan: perjalanan 3–6 Januari yang
     * keluar dari rentang Nataru pada tanggal 5 juga lintas jenis, walau
     * keempat harinya hari kerja. Tanpa musim ikut dihitung, hotel yang
     * separuh malamnya tarif Nataru akan dibekukan satu angka tanpa satu pun
     * tanda — dan selisih dua malam di tarif tinggi adalah selisih yang
     * memakan seluruh margin penawarannya.
     */
    public function lintasJenisHari(): bool
    {
        if (! $this->rab->tanggal_mulai || (int) $this->rab->jumlah_hari < 2) {
            return false;
        }

        $jenis = [];

        for ($h = 0; $h < max(1, (int) $this->rab->jumlah_hari); $h++) {
            $tanggal = $this->rab->tanggal_mulai->copy()->addDays($h);

            /*
             | Musim disebut lebih dulu, karena ia yang menang di
             | hargaUntuk(). Memakai akhir pekan sebagai jenis pada tanggal
             | yang juga masuk Nataru membuat 25 & 26 Desember terbaca jenis
             | yang sama dengan Sabtu biasa di bulan lain.
             */
            $musim = MusimHarga::pada($tanggal, $this->musim);

            $jenis[] = $musim
                ? 'musim:'.$musim->id
                : (HariLibur::periksa($tanggal, $this->libur)['akhir_pekan'] ? 'akhir_pekan' : 'hari_kerja');
        }

        return count(array_unique($jenis)) > 1;
    }
}
