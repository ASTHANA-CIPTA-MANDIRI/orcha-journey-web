<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Rencana anggaran biaya sebuah private trip, berikut itinerary-nya.
 *
 * Angka totalnya TIDAK disimpan — ia diturunkan dari baris biaya setiap kali
 * dibaca, lewat App\Support\Rab\HitungRab. Menyimpan total berarti dua sumber
 * kebenaran untuk satu angka, dan yang satu pasti tertinggal saat baris
 * biayanya disunting.
 */
class Rab extends Model
{
    protected $table = 'tbl_rab';

    protected $fillable = [
        'kode', 'judul', 'nama_pelanggan', 'whatsapp', 'email',
        'provinsi', 'daerah', 'tanggal_mulai', 'jumlah_hari', 'jumlah_malam',
        'jumlah_peserta', 'margin_jenis', 'margin_nilai', 'pembulatan',
        'catatan', 'catatan_penawaran', 'status', 'berlaku_sampai',
        'kode_pendaftaran', 'dibuat_oleh',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'berlaku_sampai' => 'date',
        'jumlah_hari' => 'integer',
        'jumlah_malam' => 'integer',
        'jumlah_peserta' => 'integer',
        'margin_nilai' => 'integer',
        'pembulatan' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $rab) {
            $rab->kode ??= self::kodeBaru();
            $rab->berlaku_sampai ??= now()->addDays((int) config('orcha.rab.berlaku_hari', 14))->toDateString();
            $rab->pembulatan = $rab->pembulatan ?: (int) config('orcha.rab.pembulatan', 1000);
        });
    }

    /**
     * RAB-2609-A7K3: bulan terbit + empat karakter acak.
     *
     * Huruf yang mudah tertukar (0/O, 1/I/L) dibuang, karena kode ini dibacakan
     * lewat telepon dan diketik ulang pelanggan.
     */
    public static function kodeBaru(): string
    {
        do {
            $acak = collect(str_split('ABCDEFGHJKMNPQRSTUVWXYZ23456789'))
                ->shuffle()->take(4)->implode('');
            $kode = 'RAB-'.now()->format('ym').'-'.$acak;
        } while (self::where('kode', $kode)->exists());

        return $kode;
    }

    public function itinerary()
    {
        return $this->hasMany(RabItinerary::class)->orderBy('hari_ke')->orderBy('urutan')->orderBy('id');
    }

    public function biaya()
    {
        return $this->hasMany(RabBiaya::class)->orderBy('urutan')->orderBy('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return config("orcha.rab.status.{$this->status}") ?? ucfirst($this->status);
    }

    /** Nama berkas PDF yang aman untuk sistem berkas mana pun. */
    public function namaBerkas(string $jenis): string
    {
        return Str::slug($jenis.'-'.$this->kode.'-'.$this->nama_pelanggan).'.pdf';
    }
}
