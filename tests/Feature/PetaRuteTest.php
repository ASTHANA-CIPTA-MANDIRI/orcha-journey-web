<?php

use App\Support\SewaKendaraan\PetaRute;
use Illuminate\Support\Facades\Http;

/**
 * Peta rute di formulir sewa: koordinat titik jemput dan tujuan, beserta jarak
 * JALAN di antara keduanya.
 *
 * Sifatnya keterangan, bukan dasar hitungan — tarif sewa dihitung per hari,
 * bukan per kilometer. Karena itu yang dijaga di sini bukan cuma "angkanya
 * muncul", melainkan juga bahwa kegagalan layanan tidak pernah menghentikan
 * pemesanan.
 */
function fakeTitikDanRute(?array $rute = null): void
{
    config()->set('orcha.rute.kunci', 'kunci-uji');

    Http::fake([
        '*nominatim*' => Http::sequence()
            ->push([[
                'lat' => '-7.7908433', 'lon' => '110.3668730',
                'display_name' => 'Grand Inna Malioboro, Jalan Mataram, Yogyakarta, Indonesia',
            ]])
            ->push([[
                'lat' => '-7.6079583', 'lon' => '110.2038245',
                'display_name' => 'Candi Borobudur, Magelang, Jawa Tengah, Indonesia',
            ]]),
        '*openrouteservice*' => Http::response($rute ?? [
            'features' => [[
                'properties' => ['summary' => ['distance' => 42340.5, 'duration' => 2100]],
                'geometry' => ['coordinates' => [[110.3668, -7.7908], [110.2038, -7.6079]]],
            ]],
        ]),
    ]);
}

test('titik jemput dan tujuan ketemu koordinatnya beserta jarak jalannya', function () {
    fakeTitikDanRute();

    $hasil = (new PetaRute)->rangkum('Hotel Malioboro Yogyakarta', 'Candi Borobudur');

    expect($hasil['jemput']['lat'])->toBe(-7.7908433)
        // Nama tempatnya saja, bukan alamat lengkap sampai negaranya.
        ->and($hasil['jemput']['nama'])->toBe('Grand Inna Malioboro')
        ->and($hasil['tujuan']['nama'])->toBe('Candi Borobudur')
        ->and($hasil['jarak_km'])->toBe(42.3)
        ->and($hasil['durasi_menit'])->toBe(35);
});

test('koordinat dikirim ke layanan rute dalam urutan lon-lat', function () {
    fakeTitikDanRute();

    (new PetaRute)->rangkum('Hotel Malioboro Yogyakarta', 'Candi Borobudur');

    // OpenRouteService memakai [lon, lat] — kebalikan dari kebiasaan menulis
    // lat/lon. Tertukar, rutenya mendarat di laut lepas dan jaraknya jadi
    // ribuan kilometer, tanpa satu pun galat yang terlihat.
    Http::assertSent(function ($permintaan) {
        if (! str_contains($permintaan->url(), 'openrouteservice')) {
            return false;
        }

        [$dari, $ke] = $permintaan->data()['coordinates'];

        return $dari[0] === 110.366873 && $dari[1] === -7.7908433
            && $ke[0] === 110.2038245 && $ke[1] === -7.6079583;
    });
});

test('layanan rute diberi radius pencarian jalan yang longgar', function () {
    fakeTitikDanRute();

    (new PetaRute)->rangkum('Hotel Malioboro Yogyakarta', 'Candi Borobudur');

    // Bawaan OpenRouteService 350 meter, dan itu terlalu rapat untuk titik yang
    // datang dari pencarian nama: "bali" menghasilkan titik tengah provinsinya,
    // yang jatuh jauh dari jalan mana pun dan dijawab 404.
    Http::assertSent(fn ($permintaan) => ! str_contains($permintaan->url(), 'openrouteservice')
        || $permintaan->data()['radiuses'] === [5000, 5000]);
});

test('garis rute dibalik jadi lat-lon untuk peta', function () {
    fakeTitikDanRute();

    $hasil = (new PetaRute)->rangkum('Hotel Malioboro Yogyakarta', 'Candi Borobudur');

    // Dibalik sekali di satu tempat, supaya sisi tampilan tidak perlu tahu
    // urutan milik siapa.
    expect($hasil['garis'][0])->toBe([-7.7908, 110.3668]);
});

test('tanpa kunci layanan rute, kedua titik tetap ada tetapi jaraknya tidak', function () {
    fakeTitikDanRute();
    config()->set('orcha.rute.kunci', null);

    $hasil = (new PetaRute)->rangkum('Hotel Malioboro Yogyakarta', 'Candi Borobudur');

    // Peta dengan dua penanda di letak yang benar tetap berguna; yang tidak
    // ada hanya angkanya. Halaman tidak boleh gagal hanya karena kunci belum
    // dipasang.
    expect($hasil['jemput'])->not->toBeNull()
        ->and($hasil['tujuan'])->not->toBeNull()
        ->and($hasil['jarak_km'])->toBeNull();
});

test('tulisan terlalu pendek tidak ditanyakan ke layanan mana pun', function () {
    Http::fake();

    expect((new PetaRute)->titik('Jl'))->toBeNull();

    Http::assertNothingSent();
});

test('layanan yang gagal tidak melempar galat, hanya tidak menjawab', function () {
    config()->set('orcha.rute.kunci', 'kunci-uji');

    Http::fake([
        '*nominatim*' => Http::response([], 503),
        '*openrouteservice*' => Http::response([], 500),
    ]);

    $hasil = (new PetaRute)->rangkum('Hotel Malioboro Yogyakarta', 'Candi Borobudur');

    // Yang gagal mengembalikan null. Formulir pemesanan tidak boleh berhenti
    // hanya karena layanan peta sedang mati.
    expect($hasil['jemput'])->toBeNull()
        ->and($hasil['jarak_km'])->toBeNull();
});
