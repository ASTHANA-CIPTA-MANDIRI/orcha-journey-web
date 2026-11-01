<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tanggal merah yang diperlakukan seperti akhir pekan.
 *
 * Sabtu & Minggu tidak perlu didaftarkan — keduanya terbaca dari tanggalnya
 * sendiri. Yang didaftarkan di sini hanya libur yang tidak bisa dihitung.
 */
class HariLibur extends Model
{
    protected $table = 'tbl_hari_libur';

    protected $fillable = ['tanggal', 'nama', 'oleh'];

    protected $casts = ['tanggal' => 'date'];

    /**
     * Yang tersimpan selalu tanggal saja, tanpa jam.
     *
     * Tanpa mutator ini Eloquent menulis "2027-01-09 00:00:00" ke kolomnya.
     * MySQL memangkasnya sendiri karena kolomnya DATE, jadi tidak ada yang
     * terlihat salah — tetapi aturan unique: di validasi membandingkan
     * "2027-01-09" dengan apa yang BENAR-BENAR tersimpan, dan di SQLite
     * perbandingannya gagal: tanggal kembar lolos validasi lalu meledak jadi
     * 500 di kunci unik tabel. Dinormalkan di sini supaya kebenarannya tidak
     * bergantung pada jenis basis datanya.
     */
    protected function setTanggalAttribute($nilai): void
    {
        $this->attributes['tanggal'] = $nilai === null
            ? null
            : Carbon::parse($nilai)->toDateString();
    }

    /**
     * Seluruh tanggal libur, sebagai kunci 'Y-m-d' supaya dicek sekali jalan.
     *
     * @return array<string, string>
     */
    public static function peta(): array
    {
        return self::query()
            ->orderBy('tanggal')
            ->get()
            ->mapWithKeys(fn (self $h) => [$h->tanggal->toDateString() => $h->nama])
            ->all();
    }

    /**
     * Apakah tanggal ini dihitung akhir pekan, dan karena apa.
     *
     * Alasannya ikut dipulangkan, bukan cuma benar/salah: harga yang naik
     * Rp 10.000 tanpa sebab yang tertulis akan dikira salah ketik, dan
     * admin akan membetulkannya kembali ke harga yang justru keliru.
     *
     * @param  array<string, string>|null  $libur  peta dari self::peta()
     * @return array{akhir_pekan: bool, sebab: string|null}
     */
    public static function periksa(?Carbon $tanggal, ?array $libur = null): array
    {
        if (! $tanggal) {
            return ['akhir_pekan' => false, 'sebab' => null];
        }

        $libur ??= self::peta();
        $kunci = $tanggal->toDateString();

        if (isset($libur[$kunci])) {
            return ['akhir_pekan' => true, 'sebab' => $libur[$kunci]];
        }

        if ($tanggal->isSaturday() || $tanggal->isSunday()) {
            return [
                'akhir_pekan' => true,
                'sebab' => $tanggal->locale('id')->translatedFormat('l'),
            ];
        }

        return ['akhir_pekan' => false, 'sebab' => null];
    }
}
