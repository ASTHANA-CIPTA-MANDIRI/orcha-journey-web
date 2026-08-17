<?php

namespace App\Http\Resources\SewaKendaraan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\SewaKendaraan\Car $resource
 */
class KendaraanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'nama' => $this->name,
            'merek' => $this->brand,
            'varian' => $this->varian,
            'tahun' => $this->tahun,
            'cc' => $this->cc,
            // Sebutan lengkap dirakit di sini, sekali, supaya lemon dan halaman
            // publik tidak masing-masing menyusun urutannya sendiri lalu berbeda.
            'sebutan' => $this->sebutan_lengkap,
            'jenis' => $this->type,
            'jenis_label' => $this->type_label,
            'nopol' => $this->nopol,
            'kapasitas' => $this->capacity,
            'transmisi_tersedia' => $this->transmisi_tersedia_list,
            'transmisi_label' => $this->transmisi_label,
            'tarif' => [
                'jam' => $this->harga_per_jam,
                '12jam' => $this->harga_12_jam,
                'hari' => $this->price_per_day,
                'sopir_per_hari' => $this->harga_sopir,
            ],
            'gambar' => $this->image,
            'tersedia' => (bool) $this->is_available,
            'jumlah_penyewaan' => $this->whenCounted('penyewaan'),

            // Keadaan fisik unit, hasil pemeriksaan serah terima terakhir.
            //
            // Selama ini dicatat rapi tiap unit kembali, lalu tidak pernah
            // terbaca lagi — halaman armada hanya menampilkan tarif. Akibatnya
            // unit yang kacanya retak bisa disewakan lagi tanpa ada yang tahu
            // sampai penyewa berikutnya mengeluh.
            'kondisi' => $this->ringkasKondisi(),
            // Bentuk mentahnya (bagian => nilai) dipakai formulir armada untuk
            // mengisikan pilihan per bagian saat pemilik mencatat perbaikan.
            'kondisi_terkini' => $this->kondisi_terkini ?: (object) [],

            // Sedang dipakai atau tidak. Tanpa ini admin harus membuka daftar
            // penyewaan untuk menjawab "unit ini bisa dipakai besok?".
            'jadwal' => $this->ringkasJadwal(),
        ];
    }

    /**
     * @return array{diperiksa_pada: string|null, rusak: int, lecet: int, hilang: int, perlu_perhatian: bool}|null
     */
    private function ringkasKondisi(): ?array
    {
        $kondisi = $this->kondisi_terkini;

        if (blank($kondisi)) {
            return null;
        }

        $hitung = fn (string $nilai) => count(array_filter($kondisi, fn ($k) => $k === $nilai));

        $rusak = $hitung('rusak');
        $hilang = $hitung('hilang');
        $lecet = $hitung('lecet');

        return [
            'diperiksa_pada' => $this->kondisi_diperiksa_pada?->toIso8601String(),
            'catatan' => $this->kondisi_catatan,
            'rusak' => $rusak,
            'lecet' => $lecet,
            'hilang' => $hilang,
            // Lecet tidak menghalangi unit disewakan; rusak dan hilang iya.
            'perlu_perhatian' => ($rusak + $hilang) > 0,
            'rincian' => collect($kondisi)
                ->filter(fn ($nilai) => $nilai !== 'baik')
                ->map(fn ($nilai, $bagian) => [
                    'bagian' => config('orcha.pemeriksaan_kendaraan')[$bagian] ?? $bagian,
                    'kondisi' => config('orcha.kondisi_pemeriksaan')[$nilai] ?? $nilai,
                    'nilai' => $nilai,
                ])->values()->all(),
        ];
    }

    /**
     * Penyewaan yang sedang berjalan dan yang paling dekat menyusul.
     *
     * Yang dijawab: "unit ini sekarang di mana, dan kapan bebas lagi" —
     * pertanyaan yang muncul tiap kali ada calon penyewa menelepon.
     */
    private function ringkasJadwal(): array
    {
        $berjalan = $this->penyewaan()
            ->whereIn('status', ['berjalan', 'dp_masuk', 'dikonfirmasi'])
            ->get()
            ->first(fn ($sewa) => $sewa->jadwal_mulai?->isPast() && ! $sewa->dikembalikan_pada);

        $berikutnya = $this->penyewaan()
            ->whereIn('status', ['baru', 'dikonfirmasi', 'dp_masuk'])
            ->get()
            ->filter(fn ($sewa) => $sewa->jadwal_mulai?->isFuture())
            ->sortBy(fn ($sewa) => $sewa->jadwal_mulai)
            ->first();

        return [
            'sedang_disewa' => (bool) $berjalan,
            'kode_berjalan' => $berjalan?->kode,
            'kembali_pada' => $berjalan?->jadwal_selesai?->toIso8601String(),
            'kode_berikutnya' => $berikutnya?->kode,
            'mulai_berikutnya' => $berikutnya?->jadwal_mulai?->toIso8601String(),
        ];
    }
}
