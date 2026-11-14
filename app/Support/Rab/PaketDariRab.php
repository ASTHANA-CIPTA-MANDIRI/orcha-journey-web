<?php

namespace App\Support\Rab;

use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\Rab;
use Illuminate\Support\Str;

/**
 * Paket wisata publik dirakit dari RAB, bukan diketik ulang.
 *
 * Open trip disusun di RAB sampai lengkap — harga jual, modal per orang,
 * itinerary berjam, daftar destinasi, dan daftar "sudah termasuk" semuanya
 * sudah ada di sana. Untuk bisa didaftari publik, semua itu dulu harus
 * diketik ulang ke borang paket wisata: dua puluhan kolom, ditambah seluruh
 * itinerary hari per hari.
 *
 * Yang mahal bukan waktu mengetiknya, melainkan bahwa sesudah itu ada DUA
 * sumber untuk angka yang sama. RAB disunting, paketnya tidak — dan yang
 * dibaca publik diam-diam jadi harga yang sudah tidak berlaku.
 *
 * MILIK SIAPA KOLOMNYA. Yang dirakit di sini hanya yang memang hidup di RAB.
 * Foto, kuota, titik jemput, status tayang, dan segala pengaturan promo tidak
 * pernah disentuh — itu milik borang paket, dan menimpanya saat penyelarasan
 * akan menghapus pekerjaan admin tanpa ada yang meminta.
 *
 * PAKET LAHIR SEBAGAI DRAF. Menerbitkan adalah keputusan manusia: paket yang
 * langsung tayang tanpa foto dan tanpa kuota terlihat rusak di mata calon
 * pelanggan, dan sekali terlihat tidak bisa ditarik.
 */
class PaketDariRab
{
    /**
     * Membuat paket baru dari RAB, lalu menautkan keduanya.
     */
    public static function buat(Rab $rab): TravelPackage
    {
        $paket = TravelPackage::create([
            'uuid' => (string) Str::uuid(),
            'name' => $rab->judul,
            'category' => 'open_trip',
            // Draf, bukan terbit. Lihat catatan kelas.
            'status' => 'draf',
            'berakhir_otomatis' => true,
            'minimal_peserta' => max(1, (int) ($rab->peserta_minimum ?: $rab->jumlah_peserta)),
        ] + self::isiDariRab($rab));

        $rab->forceFill(['travel_package_id' => $paket->id])->save();

        return $paket;
    }

    /**
     * Menyelaraskan paket yang sudah tertaut dengan keadaan RAB sekarang.
     *
     * Dipanggil setiap RAB berubah — baris biaya, itinerary, atau kepalanya.
     * Pendaftar yang sudah ada TIDAK terpengaruh: PendaftaranOpenTrip
     * membekukan harga_jual dan harga_modal miliknya sendiri saat dibuat,
     * jadi yang berubah di sini hanya angka untuk pendaftaran berikutnya.
     */
    public static function selaraskan(Rab $rab): ?TravelPackage
    {
        if (! $rab->travel_package_id) {
            return null;
        }

        $paket = TravelPackage::find($rab->travel_package_id);

        if (! $paket) {
            // Pakernya dihapus admin. Tautan yang menggantung dibersihkan,
            // bukan dibiarkan memulangkan galat di setiap penyimpanan.
            $rab->forceFill(['travel_package_id' => null])->saveQuietly();

            return null;
        }

        $paket->forceFill(self::isiDariRab($rab))->save();

        return $paket;
    }

    /**
     * Kolom paket yang memang hidup di RAB — tidak lebih.
     *
     * @return array<string, mixed>
     */
    private static function isiDariRab(Rab $rab): array
    {
        // vendor ikut: nama rekanan dipakai menyaring apa yang boleh tampil
        // di halaman publik (lihat namaRahasia()). Tanpa dimuat di sini, tiap
        // baris biaya menembakkan satu kueri sendiri.
        $rab->loadMissing('itinerary', 'biaya.master.vendor');
        $r = HitungRab::ringkas($rab);

        $hari = max(1, (int) $rab->jumlah_hari);
        $malam = max(0, (int) $rab->jumlah_malam);
        $mulai = $rab->tanggal_mulai;

        return [
            'name' => $rab->judul,
            'duration' => $malam > 0 ? "{$hari} Hari {$malam} Malam" : "{$hari} Hari",
            'tanggal_berangkat' => $mulai?->toDateString(),
            'tanggal_pulang' => $mulai?->copy()->addDays($hari - 1)->toDateString(),
            'price' => $r['harga_per_orang'],
            // Modal ikut, supaya laporan keuntungan tidak perlu menebak.
            'harga_modal' => $r['untuk_pendaftaran']['harga_modal'],
            'destination_list' => self::destinasi($rab),
            // Persis kalimat yang dibaca pelanggan di PDF penawaran, supaya
            // yang dijanjikan di berkas dan di halaman daftar tidak berbeda.
            'fasilitas' => self::fasilitas($rab),
            'itinerary' => self::itinerary($rab),
        ];
    }

    /** @return array<int, string> */
    private static function destinasi(Rab $rab): array
    {
        return $rab->itinerary
            ->pluck('destinasi')
            ->map(fn ($d) => trim((string) $d))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private static function fasilitas(Rab $rab): array
    {
        [, $data] = BerkasRab::bahan($rab, 'penawaran');

        return collect($data['termasuk'] ?? [])->pluck('judul')->values()->all();
    }

    /**
     * Itinerary RAB ke bentuk yang dibaca halaman paket.
     *
     * Paket menyimpannya sebagai larik per hari berisi `agenda`, dengan jam
     * bertitik ("08.00") — lihat terjemahkanAgenda() di RabController untuk
     * arah sebaliknya. Hari tanpa kegiatan TETAP ikut: hari yang hilang
     * membuat "Hari 3" di halaman publik sebenarnya hari keempat.
     *
     * @return array<int, array{agenda: array<int, array{jam: string, kegiatan: string}>}>
     */
    private static function itinerary(Rab $rab): array
    {
        $perHari = $rab->itinerary->sortBy([['hari_ke', 'asc'], ['urutan', 'asc'], ['jam', 'asc']])
            ->groupBy('hari_ke');

        $rahasia = self::namaRahasia($rab);
        $hasil = [];

        for ($h = 1; $h <= max(1, (int) $rab->jumlah_hari); $h++) {
            $agenda = ($perHari[$h] ?? collect())
                ->map(fn ($k) => [
                    'jam' => self::jamPublik($k),
                    'kegiatan' => self::labelPublik($k, $rahasia),
                ])
                ->filter(fn ($a) => $a['kegiatan'] !== '')
                ->values()
                ->all();

            $hasil[] = ['agenda' => $agenda];
        }

        return $hasil;
    }

    /**
     * Jam kegiatan berikut jam selesainya, bila ada.
     *
     * RAB menyimpan keduanya; sebelumnya hanya jam mulai yang ikut ke paket,
     * sehingga "00:00 – 01:20 Menuju destinasi" terbaca di halaman publik
     * sebagai "00.00 Menuju destinasi" — berapa lamanya hilang, padahal
     * itulah yang paling sering ditanyakan calon peserta.
     *
     * Pemisahnya tanda hubung biasa, bukan en dash: nilainya juga diketik
     * ulang admin di borang paket, dan tanda yang tidak ada di papan ketik
     * akan hilang begitu satu baris disunting tangan.
     */
    private static function jamPublik($k): string
    {
        $titik = fn ($t) => str_replace(':', '.', trim((string) $t));

        $mulai = $titik($k->jam);
        $selesai = $titik($k->jam_selesai);

        if ($mulai === '') {
            return '';
        }

        return $selesai === '' || $selesai === $mulai ? $mulai : $mulai.' - '.$selesai;
    }

    /**
     * Nama yang TIDAK BOLEH tampil di halaman publik.
     *
     * Rumah makan dan penginapan yang dipakai trip adalah hasil negosiasi —
     * harga, komisi, dan kesediaannya dibangun bertahun-tahun. Memajangnya di
     * halaman paket sama dengan menyerahkan daftar rekanan kepada siapa pun
     * yang membuka situs, termasuk pesaing yang tinggal menelepon satu per
     * satu.
     *
     * Dirakit dari RAB-nya sendiri, bukan dari daftar kata terlarang: yang
     * rahasia adalah nama yang BENAR-BENAR dipakai trip ini — nama vendornya,
     * dan nama baris biaya akomodasi & konsumsinya.
     *
     * @return array<int, string> huruf kecil, sudah dirapikan
     */
    private static function namaRahasia(Rab $rab): array
    {
        $rapi = fn ($t) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $t)));

        return $rab->biaya
            ->flatMap(fn ($b) => [
                $b->master?->vendor?->nama,
                // Nama baris yang dibekukan, bukan nama master-nya: baris
                // boleh diganti namanya di RAB, dan yang tertulis di
                // itinerary mengikuti yang itu.
                in_array($b->kategori, ['akomodasi', 'konsumsi'], true) ? $b->nama : null,
                in_array($b->master?->kategori, ['akomodasi', 'konsumsi'], true) ? $b->master?->nama : null,
            ])
            ->map($rapi)
            ->filter(fn ($n) => mb_strlen($n) >= 4)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Nama satu kegiatan sebagaimana boleh dibaca umum.
     *
     * Tiga lapis, dari yang paling tepat ke yang paling aman:
     *
     * 1. DESTINASI-nya bila ada. Itu nama katalog yang memang dipajang di
     *    situs — Kawah Ijen, Baluran, De Djawatan.
     * 2. Namanya sendiri, bila kegiatannya bukan makan/snack/istirahat.
     *    "Menuju destinasi", "Penjemputan peserta", "Kembali ke hotel" tidak
     *    menyebut rekanan siapa pun.
     * 3. Label umum menurut isyarat di nama & keterangannya. Di sinilah
     *    "Yogi Cafe & resto Pantai Pulau Merah" jadi "Makan malam" dan
     *    "Naila Homestay Banyuwangi" jadi "Check-in penginapan".
     *
     * Lapis 2 dijaring ulang terhadap daftar rahasia: kegiatan bernama
     * "Menuju Naila Homestay" lolos penggolong jenis (ia perjalanan), tetapi
     * tetap menyebut penginapannya.
     */
    private static function labelPublik($k, array $rahasia): string
    {
        $destinasi = trim((string) $k->destinasi);

        if ($destinasi !== '') {
            return $destinasi;
        }

        $nama = trim(preg_replace('/\s+/', ' ', (string) $k->nama));
        $jenis = BerkasRab::jenis($k->nama, $k->keterangan, $k->destinasi);
        $menyebutRahasia = fn (string $teks) => collect($rahasia)
            ->contains(fn ($r) => $r !== '' && str_contains(mb_strtolower($teks), $r));

        $bolehApaAdanya = ! in_array($jenis, ['makan', 'snack', 'istirahat'], true)
            && $nama !== ''
            && ! $menyebutRahasia($nama);

        if ($bolehApaAdanya) {
            return $nama;
        }

        return self::labelUmum($nama.' '.$k->keterangan, $jenis);
    }

    /**
     * Label umum dari isyarat kata, bukan dari nama tempatnya.
     *
     * Isyaratnya dicari di nama DAN keterangan sekaligus: yang tertulis di
     * nama kerap cuma nama rumah makannya, sementara keterangannya justru
     * menyebut jatahnya — "makan malam & ishoma", "check in & istirahat".
     */
    private static function labelUmum(string $teks, string $jenis): string
    {
        $t = mb_strtolower($teks);

        return match (true) {
            str_contains($t, 'sarapan') => 'Sarapan',
            str_contains($t, 'makan siang') => 'Makan siang',
            str_contains($t, 'makan malam') => 'Makan malam',
            str_contains($t, 'makan pagi') => 'Makan pagi',
            str_contains($t, 'oleh-oleh') => 'Belanja oleh-oleh',
            str_contains($t, 'snack') => 'Snack',
            (bool) preg_match('/\bcheck[\s-]?out\b/u', $t) => 'Check-out penginapan',
            (bool) preg_match('/\bcheck[\s-]?in\b/u', $t) => 'Check-in penginapan',
            str_contains($t, 'ishoma') => 'ISHOMA',
            $jenis === 'snack' => 'Snack',
            $jenis === 'makan' => 'Makan',
            $jenis === 'istirahat' => 'Istirahat',
            default => 'Kegiatan',
        };
    }
}
