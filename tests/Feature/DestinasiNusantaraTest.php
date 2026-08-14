<?php

use App\Models\DestinationPopuler;
use App\Support\SampulDestinasi;

function buatDestinasi(string $nama, string $wilayah, string $provinsi, int $pengunjung = 1000): DestinationPopuler
{
    return DestinationPopuler::create([
        'destination_name' => $nama,
        'wilayah' => $wilayah,
        'provinsi' => $provinsi,
        'deskripsi' => "Keterangan singkat tentang $nama.",
        'total_visitor' => $pengunjung,
        'main_photo' => '/images/destinasi/contoh.svg',
        'others_photo' => [],
    ]);
}

test('destinasi bisa disaring per wilayah', function () {
    buatDestinasi('Raja Ampat', 'maluku_papua', 'Papua Barat Daya', 4800);
    buatDestinasi('Karimunjawa', 'jawa', 'Jawa Tengah', 14200);

    $this->get(route('destinasi'))
        ->assertOk()
        ->assertSee('Raja Ampat')
        ->assertSee('Karimunjawa');

    $this->get(route('destinasi', ['wilayah' => 'maluku_papua']))
        ->assertOk()
        ->assertSee('Raja Ampat')
        ->assertDontSee('Karimunjawa');
});

test('destinasi bisa dicari lewat nama provinsi', function () {
    buatDestinasi('Danau Toba', 'sumatera', 'Sumatera Utara');
    buatDestinasi('Nusa Penida', 'bali_nusa', 'Bali');

    $this->get(route('destinasi', ['cari' => 'Sumatera Utara']))
        ->assertOk()
        ->assertSee('Danau Toba')
        ->assertDontSee('Nusa Penida');
});

test('kartu destinasi menampilkan wilayah, provinsi, dan deskripsi', function () {
    buatDestinasi('Banda Neira', 'maluku_papua', 'Maluku');

    $this->get(route('destinasi'))
        ->assertOk()
        ->assertSee('Maluku &amp; Papua', false)
        ->assertSee('Maluku')
        ->assertSee('Keterangan singkat tentang Banda Neira.');
});

test('label wilayah jatuh ke Indonesia bila kuncinya tidak dikenal', function () {
    $destinasi = buatDestinasi('Tempat Uji', 'jawa', 'Jawa Barat');
    expect($destinasi->wilayah_label)->toBe('Jawa');

    $destinasi->wilayah = 'antariksa';
    expect($destinasi->wilayah_label)->toBe('Indonesia');
});

test('pembuat sampul menghasilkan svg yang sama untuk nama yang sama', function () {
    $sampul = new SampulDestinasi;

    $pertama = $sampul->render('Karimunjawa');
    $kedua = $sampul->render('Karimunjawa');
    $beda = $sampul->render('Nusa Penida');

    expect($pertama)->toStartWith('<svg')
        ->and($pertama)->toContain('</svg>')
        ->and($pertama)->toBe($kedua)      // sama persis bila dibuat ulang
        ->and($pertama)->not->toBe($beda); // beda destinasi, beda gambar
});

test('berkas sampul destinasi tersedia di public', function () {
    foreach (['karimunjawa', 'raja-ampat', 'banda-neira', 'nusa-penida', 'danau-toba'] as $slug) {
        expect(file_exists(public_path("images/destinasi/$slug.svg")))->toBeTrue();
    }
});

test('seeder mengisi destinasi dari berbagai wilayah Indonesia', function () {
    (new Database\Seeders\DestinationPopulerSeeder)->run();

    $wilayah = DestinationPopuler::distinct()->pluck('wilayah');

    expect(DestinationPopuler::count())->toBeGreaterThanOrEqual(20)
        ->and($wilayah)->toContain('sumatera', 'jawa', 'bali_nusa', 'kalimantan', 'sulawesi', 'maluku_papua');
});
