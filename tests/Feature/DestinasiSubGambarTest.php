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
        ->set('wilayah', 'maluku_papua')
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
