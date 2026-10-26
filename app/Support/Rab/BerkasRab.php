<?php

namespace App\Support\Rab;

use App\Models\Rab\Rab;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * PDF penawaran (untuk pelanggan) dan RAB internal (untuk kantor).
 *
 * DUA BERKAS, BUKAN SATU BERKAS DENGAN BAGIAN TERSEMBUNYI. Angka modal yang
 * bocor ke pelanggan adalah kesalahan yang tidak bisa ditarik: sekali ia tahu
 * modal bus Rp 850.000 sehari, setiap penawaran berikutnya menjadi tawar-
 * menawar margin. Templat penawaran karena itu TIDAK PERNAH menerima angka
 * modal — yang dikirim ke sana hanya harga jual dan nama-nama barang.
 *
 * Keduanya dirakit dari satu HitungRab::ringkas(), jadi harga di penawaran
 * dan di RAB internal tidak mungkin berbeda.
 */
class BerkasRab
{
    public static function buat(Rab $rab, string $jenis): string
    {
        [$view, $data] = self::bahan($rab, $jenis);

        return Pdf::loadView($view, $data)->setPaper('a4')->output();
    }

    /**
     * Nama templat beserta datanya. Terpisah dari buat() supaya uji bisa
     * membaca HTML-nya langsung — teks di dalam PDF termampatkan.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function bahan(Rab $rab, string $jenis): array
    {
        $rab->loadMissing('itinerary', 'biaya.master');
        $r = HitungRab::ringkas($rab);

        $data = [
            'rab' => $rab,
            'hari' => self::hariDenganTanggal($rab),
            'rentang' => self::rentangTanggal($rab),
            'durasi' => self::durasi($rab),
            'tujuan' => self::tujuan($rab),
            'terbit' => now()->locale('id')->translatedFormat('j F Y'),
            'berlaku' => $rab->berlaku_sampai?->locale('id')->translatedFormat('j F Y'),
            'kontak' => [
                'whatsapp' => config('orcha.whatsapp'),
                'email' => config('orcha.email'),
                'instagram' => config('orcha.instagram'),
                'alamat' => config('orcha.alamat'),
            ],
            'logo' => file_exists(public_path('orcha-logo-surat.png')) ? public_path('orcha-logo-surat.png') : null,
            'sorotan' => self::sorotan($rab),
            'rute' => self::rute($rab),
        ];

        $data['hari'] = self::berjudul($data['hari']);

        if ($jenis === 'internal') {
            $data['r'] = $r;
            $view = 'pdf.rab-internal';
        } else {
            // Yang diserahkan ke templat penawaran HANYA harga jual dan nama
            // barang. Tidak ada modal, tidak ada margin, tidak ada harga
            // satuan — templatnya bahkan tidak bisa membocorkannya karena
            // angkanya tidak pernah sampai ke sana.
            $data['harga'] = [
                'per_orang_teks' => $r['harga_per_orang_teks'],
                'total_teks' => $r['harga_total_teks'],
            ];
            $data['termasuk'] = collect($r['baris'])
                ->groupBy('kategori_label')
                ->map(fn ($isi) => $isi->pluck('nama')->unique()->values()->all())
                ->all();
            $data['dp_persen'] = (int) config('orcha.pembayaran.dp_persen', 30);
            $data['pelunasan_hari'] = (int) config('orcha.pembayaran.pelunasan_hari_sebelum', 5);
            $data['atas_nama'] = config('orcha.pembayaran.atas_nama', 'PT ASTHANA CIPTA MANDIRI');
            $view = 'pdf.rab-penawaran';
        }

        return [$view, $data];
    }

    /**
     * Itinerary dikelompokkan per hari, lengkap dengan tanggalnya bila tanggal
     * mulai sudah diketahui. Hari tanpa kegiatan TETAP ikut: "Hari 2 — bebas"
     * lebih jujur daripada hari yang hilang dari jadwal.
     *
     * @return array<int, array{hari_ke:int, tanggal:?string, kegiatan:array}>
     */
    private static function hariDenganTanggal(Rab $rab): array
    {
        $kelompok = $rab->itinerary->groupBy('hari_ke');
        $hasil = [];

        for ($h = 1; $h <= max(1, $rab->jumlah_hari); $h++) {
            $tanggal = $rab->tanggal_mulai
                ? Carbon::parse($rab->tanggal_mulai)->addDays($h - 1)->locale('id')->translatedFormat('l, j F Y')
                : null;

            $hasil[] = [
                'hari_ke' => $h,
                'tanggal' => $tanggal,
                'kegiatan' => ($kelompok[$h] ?? collect())->values()->all(),
            ];
        }

        return $hasil;
    }

    /**
     * Jenis sebuah kegiatan, ditebak dari isinya.
     *
     * Jadwal yang seluruh barisnya tampil sama menuntut pembacanya mengeja
     * tiap baris untuk tahu mana yang perjalanan, mana yang destinasi, dan
     * mana yang cuma makan. Satu penanda berwarna menjawabnya sebelum
     * kalimatnya dibaca.
     *
     * TEBAKAN, dan sengaja begitu: memaksa admin memilih jenis pada tiap
     * kegiatan menambah satu isian pada pekerjaan yang paling sering
     * dilakukan, demi sesuatu yang hampir selalu bisa dibaca dari namanya
     * sendiri. Yang tidak terbaca jatuh ke 'lain' — tanpa penanda, bukan
     * dengan penanda yang salah.
     */
    public static function jenis(string $nama, ?string $keterangan = null, ?string $destinasi = null): string
    {
        $teks = mb_strtolower($nama.' '.$keterangan);

        return match (true) {
            str_contains($teks, 'snack') => 'snack',
            (bool) preg_match('/\b(makan|sarapan|ishoma|kuliner|oleh-oleh)\b/u', $teks) => 'makan',
            (bool) preg_match('/\b(perjalanan|menuju|transit|pulang|berangkat|kumpul|penjemputan)\b/u', $teks) => 'perjalanan',
            (bool) preg_match('/\b(istirahat|check-in|check in|check-out|check out|bersih|briefing|bebas)\b/u', $teks) => 'istirahat',
            trim((string) $destinasi) !== '' => 'destinasi',
            default => 'lain',
        };
    }

    /**
     * Judul dan ringkasan tiap hari, dirakit dari isinya sendiri.
     *
     * Tidak disimpan sebagai kolom: satu isian lagi per hari untuk kalimat
     * yang bisa dibaca dari daftar kegiatannya. Yang dipakai destinasi hari
     * itu — "Baluran · Djawatan" — karena itulah yang diingat orang tentang
     * sebuah hari, bukan jam berangkatnya.
     *
     * @param  array<int, array<string, mixed>>  $hari
     * @return array<int, array<string, mixed>>
     */
    private static function berjudul(array $hari): array
    {
        foreach ($hari as $i => $h) {
            $kegiatan = collect($h['kegiatan']);

            $utama = $kegiatan
                ->filter(fn ($k) => self::jenis($k->nama, $k->keterangan, $k->destinasi) === 'destinasi')
                ->map(fn ($k) => trim((string) ($k->destinasi ?: $k->nama)))
                ->unique()->values();

            // Tanpa satu pun destinasi, nama kegiatannya sendiri yang dipakai
            // — hari perjalanan tetap punya judul, bukan kotak kosong.
            if ($utama->isEmpty()) {
                $utama = $kegiatan
                    ->reject(fn ($k) => self::jenis($k->nama, $k->keterangan, $k->destinasi) === 'perjalanan')
                    ->map(fn ($k) => trim((string) $k->nama))
                    ->filter()->unique()->values();
            }

            /*
             | Dipendekkan. Judul hari yang memuat kalimat utuh "Kumpul di
             | meeting point Jogja Jombor, Prambanan, Tugu Kartasura" bukan
             | judul lagi — ia kegiatan pertama yang kebetulan dicetak besar,
             | dan mendorong tanggalnya keluar dari barisnya.
             */
            $judul = $utama->take(3)->map(fn ($t) => self::pendek($t, 34));

            $hari[$i]['judul'] = $judul->implode(' · ') ?: 'Waktu bebas';

            /*
             | Ringkasannya menyebut yang BELUM ada di judul. Mengulang kata
             | yang sama dua baris berturut-turut membuat baris kedua tidak
             | dibaca sama sekali — lalu kegiatan yang benar-benar baru ikut
             | tidak terbaca.
             */
            $hari[$i]['ringkasan'] = $kegiatan
                ->map(fn ($k) => trim((string) $k->nama))
                ->filter()
                ->unique()
                ->reject(fn ($n) => $judul->contains(fn ($j) => str_starts_with($n, rtrim($j, '…'))))
                ->take(5)
                ->map(fn ($n) => self::pendek($n, 44))
                ->implode(', ');
        }

        return $hari;
    }

    /** Memotong di batas kata; "Perjalanan Mepo…" terbaca, "Perjalanan Me…" tidak. */
    private static function pendek(string $teks, int $batas): string
    {
        if (mb_strlen($teks) <= $batas) {
            return $teks;
        }

        $potong = mb_substr($teks, 0, $batas);
        $spasi = mb_strrpos($potong, ' ');

        return rtrim($spasi !== false && $spasi > $batas - 14 ? mb_substr($potong, 0, $spasi) : $potong, ' ,.·-').'…';
    }

    /**
     * Destinasi yang jadi daya tarik perjalanan ini — untuk cip di kepala.
     *
     * @return array<int, string>
     */
    private static function sorotan(Rab $rab): array
    {
        return $rab->itinerary
            ->filter(fn ($k) => trim((string) $k->destinasi) !== '')
            ->map(fn ($k) => trim((string) $k->destinasi))
            ->unique()->take(8)->values()->all();
    }

    /**
     * Urutan singgah per hari: nomor, waktu, dan namanya.
     *
     * BUKAN peta: menggambar peta menuntut data geografis yang tidak ada saat
     * berkas dicetak, dan peta yang dikarang lebih buruk daripada tidak ada
     * peta. Yang diberikan di sini urutan kunjungannya — isi yang sama dengan
     * yang dibaca orang dari daftar di samping peta.
     *
     * @return array<int, array{hari_ke:int, judul:string, titik:array}>
     */
    private static function rute(Rab $rab): array
    {
        $hasil = [];

        foreach ($rab->itinerary->groupBy('hari_ke') as $hariKe => $kegiatan) {
            $titik = [];
            $no = 0;

            foreach ($kegiatan as $k) {
                $jenis = self::jenis($k->nama, $k->keterangan, $k->destinasi);

                // Perjalanan adalah RUAS antara dua singgahan, bukan
                // singgahannya; menomorinya membuat urutannya terbaca seolah
                // rombongan berhenti di tengah jalan tol.
                if ($jenis === 'perjalanan') {
                    continue;
                }

                $titik[] = [
                    'no' => ++$no,
                    'waktu' => self::rentangJam($k),
                    'nama' => trim((string) ($k->destinasi ?: $k->nama)),
                    'jenis' => $jenis,
                ];
            }

            if ($titik !== []) {
                $hasil[] = ['hari_ke' => (int) $hariKe, 'titik' => $titik];
            }
        }

        return $hasil;
    }

    private static function rentangJam($k): string
    {
        $mulai = trim((string) $k->jam);
        $selesai = trim((string) $k->jam_selesai);

        return match (true) {
            $mulai !== '' && $selesai !== '' => $mulai.' – '.$selesai,
            $mulai !== '' => $mulai,
            default => '',
        };
    }

    /**
     * "Yogyakarta, DI Yogyakarta" terbaca gagap; bila nama daerah sudah
     * terkandung di nama provinsi, cukup provinsinya.
     */
    private static function tujuan(Rab $rab): string
    {
        $daerah = trim((string) $rab->daerah);

        if ($daerah === '' || str_contains(mb_strtolower($rab->provinsi), mb_strtolower($daerah))) {
            return $rab->provinsi;
        }

        return $daerah.', '.$rab->provinsi;
    }

    private static function rentangTanggal(Rab $rab): ?string
    {
        if (! $rab->tanggal_mulai) {
            return null;
        }

        $mulai = Carbon::parse($rab->tanggal_mulai)->locale('id');
        $selesai = $mulai->copy()->addDays(max(1, $rab->jumlah_hari) - 1);

        return $mulai->isSameDay($selesai)
            ? $mulai->translatedFormat('j F Y')
            : $mulai->translatedFormat('j M').' – '.$selesai->translatedFormat('j M Y');
    }

    private static function durasi(Rab $rab): string
    {
        $teks = $rab->jumlah_hari.' Hari';

        return $rab->jumlah_malam > 0 ? $teks.' '.$rab->jumlah_malam.' Malam' : $teks;
    }
}
