<?php

namespace App\Http\Controllers\Api\Pelanggan;

use App\Http\Controllers\Api\ApiController;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\Rujukan\KodeRujukan;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\NomorTelepon;
use App\Support\Rujukan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Orang, bukan pesanan.
 *
 * Seluruh layar lain menyusun data menurut PESANAN — satu baris satu
 * pendaftaran, satu baris satu penyewaan. Yang tidak terjawab di mana pun:
 * siapa saja yang pernah memesan, dan siapa yang sudah memesan berkali-kali.
 * Pertanyaan itu muncul justru saat yang paling berharga: ketika ada trip baru
 * yang perlu ditawarkan, atau ketika kode rujukan perlu diberikan kepada orang
 * yang tidak mencantumkan surel.
 *
 * TIDAK ADA TABEL PELANGGAN. Datanya disusun dari pendaftaran dan penyewaan,
 * dikelompokkan menurut nomor WhatsApp — satu-satunya keterangan yang wajib
 * diisi di kedua formulir. Membuat tabelnya sendiri berarti dua tempat
 * menyimpan nama dan nomor orang yang sama, dan keduanya akan berbeda dalam
 * sebulan.
 *
 * PENGELOMPOKANNYA DI PHP, BUKAN DI SQL. Nomornya tersimpan apa adanya seperti
 * yang diketik: "+62812...", "0812...", dan "0812-3456-7890" adalah tiga teks
 * berbeda untuk satu orang yang sama, dan GROUP BY akan menghitungnya sebagai
 * tiga pelanggan. Konsekuensinya seluruh barisnya dibaca lebih dulu — layak
 * untuk jumlah pesanan sebesar Orcha sekarang, dan yang perlu diubah bila
 * suatu saat tidak lagi: menambah kolom nomor yang sudah dinormalkan pada
 * kedua tabel, lalu mengelompokkannya di SQL.
 */
class PelangganController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $cari = trim($request->string('cari')->toString());

        $orang = $this->kumpulkan($cari);

        // Yang terakhir memesan di atas: itu yang paling mungkin sedang
        // dibicarakan saat layar ini dibuka.
        $orang = $orang->sortByDesc('terakhir_pada')->values();

        $perHalaman = $this->perHalaman($request);
        $halaman = max(1, (int) $request->integer('page', 1));

        return response()->json([
            'data' => $orang->forPage($halaman, $perHalaman)->values()->all(),
            'meta' => [
                'halaman' => $halaman,
                'per_halaman' => $perHalaman,
                'total' => $orang->count(),
                'halaman_terakhir' => max(1, (int) ceil($orang->count() / $perHalaman)),
                // Dipakai layar untuk menyusun pesan WhatsApp yang siap kirim.
                'rujukan_potongan' => Rujukan::potongan(),
                'rujukan_imbalan' => Rujukan::imbalan(),
            ],
        ]);
    }

    /**
     * Membuatkan kode rujukan untuk satu pelanggan.
     *
     * Dipisahkan dari daftar, dan itu disengaja: MEMBUKA layar tidak boleh
     * membuat apa pun. Kalau kodenya dibuat sendiri saat daftarnya digambar,
     * setiap orang yang pernah memesan sekali mendapat kode — termasuk yang
     * pesanannya batal, dan termasuk saat admin cuma sedang mencari nomor
     * telepon seseorang.
     */
    public function buatkanKode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'whatsapp' => ['required', 'string', 'max:32'],
            'nama' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $nomor = NomorTelepon::angka($data['whatsapp']);

        if ($nomor === '') {
            abort(422, 'Nomor WhatsApp tidak terbaca.');
        }

        $ada = KodeRujukan::where('whatsapp', $nomor)->first();

        if ($ada) {
            return response()->json(['data' => ['kode' => $ada->kode, 'baru' => false]]);
        }

        // Pesanan pertamanya dicatat sebagai asal — saat komisi dibayarkan,
        // pertanyaan "ini siapa, ya?" hampir selalu muncul.
        $asal = PendaftaranOpenTrip::query()
            ->get(['kode', 'whatsapp'])
            ->first(fn ($satu) => NomorTelepon::angka($satu->whatsapp) === $nomor);

        $kode = KodeRujukan::create([
            'nama' => $data['nama'],
            'whatsapp' => $nomor,
            'email' => $data['email'] ?? null,
            'kode_pendaftaran_asal' => $asal?->kode,
        ]);

        $this->catat($request, 'buat kode rujukan dari layar pelanggan', [
            'kode' => $kode->kode,
            'nama' => $kode->nama,
        ]);

        return response()->json(['data' => ['kode' => $kode->kode, 'baru' => true]], 201);
    }

    /**
     * Bagian nomor dari kata yang dicari, DINORMALKAN seperti nomor tersimpan.
     *
     * Admin menyalin nomornya dari WhatsApp, yang menuliskannya
     * "+62 812-3456-7890". Membuang tanda bacanya saja menghasilkan
     * "6281234567890" — sementara yang tersimpan "081234567890", dan
     * pencariannya tidak menemukan apa pun.
     *
     * Yang gagal tanpa suara: admin menyimpulkan orangnya belum pernah
     * memesan, lalu memperlakukannya sebagai pelanggan baru.
     *
     * Kata yang tidak mengandung angka sama sekali dikembalikan apa adanya,
     * supaya pencarian nama tidak berubah jadi mencari string kosong — yang
     * cocok dengan SEMUA baris.
     */
    private function nomorCari(string $cari): string
    {
        $angka = NomorTelepon::angka($cari);

        return $angka !== '' ? $angka : $cari;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function kumpulkan(string $cari): Collection
    {
        $orang = [];

        $catat = function (
            ?string $nama, ?string $whatsapp, ?string $email,
            ?string $kode, $tanggal, ?string $status, string $jenis,
        ) use (&$orang) {
            $nomor = NomorTelepon::angka($whatsapp);

            if ($nomor === '') {
                return;
            }

            $orang[$nomor] ??= [
                'whatsapp' => NomorTelepon::rapi($nomor),
                'whatsapp_angka' => $nomor,
                'nama' => trim((string) $nama),
                'email' => null,
                'jumlah_trip' => 0,
                'jumlah_sewa' => 0,
                'jumlah_batal' => 0,
                'terakhir_pada' => null,
                'terakhir_kode' => null,
                'terakhir_jenis' => null,
            ];

            $baris = &$orang[$nomor];

            $baris[$jenis === 'trip' ? 'jumlah_trip' : 'jumlah_sewa']++;

            if ($status === 'batal') {
                $baris['jumlah_batal']++;
            }

            /*
             | Surel diambil dari pesanan mana pun yang mencantumkannya.
             |
             | Kolomnya opsional, jadi orang yang sama bisa mengisinya sekali
             | dan mengosongkannya lain kali. Yang berguna alamat yang PERNAH
             | diberikannya, bukan yang kebetulan ada di pesanan terakhir.
             */
            if (blank($baris['email']) && filled($email)) {
                $baris['email'] = $email;
            }

            $waktu = $tanggal ? \Illuminate\Support\Carbon::parse($tanggal) : null;

            if ($waktu && (! $baris['terakhir_pada'] || $waktu->gt($baris['terakhir_pada']))) {
                $baris['terakhir_pada'] = $waktu;
                $baris['terakhir_kode'] = $kode;
                $baris['terakhir_jenis'] = $jenis;
                // Nama pun ikut yang terbaru: orang menikah, berganti panggilan,
                // atau sekadar mengetik lengkap di pesanan kedua.
                $baris['nama'] = trim((string) $nama) ?: $baris['nama'];
            }
        };

        PendaftaranOpenTrip::query()
            ->when($cari !== '', fn ($q) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', '%'.$this->nomorCari($cari).'%')
                    ->orWhere('email', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
            ))
            ->get(['nama', 'whatsapp', 'email', 'kode', 'created_at', 'status'])
            ->each(fn ($satu) => $catat(
                $satu->nama, $satu->whatsapp, $satu->email,
                $satu->kode, $satu->created_at, $satu->status, 'trip',
            ));

        PenyewaanKendaraan::query()
            ->when($cari !== '', fn ($q) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', '%'.$this->nomorCari($cari).'%')
                    ->orWhere('email', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
            ))
            ->get(['nama', 'whatsapp', 'email', 'kode', 'created_at', 'status'])
            ->each(fn ($satu) => $catat(
                $satu->nama, $satu->whatsapp, $satu->email,
                $satu->kode, $satu->created_at, $satu->status, 'sewa',
            ));

        return $this->lengkapiRujukan(collect($orang));
    }

    /**
     * Menempelkan keadaan kode rujukan tiap orang.
     *
     * Dikerjakan sekali untuk seluruh halaman, bukan sekali per baris.
     * Lima puluh pelanggan yang masing-masing mencari kodenya sendiri
     * menghasilkan lima puluh kueri tambahan — pada layar yang justru dibuka
     * untuk menelusuri banyak orang sekaligus.
     *
     * @param  Collection<string, array<string, mixed>>  $orang
     * @return Collection<int, array<string, mixed>>
     */
    private function lengkapiRujukan(Collection $orang): Collection
    {
        $kode = KodeRujukan::query()
            ->whereIn('whatsapp', $orang->keys()->all())
            ->withSum([
                'pendaftaran as komisi_belum_dibayar' => fn ($q) => $q->whereNull('imbalan_dibayar_pada'),
            ], 'imbalan_rujukan')
            ->withCount('pendaftaran as rujukan_dipakai')
            ->get()
            ->keyBy('whatsapp');

        return $orang->map(function (array $baris, string $nomor) use ($kode) {
            $milik = $kode->get($nomor);

            return array_merge($baris, [
                'terakhir_pada' => $baris['terakhir_pada']?->toIso8601String(),
                'kode_rujukan' => $milik?->kode,
                'rujukan_aktif' => $milik?->aktif,
                'rujukan_dipakai' => (int) ($milik->rujukan_dipakai ?? 0),
                // withSum mengembalikan NULL, bukan 0, saat tidak ada barisnya.
                'komisi_belum_dibayar' => (int) ($milik->komisi_belum_dibayar ?? 0),
            ]);
        })->values();
    }
}
