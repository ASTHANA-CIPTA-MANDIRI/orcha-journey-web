<?php

namespace App\Http\Resources\OpenTrip;

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\PerkiraanPotongan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\OpenTrip\Pembatalan $resource
 */
class PembatalanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_pendaftaran' => $this->kode_pendaftaran,

            /*
             | Jenis pesanannya, sampai ke kategori paketnya.
             |
             | Sempat cuma dua: sewa kendaraan, atau "Open Trip" untuk segala
             | yang bukan sewa. Akibatnya pembatalan private trip dan study tour
             | ikut berlabel Open Trip — padahal ketiganya beda kebijakan
             | potongannya, dan admin yang menyaring "Open Trip" mendapat
             | rombongan sekolah di dalamnya.
             |
             | Paketnya sudah ikut termuat lewat with('pendaftaran.paket'), jadi
             | ini tidak menambah satu query pun per baris.
             */
            'jenis' => self::jenisPesanan($this->resource),
            'jenis_label' => self::labelJenis(self::jenisPesanan($this->resource)),
            'nama_pemohon' => $this->nama_pemohon,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'alasan' => $this->alasan,
            'alasan_label' => $this->alasan_label,
            'penjelasan' => $this->penjelasan,
            'jumlah_dibatalkan' => $this->jumlah_dibatalkan,
            'rekening' => [
                'bank' => $this->bank,
                'nomor' => $this->nomor_rekening,
                'atas_nama' => $this->atas_nama_rekening,
            ],
            'status' => $this->status,
            'status_label' => $this->status_label,
            'catatan_admin' => $this->catatan_admin,

            // Perkiraan potongan menurut tangga yang berlaku. Dikirim supaya
            // admin tidak menghitungnya ulang satu per satu — pertanyaan
            // pertama pada tiap pengajuan selalu "kembalinya berapa".
            //
            // Tetap perkiraan: yang menetapkan tim, karena ada hal yang tidak
            // diketahui sistem (biaya yang sudah terlanjur dibayarkan ke pihak
            // ketiga, kesepakatan menjadwal ulang).
            'perkiraan' => PerkiraanPotongan::untuk($this->pesanan(), $this->potongan_ditetapkan),
            'potongan_ditetapkan' => $this->potongan_ditetapkan,
            'pendaftaran' => $this->whenLoaded('pendaftaran', fn () => [
                'kode' => $this->pendaftaran?->kode,
                'nama_paket' => $this->pendaftaran?->nama_paket,
                'tanggal_berangkat' => $this->pendaftaran?->tanggal_berangkat?->toDateString(),
                'jumlah_peserta' => $this->pendaftaran?->jumlah_peserta,
            ]),

            // Pesanan yang dibatalkan, dalam satu bentuk untuk kedua jenisnya.
            // Halaman detail perlu tahu siapa pemesannya dan apa yang batal —
            // tanpa ini admin harus membuka halaman lain hanya untuk mencocokkan
            // nama pemohon dengan nama pemesan.
            'pesanan' => self::ringkasPesanan($this->pesanan()),

            // Riwayat pembayarannya ikut, karena itulah dasar angka yang akan
            // dikirim balik. Pengembalian yang dihitung tanpa melihat bukti
            // yang masuk adalah pengembalian yang menunggu dipersoalkan.
            'pembayaran' => KonfirmasiPembayaran::where('kode', $this->kode_pendaftaran)
                ->latest('id')
                ->get()
                ->map(fn (KonfirmasiPembayaran $bayar) => [
                    'id' => $bayar->id,
                    'jenis_label' => $bayar->jenis_label,
                    'nominal' => $bayar->nominal,
                    'nominal_formatted' => $bayar->nominal_formatted,
                    'tanggal_transfer' => $bayar->tanggal_transfer?->toDateString(),
                    'bank_pengirim' => $bayar->bank_pengirim,
                    'atas_nama_pengirim' => $bayar->atas_nama_pengirim,
                    'bukti' => $bayar->bukti,
                    'status' => $bayar->status,
                    'status_label' => $bayar->status_label,
                    'catatan_admin' => $bayar->catatan_admin,
                ])->all(),

            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Pesanan yang dibatalkan, diringkas jadi satu bentuk untuk kedua jenisnya.
     *
     * Yang berbeda hanya isi barisnya — unit dan jadwal ambil untuk sewa, paket
     * dan tanggal berangkat untuk open trip. Tampilan di lemon tidak perlu
     * bercabang dua hanya karena sumbernya dua tabel.
     */
    /**
     * Jenis pesanan yang dibatalkan: sewa kendaraan, atau kategori paketnya.
     *
     * Kode "SK-" sudah cukup menandai sewa. Selebihnya dibaca dari kategori
     * paketnya; kalau pesanannya tidak ketemu — kode salah ketik — jatuh ke
     * open trip, sebutan yang paling sering benar.
     */
    private static function jenisPesanan($pembatalan): string
    {
        if (str_starts_with((string) $pembatalan->kode_pendaftaran, 'SK-')) {
            return 'sewa_kendaraan';
        }

        $kategori = $pembatalan->pendaftaran?->paket?->category;

        return in_array($kategori, ['open_trip', 'private_trip', 'study_tour'], true)
            ? $kategori
            : 'open_trip';
    }

    private static function labelJenis(string $jenis): string
    {
        return $jenis === 'sewa_kendaraan'
            ? 'Sewa Kendaraan'
            : (config('orcha.kategori_paket')[$jenis] ?? 'Open Trip');
    }

    private static function ringkasPesanan($pesanan): ?array
    {
        if (! $pesanan) {
            return null;
        }

        $sewa = $pesanan instanceof PenyewaanKendaraan;

        return [
            'jenis' => $sewa ? 'sewa_kendaraan' : 'open_trip',
            'nama' => $pesanan->nama,
            'whatsapp' => $pesanan->whatsapp,
            'email' => $pesanan->email,
            'status' => $pesanan->status,
            'status_label' => $pesanan->status_label,
            'keterangan' => $sewa ? $pesanan->nama_kendaraan : $pesanan->nama_paket,
            'mulai' => $sewa
                ? $pesanan->jadwal_mulai?->toIso8601String()
                : $pesanan->tanggal_berangkat?->toIso8601String(),
            'jumlah_peserta' => $sewa ? null : $pesanan->jumlah_peserta,
            'durasi_label' => $sewa ? $pesanan->durasi_label : null,
        ];
    }
}
