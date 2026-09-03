<?php

namespace App\Models\Rujukan;

use App\Support\KodePesanan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Kode yang dibawa alumni trip untuk mengajak orang baru.
 *
 * Bedanya dengan promo rombongan tegas, dan bukan sekadar bentuk lain dari
 * potongan yang sama:
 *
 *   Promo rombongan berlaku dalam SATU pendaftaran — ramai orang berangkat
 *   bersama di tanggal yang sama.
 *
 *   Kode rujukan berlaku LINTAS pendaftaran — orang yang sudah pulang
 *   mengajak temannya ikut trip berikutnya, di tanggal yang berbeda.
 *
 * Yang kedua inilah yang membuat alumni terus menjual tanpa diminta. Selama
 * ini setiap peserta yang pulang senang adalah tenaga penjual yang tidak
 * pernah kita berikan alat apa pun: ia merekomendasikan lewat mulut, dan tidak
 * ada satu pun cara mengetahui bahwa pendaftaran baru itu datang darinya.
 */
class KodeRujukan extends Model
{
    protected $table = 'tbl_kode_rujukan';

    protected $fillable = [
        'kode', 'nama', 'whatsapp', 'email',
        'kode_pendaftaran_asal', 'aktif', 'catatan',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $rujukan) {
            $rujukan->whatsapp = \App\Support\NomorTelepon::angka($rujukan->whatsapp);

            if (blank($rujukan->kode)) {
                do {
                    $kode = self::kodeUntuk($rujukan->nama);
                } while (static::where('kode', $kode)->exists());

                $rujukan->kode = $kode;
            }
        });

        static::updating(function (self $rujukan) {
            $rujukan->whatsapp = \App\Support\NomorTelepon::angka($rujukan->whatsapp);
        });
    }

    /**
     * "BUDI-K7QM" — nama depan pemiliknya, lalu bagian acak.
     *
     * Namanya ikut karena kode ini DIUCAPKAN dan DIBANGGAKAN. Orang menyebar
     * kodenya sendiri ke grup WhatsApp temannya, dan "pakai kode BUDI-K7QM"
     * jauh lebih mudah diucapkan — dan jauh lebih mungkin benar-benar diucapkan
     * — daripada deretan huruf acak yang harus disalin.
     *
     * Bagian acaknya tetap ada dan tetap dari abjad yang sama dengan kode
     * pesanan: tanpa itu, dua orang bernama Budi menghasilkan kode yang sama,
     * dan kode rujukan yang bisa ditebak berarti komisi yang bisa diakui orang
     * lain.
     */
    public static function kodeUntuk(string $nama): string
    {
        $depan = Str::of($nama)
            ->ascii()
            ->upper()
            // Hanya huruf: nama dengan tanda baca atau angka menghasilkan kode
            // yang tidak bisa diketik ulang dari ingatan.
            ->replaceMatches('/[^A-Z ]/', '')
            ->trim()
            ->explode(' ')
            ->first() ?: 'ORCHA';

        return Str::substr($depan, 0, 8).'-'.KodePesanan::acak(4);
    }

    /**
     * Pendaftaran yang memakai kode ini.
     *
     * Ditautkan lewat KODE, bukan id. Kode rujukan sengaja tidak bisa diubah
     * justru supaya tautan ini tetap sah — dan pendaftaran yang lama tetap
     * terhitung meski barisnya suatu saat dibuat ulang.
     */
    public function pendaftaran(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\OpenTrip\PendaftaranOpenTrip::class, 'kode_rujukan', 'kode');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
