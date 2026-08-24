<?php

namespace App\Models\Umum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tautan pendek untuk berkas yang dibagikan ke pelanggan.
 *
 * Menggantikan alamat bertanda tangan Laravel yang panjangnya lebih dari 200
 * karakter — di gelembung WhatsApp ia patah ke banyak baris dan lebih tampak
 * seperti tautan sampah daripada berkas resmi.
 */
class TautanPendek extends Model
{
    protected $table = 'tbl_tautan_pendek';

    protected $fillable = ['kode', 'jenis', 'pendaftaran_id', 'kedaluwarsa_pada'];

    protected $casts = ['kedaluwarsa_pada' => 'datetime'];

    /**
     * Tautan untuk satu berkas, dipakai ulang bila sudah ada.
     *
     * Dipakai ulang, bukan dibuat baru tiap kali halaman detail dibuka: kalau
     * tidak, satu pendaftaran menumpuk puluhan baris dan tautan yang sudah
     * telanjur dikirim ke pelanggan berdampingan dengan yang belum.
     *
     * Umurnya diperpanjang setiap kali diminta — admin yang membuka halaman
     * hari ini berarti masih mengurus pendaftaran ini.
     */
    public static function untuk(int $pendaftaranId, string $jenis, int $hari = 30): self
    {
        $tautan = static::firstOrNew(['jenis' => $jenis, 'pendaftaran_id' => $pendaftaranId]);

        // Kode acak, bukan hasil hitungan dari id-nya: kode yang bisa dihitung
        // ulang berarti bisa ditebak, dan berkasnya memuat nama, nomor telepon,
        // dan rincian biaya seseorang.
        $tautan->kode ??= static::kodeBaru();
        $tautan->kedaluwarsa_pada = now()->addDays($hari);
        $tautan->save();

        return $tautan;
    }

    public function masihBerlaku(): bool
    {
        return $this->kedaluwarsa_pada === null || $this->kedaluwarsa_pada->isFuture();
    }

    /** Kode 10 huruf-angka; diulang bila kebetulan sudah terpakai. */
    private static function kodeBaru(): string
    {
        do {
            $kode = Str::lower(Str::random(10));
        } while (static::where('kode', $kode)->exists());

        return $kode;
    }
}
