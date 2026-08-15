<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Support\GambarWebp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Penanganan gambar yang sama untuk semua etalase: paket, armada, destinasi,
 * testimoni, dan partner.
 *
 * Apa pun yang diunggah disimpan sebagai WebP — lihat App\Support\GambarWebp.
 */
trait MenyimpanGambar
{
    /**
     * Simpan gambar yang ikut di permintaan. Bila tidak ada berkas baru,
     * kembalikan yang lama — supaya menyunting data tanpa mengganti gambar
     * tidak menghapus gambarnya.
     */
    protected function simpanGambar(Request $request, string $folder, ?string $lama = null): ?string
    {
        if (! $request->hasFile('gambar')) {
            return $lama;
        }

        $this->hapusGambar($lama);

        return GambarWebp::simpan($request->file('gambar'), $folder);
    }

    protected function hapusGambar(?string $jalur): void
    {
        if (blank($jalur) || ! str_starts_with($jalur, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(str_replace('/storage/', '', $jalur));
    }
}
