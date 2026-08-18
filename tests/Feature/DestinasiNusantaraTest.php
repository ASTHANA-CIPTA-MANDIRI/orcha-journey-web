<?php

use App\Models\Etalase\DestinationPopuler;
use App\Support\Etalase\SampulDestinasi;

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
    buatDestinasi('Raja Ampat', 'papua', 'Papua Barat Daya', 4800);
    buatDestinasi('Karimunjawa', 'jawa', 'Jawa Tengah', 14200);

    $this->get(route('destinasi'))
        ->assertOk()
        ->assertSee('Raja Ampat')
        ->assertSee('Karimunjawa');

    $this->get(route('destinasi', ['wilayah' => 'papua']))
        ->assertOk()
        ->assertSee('Raja Ampat')
        ->assertDontSee('Karimunjawa');
});

test('destinasi bisa dicari lewat nama provinsi', function () {
    buatDestinasi('Danau Toba', 'sumatera', 'Sumatera Utara');
    buatDestinasi('Nusa Penida', 'bali', 'Bali');

    $this->get(route('destinasi', ['cari' => 'Sumatera Utara']))
        ->assertOk()
        ->assertSee('Danau Toba')
        ->assertDontSee('Nusa Penida');
});

test('kartu destinasi menampilkan wilayah, provinsi, dan deskripsi', function () {
    buatDestinasi('Banda Neira', 'maluku', 'Maluku');

    $this->get(route('destinasi'))
        ->assertOk()
        ->assertSee('Maluku', false)
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
        ->and($wilayah)->toContain('sumatera', 'jawa', 'bali', 'nusa_tenggara', 'kalimantan', 'sulawesi', 'maluku', 'papua');
});

test('destinasi terbaru tampil lebih dulu di halaman publik', function () {
    // Ramai belum tentu baru. Diurutkan menurut jumlah pengunjung, destinasi
    // yang baru saja ditambahkan admin bisa mendarat di halaman terakhir dan
    // praktis tidak pernah terlihat — padahal justru itu yang layak dikabarkan.
    $lama = buatDestinasi('Raja Ampat', 'papua', 'Papua Barat Daya', 48000);
    $lama->forceFill(['created_at' => now()->subMonth()])->save();

    $baru = buatDestinasi('Pantai Ujung Gelam', 'jawa', 'Jawa Tengah', 120);

    $urutan = Livewire\Volt\Volt::test('public.destinasi.index')
        ->viewData('destinations')
        ->pluck('destination_name')
        ->all();

    expect($urutan)->toBe([$baru->destination_name, $lama->destination_name]);
});

test('destinasi yang tercatat pada detik yang sama tetap berurutan tetap', function () {
    // Dua puluh satu destinasi bawaan tercatat pada detik yang sama. Tanpa
    // pemutus, urutannya berubah-ubah tanpa ada yang berubah.
    $waktu = now()->subDay();

    foreach (['Danau Toba', 'Bunaken', 'Wakatobi'] as $nama) {
        buatDestinasi($nama, 'jawa', 'Jawa Tengah')
            ->forceFill(['created_at' => $waktu])->save();
    }

    $sekali = fn () => Livewire\Volt\Volt::test('public.destinasi.index')
        ->viewData('destinations')->pluck('id')->all();

    expect($sekali())->toBe($sekali())
        ->and($sekali())->toBe(DestinationPopuler::orderByDesc('id')->pluck('id')->all());
});
