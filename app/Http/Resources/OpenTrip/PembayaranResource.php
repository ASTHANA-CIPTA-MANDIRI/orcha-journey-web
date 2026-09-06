<?php

namespace App\Http\Resources\OpenTrip;

use App\Support\TagihanPesanan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\OpenTrip\KonfirmasiPembayaran $resource
 */
class PembayaranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pesanan = $this->pesanan();

        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'jenis' => $this->jenis,
            'jenis_label' => $this->jenis_label,

            /*
             | Dari mana uang ini datang: 'doku' atau 'transfer'.
             |
             | Dikirim supaya layar admin bisa membedakan keduanya tanpa
             | membaca catatan admin. Bedanya nyata di meja kerja: baris
             | 'doku' sudah pasti uangnya ada dan tidak punya bukti untuk
             | dibuka, sedangkan baris 'transfer' memang menunggu dicocokkan
             | dengan mutasi. Tanpa penanda ini, admin membuka baris DOKU
             | mencari gambar bukti yang memang tidak akan pernah ada.
             */
            'kanal' => $this->kanal ?? 'transfer',
            'kanal_label' => ($this->kanal ?? 'transfer') === 'doku'
                ? 'Pembayaran Online'
                : 'Transfer Manual',
            'nominal' => $this->nominal,
            'nominal_formatted' => $this->nominal_formatted,

            /*
             | Rincian angkanya dipecah untuk baris yang masuk lewat gerbang.
             |
             | Satu angka gelondongan seperti "Rp 858.889" memaksa admin
             | menerka: berapa uang mukanya, dan berapa yang cuma penanda?
             | Pertanyaan itu muncul persis saat ia menghitung sisa tagihan
             | pelanggan, dan menerkanya menghasilkan sisa yang meleset
             | beberapa ratus rupiah — cukup untuk membuat dua orang berdebat
             | tentang siapa yang salah hitung.
             |
             | Null untuk baris transfer manual: di sana memang tidak ada
             | pemecahan yang bisa dipertanggungjawabkan, karena nominalnya
             | apa adanya seperti yang tertera di mutasi.
             */
            'rincian' => $this->rincian_gerbang,
            'tanggal_transfer' => $this->tanggal_transfer?->toDateString(),
            'bank_pengirim' => $this->bank_pengirim,
            'atas_nama_pengirim' => $this->atas_nama_pengirim,
            'bukti' => \App\Support\BerkasRahasia::tautan($this->bukti),

            'bukti_riwayat' => $this->riwayatBukti(),
            'catatan' => $this->catatan,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'catatan_admin' => $this->catatan_admin,

            // Kode bisa salah ketik; bila tidak ketemu, admin tetap melihat
            // buktinya dan mencocokkan sendiri.
            'pesanan' => $pesanan ? [
                'nama' => $pesanan->nama,
                'whatsapp' => $pesanan->whatsapp,
                'keterangan' => $pesanan->nama_paket ?? $pesanan->nama_kendaraan ?? null,

                // Sisa tagihannya ikut dikirim karena itulah yang ditanyakan
                // pelanggan begitu buktinya diterima — "berarti kurang berapa
                // lagi?". Tanpa ini admin harus membuka halaman pesanan dulu
                // sebelum bisa menjawabnya.
                'tagihan' => TagihanPesanan::untuk($pesanan, hanyaDiterima: true),
            ] : null,

            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Bukti yang pernah dipakai catatan ini, terbaru lebih dulu.
     *
     * Dikirim supaya layar bisa menyebutkan bahwa buktinya PERNAH diganti.
     * Catatan uang yang buktinya berganti diam-diam adalah hal yang paling
     * sulit dijelaskan saat dipersoalkan — dan yang mempersoalkannya biasanya
     * bukan kita.
     *
     * @return array<int, array<string, string|null>>
     */
    private function riwayatBukti(): array
    {
        $riwayat = $this->bukti_riwayat ?? [];

        $hasil = [];

        foreach (array_reverse($riwayat) as $satu) {
            $hasil[] = [
                'bukti' => \App\Support\BerkasRahasia::tautan($satu['jalur'] ?? null),
                'diganti_pada' => $satu['diganti_pada'] ?? null,
                'oleh' => $satu['oleh'] ?? null,
            ];
        }

        return $hasil;
    }
}
