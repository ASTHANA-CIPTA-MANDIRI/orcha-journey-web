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
            $data['rekap_vendor'] = self::rekapVendor($r);
            $data['siapkan'] = self::siapkan($r);
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
            $data['termasuk'] = self::termasuk($rab, $r);
            $data['dp_persen'] = (int) config('orcha.pembayaran.dp_persen', 30);
            $data['pelunasan_hari'] = (int) config('orcha.pembayaran.pelunasan_hari_sebelum', 5);
            $data['atas_nama'] = config('orcha.pembayaran.atas_nama', 'PT ASTHANA CIPTA MANDIRI');
            $view = 'pdf.rab-penawaran';
        }

        return [$view, $data];
    }

    /**
     * Siapa harus dibayar berapa.
     *
     * Vendor sudah tercetak di tiap baris, tetapi kategori memisahkan yang
     * sebenarnya satu tagihan: Innova di transportasi, homestay di akomodasi,
     * dan keduanya bisa dari orang yang sama. Yang menyiapkan pembayaran
     * harus menjumlahkannya sendiri dari tabel yang urutannya bukan urutan
     * pembayaran — dan salah jumlah di sini berarti vendor ditelepon dua kali.
     *
     * Baris tanpa vendor IKUT DIHITUNG, bukan diabaikan. Yang tidak terlihat
     * di rekap akan dikira sudah ada yang mengurus.
     *
     * @param  array<string, mixed>  $ringkas
     * @return array{daftar: array<int, array<string, mixed>>, tanpa_vendor: int, tanpa_vendor_teks: string}
     */
    private static function rekapVendor(array $ringkas): array
    {
        $baris = collect($ringkas['baris'] ?? []);
        $punya = $baris->filter(fn ($b) => ! empty($b['vendor']));

        $daftar = $punya->groupBy('vendor')->map(function ($isi, $nama) {
            $total = $isi->sum(fn ($b) => (int) $b['subtotal']);

            return [
                'nama' => $nama,
                'kontak' => $isi->firstWhere('vendor_kontak', '!=', null)['vendor_kontak'] ?? null,
                // Syarat bayar hidup di catatan vendor: "DP 50% H-3" tidak
                // berguna kalau baru terbaca saat menyiapkan uangnya.
                'catatan' => $isi->firstWhere('vendor_catatan', '!=', null)['vendor_catatan'] ?? null,
                'jumlah_baris' => $isi->count(),
                'total' => $total,
                'total_teks' => 'Rp '.number_format($total, 0, ',', '.'),
            ];
        })->sortByDesc('total')->values()->all();

        $tanpa = $baris->count() - $punya->count();
        $tanpaTotal = $baris->filter(fn ($b) => empty($b['vendor']))->sum(fn ($b) => (int) $b['subtotal']);

        return [
            'daftar' => $daftar,
            'tanpa_vendor' => $tanpa,
            'tanpa_vendor_teks' => 'Rp '.number_format($tanpaTotal, 0, ',', '.'),
        ];
    }

    /**
     * Yang disiapkan tim sendiri, bukan dibeli di tempat.
     *
     * Dokumentasi, P3K, banner, tour leader, merchandise — semuanya sudah
     * tercetak sebagai biaya, tetapi biaya adalah daftar UANG, bukan daftar
     * BARANG. Yang berangkat pagi-pagi membaca tabel rincian untuk tahu
     * berapa yang dibayar, bukan untuk tahu apa yang harus dimasukkan ke
     * mobil; banner yang anggarannya sudah benar tetap tertinggal di kantor.
     *
     * @param  array<string, mixed>  $ringkas
     * @return array<int, string>
     */
    private static function siapkan(array $ringkas): array
    {
        $kategori = ['dokumentasi', 'pemandu', 'asuransi'];
        $kata = '/p3k|obat|banner|spanduk|backdrop|merchandise|merchendise|souvenir|suvenir|kaos|jaket|tumbler|id card/u';

        return collect($ringkas['baris'] ?? [])
            ->filter(fn ($b) => in_array($b['kategori'] ?? '', $kategori, true)
                || preg_match($kata, mb_strtolower((string) ($b['nama'] ?? ''))))
            ->pluck('nama')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Daftar "harga sudah termasuk" untuk pelanggan.
     *
     * NAMA BARIS BIAYA TIDAK PERNAH IKUT KE SINI. Admin menulis nama baris
     * untuk dirinya sendiri — "Dana Tak Terduga", "Uang Rokok Supir",
     * "Operasional Tim Internal", "Tour Leader Internal" — dan nama seperti
     * itu dulu sampai ke pelanggan karena daftarnya memungut nama mentah lalu
     * mengelompokkannya per kategori. Pelanggan jadi membaca istilah dapur
     * kami, dan baris yang sebenarnya cuma cadangan margin terbaca sebagai
     * biaya yang ia tanggung.
     *
     * Sekarang setiap kalimat di bawah KAMI yang tulis. Baris biaya hanya
     * menentukan kalimat mana yang MUNCUL, tidak pernah apa isinya — jadi apa
     * pun yang diketik admin, yang dibaca pelanggan tetap bahasa sehari-hari.
     * Kategori yang murni internal (operasional, cadangan) sengaja tidak punya
     * kalimat sama sekali; yang mewakilinya satu catatan penutup.
     *
     * @param  array<string, mixed>  $ringkas
     * @return array<int, array{judul: string, rinci: string}>
     */
    private static function termasuk(Rab $rab, array $ringkas): array
    {
        $baris = collect($ringkas['baris'] ?? []);

        $punya = fn (string ...$kategori) => $baris->contains(
            fn ($b) => in_array($b['kategori'] ?? '', $kategori, true)
        );

        // Nama dibaca HANYA untuk memilah, tidak pernah untuk ditampilkan.
        $cocok = fn (string $pola) => $baris->contains(
            fn ($b) => (bool) preg_match($pola, mb_strtolower((string) ($b['nama'] ?? '')))
        );

        $daftar = [];

        if ($punya('transportasi', 'sopir')) {
            $daftar[] = [
                'judul' => 'Transportasi',
                'rinci' => 'Kendaraan ber-AC selama perjalanan, sudah termasuk BBM, tol, parkir, dan sopir',
            ];
        }

        $malam = max(0, (int) $rab->jumlah_malam);
        if ($punya('akomodasi')) {
            $daftar[] = [
                'judul' => $malam > 0 ? "Penginapan {$malam} malam" : 'Penginapan',
                'rinci' => 'Kamar sesuai jadwal, siap pakai saat tiba',
            ];
        }

        // "Makan 5x" dihitung dari baris konsumsi, bukan dari kegiatan: satu
        // kegiatan "ishoma" bisa jadi satu kali makan, dan "oleh-oleh" ikut
        // terbaca sebagai makan oleh jenis(). Baris snack dikeluarkan supaya
        // tidak ikut terhitung sebagai jatah makan.
        $snack = $baris->filter(
            fn ($b) => ($b['kategori'] ?? '') === 'konsumsi'
                && preg_match('/snack|coffee|air mineral|camilan/u', mb_strtolower((string) ($b['nama'] ?? '')))
        );
        $makan = $baris->filter(fn ($b) => ($b['kategori'] ?? '') === 'konsumsi')->count() - $snack->count();

        if ($makan > 0) {
            $daftar[] = [
                'judul' => "Makan {$makan}×",
                'rinci' => 'Sudah diatur di jadwal, peserta tidak perlu cari makan sendiri',
            ];
        } elseif ($punya('konsumsi')) {
            $daftar[] = [
                'judul' => 'Konsumsi',
                'rinci' => 'Makan peserta selama perjalanan',
            ];
        }

        if ($snack->isNotEmpty()) {
            $daftar[] = [
                'judul' => 'Snack & air mineral',
                'rinci' => 'Dibagikan di kendaraan sepanjang perjalanan',
            ];
        }

        if ($punya('tiket')) {
            $daftar[] = [
                'judul' => 'Tiket masuk & retribusi',
                'rinci' => 'Seluruh tempat yang tertulis di jadwal, tanpa bayar lagi di lokasi',
            ];
        }

        if ($punya('pemandu')) {
            $daftar[] = [
                'judul' => 'Tour leader & pemandu',
                'rinci' => 'Mendampingi rombongan dari awal sampai kembali',
            ];
        }

        if ($punya('dokumentasi')) {
            $daftar[] = [
                'judul' => 'Dokumentasi perjalanan',
                'rinci' => 'Foto dan video oleh tim kami, dibagikan setelah perjalanan',
            ];
        }

        if ($punya('asuransi')) {
            $daftar[] = [
                'judul' => 'Asuransi perjalanan',
                'rinci' => 'Perlindungan untuk seluruh peserta',
            ];
        }

        if ($cocok('/p3k|obat|kesehatan/u')) {
            $daftar[] = [
                'judul' => 'Perlengkapan P3K',
                'rinci' => 'Obat-obatan dasar selalu dibawa tim',
            ];
        }

        if ($cocok('/merchandise|merchendise|souvenir|suvenir|kaos|jaket|tumbler|goodie|goody|id card/u')) {
            $daftar[] = [
                'judul' => 'Merchandise peserta',
                'rinci' => 'Dibagikan ke setiap peserta di awal perjalanan',
            ];
        }

        if ($cocok('/banner|spanduk|backdrop/u')) {
            $daftar[] = [
                'judul' => 'Banner kegiatan',
                'rinci' => 'Disiapkan untuk foto bersama rombongan',
            ];
        }

        return $daftar;
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
