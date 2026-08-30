<?php

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu tulisan di blog Orcha Journey.
 *
 * Tayang ditentukan DUA hal sekaligus — status dan tanggal terbit — dan
 * keduanya diperiksa di satu tempat: scopeTayang(). Halaman daftar, halaman
 * detail, dan peta situs semuanya lewat sana. Menulis penyaringnya sendiri di
 * salah satu dari ketiganya berarti suatu saat peta situs menunjuk artikel
 * yang halamannya menjawab 404, atau draf yang belum matang bisa dibuka lewat
 * tautan langsung.
 */
class Artikel extends Model
{
    protected $table = 'tbl_artikel';

    protected $fillable = [
        'judul',
        'slug',
        'ringkasan',
        'isi',
        'sampul',
        'kategori',
        'penulis',
        'meta_title',
        'meta_description',
        'status',
        'terbit_pada',
        'dilihat',
    ];

    protected $casts = [
        'terbit_pada' => 'datetime',
        'dilihat' => 'integer',
    ];

    /**
     * Slug dibuat otomatis dari judul bila tidak diisi, dan dijaga unik.
     *
     * Slug yang bentrok tidak menimbulkan galat yang jelas — yang terjadi
     * hanya satu artikel selalu menang dan yang lain tidak pernah bisa dibuka.
     * Karena itu tabrakannya diselesaikan di sini, bukan diserahkan pada
     * kunci unik basis data yang baru meledak saat admin menekan simpan.
     */
    protected static function booted(): void
    {
        static::saving(function (self $artikel) {
            if (blank($artikel->slug)) {
                $artikel->slug = static::slugUnik($artikel->judul, $artikel->id);
            }
        });
    }

    public static function slugUnik(string $judul, ?int $kecuali = null): string
    {
        $dasar = Str::slug($judul) ?: 'artikel';
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

    /** Kunci route-model binding: /blog/{slug}, bukan id angka. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* ---------------------------------------------------------------- Scope */

    /**
     * Artikel yang boleh dilihat pengunjung.
     *
     * Artikel tanpa tanggal terbit dianggap BELUM tayang meski statusnya
     * "tayang" — kalau tidak, tulisan yang statusnya telanjur diubah sebelum
     * tanggalnya ditentukan langsung muncul di beranda blog.
     */
    public function scopeTayang(Builder $kueri): Builder
    {
        return $kueri->where('status', 'tayang')
            ->whereNotNull('terbit_pada')
            ->where('terbit_pada', '<=', now());
    }

    public function scopeKategori(Builder $kueri, ?string $kategori): Builder
    {
        return $kueri->when($kategori, fn ($q) => $q->where('kategori', $kategori));
    }

    /** Pencarian judul dan ringkasan; isi sengaja tidak ikut. */
    public function scopeCari(Builder $kueri, ?string $kata): Builder
    {
        return $kueri->when($kata, fn ($q) => $q->where(
            fn ($sub) => $sub->where('judul', 'like', "%$kata%")
                ->orWhere('ringkasan', 'like', "%$kata%")
        ));
    }

    /* ------------------------------------------------------------ Aksesor */

    public function getSedangTayangAttribute(): bool
    {
        return $this->status === 'tayang'
            && $this->terbit_pada !== null
            && $this->terbit_pada->lessThanOrEqualTo(now());
    }

    public function getKategoriLabelAttribute(): ?string
    {
        return KategoriArtikel::nama($this->kategori);
    }

    /**
     * Judul untuk mesin pencari.
     *
     * Jatuh ke judul artikel bila belum diisi — halaman tanpa judul di hasil
     * pencarian jauh lebih buruk daripada judul yang kepanjangan.
     */
    public function getMetaTitleTampilAttribute(): string
    {
        return filled($this->meta_title) ? $this->meta_title : $this->judul;
    }

    /**
     * Keterangan untuk mesin pencari.
     *
     * Berbeda dari ringkasan yang dibaca pengunjung: yang ini dibatasi panjang
     * yang muat di hasil pencarian. Kalau belum diisi, ringkasan dipakai.
     */
    public function getMetaDescriptionTampilAttribute(): string
    {
        return filled($this->meta_description) ? $this->meta_description : $this->ringkasan_tampil;
    }

    /**
     * Sampul cadangan bila artikel belum punya foto.
     *
     * Dipakai di DUA tempat: gambar kartu di daftar, dan latar hero di halaman
     * detail. Karena itu cadangannya foto blog, bukan foto destinasi — artikel
     * yang belum bersampul tetap terbaca sebagai bagian dari blog, bukan
     * seolah tulisan tentang satu destinasi tertentu.
     *
     * Kartu tanpa gambar merusak baris kartu di sebelahnya, dan bidang kosong
     * terbaca sebagai gambar yang gagal dimuat.
     */
    public function getSampulTampilAttribute(): string
    {
        return filled($this->sampul) ? $this->sampul : asset('images/HERO/blog.webp');
    }

    /**
     * Bagian mana dari sampul yang tampil setelah dipotong jadi pita hero.
     *
     * Posisinya mengikuti GAMBARNYA, bukan halamannya:
     *
     *   - Sampul cadangan selalu foto yang sama, dan bagian terbaiknya sudah
     *     diketahui — dua pendaki di bagian bawah. Nilainya disamakan dengan
     *     hero /blog supaya artikel tanpa sampul tidak terlihat berbeda dari
     *     halaman daftar yang baru saja ditinggalkan pembaca.
     *
     *   - Sampul unggahan admin bisa berupa apa saja, dan nilai yang dipaskan
     *     untuk satu foto akan memenggal foto berikutnya. Untuk gambar yang
     *     belum diketahui, sekitar tengah adalah taruhan paling aman — sedikit
     *     di bawahnya karena foto lanskap biasanya menaruh subjeknya agak ke
     *     bawah garis tengah.
     */
    public function getPosisiSampulAttribute(): string
    {
        return filled($this->sampul) ? 'center 55%' : 'center 88%';
    }

    /**
     * Tanggal berbahasa Indonesia.
     *
     * translatedFormat dengan locale dipaksa 'id' karena APP_LOCALE=en —
     * tanpa itu bulannya tertulis "August" di halaman berbahasa Indonesia.
     */
    public function getTanggalTerbitAttribute(): ?string
    {
        return $this->terbit_pada?->locale('id')->translatedFormat('j F Y');
    }

    /**
     * Perkiraan lama baca.
     *
     * 200 kata per menit, angka yang lazim dipakai untuk teks non-teknis.
     * Gunanya bukan ketepatan melainkan memberi pembaca ukuran sebelum ia
     * memutuskan mulai membaca atau menyimpannya untuk nanti.
     */
    public function getLamaBacaAttribute(): int
    {
        $kata = str_word_count(strip_tags((string) $this->isi));

        return max(1, (int) ceil($kata / 200));
    }

    public function getRingkasanTampilAttribute(): string
    {
        if (filled($this->ringkasan)) {
            return $this->ringkasan;
        }

        // Cadangan bila ringkasan belum diisi: potong pada batas kata.
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->isi))), 160, '…');
    }

    /* ------------------------------------------------------------- Perilaku */

    /**
     * Tambah satu pembaca.
     *
     * Memakai increment() langsung ke basis data, bukan baca-tambah-simpan:
     * dua pembaca yang membuka artikel bersamaan pada cara kedua sama-sama
     * membaca angka lama dan menyimpan angka yang sama, sehingga satu
     * kunjungan hilang. Juga tidak menyentuh updated_at — artikelnya tidak
     * berubah, hanya dibaca, dan urutan "terbaru" tidak boleh ikut bergeser
     * tiap kali ada yang membuka.
     */
    public function tambahDilihat(): void
    {
        $this->newQuery()->whereKey($this->getKey())->update([
            'dilihat' => DB::raw('dilihat + 1'),
        ]);
    }

    /**
     * Artikel lain yang pantas dibaca sesudah ini.
     *
     * Sekategori lebih dulu; bila belum cukup, dilengkapi artikel terbaru apa
     * pun. Yang dihindari: bagian "baca juga" yang kosong hanya karena
     * kategorinya baru punya satu tulisan.
     */
    public function lainnya(int $jumlah = 3): Collection
    {
        $sekategori = static::query()->tayang()
            ->whereKeyNot($this->getKey())
            ->kategori($this->kategori)
            ->latest('terbit_pada')
            ->take($jumlah)
            ->get();

        if ($sekategori->count() >= $jumlah) {
            return $sekategori;
        }

        $pelengkap = static::query()->tayang()
            ->whereKeyNot($this->getKey())
            ->whereNotIn('id', $sekategori->modelKeys())
            ->latest('terbit_pada')
            ->take($jumlah - $sekategori->count())
            ->get();

        return $sekategori->concat($pelengkap);
    }
}
