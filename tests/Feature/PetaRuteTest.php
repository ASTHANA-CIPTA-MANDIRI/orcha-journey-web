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
        '*nominatim*' => Http::response([
            [
                'lat' => '-7.7908433', 'lon' => '110.3668730',
                'display_name' => 'Malioboro, Jalan Malioboro, Sosrokusuman, Yogyakarta, Indonesia',
            ],
            [
                'lat' => '-7.2800149', 'lon' => '112.7704793',
                'display_name' => 'Malioboro, Jalan Manyar Kertoarjo, Mojo, Surabaya, Indonesia',
            ],
        ]),
        '*openrouteservice*' => Http::response($rute ?? [
            'features' => [[
                'properties' => ['summary' => ['distance' => 42340.5, 'duration' => 2100]],
                'geometry' => ['coordinates' => [[110.3668, -7.7908], [110.2038, -7.6079]]],
            ]],
        ]),
    ]);
}

test('pencarian mengembalikan BEBERAPA calon, bukan satu tebakan', function () {
    fakeTitikDanRute();

    $calon = (new PetaRute)->cari('malioboro');

    // Menebak sendiri tidak pernah benar untuk semua orang: nama yang sama
    // persis bisa berarti jalan di Yogyakarta atau pusat belanja di Surabaya,
    // 370 km terpisah. Halaman ini pernah menampilkan 369,7 km untuk
    // perjalanan yang sebenarnya 41 km karena menebak.
    expect($calon)->toHaveCount(2)
        ->and($calon[0]['nama'])->toBe('Malioboro')
        // Alamatnya ikut, karena tanpa itu dua baris "Malioboro" terlihat sama
        // persis dan tidak bisa dipilih dengan yakin.
        ->and($calon[0]['alamat'])->toContain('Yogyakarta')
        ->and($calon[1]['alamat'])->toContain('Surabaya');
});

test('pencarian tidak mengutamakan wilayah tertentu', function () {
    fakeTitikDanRute();

    (new PetaRute)->cari('Bromo');

    // Selama jawabannya satu, mengutamakan yang dekat pangkalan menolong.
    // Begitu jawabannya berupa daftar, pengutamaan itu berbalik merugikan:
    // terukur, "Bromo" dengan pengutamaan menghasilkan lima Jalan Bromo dan
    // GUNUNGNYA tersingkir dari daftar — penyewa tidak punya cara memilihnya.
    Http::assertSent(fn ($permintaan) => ! str_contains($permintaan->url(), 'nominatim')
        || ! str_contains($permintaan->url(), 'viewbox'));
});

test('jarak jalan dihitung dari titik yang sudah dipilih', function () {
    fakeTitikDanRute();

    $peta = new PetaRute;
    $calon = $peta->cari('malioboro');
    $rute = $peta->rute($calon[0], $calon[1]);

    expect($rute['jarak_km'])->toBe(42.3)
        ->and($rute['durasi_menit'])->toBe(35);
});

test('layanan rute diberi radius pencarian jalan yang longgar', function () {
    fakeTitikDanRute();

    $peta = new PetaRute;
    $calon = $peta->cari('malioboro');
    $peta->rute($calon[0], $calon[1]);

    // Bawaan OpenRouteService 350 meter, dan itu terlalu rapat untuk titik yang
    // datang dari pencarian nama: "bali" menghasilkan titik tengah provinsinya,
    // yang jatuh jauh dari jalan mana pun dan dijawab 404.
    Http::assertSent(fn ($permintaan) => ! str_contains($permintaan->url(), 'openrouteservice')
        || $permintaan->data()['radiuses'] === [5000, 5000]);
});

test('garis rute dibalik jadi lat-lon untuk peta', function () {
    fakeTitikDanRute();

    $peta = new PetaRute;
    $calon = $peta->cari('malioboro');

    // Dibalik sekali di satu tempat, supaya sisi tampilan tidak perlu tahu
    // urutan milik siapa.
    expect($peta->rute($calon[0], $calon[1])['garis'][0])->toBe([-7.7908, 110.3668]);
});

test('tanpa kunci layanan rute, kedua titik tetap ada tetapi jaraknya tidak', function () {
    fakeTitikDanRute();
    config()->set('orcha.rute.kunci', null);

    $peta = new PetaRute;
    $calon = $peta->cari('malioboro');

    // Peta dengan dua penanda di letak yang benar tetap berguna; yang tidak
    // ada hanya angkanya. Halaman tidak boleh gagal hanya karena kunci belum
    // dipasang.
    expect($calon)->toHaveCount(2)
        ->and($peta->rute($calon[0], $calon[1]))->toBeNull();
});

test('tulisan terlalu pendek tidak ditanyakan ke layanan mana pun', function () {
    Http::fake();

    expect((new PetaRute)->cari('Jl'))->toBe([]);

    Http::assertNothingSent();
});

test('layanan yang gagal tidak melempar galat, hanya tidak menjawab', function () {
    config()->set('orcha.rute.kunci', 'kunci-uji');

    Http::fake([
        '*nominatim*' => Http::response([], 503),
        '*openrouteservice*' => Http::response([], 500),
    ]);

    // Yang gagal mengembalikan kosong. Formulir pemesanan tidak boleh berhenti
    // hanya karena layanan peta sedang mati.
    expect((new PetaRute)->cari('Hotel Malioboro Yogyakarta'))->toBe([]);
});
