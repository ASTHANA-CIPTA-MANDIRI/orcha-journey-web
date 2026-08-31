<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Catatan siapa mengubah apa, kapan.
 *
 * Dipakai lewat JejakAudit::catat() dari controller API — lihat
 * ApiController::catat() yang meneruskannya berikut identitas pemanggilnya.
 */
class JejakAudit extends Model
{
    protected $table = 'tbl_jejak_audit';

    protected $fillable = ['admin', 'aksi', 'ringkasan', 'kode', 'sebelum', 'sesudah', 'ip'];

    /**
     * Mencatat satu kejadian.
     *
     * Kegagalannya DIAM, dan itu disengaja. Jejak audit tidak boleh sanggup
     * menggagalkan pekerjaan yang sedang dicatatnya: admin yang menyetujui
     * pengembalian dana tidak boleh gagal hanya karena tabel jejaknya
     * bermasalah. Yang hilang satu baris catatan; yang diselamatkan pekerjaan
     * yang sebenarnya.
     */
    public static function catat(
        Request $request,
        string $aksi,
        string $ringkasan,
        ?string $kode = null,
        ?string $sebelum = null,
        ?string $sesudah = null,
    ): void {
        try {
            static::create([
                'admin' => $request->attributes->get('admin_pemanggil') ?: 'tidak diketahui',
                'aksi' => $aksi,
                'ringkasan' => mb_substr($ringkasan, 0, 500),
                'kode' => $kode,
                'sebelum' => $sebelum === null ? null : mb_substr($sebelum, 0, 191),
                'sesudah' => $sesudah === null ? null : mb_substr($sesudah, 0, 191),
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable) {
            // Sengaja dibiarkan — lihat alasannya di atas.
        }
    }

    public function scopeCari($query, ?string $kata)
    {
        if (blank($kata)) {
            return $query;
        }

        return $query->where(function ($q) use ($kata) {
            $q->where('kode', 'like', "%$kata%")
                ->orWhere('admin', 'like', "%$kata%")
                ->orWhere('ringkasan', 'like', "%$kata%");
        });
    }
}
