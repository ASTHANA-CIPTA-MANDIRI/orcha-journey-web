<?php

namespace App\Models\PaketWisata;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu tingkat promo rombongan.
 *
 * Dulu daftarnya tetap di config('orcha.promo_rombongan'). Admin tidak punya
 * akses ke berkas config, jadi mengubah "ajak 5 dapat 5%" jadi 7% berarti
 * menunggu ada yang menyunting kode dan menaikkannya ke server — padahal
 * justru angka inilah yang paling sering diutak-atik, mengikuti musim liburan
 * dan tawaran pesaing.
 */
class PromoRombonganTingkat extends Model
{
    protected $table = 'tbl_promo_rombongan';

    protected $fillable = [
        'min_peserta', 'potongan_persen', 'gratis_orang', 'label', 'ajakan', 'aktif',
    ];

    protected $casts = [
        'min_peserta' => 'integer',
        'potongan_persen' => 'integer',
        'gratis_orang' => 'integer',
        'aktif' => 'boolean',
    ];

    /**
     * Tulisan promo dan kalimat ajakannya dirakit sendiri.
     *
     * Dulu keduanya diketik admin. Dua akibatnya, dan keduanya terlihat di
     * data nyata: kalimatnya jadi berbeda-beda gayanya antar tingkat, dan
     * angkanya bisa berbeda dari yang benar-benar berlaku — admin mengubah
     * potongan dari 5% jadi 7% lalu lupa menyunting kalimat "hemat 5%" di
     * sebelahnya. Yang dibaca pelanggan angka yang salah, dan yang ditagih
     * angka yang benar.
     *
     * Dirakit di MODEL, bukan di layar admin, supaya jalur mana pun —
     * formulir lemon, panggilan API langsung, seeder — menghasilkan kalimat
     * yang sama.
     */
    protected static function booted(): void
    {
        static::saving(function (self $tingkat) {
            $tingkat->label = $tingkat->labelOtomatis();
            $tingkat->ajakan = $tingkat->ajakanOtomatis();
        });
    }

    /**
     * Berapa REKAN yang harus diajak untuk mencapai tingkat ini.
     *
     * min_peserta menghitung seluruh peserta pendaftaran — pemesannya ikut
     * terhitung. Tingkat 6 berarti si pemesan mengajak lima rekan, bukan enam.
     *
     * Bedanya satu angka, dan justru karena cuma satu angka ia lolos dari
     * pemeriksaan: kalimat "Ajak 6 orang" pada tingkat yang syaratnya 6 total
     * terbaca benar sampai ada pelanggan yang benar-benar mengumpulkan enam
     * temannya, datang bertujuh, lalu menagih tingkat yang lebih tinggi.
     */
    public function jumlahRekan(): int
    {
        return max(1, $this->min_peserta - 1);
    }

    /**
     * "Ajak 10 rekan — gratis 1 orang"
     *
     * Untuk bentuk persen, kalimatnya menyebut UNTUK SIAPA potongannya.
     *
     * "Hemat 5%" saja terbaca sebagai 5% dari seluruh tagihan rombongan,
     * padahal yang berlaku 5% dari satu kursi — kursi yang mengajak. Janji
     * yang lebih besar daripada yang ditepati akan ketahuan tepat saat
     * pelanggan menjumlahkan sendiri, dan itu percakapan yang jauh lebih mahal
     * daripada satu kata tambahan di sini.
     */
    public function labelOtomatis(): string
    {
        $awalan = 'Ajak '.$this->jumlahRekan().' rekan — ';

        return $this->gratis_orang > 0
            ? $awalan.'gratis '.$this->gratis_orang.' orang'
            : $awalan.'potongan '.$this->potongan_persen.'% untuk pemesan';
    }

    /**
     * "Ajak 10 rekan, 1 orang gratis."
     *
     * Dibaca yang BELUM mencapai tingkatnya, jadi bentuknya ajakan — bukan
     * keterangan seperti labelnya.
     */
    public function ajakanOtomatis(): string
    {
        return $this->gratis_orang > 0
            ? 'Ajak '.$this->jumlahRekan().' rekan, '.$this->gratis_orang.' orang gratis.'
            : 'Ajak '.$this->jumlahRekan().' rekan, Anda dapat potongan '.$this->potongan_persen.'%.';
    }

    /**
     * Seluruh tingkat yang berlaku, dalam bentuk yang SAMA dengan config
     * yang digantikannya.
     *
     * Bentuknya sengaja tidak berubah supaya App\Support\PromoRombongan —
     * satu-satunya yang menghitung — tidak perlu tahu angkanya datang dari
     * mana.
     *
     * Config dipakai sebagai cadangan bila tabelnya kosong. Itu bukan hiasan:
     * saat migrasinya belum jalan di sebuah lingkungan, promo yang sedang
     * berjalan tetap berlaku alih-alih mati diam-diam — dan yang menyadarinya
     * pelanggan, bukan kita.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftar(): array
    {
        $dari = static::query()
            ->where('aktif', true)
            ->orderBy('min_peserta')
            ->get()
            ->map(fn (self $t) => [
                'min' => $t->min_peserta,
                'potongan_persen' => $t->potongan_persen,
                'gratis_orang' => $t->gratis_orang,
                'label' => $t->label,
                'ajakan' => $t->ajakan,
            ])
            ->all();

        return $dari !== [] ? $dari : config('orcha.promo_rombongan', []);
    }
}
