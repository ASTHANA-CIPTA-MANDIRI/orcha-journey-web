<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabRealisasi;
use App\Models\Rab\RabRealisasiBayar;
use App\Support\GambarWebp;
use App\Support\Rab\RekapRab;
use App\Support\RincianBiaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rekap pengeluaran sesudah trip jalan.
 *
 * Menjawab dua hal yang tidak bisa dijawab RAB sendirian: trip ini
 * sebenarnya untung berapa, dan uang mana yang masih menggantung.
 *
 * Yang dicatat per baris biaya ada TIGA angka — RAB, tagihan vendor, dan
 * yang dibayar. Alasan angka ketiga ada di RekapRab; ringkasnya, tanpa
 * tagihan yang dicatat terpisah, hotel yang lupa dilunasi terlihat persis
 * seperti hotel yang memberi cashback.
 */
class RealisasiController extends ApiController
{
    /** Rekap lengkap sebuah RAB. */
    public function index(Rab $rab): JsonResponse
    {
        return response()->json([
            'data' => RekapRab::untuk($rab) + [
                'rab' => [
                    'id' => $rab->id,
                    'kode' => $rab->kode,
                    'judul' => $rab->judul,
                    'status' => $rab->status,
                    'status_label' => $rab->status_label,
                    'tanggal_mulai' => $rab->tanggal_mulai?->toDateString(),
                    'jumlah_peserta' => $rab->jumlah_peserta,
                ],
                'jenis_bayar' => config('orcha.rab.realisasi_jenis'),
            ],
        ]);
    }

    /**
     * Mengisi atau memperbarui tagihan sebuah baris biaya.
     *
     * Dibuat saat pertama diisi, bukan disiapkan kosong untuk seluruh baris:
     * baris yang PUNYA rekap berarti sudah diperiksa orang, dan itu yang
     * membedakannya dari yang terlupa.
     */
    public function simpan(Rab $rab, RabBiaya $biaya, Request $request): JsonResponse
    {
        abort_unless((int) $biaya->rab_id === (int) $rab->id, 404);

        $data = $request->validate([
            /*
             | present, bukan required: null mengembalikannya ke "belum
             | direkap". Nol adalah tagihan yang SAH — destinasi yang
             | ternyata gratis — jadi keduanya tidak boleh ditumpuk.
             */
            'tagihan' => ['present', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'pic' => ['sometimes', 'nullable', 'string', 'max:120'],
            'catatan' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $realisasi = RabRealisasi::updateOrCreate(
            ['rab_biaya_id' => $biaya->id],
            $data + ['oleh' => $request->attributes->get('admin_pemanggil')],
        );

        $this->catat($request, 'rekap biaya rab', [
            'kode' => $rab->kode, 'biaya' => $biaya->nama, 'tagihan' => $realisasi->tagihan,
        ]);

        return response()->json([
            'data' => RekapRab::untuk($rab->fresh()),
            'pesan' => $realisasi->tagihan === null
                ? 'Tagihan dikosongkan — baris ini kembali dihitung belum direkap.'
                : 'Tagihan "'.$biaya->nama.'" disimpan.',
        ]);
    }

    /**
     * Mencatat satu pembayaran ke vendor, beserta buktinya.
     *
     * Buktinya opsional di sini — pembayaran yang sudah terjadi tetap harus
     * bisa dicatat walau fotonya masih di ponsel orang lain. Yang belum
     * punya bukti DISEBUT di layar, jadi ia tidak hilang diam-diam.
     */
    public function tambahBayar(Rab $rab, RabBiaya $biaya, Request $request): JsonResponse
    {
        abort_unless((int) $biaya->rab_id === (int) $rab->id, 404);

        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(config('orcha.rab.realisasi_jenis')))],
            /*
             | SELALU positif. Refund adalah jenis tersendiri, bukan nominal
             | negatif: sekali angka boleh negatif, setiap penjumlahan di
             | seluruh laporan harus ingat memeriksa tandanya.
             */
            'nominal' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'tanggal' => ['sometimes', 'nullable', 'date'],
            'catatan' => ['sometimes', 'nullable', 'string', 'max:300'],
            'bukti' => ['sometimes', 'nullable', 'image', 'max:4096'],
        ], [], ['bukti' => 'bukti transfer']);

        /*
         | Baris rekapnya dibuat kalau belum ada, dengan tagihan KOSONG.
         |
         | Mengisinya otomatis sebesar nominal yang baru dibayar akan membuat
         | DP Rp 500.000 terbaca sebagai tagihan Rp 500.000 — lalu hotel
         | delapan juta tampil hemat tujuh setengah juta.
         */
        $realisasi = RabRealisasi::firstOrCreate(
            ['rab_biaya_id' => $biaya->id],
            ['oleh' => $request->attributes->get('admin_pemanggil')],
        );

        $bayar = RabRealisasiBayar::create([
            'realisasi_id' => $realisasi->id,
            'jenis' => $data['jenis'],
            'nominal' => (int) $data['nominal'],
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'catatan' => $data['catatan'] ?? null,
            'oleh' => $request->attributes->get('admin_pemanggil'),
        ]);

        if ($request->hasFile('bukti')) {
            // Jalur yang sama dengan bukti pelanggan: cakram rahasia, di luar
            // disk publik. Bukti transfer memuat nomor rekening dan nama.
            $bayar->update(['bukti' => GambarWebp::simpan($request->file('bukti'), 'bukti-bayar')]);
        }

        $this->catat($request, 'bayar vendor rab', [
            'kode' => $rab->kode, 'biaya' => $biaya->nama,
            'jenis' => $bayar->jenis, 'nominal' => $bayar->nominal,
        ]);

        return response()->json([
            'data' => RekapRab::untuk($rab->fresh()),
            'pesan' => $bayar->jenis_label.' '.RincianBiaya::rupiah($bayar->nominal)
                .' dicatat'.($bayar->bukti ? ' beserta buktinya.' : ' — buktinya belum diunggah.'),
        ], 201);
    }

    /** Mengganti bukti sebuah pembayaran; yang lama diarsipkan. */
    public function gantiBukti(Rab $rab, RabRealisasiBayar $bayar, Request $request): JsonResponse
    {
        abort_unless((int) $bayar->realisasi->biaya->rab_id === (int) $rab->id, 404);

        $request->validate([
            // Wajib: jalur ini tidak punya guna lain selain melampirkan
            // berkasnya.
            'bukti' => ['required', 'image', 'max:4096'],
        ], [], ['bukti' => 'bukti transfer']);

        $bayar->gantiBukti(
            GambarWebp::simpan($request->file('bukti'), 'bukti-bayar'),
            $request->attributes->get('admin_pemanggil'),
        );

        $this->catat($request, 'ganti bukti bayar vendor', ['kode' => $rab->kode, 'bayar_id' => $bayar->id]);

        return response()->json([
            'data' => RekapRab::untuk($rab->fresh()),
            'pesan' => 'Bukti diperbarui. Yang lama tetap tersimpan di riwayat.',
        ]);
    }

    public function hapusBayar(Rab $rab, RabRealisasiBayar $bayar, Request $request): JsonResponse
    {
        abort_unless((int) $bayar->realisasi->biaya->rab_id === (int) $rab->id, 404);

        $this->catat($request, 'hapus bayar vendor rab', [
            'kode' => $rab->kode, 'jenis' => $bayar->jenis, 'nominal' => $bayar->nominal,
        ]);

        $bayar->delete();

        return response()->json([
            'data' => RekapRab::untuk($rab->fresh()),
            // Berkasnya TIDAK ikut dihapus dari cakram: bukti sebuah
            // transaksi yang pernah dicatat tidak boleh hilang karena satu
            // baris salah ketik. orcha:berkas-yatim memang tidak menyentuh
            // folder bukti-bayar.
            'pesan' => 'Pembayaran dihapus. Berkas buktinya tetap tersimpan di arsip.',
        ]);
    }

    /**
     * RAB selesai yang rekapnya belum beres — daftar tunggakan.
     *
     * Pertanyaan operasionalnya bukan "trip ini untung berapa" melainkan
     * "trip mana yang uangnya masih menggantung".
     */
    public function tunggakan(): JsonResponse
    {
        $daftar = RekapRab::tunggakan();

        return response()->json([
            'data' => $daftar->all(),
            'meta' => ['jumlah' => $daftar->count()],
        ]);
    }
}
