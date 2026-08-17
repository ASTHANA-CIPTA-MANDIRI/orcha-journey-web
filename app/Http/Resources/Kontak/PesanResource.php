<?php

namespace App\Http\Resources\Kontak;

use App\Models\Kontak\PesanKontak;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\NomorTelepon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Kontak\PesanKontak $resource
 */
class PesanResource extends JsonResource
{
    /**
     * Data tambahan hanya untuk halaman detail.
     *
     * Mencari pesanan dan pesan lain milik pengirim yang sama butuh beberapa
     * query. Di daftar yang memuat sepuluh pesan sekaligus itu tidak sebanding
     * — jadi hanya dinyalakan saat satu pesan dibuka.
     */
    public bool $rinci = false;

    public static function rinci(PesanKontak $pesan): self
    {
        $resource = new self($pesan);
        $resource->rinci = true;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $dasar = [
            'id' => $this->id,
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'keperluan' => $this->keperluan,
            'keperluan_label' => $this->keperluan_label,
            'pesan' => $this->pesan,
            'sudah_dibaca' => $this->sudah_dibaca,
            'dibaca_pada' => $this->dibaca_pada?->toIso8601String(),
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];

        if (! $this->rinci) {
            return $dasar;
        }

        return $dasar + [
            // Pertanyaan pertama admin saat membuka pesan: orang ini calon
            // pelanggan, atau pemesan yang sedang menanyakan pesanannya?
            // Jawabannya menentukan seluruh nada balasan, dan selama ini
            // dicari sendiri dengan menyalin nomornya ke kolom pencarian.
            'pesanan_terkait' => $this->pesananTerkait(),

            // Pesan sebelumnya dari orang yang sama. Orang yang sudah tiga kali
            // bertanya dan belum dibalas perlu diperlakukan berbeda dari yang
            // baru pertama menulis.
            'pesan_lain' => $this->pesanLain(),
        ];
    }

    /**
     * Pesanan milik pengirim, dicocokkan lewat nomor WhatsApp atau email.
     *
     * Nomornya dibandingkan dalam bentuk angka saja: yang tersimpan di
     * pemesanan bisa "0812-3456-7890" sedangkan di formulir kontak
     * "081234567890", dan keduanya orang yang sama.
     */
    private function pesananTerkait(): array
    {
        $digit = NomorTelepon::angka($this->whatsapp);
        $email = trim((string) $this->email);

        if (blank($digit) && blank($email)) {
            return [];
        }

        $cocok = function ($query) use ($digit, $email) {
            $query->when(filled($digit), fn ($q) => $q->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(whatsapp, '-', ''), ' ', ''), '+', '') LIKE ?",
                ['%'.$digit]
            ))->when(filled($email), fn ($q) => $q->orWhere('email', $email));
        };

        $trip = PendaftaranOpenTrip::where($cocok)->latest('id')->limit(5)->get()
            ->map(fn (PendaftaranOpenTrip $p) => [
                'kode' => $p->kode,
                'jenis' => 'open_trip',
                'jenis_label' => 'Open Trip',
                'keterangan' => $p->nama_paket,
                'status' => $p->status,
                'status_label' => $p->status_label,
                'mulai' => $p->tanggal_berangkat?->toDateString(),
            ]);

        $sewa = PenyewaanKendaraan::where($cocok)->latest('id')->limit(5)->get()
            ->map(fn (PenyewaanKendaraan $p) => [
                'kode' => $p->kode,
                'jenis' => 'sewa_kendaraan',
                'jenis_label' => 'Sewa Kendaraan',
                'keterangan' => $p->nama_kendaraan,
                'status' => $p->status,
                'status_label' => $p->status_label,
                'mulai' => $p->jadwal_mulai?->toDateString(),
            ]);

        return $trip->concat($sewa)->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function pesanLain(): array
    {
        $digit = NomorTelepon::angka($this->whatsapp);

        return PesanKontak::where('id', '!=', $this->id)
            ->where(function ($q) use ($digit) {
                $q->when(filled($digit), fn ($sub) => $sub->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(whatsapp, '-', ''), ' ', ''), '+', '') LIKE ?",
                    ['%'.$digit]
                ))->when(filled($this->email), fn ($sub) => $sub->orWhere('email', $this->email));
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (PesanKontak $p) => [
                'id' => $p->id,
                'keperluan_label' => $p->keperluan_label,
                'pesan' => $p->pesan,
                'sudah_dibaca' => $p->sudah_dibaca,
                'dibuat_pada' => $p->created_at?->toIso8601String(),
            ])->all();
    }
}
