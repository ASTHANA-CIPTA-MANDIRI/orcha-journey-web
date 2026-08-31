<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimoni extends Model
{
    use HasFactory;

    protected $table = 'tbl_testimoni';

    protected $fillable = [
        'customer_name',
        'rating',
        'testimonial',
        'avatar',
        'status',
        'kode_pesanan',
    ];

    /**
     * Testimoni yang boleh dilihat pengunjung.
     *
     * Yang ditulis pelanggan masuk sebagai 'menunggu' dan TIDAK langsung
     * tayang. Bukan karena penulisnya diragukan — ia sudah membuktikan
     * pesanannya — melainkan karena halaman ini terbaca sebagai suara
     * perusahaan: satu kalimat kasar yang lolos ke beranda merugikan pembacanya
     * maupun yang menulisnya.
     */
    public function scopeTayang($query)
    {
        return $query->where('status', 'tayang');
    }

    /**
     * Ditulis pelanggan yang pesanannya terbukti, bukan diketikkan admin.
     *
     * Ditandai di halaman publik. Testimoni yang jelas dikurasi penjual dibaca
     * sebagai bahan pemasaran; yang bisa ditelusuri ke sebuah pesanan nyata
     * dibaca sebagai kesaksian — dan bedanya justru yang dicari calon pembeli.
     */
    public function getTerverifikasiAttribute(): bool
    {
        return filled($this->kode_pesanan);
    }
}
