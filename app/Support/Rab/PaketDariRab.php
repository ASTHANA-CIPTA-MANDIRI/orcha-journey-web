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
        $rab->loadMissing('itinerary', 'biaya.master');
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

        $hasil = [];

        for ($h = 1; $h <= max(1, (int) $rab->jumlah_hari); $h++) {
            $agenda = ($perHari[$h] ?? collect())
                ->map(fn ($k) => [
                    'jam' => str_replace(':', '.', (string) $k->jam),
                    'kegiatan' => trim((string) $k->nama),
                ])
                ->filter(fn ($a) => $a['kegiatan'] !== '')
                ->values()
                ->all();

            $hasil[] = ['agenda' => $agenda];
        }

        return $hasil;
    }
}
