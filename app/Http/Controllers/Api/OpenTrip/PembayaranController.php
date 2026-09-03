<?php

namespace App\Http\Controllers\Api\OpenTrip;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OpenTrip\PembayaranResource;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\GambarWebp;
use App\Support\KabarPembayaran;
use App\Support\StatusPendaftaran;
use App\Support\TagihanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembayaranController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = KonfirmasiPembayaran::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('kode', 'like', "%{$cari}%")
                    ->orWhere('atas_nama_pengirim', 'like', "%{$cari}%")
                    ->orWhere('bank_pengirim', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PembayaranResource::class);
    }

    /**
     * Berapa bukti yang masih menunggu dicek.
     *
     * Jalur tersendiri, bukan menghitung dari daftar: yang dibutuhkan cuma satu
     * bilangan, dan mengambil sehalaman penuh bukti berikut pesanan tiap
     * barisnya hanya untuk membaca meta.total adalah pekerjaan yang jauh lebih
     * berat daripada jawabannya.
     *
     * Dipanggil bilah samping lemon di SETIAP halaman admin, jadi murahnya
     * bukan kemewahan.
     */
    public function menunggu(): JsonResponse
    {
        $menunggu = KonfirmasiPembayaran::menunggu();

        return response()->json(['data' => [
            'jumlah' => (int) (clone $menunggu)->count(),
            'nominal' => (int) $menunggu->sum('nominal'),
        ]]);
    }

    public function show(KonfirmasiPembayaran $pembayaran): JsonResponse
    {
        return response()->json([
            'data' => (new PembayaranResource($pembayaran))->resolve(),
        ]);
    }

    /**
     * Mencatat pembayaran yang diterima admin sendiri.
     *
     * Private trip dan study tour tidak lewat formulir konfirmasi publik.
     * Panitia mentransfer lalu mengabari lewat WhatsApp, kadang cuma dengan
     * kalimat "sudah ditransfer ya" tanpa tangkapan layar. Yang memastikan
     * uangnya benar-benar masuk adalah admin yang membuka mutasi rekening —
     * dan sampai sekarang tidak ada satu pun tempat mencatat pemeriksaan itu.
     *
     * Akibatnya uang yang sudah diterima tidak pernah tercatat: statusnya
     * tertahan di "Baru", formulir riwayat kesehatannya tetap tertutup, dan
     * laporan keuangan menyebut nol untuk rombongan yang sudah membayar penuh.
     *
     * LANGSUNG BERSTATUS DITERIMA, dan itu disengaja. Bukti dari pelanggan
     * menunggu diperiksa karena siapa pun bisa mengunggah gambar; yang
     * dicatat di sini adalah HASIL pemeriksaan itu sendiri. Menaruhnya di
     * antrean menunggu berarti admin harus menyetujui catatannya sendiri —
     * langkah yang tidak memeriksa apa pun dan hanya menunda uangnya tercatat.
     *
     * Karena itu ia dicatat di jejak audit dengan nama admin yang memasukkannya.
     */
    public function catatManual(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'nominal' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'tanggal_transfer' => ['required', 'date', 'before_or_equal:today'],
            'bank_pengirim' => ['required', 'string', 'max:60'],
            'atas_nama_pengirim' => ['required', 'string', 'max:120'],
            'jenis' => ['required', 'in:'.implode(',', array_keys(config('orcha.jenis_pembayaran')))],
            'catatan' => ['nullable', 'string', 'max:1000'],

            /*
             | Bukti transfer BOLEH kosong di jalur ini, dan itu bedanya dengan
             | formulir publik.
             |
             | Di sana bukti wajib karena tanpa gambar tidak ada yang bisa
             | dicek. Di sini yang mencatat justru orang yang SUDAH mengecek —
             | ia menatap mutasi rekening, bukan menunggu dikirimi gambar. Dan
             | sebagian panitia memang cuma menulis "sudah ditransfer ya" tanpa
             | tangkapan layar apa pun.
             |
             | Mewajibkannya berarti pembayaran yang nyata tidak bisa dicatat
             | karena kurang sebuah gambar — dan yang terjadi berikutnya bukan
             | admin mengejar gambarnya, melainkan pembayarannya tidak dicatat
             | sama sekali.
             */
            'bukti' => ['nullable', 'image', 'max:4096'],
        ], [], [
            'nominal' => 'nominal',
            'tanggal_transfer' => 'tanggal transfer',
            'bank_pengirim' => 'bank pengirim',
            'atas_nama_pengirim' => 'atas nama pengirim',
        ]);

        $pembayaran = KonfirmasiPembayaran::create([
            'kode' => $pendaftaran->kode,
            'jenis' => $data['jenis'],
            'nominal' => $data['nominal'],
            'tanggal_transfer' => $data['tanggal_transfer'],
            'bank_pengirim' => $data['bank_pengirim'],
            'atas_nama_pengirim' => $data['atas_nama_pengirim'],
            'catatan' => $data['catatan'] ?? null,
            // Disimpan lewat jalur yang sama dengan bukti dari pelanggan:
            // folder rahasia, di luar disk publik. Bukti transfer memuat nomor
            // rekening dan nama orang.
            'bukti' => $request->hasFile('bukti')
                ? GambarWebp::simpan($request->file('bukti'), 'bukti-bayar')
                : null,
            'status' => 'diterima',

            /*
             | Ditandai sebagai catatan admin, bukan bukti pelanggan.
             |
             | Yang membaca daftar pembayaran setahun kemudian perlu bisa
             | membedakan keduanya: yang satu datang dari pelanggan dan
             | diperiksa, yang satu dicatat orang dalam. Tanpa penanda,
             | keduanya terlihat sama persis dan pertanyaan "ini dari mana?"
             | tidak bisa dijawab.
             */
            'catatan_admin' => 'Dicatat manual oleh '
                .($request->attributes->get('admin_pemanggil') ?: 'admin')
                .' — dicocokkan dengan mutasi rekening.',
        ]);

        $this->catat($request, 'catat pembayaran manual', [
            'kode' => $pendaftaran->kode,
            'nominal' => $pembayaran->nominal,
            'jenis' => $pembayaran->jenis,
            'bank' => $pembayaran->bank_pengirim,
            // Ada tidaknya bukti ikut dicatat: yang menelusuri setahun kemudian
            // perlu tahu apakah masih ada gambar yang bisa dibuka, atau
            // catatan ini bersandar sepenuhnya pada seseorang yang membuka
            // mutasi rekening pada hari itu.
            'berbukti' => $pembayaran->bukti !== null,
        ]);

        // Satu kejadian, satu langkah — sama seperti menyetujui bukti
        // pelanggan: statusnya ikut maju tanpa perlu diingat admin.
        $pesan = 'Pembayaran dicatat.';
        $statusBaru = StatusPendaftaran::selaraskan($pendaftaran->fresh());

        if ($statusBaru) {
            $label = config('orcha.status_pendaftaran')[$statusBaru] ?? $statusBaru;
            $pesan .= " Status pesanan ikut menjadi {$label}.";

            $this->catat($request, 'status pesanan menyesuaikan pembayaran', [
                'kode' => $pendaftaran->kode,
                'ke' => $statusBaru,
            ]);
        }

        return response()->json([
            'pesan' => $pesan,
            'data' => [
                'id' => $pembayaran->id,
                'nominal' => $pembayaran->nominal,
                'status_pendaftaran' => $pendaftaran->fresh()->status,
                'tagihan' => TagihanPesanan::untuk($pendaftaran->fresh(), hanyaDiterima: true),
            ],
        ], 201);
    }

    public function ubahStatus(KonfirmasiPembayaran $pembayaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('orcha.status_pembayaran'))),
            'catatan_admin' => 'nullable|string|max:1000',
        ]);

        $sebelum = $pembayaran->status;
        $pembayaran->update($data);

        $this->catat($request, 'ubah status pembayaran', [
            'kode' => $pembayaran->kode,
            'nominal' => $pembayaran->nominal,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        // Satu kejadian, satu langkah: menyetujui bukti transfer sekaligus
        // memajukan status pendaftarannya. Dijalankan di sini, bukan di lemon,
        // supaya berlaku dari mana pun statusnya diubah.
        $pesan = 'Status pembayaran diperbarui.';
        $pesanan = $pembayaran->pesanan();
        $statusBaru = StatusPendaftaran::selaraskan($pesanan);

        if ($statusBaru) {
            $rujukan = $pesanan instanceof PendaftaranOpenTrip
                ? config('orcha.status_pendaftaran')
                : config('orcha.status_penyewaan');

            $label = $rujukan[$statusBaru] ?? $statusBaru;
            $pesan .= " Status pesanan {$pesanan->kode} ikut menjadi {$label}.";

            $this->catat($request, 'status pesanan menyesuaikan pembayaran', [
                'kode' => $pesanan->kode,
                'ke' => $statusBaru,
            ]);
        }

        // Pelanggan diberi tahu begitu pembayarannya diperiksa. Sebelumnya ia
        // hanya diberi tahu saat MENGIRIM bukti, lalu menunggu tanpa kabar —
        // dan yang paling sering ditanyakan lewat WhatsApp justru ini.
        if ($sebelum !== $data['status']) {
            KabarPembayaran::kirim($pembayaran->fresh(), $pesanan);
        }

        return response()->json([
            'data' => (new PembayaranResource($pembayaran->fresh()))->resolve(),
            'pesan' => $pesan,
        ]);
    }
}
