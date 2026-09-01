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

    /** "Ajak 10 orang — gratis 1 orang" */
    public function labelOtomatis(): string
    {
        $awalan = 'Ajak '.$this->min_peserta.' orang — ';

        return $this->gratis_orang > 0
            ? $awalan.'gratis '.$this->gratis_orang.' orang'
            : $awalan.'hemat '.$this->potongan_persen.'%';
    }

    /**
     * "Ajak 10 orang, satu orang gratis."
     *
     * Dibaca yang BELUM mencapai tingkatnya, jadi bentuknya ajakan — bukan
     * keterangan seperti labelnya.
     */
    public function ajakanOtomatis(): string
    {
        return $this->gratis_orang > 0
            ? 'Ajak '.$this->min_peserta.' orang, '.$this->gratis_orang.' orang gratis.'
            : 'Ajak '.$this->min_peserta.' orang, hemat '.$this->potongan_persen.'% untuk seluruh rombongan.';
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
