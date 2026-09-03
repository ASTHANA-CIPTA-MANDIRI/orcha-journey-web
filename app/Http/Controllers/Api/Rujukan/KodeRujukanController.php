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
            ->withSum(['pendaftaran as imbalan_total' => fn ($q) => $q], 'imbalan_rujukan')
            ->withSum([
                'pendaftaran as imbalan_belum_dibayar' => fn ($q) => $q->whereNull('imbalan_dibayar_pada'),
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
