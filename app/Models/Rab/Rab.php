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
        'provinsi', 'jenis', 'daerah', 'tanggal_mulai', 'jumlah_hari', 'jumlah_malam',
        'jumlah_peserta', 'peserta_minimum', 'harga_dasar',
        'margin_jenis', 'margin_nilai', 'pembulatan',
        'catatan', 'catatan_penawaran', 'status', 'berlaku_sampai',
        'kode_pendaftaran', 'dibuat_oleh',
    ];

    /**
     * Jumlah peserta yang dipakai MENGHITUNG HARGA.
     *
     * Pada private trip sama dengan jumlah pesertanya: orangnya sudah pasti.
     * Pada open trip boleh jumlah paling rendah yang masih mau
     * diberangkatkan — harga diumumkan lebih dulu, dan yang datang di atas
     * angka itu jadi untung, bukan penyelamat.
     *
     * Tidak pernah nol: pembagi nol akan meledak di tempat yang jauh dari
     * sebabnya, dan RAB tanpa peserta minimum cukup dihitung seperti biasa.
     *
     * Catatan untuk open trip ber-harga_dasar 'peserta': harganya ikut jumlah
     * yang sedang terdaftar, jadi ia tidak pernah rugi — dan juga tidak
     * pernah bisa diumumkan lebih dulu. Itu perilaku private trip dengan nama
     * lain; pengaman titik impas tidak akan pernah menyala di sana, dan
     * memang tidak ada yang perlu diamankan.
     */
    public function pesertaDasar(): int
    {
        if ($this->jenis === 'open' && $this->harga_dasar === 'minimum' && (int) $this->peserta_minimum > 0) {
            return (int) $this->peserta_minimum;
        }

        return max(1, (int) $this->jumlah_peserta);
    }

    protected $casts = [
        'tanggal_mulai' => 'date',
        'berlaku_sampai' => 'date',
        'jumlah_hari' => 'integer',
        'jumlah_malam' => 'integer',
        'jumlah_peserta' => 'integer',
        'peserta_minimum' => 'integer',
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

    /**
     * Tanggal pulang, DITURUNKAN dari tanggal berangkat dan jumlah hari.
     *
     * Tidak disimpan sebagai kolom sendiri. Dua kolom yang menjawab hal
     * yang sama akan berselisih begitu salah satunya diubah lewat jalur
     * lain — dan yang terbaca pelanggan di penawaran adalah yang salah,
     * tanpa ada yang tahu mana yang benar.
     *
     * Trip 3 hari yang berangkat tanggal 10 pulang tanggal 12, bukan 13:
     * hari keberangkatan ikut terhitung.
     */
    public function getTanggalPulangAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->tanggal_mulai?->copy()->addDays(max(1, (int) $this->jumlah_hari) - 1);
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
