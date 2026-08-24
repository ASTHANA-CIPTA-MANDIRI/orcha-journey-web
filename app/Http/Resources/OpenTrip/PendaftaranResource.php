<?php

namespace App\Http\Resources\OpenTrip;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\OpenTrip\PendaftaranOpenTrip $resource
 */
class PendaftaranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'jumlah_peserta' => $this->jumlah_peserta,
            // Bentuk seragam: tiap peserta punya nama dan titik jemputnya
            'peserta' => $this->peserta,
            'jemput_per_titik' => $this->jemput_per_titik,
            'kesehatan_terisi' => $this->kesehatan_terisi,
            'kesehatan_lengkap' => $this->kesehatan_lengkap,
            'peserta_belum_isi' => $this->peserta_belum_isi,
            'paket' => [
                'id' => $this->travel_package_id,
                'nama' => $this->nama_paket,
                // Titik jemput yang ditawarkan paketnya. Dipakai lemon sebagai
                // daftar pilihan saat admin mengisikan peserta: mengetik bebas
                // menghasilkan ejaan yang berbeda-beda untuk tempat yang sama,
                // dan manifes lalu mencetak dua kelompok bernama sama.
                'titik_jemput' => $this->paket?->titik_jemput_list ?? [],
            ],
            'tanggal_berangkat' => $this->tanggal_berangkat?->toDateString(),
            'titik_jemput' => $this->titik_jemput,
            'catatan' => $this->catatan,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'jumlah_riwayat_kesehatan' => $this->whenCounted('riwayatKesehatan'),

            /*
             | Jejak perubahan nama peserta: [{dari, ke, pada, oleh}].
             |
             | Nama lama tidak dihapus dari mana pun. Ia yang membayar, atau yang
             | riwayat kesehatannya sudah masuk — dan pertanyaan "dulu siapa yang
             | didaftarkan" hampir selalu muncul belakangan, saat tidak ada lagi
             | yang mengingatnya.
             */
            'riwayat_penggantian' => $this->riwayat_penggantian ?? [],

            // Surat bertanda tangan: alamat penuh supaya lemon bisa langsung
            // menautkannya tanpa menebak host berkasnya.
            'surat_penggantian' => $this->surat_penggantian
                ? url($this->surat_penggantian)
                : null,
            'surat_penggantian_pada' => $this->surat_penggantian_pada?->toIso8601String(),

            /*
             | Keuntungan pendaftaran ini, memakai harga jual dan modal yang
             | dibekukan saat kodenya dibuat. Ikut di sini supaya halaman
             | detail di lemon tidak perlu memanggil laporan hanya untuk
             | menampilkan satu baris — dan angkanya pasti sama, karena
             | hitungannya satu kelas yang sama.
             */
            'keuntungan' => [
                'jual_satuan' => $this->jual_satuan,
                'modal_satuan' => $this->modal_satuan,
                'margin_satuan' => $this->margin_satuan,
                'omzet' => $this->omzet,
                'modal' => $this->modal_total,
                'untung' => $this->keuntungan,
                'modal_terisi' => $this->modal_satuan !== null,
                'dihitung' => $this->status === 'lunas',
            ],
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
