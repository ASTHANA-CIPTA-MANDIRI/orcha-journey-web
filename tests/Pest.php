<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        /*
         * Sumber luar dimatikan secara bawaan.
         *
         * Http::fake(['*nominatim*' => ...]) TIDAK menghalangi permintaan ke
         * alamat lain — yang tidak cocok dengan satu pun pola benar-benar
         * ditembak keluar (lihat PendingRequest::buildStubHandler). Jadi begitu
         * ensiklopedia ditambahkan sebagai sumber pertama, puluhan uji yang
         * hanya menstub peta diam-diam mulai menembak Wikipedia sungguhan:
         * lambat, bergantung jaringan, dan hasilnya berubah tanpa ada kode yang
         * berubah.
         *
         * Uji yang memang menguji ensiklopedia menyalakannya sendiri.
         */
        config()->set('orcha.ensiklopedia.aktif', false);

        // Jaring pengaman: permintaan keluar yang tidak distub menggagalkan uji,
        // bukan diam-diam berhasil.
        Http::preventStrayRequests();

        /*
         | Item wajib dikosongkan dari DB uji.
         |
         | Migrasi 2026_11_04_090000 menanam empat master harga bertanda
         | `otomatis` — dokumentasi, P3K, banner, tour leader — supaya setiap
         | RAB di PRODUKSI lahir sudah membawanya. Di sini keempatnya jadi
         | latar yang ikut terhitung di mana-mana: daftar master harga
         | bertambah empat, penyaring dan pengurutnya bergeser, dan "tarik
         | biaya" menariknya lagi. Enam belas uji memerah bukan karena ada
         | yang rusak, melainkan karena latarnya berubah.
         |
         | Yang dihapus HANYA yang bertanda `otomatis`, dan perilakunya tetap
         | diuji tersendiri di ItemWajibTiapRabTest yang menanamnya kembali.
         | Jadi ini bukan menyembunyikan fitur dari uji — ini memisahkan latar
         | dari yang sedang diuji.
         */
        if (Schema::hasTable('tbl_master_harga')) {
            DB::table('tbl_master_harga')->where('otomatis', true)->delete();
        }
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
