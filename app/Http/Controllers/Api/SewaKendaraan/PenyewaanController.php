<?php

namespace App\Http\Controllers\Api\SewaKendaraan;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SewaKendaraan\PenyewaanResource;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\BerkasKwitansi;
use App\Support\GambarWebp;
use App\Support\KirimPemberitahuan;
use App\Support\SalinanPelanggan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenyewaanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = PenyewaanKendaraan::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('nama_kendaraan', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PenyewaanResource::class);
    }

    public function show(PenyewaanKendaraan $penyewaan): JsonResponse
    {
        return response()->json([
            'data' => (new PenyewaanResource($penyewaan))->resolve(),
        ]);
    }

    public function ubahStatus(PenyewaanKendaraan $penyewaan, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('orcha.status_penyewaan'))),
        ]);

        $sebelum = $penyewaan->status;
        $penyewaan->update($data);

        $this->catat($request, 'ubah status penyewaan', [
            'kode' => $penyewaan->kode,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        return response()->json([
            'data' => (new PenyewaanResource($penyewaan->fresh()))->resolve(),
            'pesan' => 'Status pemesanan sewa diperbarui.',
        ]);
    }

    /**
     * Kwitansi sewa kendaraan.
     *
     * Isinya mengikuti keadaan pesanannya: sebelum unit kembali yang tampil
     * estimasi biaya sewa, sesudahnya yang tampil total termasuk denda. Denda
     * dirinci per jenis supaya penyewa bisa melihat asal angkanya — nota yang
     * hanya menyebut satu total selalu berakhir jadi perdebatan.
     */
    public function kwitansi(PenyewaanKendaraan $penyewaan, Request $request)
    {
        $sudahKembali = (bool) $penyewaan->dikembalikan_pada;

        $rincian = array_filter([
            'Kendaraan' => $penyewaan->nama_kendaraan.' ('.$penyewaan->transmisi.')',
            'Sopir' => $penyewaan->dengan_sopir ? 'Dengan sopir' : 'Lepas kunci',
            'Mulai' => $penyewaan->jadwal_mulai?->translatedFormat('j F Y, H:i'),
            'Ditunggu kembali' => $penyewaan->jadwal_selesai?->translatedFormat('j F Y, H:i'),
            'Kembali pada' => $penyewaan->dikembalikan_pada?->translatedFormat('j F Y, H:i'),
            'Durasi' => $penyewaan->durasi_label,
            'Lokasi pengantaran' => $penyewaan->lokasi_antar,
            'Lokasi pengembalian' => $penyewaan->lokasi_kembali,
            'Penyewa' => $penyewaan->nama,
            'WhatsApp' => $penyewaan->whatsapp,
        ]);

        $isi = BerkasKwitansi::buat(
            $sudahKembali ? 'Nota Akhir Sewa Kendaraan' : 'Rincian Pemesanan Sewa Kendaraan',
            $penyewaan->kode,
            $rincian,
            $penyewaan->catatan_denda ?: $penyewaan->catatan,
            'Rp '.number_format($penyewaan->total_tagihan, 0, ',', '.'),
            $sudahKembali ? 'Total termasuk denda' : 'Estimasi biaya sewa',
            $sudahKembali ? 'Nota Akhir' : 'Belum Dibayar',
            // Biaya dan denda dijumlahkan di notanya sendiri. Sebelumnya denda
            // hanya jadi baris keterangan di antara data lain dan tidak pernah
            // ditambahkan, jadi penyewa harus menjumlahkan sendiri dari berkas
            // yang seharusnya menjawab itu.
            nota: self::notaSewa($penyewaan),
        );

        abort_if($isi === null, 503, 'Kwitansi gagal dibuat.');

        $this->catat($request, 'unduh kwitansi sewa', ['kode' => $penyewaan->kode]);

        return response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                .BerkasKwitansi::namaBerkas($sudahKembali ? 'nota-sewa' : 'rincian-sewa', $penyewaan->kode).'"',
        ]);
    }

    /**
     * Mengunggah foto berkas jaminan penyewa (KTP, SIM, dan sejenisnya).
     *
     * Jalur tersendiri karena bentuknya berkas, bukan JSON — dan karena ini
     * data pribadi, pengunggahannya dicatat seperti pembukaan riwayat
     * kesehatan.
     */
    public function berkasJaminan(PenyewaanKendaraan $penyewaan, Request $request): JsonResponse
    {
        $request->validate(['berkas' => 'required|image|max:8192']);

        $penyewaan->update([
            'berkas_jaminan' => GambarWebp::simpan($request->file('berkas'), 'jaminan'),
        ]);

        $this->catat($request, 'unggah berkas jaminan penyewa', ['kode' => $penyewaan->kode]);

        return response()->json([
            'data' => (new PenyewaanResource($penyewaan->fresh()))->resolve(),
            'pesan' => 'Berkas jaminan tersimpan.',
        ]);
    }

    /**
     * Mengirim nota akhir ke penyewa sebagai bukti penagihan.
     *
     * Berkasnya sama persis dengan yang bisa diunduh admin, jadi tidak ada dua
     * versi angka yang beredar. Kegagalan mengirim tidak membatalkan catatan
     * serah terimanya — itu sudah tersimpan sebelum ini dipanggil.
     */
    private static function kirimNotaAkhir(PenyewaanKendaraan $penyewaan): void
    {
        if (blank($penyewaan->email)) {
            return;
        }

        $rincian = array_filter([
            'Kendaraan' => $penyewaan->nama_kendaraan.' ('.$penyewaan->transmisi.')',
            'Kembali pada' => $penyewaan->dikembalikan_pada?->translatedFormat('j F Y, H:i'),
            'Ditunggu kembali' => $penyewaan->jadwal_selesai?->translatedFormat('j F Y, H:i'),
            'Lokasi pengembalian' => $penyewaan->lokasi_kembali,
            'Total tagihan' => 'Rp '.number_format($penyewaan->total_tagihan, 0, ',', '.'),
        ]);

        $berkas = BerkasKwitansi::buat(
            'Nota Akhir Sewa Kendaraan',
            $penyewaan->kode,
            $rincian,
            $penyewaan->catatan_denda,
            'Rp '.number_format($penyewaan->total_tagihan, 0, ',', '.'),
            'Total termasuk denda',
            'Nota Akhir',
            nota: self::notaSewa($penyewaan),
        );

        $adaDenda = $penyewaan->total_denda > 0;

        KirimPemberitahuan::kirim(
            'Unit Kembali & Nota Akhir',
            $penyewaan->kode,
            $rincian,
            $penyewaan->catatan_denda,
            [],
            $berkas ? [BerkasKwitansi::namaBerkas('nota-sewa', $penyewaan->kode) => $berkas] : [],
            pelanggan: new SalinanPelanggan(
                email: $penyewaan->email,
                judul: $adaDenda ? 'Nota Akhir Sewa — Ada Denda' : 'Terima Kasih, Unit Sudah Kembali',
                langkah: $adaDenda
                    ? 'Unit sudah kembali dan diperiksa. Ada denda yang perlu diselesaikan, '
                        ."rinciannya ada di lampiran surat ini — bagian mana, alasannya, dan berapa.\n\n"
                        .'Bila menurut Anda ada yang tidak sesuai, hubungi kami lewat WhatsApp sebelum '
                        .'membayar; hasil pemeriksaan saat unit diserahkan kami simpan dan bisa dibandingkan.'
                    : 'Unit sudah kembali dan diperiksa, tidak ada denda. Terima kasih sudah menjaga '
                        .'kendaraannya. Nota akhirnya terlampir untuk arsip Anda.',
            ),
        );
    }

    /**
     * Baris nota sewa: biaya sewanya, lalu tiap denda yang benar-benar ada.
     *
     * Denda yang nol tidak ditampilkan — nota yang penuh baris "Rp 0" membuat
     * yang benar-benar ditagih jadi sulit ditemukan.
     *
     * @return array<string, mixed>
     */
    public static function notaSewa(PenyewaanKendaraan $penyewaan): array
    {
        $rp = fn ($angka) => 'Rp '.number_format((int) $angka, 0, ',', '.');

        $baris = [[
            'label' => 'Biaya sewa',
            'keterangan' => $penyewaan->durasi_label.' · '.($penyewaan->dengan_sopir ? 'dengan sopir' : 'lepas kunci'),
            'nilai' => $rp($penyewaan->estimasi_biaya),
        ]];

        foreach ([
            ['Denda keterlambatan', $penyewaan->denda_keterlambatan, $penyewaan->terlambat
                ? floor($penyewaan->terlambat_menit / 60).' jam '.($penyewaan->terlambat_menit % 60).' menit lewat tenggat'
                : null],
            ['Denda kerusakan', $penyewaan->denda_kerusakan, collect($penyewaan->kerusakan_baru)
                ->pluck('bagian')->implode(', ') ?: null],
            ['Denda lain', $penyewaan->denda_lain, null],
        ] as [$label, $nilai, $keterangan]) {
            if ((int) $nilai <= 0) {
                continue;
            }

            $baris[] = [
                'label' => $label,
                'keterangan' => $keterangan,
                'nilai' => $rp($nilai),
                'denda' => true,
            ];
        }

        return [
            'baris' => $baris,
            'total' => $rp($penyewaan->total_tagihan),
            'label_total' => 'Total tagihan',
        ];
    }

    /**
     * Serah terima unit: saat diserahkan, dan saat kembali.
     *
     * Satu jalur untuk dua kejadian, karena bentuk datanya sama — kilometer,
     * bahan bakar, dan hasil pemeriksaan per bagian. Yang membedakan hanya
     * kapan diisinya.
     *
     * Denda TIDAK dihitung sendiri lalu langsung ditagihkan. Sistem hanya
     * mengusulkan angkanya; yang menetapkan tetap admin, karena alasan telat
     * kadang memang di luar kuasa penyewa. Yang penting angkanya bisa
     * dijelaskan asal-usulnya, bukan muncul begitu saja.
     */
    public function serahTerima(PenyewaanKendaraan $penyewaan, Request $request): JsonResponse
    {
        $bagian = implode(',', array_keys(config('orcha.pemeriksaan_kendaraan')));
        $kondisi = implode(',', array_keys(config('orcha.kondisi_pemeriksaan')));

        $data = $request->validate([
            'diserahkan_pada' => 'nullable|date',
            'dikembalikan_pada' => 'nullable|date',
            'kilometer_awal' => 'nullable|integer|min:0|max:9999999',
            'kilometer_akhir' => 'nullable|integer|min:0|max:9999999',
            'bahan_bakar_awal' => 'nullable|string|max:20',
            'bahan_bakar_akhir' => 'nullable|string|max:20',
            'jaminan' => 'nullable|string|max:191',
            'kondisi_awal' => 'nullable|array',
            'kondisi_awal.*' => 'in:'.$kondisi,
            'kondisi_akhir' => 'nullable|array',
            'kondisi_akhir.*' => 'in:'.$kondisi,
            'denda_keterlambatan' => 'nullable|integer|min:0',
            'denda_kerusakan' => 'nullable|integer|min:0',
            'denda_lain' => 'nullable|integer|min:0',
            'catatan_denda' => 'nullable|string|max:1000',
        ]);

        // Bagian yang tidak dikenal ditolak diam-diam, supaya perbandingan
        // kondisi awal dan akhir selalu memakai daftar yang sama.
        foreach (['kondisi_awal', 'kondisi_akhir'] as $kunci) {
            if (isset($data[$kunci])) {
                $data[$kunci] = array_intersect_key($data[$kunci], array_flip(explode(',', $bagian)));
            }
        }

        $penyewaan->update(array_filter($data, fn ($nilai) => $nilai !== null));

        // Unit membawa keadaannya sendiri ke sewa berikutnya. Tanpa ini, admin
        // mengetik ulang daftar lecet lama setiap kali unit disewakan — dan
        // yang lupa diketik akan tertagih ke penyewa berikutnya.
        if (filled($data['kondisi_akhir'] ?? null) && $penyewaan->kendaraan) {
            $penyewaan->kendaraan->update([
                'kondisi_terkini' => $data['kondisi_akhir'],
                'kondisi_diperiksa_pada' => now(),
            ]);
        }

        // Unit yang sudah kembali berarti sewanya selesai. Status yang harus
        // diingat sendiri adalah status yang paling sering tertinggal.
        if (filled($data['dikembalikan_pada'] ?? null) && ! in_array($penyewaan->status, ['selesai', 'batal'], true)) {
            $penyewaan->update(['status' => 'selesai']);
        }

        // Nota akhir dikirim ke penyewa begitu unitnya kembali. Denda yang
        // hanya disebut lisan di loket gampang jadi perdebatan seminggu
        // kemudian; yang dikirim ini menyebut bagian mana, kenapa, dan berapa.
        if (filled($data['dikembalikan_pada'] ?? null)) {
            self::kirimNotaAkhir($penyewaan->fresh());
        }

        $this->catat($request, 'catat serah terima kendaraan', [
            'kode' => $penyewaan->kode,
            'dikembalikan' => $penyewaan->dikembalikan_pada?->toDateTimeString(),
            'total_denda' => $penyewaan->fresh()->total_denda,
        ]);

        return response()->json([
            'data' => (new PenyewaanResource($penyewaan->fresh()))->resolve(),
            'pesan' => 'Catatan serah terima kendaraan tersimpan.',
        ]);
    }
}
