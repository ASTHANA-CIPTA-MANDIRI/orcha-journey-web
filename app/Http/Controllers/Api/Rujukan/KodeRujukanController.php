<?php

namespace App\Http\Controllers\Api\Rujukan;

use App\Http\Controllers\Api\ApiController;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\Rujukan\KodeRujukan;
use App\Support\NomorTelepon;
use App\Support\Rujukan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kode rujukan dan komisi yang menyertainya.
 *
 * Dua hal dilayani jalur ini, dan yang kedua justru yang paling sering
 * ditanyakan: siapa yang membawa berapa pendaftaran, dan berapa yang belum
 * dibayarkan kepadanya. Tanpa itu, satu-satunya cara mengetahui komisi mana
 * yang sudah dibayar adalah mengingatnya — dan yang menagih nanti orang yang
 * merasa haknya belum diberikan, sambil kita tidak punya cara membuktikan
 * sebaliknya.
 */
class KodeRujukanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = KodeRujukan::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
            ))
            ->when($request->filled('aktif'), fn ($q) => $q->where('aktif', $request->boolean('aktif')))

            /*
             | Angka komisinya dihitung LEWAT SATU KUERI, bukan per baris.
             |
             | Halaman berisi lima puluh kode yang masing-masing menghitung
             | pemakaiannya sendiri menghasilkan lima puluh kueri tambahan —
             | dan justru halaman inilah yang paling sering dibuka saat komisi
             | dibayarkan tiap akhir bulan.
             */
            ->withCount(['pendaftaran as jumlah_dipakai'])

            /*
             | Yang dihitung sebagai imbalan HANYA pendaftaran yang sudah lunas.
             |
             | Sebelum ini imbalannya terhitung sejak orangnya mengisi formulir,
             | sehingga laporan komisi memuat uang yang belum pernah masuk:
             | yang mendaftar lalu tidak pernah membayar, dan yang membatalkan,
             | keduanya tetap menambah tagihan. Yang menagihnya kemudian pemilik
             | kode — dengan angka yang kita sendiri yang menampilkan.
             */
            ->withSum([
                'pendaftaran as imbalan_total' => fn ($q) => $q->imbalanBerhak(),
            ], 'imbalan_rujukan')
            ->withSum([
                'pendaftaran as imbalan_belum_dibayar' => fn ($q) => $q->imbalanBelumDibayar(),
            ], 'imbalan_rujukan')

            /*
             | Yang sudah memakai kode tetapi belum lunas, dihitung terpisah.
             |
             | Ditampilkan, bukan disembunyikan: pemilik kode yang bertanya
             | "kenapa komisi saya belum muncul" perlu dijawab dengan angka,
             | bukan dengan keterangan bahwa datanya tidak ada.
             */
            ->withSum([
                'pendaftaran as imbalan_menunggu' => fn ($q) => $q->imbalanMenunggu(),
            ], 'imbalan_rujukan')

            ->latest('id')
            ->paginate($this->perHalaman($request));

        $daftar->getCollection()->transform(fn (KodeRujukan $satu) => [
            'id' => $satu->id,
            'kode' => $satu->kode,
            'nama' => $satu->nama,
            'whatsapp' => NomorTelepon::rapi($satu->whatsapp),
            'email' => $satu->email,
            'kode_pendaftaran_asal' => $satu->kode_pendaftaran_asal,
            'aktif' => $satu->aktif,
            'catatan' => $satu->catatan,
            'jumlah_dipakai' => (int) $satu->jumlah_dipakai,
            /*
             | withSum mengembalikan NULL, bukan 0, saat tidak ada barisnya.
             | Dibulatkan di sini supaya lemon tidak perlu menjaga hal yang
             | sama di tiap tempat angkanya ditampilkan.
             */
            'imbalan_total' => (int) ($satu->imbalan_total ?? 0),
            'imbalan_belum_dibayar' => (int) ($satu->imbalan_belum_dibayar ?? 0),
            // Sudah memakai kodenya, tetapi belum lunas — jadi belum jadi hak
            // siapa pun. Ditampilkan supaya pertanyaan "kenapa komisi saya
            // belum muncul" bisa dijawab dengan angka.
            'imbalan_menunggu' => (int) ($satu->imbalan_menunggu ?? 0),
            'dibuat_pada' => $satu->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $daftar->items(),
            'meta' => [
                'halaman' => $daftar->currentPage(),
                'per_halaman' => $daftar->perPage(),
                'total' => $daftar->total(),
                'halaman_terakhir' => $daftar->lastPage(),
                'potongan' => Rujukan::potongan(),
                'imbalan' => Rujukan::imbalan(),
                'aktif' => (bool) config('orcha.rujukan.aktif', true),
            ],
        ]);
    }

    /**
     * Pendaftaran yang memakai satu kode, beserta keadaan komisinya.
     */
    public function pemakaian(KodeRujukan $rujukan): JsonResponse
    {
        $pakai = PendaftaranOpenTrip::query()
            ->where('kode_rujukan', $rujukan->kode)
            ->latest('id')
            ->get()
            ->map(fn (PendaftaranOpenTrip $satu) => [
                'id' => $satu->id,
                'kode' => $satu->kode,
                'nama' => $satu->nama,
                'nama_paket' => $satu->nama_paket,
                'tanggal_berangkat' => $satu->tanggal_berangkat?->toDateString(),
                'status' => $satu->status,
                'imbalan' => (int) $satu->imbalan_rujukan,
                'dibayar_pada' => $satu->imbalan_dibayar_pada?->toIso8601String(),

                /*
                 | Apakah imbalannya sudah jadi HAK pemilik kode.
                 |
                 | Dikirim sebagai keputusan, bukan dibiarkan lemon
                 | menyimpulkannya sendiri dari status. Aturannya ada di satu
                 | tempat — kalau tidak, layar dan server bisa berbeda pendapat
                 | tentang komisi yang sama, dan yang menengahi tidak ada.
                 */
                'berhak' => $satu->status === 'lunas',
            ]);

        return response()->json(['data' => $pakai->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->periksa($request);

        /*
         | Satu orang satu kode, dikenali dari nomornya.
         |
         | Kode kedua untuk orang yang sama memecah imbalannya jadi dua catatan
         | terpisah, dan yang menagih nanti menagih keduanya — sementara
         | laporan kita hanya menunjukkan salah satunya.
         */
        $ada = KodeRujukan::where('whatsapp', NomorTelepon::angka($data['whatsapp']))->first();

        if ($ada) {
            abort(422, 'Nomor ini sudah punya kode rujukan: '.$ada->kode.'. Sunting yang itu saja.');
        }

        $rujukan = KodeRujukan::create($data);

        $this->catat($request, 'tambah kode rujukan', [
            'kode' => $rujukan->kode,
            'nama' => $rujukan->nama,
        ]);

        return response()->json(['data' => $rujukan], 201);
    }

    public function update(KodeRujukan $rujukan, Request $request): JsonResponse
    {
        /*
         | KODENYA SENDIRI TIDAK BISA DIUBAH.
         |
         | Ia sudah tersebar di grup WhatsApp temannya dan sudah menempel pada
         | pendaftaran yang lalu. Mengubahnya memutus jejak komisi yang belum
         | dibayarkan, dan membuat kode yang sedang beredar mendadak ditolak
         | tanpa ada yang bisa menjelaskan kenapa.
         */
        $rujukan->update($this->periksa($request, $rujukan));

        $this->catat($request, 'ubah kode rujukan', ['kode' => $rujukan->kode]);

        return response()->json(['data' => $rujukan->fresh()]);
    }

    /**
     * Menandai imbalan satu pendaftaran sudah dibayarkan.
     */
    public function bayar(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        if (blank($pendaftaran->kode_rujukan)) {
            abort(422, 'Pendaftaran ini tidak memakai kode rujukan.');
        }

        /*
         | Komisi baru jadi hak setelah pendaftarannya LUNAS.
         |
         | Ditahan di sini, bukan cuma disembunyikan tombolnya di layar: yang
         | dibayarkan uang, dan uang yang sudah berpindah tidak bisa ditarik
         | kembali. Layar bisa saja tertinggal keadaannya — dibuka sebelum
         | statusnya berubah, lalu tombolnya ditekan semenit kemudian.
         |
         | Uang muka tidak cukup. DP bisa hangus, pesanannya bisa batal, dan
         | kursinya bisa dilepas karena pelunasannya tidak pernah datang.
         | Membayar komisi atas pesanan yang kemudian batal berarti kehilangan
         | dua kali: trip yang tidak jadi, dan komisi yang tidak bisa ditagih
         | balik dari orang yang sudah menerimanya.
         */
        if ($pendaftaran->status !== 'lunas') {
            $label = config('orcha.status_pendaftaran')[$pendaftaran->status] ?? $pendaftaran->status;

            abort(422, 'Imbalan baru bisa dibayarkan setelah pendaftarannya lunas. '
                ."Pendaftaran {$pendaftaran->kode} masih berstatus {$label}.");
        }

        // Membayar dua kali tidak bisa ditarik kembali, jadi ditahan di sini
        // meskipun layarnya sudah menyembunyikan tombolnya.
        if ($pendaftaran->imbalan_dibayar_pada) {
            abort(422, 'Imbalan untuk pendaftaran ini sudah ditandai dibayar pada '
                .$pendaftaran->imbalan_dibayar_pada->translatedFormat('j F Y').'.');
        }

        $pendaftaran->update(['imbalan_dibayar_pada' => now()]);

        $this->catat($request, 'bayar imbalan rujukan', [
            'pendaftaran' => $pendaftaran->kode,
            'kode_rujukan' => $pendaftaran->kode_rujukan,
            'imbalan' => $pendaftaran->imbalan_rujukan,
        ]);

        return response()->json(['data' => ['dibayar_pada' => $pendaftaran->fresh()->imbalan_dibayar_pada?->toIso8601String()]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function periksa(Request $request, ?KodeRujukan $kecuali = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:120'],
            'whatsapp' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:150'],
            'kode_pendaftaran_asal' => ['nullable', 'string', 'max:32'],
            'aktif' => ['nullable', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
