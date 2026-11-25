<?php

namespace App\Http\Controllers\Api\Umum;

use App\Http\Controllers\Api\ApiController;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\Rab\Rab;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Agenda Orcha untuk kalender kegiatan di lemon.
 *
 * Lemon punya kalendernya sendiri (tabel `kegiatan`), tetapi keberangkatan dan
 * penyewaan Orcha tidak ada di basis data itu — dan selama tidak terlihat di
 * sana, satu-satunya cara tahu "tanggal ini ada rombongan berangkat" adalah
 * membuka daftar pendaftaran satu per satu.
 *
 * Yang dipulangkan DATANYA SAJA, bukan tampilan: lemon yang memutuskan
 * warnanya, bentuk pitanya, dan ke mana ia menaut. Tugas berkas ini cuma
 * menjawab "apa yang terjadi antara tanggal A dan tanggal B".
 *
 * Tidak di-paginasi. Keluarannya dibatasi rentang tanggal — satu bulan kisi
 * kalender paling banyak 42 hari — dan kalender yang menerima halaman pertama
 * saja akan diam-diam menyembunyikan keberangkatan.
 */
class KalenderController extends ApiController
{
    /**
     * Status yang berarti UANGNYA SUDAH MASUK.
     *
     * Keputusan pemilik, 25 Nov 2026: hanya yang sudah DP yang muncul di
     * kalender. Pendaftaran "baru" dan "dihubungi" belum tentu jadi berangkat,
     * dan kalender yang penuh rencana yang batal berhenti dipercaya.
     *
     * Untuk penyewaan, 'berjalan' dan 'selesai' ikut karena keduanya pasti
     * sudah melewati DP — sedangkan 'dikonfirmasi' TIDAK: ia berarti jadwalnya
     * disepakati, bukan uangnya diterima.
     */
    private const STATUS_PENDAFTARAN = ['dp_masuk', 'lunas'];

    private const STATUS_PENYEWAAN = ['dp_masuk', 'berjalan', 'selesai'];

    /**
     * RAB yang tanggalnya sudah TERPAKAI.
     *
     * 'disetujui' berarti pelanggan sudah setuju — itulah "deal", dan sejak
     * saat itu tanggalnya tidak lagi bebas ditawarkan ke rombongan lain.
     * 'selesai' ikut karena tripnya memang sudah berjalan di tanggal itu;
     * membuangnya membuat kalender bulan-bulan lampau terlihat kosong
     * padahal armadanya penuh.
     *
     * Yang TIDAK ikut: 'draf' dan 'dikirim' — keduanya baru penawaran, dan
     * penawaran yang belum dijawab bukan tanggal yang terpakai. Kalender yang
     * memasukkannya akan menolak pesanan yang sebenarnya masih bisa dilayani.
     */
    private const STATUS_RAB = ['disetujui', 'selesai'];

    /**
     * Rentang terlebar yang dilayani sekali panggil.
     *
     * Kisi kalender satu bulan paling banyak 42 hari; 120 memberi ruang untuk
     * tampilan tiga bulan tanpa membuka pintu bagi permintaan "seluruh tahun"
     * yang menarik ribuan baris sekaligus.
     */
    private const RENTANG_MAKS_HARI = 120;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari' => ['required', 'date'],
            'sampai' => ['required', 'date', 'after_or_equal:dari'],
        ]);

        $dari = Carbon::parse($data['dari'])->startOfDay();
        $sampai = Carbon::parse($data['sampai'])->endOfDay();

        if ($dari->diffInDays($sampai) > self::RENTANG_MAKS_HARI) {
            return response()->json([
                'message' => 'Rentang tanggal terlalu lebar. Paling banyak '.self::RENTANG_MAKS_HARI.' hari sekali minta.',
            ], 422);
        }

        $agenda = $this->keberangkatan($dari, $sampai)
            ->concat($this->rabDeal($dari, $sampai))
            ->concat($this->penyewaan($dari, $sampai))
            ->concat($this->katalog($dari, $sampai))
            ->sortBy(['mulai', 'judul'])
            ->values()
            ->all();

        return response()->json([
            'data' => $agenda,
            'meta' => [
                'dari' => $dari->toDateString(),
                'sampai' => $sampai->toDateString(),
                'total' => count($agenda),
            ],
        ]);
    }

    /**
     * Keberangkatan rombongan: open trip, private trip, study tour.
     *
     * Tanggal pulangnya diambil dari KEBERANGKATAN, bukan dari pendaftarannya:
     * pendaftaran hanya menyimpan `tanggal_berangkat`, dan trip tiga hari yang
     * digambar sebagai satu kotak sehari menyembunyikan dua hari armada dan
     * pemandu yang sudah terpakai.
     */
    private function keberangkatan(Carbon $dari, Carbon $sampai)
    {
        return PendaftaranOpenTrip::query()
            ->with(['paket:id,name,category', 'keberangkatan:id,tanggal_berangkat,tanggal_pulang'])
            ->whereIn('status', self::STATUS_PENDAFTARAN)
            ->whereNotNull('tanggal_berangkat')
            /*
             | Yang dicari keberangkatan yang MENYENTUH rentang ini, bukan yang
             | mulainya di dalamnya: trip 30 Agustus–2 September harus tetap
             | terlihat saat yang dibuka September. Tanggal pulang bisa kosong
             | (keberangkatannya belum terdaftar), dan kalau kosong trip itu
             | dianggap sehari.
             */
            ->where('tanggal_berangkat', '<=', $sampai)
            ->where(function ($q) use ($dari) {
                $q->whereHas('keberangkatan', fn ($k) => $k->where('tanggal_pulang', '>=', $dari))
                    ->orWhere('tanggal_berangkat', '>=', $dari);
            })
            ->get()
            ->map(function (PendaftaranOpenTrip $p) {
                $kategori = $p->paket?->category;
                $pulang = $p->keberangkatan?->tanggal_pulang;

                return [
                    'sumber' => 'pendaftaran',
                    'kategori' => $kategori,
                    'kategori_label' => config('orcha.kategori_paket')[$kategori] ?? 'Perjalanan',
                    'id' => $p->id,
                    'kode' => $p->kode,
                    'judul' => $p->nama_paket ?: ($p->paket?->name ?: 'Keberangkatan'),
                    'mulai' => $p->tanggal_berangkat->toDateString(),
                    // Tanggal pulang lebih awal daripada berangkat tidak masuk
                    // akal dan pernah terjadi karena salah ketik admin; kalau
                    // begitu, tripnya diperlakukan sehari.
                    'selesai' => $pulang && $pulang->gte($p->tanggal_berangkat)
                        ? $pulang->toDateString()
                        : null,
                    'pemesan' => $p->nama,
                    'jumlah_peserta' => (int) $p->jumlah_peserta,
                    'titik_jemput' => $p->titik_jemput,
                    'status' => $p->status,
                    'status_label' => config('orcha.status_pendaftaran')[$p->status] ?? $p->status,
                ];
            });
    }

    /**
     * RAB yang sudah deal — inti dari kalender ini.
     *
     * Pertanyaan yang dijawab halaman ini sebenarnya satu: "tanggal berapa
     * yang sudah terpakai?". Pendaftaran menjawabnya untuk rombongan yang
     * sudah membayar, tetapi banyak perjalanan sudah DEAL jauh sebelum ada
     * pendaftarannya — kesepakatannya terjadi di RAB, dan sejak RAB itu
     * disetujui tanggalnya tidak lagi bebas ditawarkan.
     *
     * Tanpa sumber ini, kalender berbohong ke arah yang paling mahal: ia
     * menampilkan tanggal yang sebenarnya sudah dipesan sebagai tanggal
     * kosong, dan rombongan kedua dijanjikan hari yang armadanya sudah
     * dipakai.
     *
     * RAB yang SUDAH jadi pendaftaran sengaja dilewati — ia sudah muncul
     * lewat pendaftarannya, dan dua pita untuk satu perjalanan membuat satu
     * hari terbaca dua kali lebih penuh daripada keadaannya.
     */
    private function rabDeal(Carbon $dari, Carbon $sampai)
    {
        return Rab::query()
            ->whereIn('status', self::STATUS_RAB)
            ->whereNull('kode_pendaftaran')
            ->whereNotNull('tanggal_mulai')
            /*
             | Tanggal selesainya DIHITUNG, bukan disimpan: RAB hanya punya
             | tanggal mulai dan jumlah hari. Trip 3 hari yang mulai tanggal
             | 10 berakhir tanggal 12 — jumlah hari dikurangi satu, karena
             | hari pertama ikut dihitung.
             |
             | Penyaringan akhirnya dikerjakan di PHP, BUKAN lewat SQL.
             |
             | Versi pertama memakai whereRaw("DATE(tanggal_mulai, '+N day')")
             | — itu sintaks SQLite. Uji di sini memang berjalan di SQLite dan
             | akan selalu hijau, sementara produksi memakai MySQL dan
             | menolaknya. Jebakan yang sama sudah pernah menggigit lewat ENUM
             | satuan harga: hijau di uji, patah di peladen.
             |
             | Jendelanya dilebarkan 30 hari ke belakang — batas jumlah_hari
             | yang diizinkan borang RAB — sehingga trip terpanjang sekalipun
             | pasti ikut terjaring, lalu dibuang lagi oleh saringan di bawah.
             | Yang ditarik tetap sedikit: 30 hari tambahan pada tabel RAB,
             | bukan seluruh tabelnya.
             */
            ->whereDate('tanggal_mulai', '<=', $sampai)
            ->whereDate('tanggal_mulai', '>=', $dari->copy()->subDays(30))
            ->get()
            ->filter(function (Rab $r) use ($dari) {
                $hari = max(1, (int) $r->jumlah_hari);

                return $r->tanggal_mulai->copy()->addDays($hari - 1)->gte($dari->copy()->startOfDay());
            })
            ->map(function (Rab $r) {
                $hari = max(1, (int) $r->jumlah_hari);
                $selesai = $r->tanggal_mulai->copy()->addDays($hari - 1);

                return [
                    'sumber' => 'rab',
                    'kategori' => $r->jenis === 'open' ? 'open_trip' : 'private_trip',
                    'kategori_label' => $r->jenis === 'open' ? 'Open Trip' : 'Private Trip',
                    'id' => $r->id,
                    'kode' => $r->kode,
                    'judul' => $r->judul ?: ($r->daerah ?: $r->provinsi ?: 'Perjalanan'),
                    'mulai' => $r->tanggal_mulai->toDateString(),
                    'selesai' => $hari > 1 ? $selesai->toDateString() : null,
                    'pemesan' => $r->nama_pelanggan,
                    'jumlah_peserta' => (int) $r->jumlah_peserta,
                    // RAB tidak punya titik jemput; yang setara dengannya
                    // tujuan perjalanannya.
                    'titik_jemput' => null,
                    'tujuan' => trim(($r->daerah ? $r->daerah.', ' : '').($r->provinsi ?: ''), ', ') ?: null,
                    'status' => $r->status,
                    'status_label' => config('orcha.rab.status')[$r->status] ?? $r->status,
                ];
            })
            // values(): filter() meninggalkan kunci berlubang, dan concat()
            // pada koleksi berkunci menghasilkan urutan yang tidak terduga.
            ->values();
    }

    /**
     * Jadwal yang sedang DIJUAL di katalog — belum deal, belum ada yang bayar.
     *
     * Tiga sumber di atas semuanya komitmen: uang sudah masuk, atau pelanggan
     * sudah setuju. Akibatnya tanggal yang open trip-nya sudah dibuka untuk
     * umum terlihat KOSONG di kalender, dan private trip ditawarkan di
     * tanggal yang armadanya sebenarnya sudah dialokasikan.
     *
     * Dipulangkan dengan sumber sendiri supaya lemon bisa menggambarnya samar
     * dan berbeda: ini rencana, bukan komitmen, dan kalender yang menyamakan
     * keduanya berhenti bisa dipakai memutuskan.
     *
     * Yang DIBUANG supaya tidak terhitung dua kali:
     * - keberangkatan yang sudah punya pendaftar berbayar (sudah muncul lewat
     *   sumber 'pendaftaran'),
     * - keberangkatan yang tanggal dan paketnya sama persis dengan RAB yang
     *   sudah disetujui (sudah muncul lewat sumber 'rab'). Dicocokkan lewat
     *   travel_package_id DAN tanggal, bukan ditebak dari namanya.
     */
    private function katalog(Carbon $dari, Carbon $sampai)
    {
        /*
         | Pasangan paket+tanggal yang sudah diwakili RAB disetujui.
         |
         | Dikumpulkan sekali di depan, bukan ditanyakan per baris: satu bulan
         | bisa berisi puluhan keberangkatan, dan kueri di dalam map() adalah
         | cara paling mudah membuat halaman kalender memanggil basis data
         | seratus kali tanpa ada yang menyadarinya.
         */
        $sudahDiwakiliRab = Rab::query()
            ->whereIn('status', self::STATUS_RAB)
            ->whereNotNull('travel_package_id')
            ->whereDate('tanggal_mulai', '<=', $sampai)
            ->whereDate('tanggal_mulai', '>=', $dari->copy()->subDays(30))
            ->get(['travel_package_id', 'tanggal_mulai'])
            ->map(fn (Rab $r) => $r->travel_package_id.'|'.$r->tanggal_mulai->toDateString())
            ->flip();

        return Keberangkatan::query()
            ->with(['paket:id,name,category,status'])
            ->where('aktif', true)
            ->whereDate('tanggal_berangkat', '<=', $sampai)
            ->where(function ($q) use ($dari) {
                $q->whereDate('tanggal_pulang', '>=', $dari)
                    ->orWhere(fn ($w) => $w->whereNull('tanggal_pulang')
                        ->whereDate('tanggal_berangkat', '>=', $dari));
            })
            // Jumlah pendaftar dihitung lewat withCount, bukan ditanyakan
            // ulang per baris.
            ->withCount([
                'pendaftaran as pendaftar_semua',
                'pendaftaran as pendaftar_bayar' => fn ($q) => $q->whereIn('status', self::STATUS_PENDAFTARAN),
            ])
            ->get()
            ->filter(function (Keberangkatan $k) use ($sudahDiwakiliRab) {
                // Paket yang belum terbit bukan "sedang dijual".
                if (($k->paket?->status ?? null) !== 'terbit') {
                    return false;
                }

                if ((int) $k->pendaftar_bayar > 0) {
                    return false;
                }

                return ! $sudahDiwakiliRab->has(
                    $k->travel_package_id.'|'.$k->tanggal_berangkat->toDateString()
                );
            })
            ->map(function (Keberangkatan $k) {
                $kategori = $k->paket?->category;
                $pulang = $k->tanggal_pulang;
                $menunggu = (int) $k->pendaftar_semua;

                return [
                    'sumber' => 'katalog',
                    'kategori' => $kategori,
                    'kategori_label' => config('orcha.kategori_paket')[$kategori] ?? 'Perjalanan',
                    'id' => $k->id,
                    // Keberangkatan katalog tidak punya kode pesanan — belum
                    // ada yang memesannya.
                    'kode' => null,
                    'judul' => $k->paket?->name ?: 'Keberangkatan',
                    'mulai' => $k->tanggal_berangkat->toDateString(),
                    'selesai' => $pulang && $pulang->gte($k->tanggal_berangkat)
                        ? $pulang->toDateString()
                        : null,
                    'pemesan' => null,
                    /*
                     | Jumlah pendaftar yang BELUM bayar, bukan kuota.
                     |
                     | Nol berarti benar-benar belum ada yang mendaftar;
                     | di atas nol berarti ada yang antre tetapi uangnya belum
                     | masuk — dua keadaan yang jauh berbeda artinya bagi yang
                     | memutuskan apakah tanggal ini masih bebas.
                     */
                    'jumlah_peserta' => $menunggu ?: null,
                    'titik_jemput' => null,
                    'tujuan' => null,
                    'kuota' => $k->kuota,
                    // Supaya lemon bisa menaut ke borang paketnya. 'id' di
                    // atas id KEBERANGKATAN, dan rute paket di lemon menerima
                    // id paket — menautkannya dengan id yang salah akan
                    // membuka paket milik orang lain.
                    'paket_id' => (int) $k->travel_package_id,
                    'status' => 'dijual',
                    'status_label' => $menunggu > 0
                        ? 'Dijual — '.$menunggu.' pendaftar belum bayar'
                        : 'Dijual — belum ada pendaftar',
                ];
            })
            ->values();
    }

    /**
     * Sewa kendaraan. Ikut atas permintaan pemilik: armada yang sedang keluar
     * menentukan apakah rombongan berikutnya bisa dilayani.
     */
    private function penyewaan(Carbon $dari, Carbon $sampai)
    {
        return PenyewaanKendaraan::query()
            ->whereIn('status', self::STATUS_PENYEWAAN)
            ->whereNotNull('tanggal_mulai')
            ->where('tanggal_mulai', '<=', $sampai)
            ->where(function ($q) use ($dari) {
                $q->where('tanggal_selesai', '>=', $dari)
                    ->orWhere(fn ($w) => $w->whereNull('tanggal_selesai')->where('tanggal_mulai', '>=', $dari));
            })
            ->get()
            ->map(function (PenyewaanKendaraan $s) {
                $selesai = $s->tanggal_selesai;

                return [
                    'sumber' => 'penyewaan',
                    'kategori' => 'sewa_kendaraan',
                    'kategori_label' => 'Sewa Kendaraan',
                    'id' => $s->id,
                    'kode' => $s->kode,
                    'judul' => $s->nama_kendaraan ?: 'Sewa kendaraan',
                    'mulai' => $s->tanggal_mulai->toDateString(),
                    'selesai' => $selesai && $selesai->gte($s->tanggal_mulai)
                        ? $selesai->toDateString()
                        : null,
                    'pemesan' => $s->nama,
                    // Penyewaan tidak mencatat jumlah peserta — yang disewa
                    // kendaraannya, bukan kursinya. Dikirim null, bukan 0:
                    // nol terbaca sebagai "tidak ada yang ikut".
                    'jumlah_peserta' => null,
                    'titik_jemput' => $s->lokasi_antar,
                    'tujuan' => $s->tujuan,
                    'dengan_sopir' => (bool) $s->dengan_sopir,
                    'status' => $s->status,
                    'status_label' => config('orcha.status_penyewaan')[$s->status] ?? $s->status,
                ];
            });
    }
}
