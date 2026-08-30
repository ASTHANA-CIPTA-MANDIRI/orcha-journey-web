<?php

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Rubrik blog yang bisa ditambah admin sendiri.
 *
 * Dulu daftarnya tetap di config('orcha.kategori_artikel'). Masalahnya admin
 * tidak punya akses ke berkas config: begitu ia butuh rubrik baru, pekerjaannya
 * berhenti sampai ada yang menyunting kode dan menaikkannya ke server.
 */
class KategoriArtikel extends Model
{
    protected $table = 'tbl_kategori_artikel';

    protected $fillable = ['nama', 'slug'];

    /**
     * Seluruh kategori sebagai [slug => nama].
     *
     * Bentuk larik ini SENGAJA sama dengan config('orcha.kategori_artikel')
     * yang digantikannya, supaya halaman yang sudah membacanya — tab di /blog,
     * peta situs, rujukan API — tidak perlu diubah bentuk pemakaiannya.
     *
     * Config dipakai sebagai cadangan bila tabelnya kosong. Itu bukan hiasan:
     * saat migrasi belum jalan di sebuah lingkungan, halaman blog tetap punya
     * kategori alih-alih kehilangan seluruh tabnya tanpa penjelasan.
     *
     * @return array<string, string>
     */
    public static function daftar(): array
    {
        $dari = static::query()->orderBy('nama')->pluck('nama', 'slug')->all();

        return $dari !== [] ? $dari : config('orcha.kategori_artikel', []);
    }

    /** Nama untuk satu slug; null bila rubriknya sudah dihapus. */
    public static function nama(?string $slug): ?string
    {
        return $slug === null ? null : (static::daftar()[$slug] ?? null);
    }

    /**
     * Slug unik dari nama.
     *
     * Sama seperti slug artikel, tabrakannya diselesaikan di sini — bukan
     * diserahkan ke kunci unik basis data yang baru meledak saat admin menekan
     * simpan.
     */
    public static function slugUnik(string $nama, ?int $kecuali = null): string
    {
        $dasar = Str::slug($nama) ?: 'kategori';
        $slug = $dasar;
        $n = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))
            ->exists()) {
            $slug = "$dasar-$n";
            $n++;
        }

        return $slug;
    }

    /** Berapa artikel yang memakai rubrik ini. */
    public function jumlahArtikel(): int
    {
        return Artikel::where('kategori', $this->slug)->count();
    }
}
