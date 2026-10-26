<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dari siapa sebuah harga dibeli.
 *
 * Satu vendor memasok banyak harga — Hiace, Elf, dan bus besar dari pemilik
 * armada yang sama — jadi nomornya hidup di satu tempat. Nomor yang berganti
 * cukup diubah sekali dan seluruh harga yang memakainya ikut benar; kalau
 * disalin ke tiap baris, yang terlewat satu pun membuat nomor lama bertahan
 * tanpa ada yang tahu mana yang masih berlaku.
 */
class Vendor extends Model
{
    protected $table = 'tbl_vendor';

    protected $fillable = ['nama', 'kontak', 'jenis', 'provinsi', 'catatan', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $v) {
            $v->nama = trim(preg_replace('/\s+/', ' ', (string) $v->nama));
            $v->kontak = trim((string) $v->kontak) ?: null;
            $v->jenis = trim((string) $v->jenis) ?: null;
            $v->provinsi = trim((string) $v->provinsi) ?: null;
            $v->catatan = trim((string) $v->catatan) ?: null;
        });
    }

    public function harga(): HasMany
    {
        return $this->hasMany(MasterHarga::class, 'vendor_id');
    }

    public function scopeAktif($q)
    {
        return $q->where('aktif', true);
    }

    /**
     * Vendor yang masuk akal untuk provinsi ini.
     *
     * Yang provinsinya kosong ikut terbawa — kosong berarti berlaku di mana
     * saja, bukan belum diisi.
     */
    public function scopeUntukProvinsi($q, ?string $provinsi)
    {
        return $q->when($provinsi, fn ($s) => $s->where(
            fn ($w) => $w->whereNull('provinsi')->orWhere('provinsi', $provinsi)
        ));
    }

    /**
     * Mencari vendor, dengan nomor yang TAHAN BEDA FORMAT.
     *
     * Satu nomor ditulis bermacam-macam: "+62 895-6302-79695",
     * "0895630279695", "0895 6302 79695". Dicocokkan mentah-mentah, mencari
     * nomor yang persis ada di data pun tidak ketemu — dan yang terbaca
     * admin adalah "vendornya belum didaftarkan", lalu ia mendaftarkannya
     * lagi.
     *
     * Caranya: karakter format dibuang dari KEDUA sisi, lalu awalan negara
     * atau nol di depannya dipotong dari kata carinya. "+62895…" dan
     * "0895…" sama-sama menyisakan "895…", jadi satu aturan menangani dua
     * arah sekaligus.
     *
     * REPLACE bersarang, bukan REGEXP_REPLACE: REGEXP_REPLACE ada di MySQL
     * dan tidak ada di SQLite yang dipakai uji, sehingga aturannya akan
     * terbukti hijau di tempat yang tidak pernah menjalankannya.
     */
    public function scopeCari($q, ?string $cari)
    {
        $cari = trim((string) $cari);

        if ($cari === '') {
            return $q;
        }

        $angka = preg_replace('/\D/', '', $cari);

        return $q->where(function ($s) use ($cari, $angka) {
            $s->where('nama', 'like', "%{$cari}%")
                ->orWhere('kontak', 'like', "%{$cari}%")
                ->orWhere('jenis', 'like', "%{$cari}%");

            // Tujuh digit: cukup untuk nomor terpendek, dan menyingkirkan
            // "30" dari "DP 30%" yang akan mencocoki hampir semua nomor.
            if (strlen($angka) >= 7) {
                $s->orWhereRaw(self::KOLOM_ANGKA.' LIKE ?', ['%'.self::ekorNomor($angka).'%']);
            }
        });
    }

    /** Kolom kontak tanpa karakter format, dirakit dari REPLACE bersarang. */
    private const KOLOM_ANGKA = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(kontak,' ',''),'-',''),'(',''),')',''),'+',''),'.','')";

    /** Nomor tanpa awalan negara atau nol di depannya. */
    public static function ekorNomor(string $angka): string
    {
        if (str_starts_with($angka, '62')) {
            return substr($angka, 2);
        }

        return ltrim($angka, '0');
    }

    /** Baris biaya RAB yang menunjuk vendor ini. */
    public function biaya(): HasMany
    {
        return $this->hasMany(RabBiaya::class, 'vendor_id');
    }
}
