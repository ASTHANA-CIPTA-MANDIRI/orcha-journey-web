<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

/**
 * Gambar tambahan destinasi populer.
 *
 * Sebelum ini satu unggahan baru MENGGANTI seluruh gambar tambahan yang sudah
 * tersimpan: menambah gambar ketiga justru menyisakan satu, dan tidak ada cara
 * menghapus satu gambar tanpa mengunggah ulang sisanya. Label "maksimal 3"
 * hanya tulisan — lima gambar pun diterima.
 */
function berkasGambar(string $nama): UploadedFile
{
    return UploadedFile::fake()->image($nama);
}

function destinasiBerkas(array $tambahan): DestinationPopuler
{
    return DestinationPopuler::create([
        'destination_name' => 'Bromo',
        'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur',
        'total_visitor' => 5000,
        'main_photo' => '/storage/destinasi_populer/utama/lama.jpg',
        'others_photo' => $tambahan,
    ]);
}

function bukaFormulir(DestinationPopuler $destinasi)
{
    return Volt::actingAs(User::factory()->create())
        ->test('admin.destinasi.index')
        ->call('edit', $destinasi->id);
}

test('gambar tambahan bisa diunggah saat menambah destinasi', function () {
    Storage::fake('public');

    Volt::actingAs(User::factory()->create())->test('admin.destinasi.index')
        ->set('destinationName', 'Raja Ampat')
        ->set('wilayah', 'papua')
        ->set('totalVisitor', 4800)
        ->set('mainPhoto', berkasGambar('utama.jpg'))
        ->set('othersPhoto', [berkasGambar('a.jpg'), berkasGambar('b.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect(DestinationPopuler::first()->others_photo)->toHaveCount(2);
});

test('menambah satu gambar saat menyunting tidak menghapus yang sudah ada', function () {
    Storage::fake('public');

    $destinasi = destinasiBerkas([
        '/storage/destinasi_populer/tambahan/satu.jpg',
        '/storage/destinasi_populer/tambahan/dua.jpg',
    ]);

    bukaFormulir($destinasi)
        ->set('othersPhoto', [berkasGambar('tiga.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    // Inti perbaikannya: yang lama bertahan, yang baru menyusul di belakangnya.
    expect($destinasi->fresh()->others_photo)->toHaveCount(3)
        ->and($destinasi->fresh()->others_photo[0])->toContain('satu.jpg')
        ->and($destinasi->fresh()->others_photo[1])->toContain('dua.jpg');
});

test('melebihi tiga gambar ditolak dan tidak ada yang tersimpan', function () {
    Storage::fake('public');

    $destinasi = destinasiBerkas(['/storage/destinasi_populer/tambahan/satu.jpg']);

    bukaFormulir($destinasi)
        ->set('othersPhoto', [berkasGambar('a.jpg'), berkasGambar('b.jpg'), berkasGambar('c.jpg')])
        ->call('save')
        ->assertHasErrors('othersPhoto');

    // Ditolak berarti benar-benar tidak berubah, bukan tersimpan sebagian.
    expect($destinasi->fresh()->others_photo)->toHaveCount(1);
});

test('batas dihitung dari total, bukan dari unggahan terakhir saja', function () {
    Storage::fake('public');

    // Dua gambar tersimpan + dua gambar baru = empat. Kalau hanya unggahan baru
    // yang dihitung, dua berkas terasa aman dan batasnya bisa dilewati diam-diam.
    $destinasi = destinasiBerkas([
        '/storage/destinasi_populer/tambahan/satu.jpg',
        '/storage/destinasi_populer/tambahan/dua.jpg',
    ]);

    bukaFormulir($destinasi)
        ->set('othersPhoto', [berkasGambar('a.jpg'), berkasGambar('b.jpg')])
        ->call('save')
        ->assertHasErrors('othersPhoto');

    expect($destinasi->fresh()->others_photo)->toHaveCount(2);
});

test('satu gambar bisa dihapus tanpa mengunggah ulang sisanya', function () {
    Storage::fake('public');
    Storage::disk('public')->put('destinasi_populer/tambahan/satu.jpg', 'x');
    Storage::disk('public')->put('destinasi_populer/tambahan/dua.jpg', 'x');

    $destinasi = destinasiBerkas([
        '/storage/destinasi_populer/tambahan/satu.jpg',
        '/storage/destinasi_populer/tambahan/dua.jpg',
    ]);

    bukaFormulir($destinasi)
        ->call('hapusSubGambar', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($destinasi->fresh()->others_photo)->toBe(['/storage/destinasi_populer/tambahan/dua.jpg']);

    // Berkas yang tidak dirujuk lagi ikut dibuang, supaya penyimpanan tidak
    // menumpuk gambar yatim tiap kali admin mengganti gambar.
    Storage::disk('public')->assertMissing('destinasi_populer/tambahan/satu.jpg');
    Storage::disk('public')->assertExists('destinasi_populer/tambahan/dua.jpg');
});

test('menutup formulir tanpa menyimpan tidak menghapus berkas apa pun', function () {
    Storage::fake('public');
    Storage::disk('public')->put('destinasi_populer/tambahan/satu.jpg', 'x');

    $destinasi = destinasiBerkas(['/storage/destinasi_populer/tambahan/satu.jpg']);

    bukaFormulir($destinasi)
        ->call('hapusSubGambar', 0)
        ->call('closeModal');

    // Penghapusan hanya niat sampai disimpan. Kalau berkasnya dihapus seketika,
    // membatalkan di tengah jalan meninggalkan kartu destinasi bergambar rusak.
    expect($destinasi->fresh()->others_photo)->toHaveCount(1);
    Storage::disk('public')->assertExists('destinasi_populer/tambahan/satu.jpg');
});

test('sisa tempat ikut berkurang oleh gambar yang baru dipilih', function () {
    Storage::fake('public');

    $destinasi = destinasiBerkas(['/storage/destinasi_populer/tambahan/satu.jpg']);

    // Dibaca dari data yang benar-benar diterima tampilan, karena angka inilah
    // yang menentukan tulisan "sisa n gambar" dan kapan kotak unggah disembunyikan.
    $formulir = bukaFormulir($destinasi);
    expect($formulir->viewData('sisaSlot'))->toBe(2);

    $formulir->set('othersPhoto', [berkasGambar('a.jpg')]);
    expect($formulir->viewData('sisaSlot'))->toBe(1);

    // Membatalkan pilihan mengembalikan tempatnya.
    $formulir->call('hapusUnggahan', 0);
    expect($formulir->viewData('sisaSlot'))->toBe(2);
});

/* -------- LEWAT API, UNTUK ADMIN LEMON -------- */

function kirimDestinasi(array $isi, string $metode = 'post', ?int $id = null)
{
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $alamat = '/api/v1/destinasi'.($id ? "/{$id}" : '');

    return test()->call($metode, $alamat, array_merge([
        'nama' => 'Bromo', 'wilayah' => 'jawa',
    ], $isi), [], [], [
        'HTTP_X_ORCHA_KEY' => 'kunci-uji',
        'HTTP_X_ORCHA_ADMIN' => 'admin@phoenix.test',
        'HTTP_ACCEPT' => 'application/json',
    ]);
}

test('daftar destinasi mengirim gambar tambahannya', function () {
    destinasiBerkas(['/storage/destinasi_populer/tambahan/a.jpg']);

    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    // Tanpa ini admin lemon tidak punya sumber data untuk menampilkannya —
    // gambar tambahan hanya bisa diurus dari admin bawaan Orcha.
    $baris = $this->getJson('/api/v1/destinasi', [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertOk()->json('data.0');

    expect($baris['sub_foto'])->toBe(['/storage/destinasi_populer/tambahan/a.jpg'])
        ->and($baris['batas_sub_foto'])->toBe(3);
});

test('unggahan baru ditambahkan, bukan menggantikan yang lama', function () {
    Storage::fake('public');
    $destinasi = destinasiBerkas(['/storage/destinasi_populer/tambahan/lama.jpg']);

    kirimDestinasi([
        'sub_foto_tetap' => ['/storage/destinasi_populer/tambahan/lama.jpg'],
        'sub_foto' => [berkasGambar('baru.jpg')],
    ], 'post', $destinasi->id)->assertOk();

    expect($destinasi->fresh()->others_photo)->toHaveCount(2);
});

test('gambar yang tidak dipertahankan dihapus saat disimpan', function () {
    Storage::fake('public');
    $destinasi = destinasiBerkas([
        '/storage/destinasi_populer/tambahan/satu.jpg',
        '/storage/destinasi_populer/tambahan/dua.jpg',
    ]);
    Storage::disk('public')->put('destinasi_populer/tambahan/satu.jpg', 'x');

    kirimDestinasi([
        'sub_foto_tetap' => ['/storage/destinasi_populer/tambahan/dua.jpg'],
    ], 'post', $destinasi->id)->assertOk();

    expect($destinasi->fresh()->others_photo)->toBe(['/storage/destinasi_populer/tambahan/dua.jpg'])
        ->and(Storage::disk('public')->exists('destinasi_populer/tambahan/satu.jpg'))->toBeFalse();
});

test('permintaan yang tidak menyebut gambar tambahan tidak menghapusnya', function () {
    $destinasi = destinasiBerkas(['/storage/destinasi_populer/tambahan/a.jpg']);

    // Pemanggil lama hanya mengirim medan yang dikenalnya. Menganggap diamnya
    // sebagai "hapus semua" membuang gambar yang tidak pernah diminta dibuang.
    kirimDestinasi(['deskripsi' => 'Diperbarui'], 'post', $destinasi->id)->assertOk();

    expect($destinasi->fresh()->others_photo)->toBe(['/storage/destinasi_populer/tambahan/a.jpg']);
});

test('batas tiga gambar berlaku juga lewat api', function () {
    Storage::fake('public');
    $destinasi = destinasiBerkas([
        '/storage/destinasi_populer/tambahan/satu.jpg',
        '/storage/destinasi_populer/tambahan/dua.jpg',
    ]);

    kirimDestinasi([
        'sub_foto_tetap' => [
            '/storage/destinasi_populer/tambahan/satu.jpg',
            '/storage/destinasi_populer/tambahan/dua.jpg',
        ],
        'sub_foto' => [berkasGambar('tiga.jpg'), berkasGambar('empat.jpg')],
    ], 'post', $destinasi->id)->assertStatus(422)->assertJsonValidationErrors('sub_foto');
});

test('jalur milik destinasi lain tidak bisa diklaim', function () {
    Storage::fake('public');
    destinasiBerkas(['/storage/destinasi_populer/tambahan/milik-lain.jpg']);
    $destinasi = destinasiBerkas([]);

    // Permintaan yang dirakit tangan bisa menautkan berkas milik destinasi lain,
    // dan menghapus salah satunya kemudian ikut merusak yang satunya.
    kirimDestinasi([
        'sub_foto_tetap' => ['/storage/destinasi_populer/tambahan/milik-lain.jpg'],
    ], 'post', $destinasi->id)->assertOk();

    expect($destinasi->fresh()->others_photo)->toBe([]);
});

test('menghapus destinasi ikut membuang gambar tambahannya', function () {
    Storage::fake('public');
    $destinasi = destinasiBerkas(['/storage/destinasi_populer/tambahan/ikut.jpg']);
    Storage::disk('public')->put('destinasi_populer/tambahan/ikut.jpg', 'x');

    kirimDestinasi([], 'delete', $destinasi->id)->assertOk();

    expect(Storage::disk('public')->exists('destinasi_populer/tambahan/ikut.jpg'))->toBeFalse();
});

test('satu destinasi bisa diambil untuk halaman ubah', function () {
    $destinasi = destinasiBerkas(['/storage/destinasi_populer/tambahan/a.jpg']);

    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    // Formulir yang mengambil seluruh daftar lalu menyaring sendiri membaca data
    // yang makin besar untuk memakai satu baris saja, dan diam-diam bergantung
    // pada daftar itu tidak berhalaman.
    $this->getJson("/api/v1/destinasi/{$destinasi->id}", [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertOk()->assertJsonPath('data.nama', 'Bromo')
        ->assertJsonPath('data.sub_foto.0', '/storage/destinasi_populer/tambahan/a.jpg')
        ->assertJsonPath('data.batas_sub_foto', 3);
});

/* -------- PROVINSI & WILAYAH -------- */

test('tiap provinsi menunjuk wilayah yang benar-benar ada', function () {
    // Peta yang menunjuk wilayah tak dikenal membuat destinasinya hilang dari
    // semua penyaring: tidak masuk wilayah mana pun, dan tidak ada pesan salah
    // yang muncul di mana pun.
    $wilayah = array_keys(config('orcha.wilayah'));

    // Yang menyimpang dikumpulkan dulu, baru diperiksa sebagai daftar: pesan
    // gagalnya menyebut provinsi mana yang salah, bukan hanya "ada yang salah".
    $menyimpang = collect(config('orcha.provinsi_wilayah'))
        ->reject(fn ($kunci) => in_array($kunci, $wilayah, true))
        ->keys()
        ->all();

    expect($menyimpang)->toBe([]);

    // 38 provinsi per pemekaran Papua 2022. Angka yang meleset berarti ada yang
    // tertinggal — dan provinsi yang tertinggal tidak bisa dipilih admin.
    expect(config('orcha.provinsi_wilayah'))->toHaveCount(38);
});

test('rujukan mengirim daftar provinsi beserta wilayahnya', function () {
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    // Disalin ke lemon berarti dua daftar yang bisa berbeda diam-diam saat ada
    // provinsi baru dimekarkan.
    $this->getJson('/api/v1/rujukan', [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertOk()
        ->assertJsonPath('data.provinsi_wilayah.Jawa Timur', 'jawa')
        ->assertJsonPath('data.provinsi_wilayah.Papua Pegunungan', 'papua');
});
