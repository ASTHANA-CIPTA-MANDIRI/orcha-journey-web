<?php

use App\Models\Etalase\DestinationPopuler;
use Livewire\Volt\Volt;

/**
 * Detail destinasi bisa dibuka dari halamannya sendiri.
 *
 * Sebelumnya kartu hanya memuat keterangan singkat dan tombol WhatsApp, jadi
 * satu-satunya cara mengetahui lebih banyak adalah menanyakannya — pertanyaan
 * yang jawabannya sudah tersimpan. Foto tambahan pun hanya tampil sebagai empat
 * gambar kecil yang tidak bisa diperbesar.
 */
function destinasiPublik(array $ubah = []): DestinationPopuler
{
    return DestinationPopuler::create(array_merge([
        'destination_name' => 'Bromo Tengger Semeru',
        'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur',
        'deskripsi' => 'Lautan pasir dan matahari terbit dari Penanjakan.',
        'total_visitor' => 26700,
        'main_photo' => '/storage/destinasi/utama.webp',
        'others_photo' => ['/storage/destinasi/tambahan/a.webp', '/storage/destinasi/tambahan/b.webp'],
    ], $ubah));
}

test('detail destinasi terbuka dari daftarnya', function () {
    $destinasi = destinasiPublik();

    Volt::test('public.destinasi.index')
        ->assertDontSee('pengunjung diantar')
        ->call('buka', $destinasi->id)
        ->assertSet('lihat', $destinasi->id)
        ->assertSee('Lautan pasir dan matahari terbit dari Penanjakan.')
        ->assertSee('pengunjung diantar');
});

test('seluruh foto ikut di galeri detail, bukan hanya foto utama', function () {
    $destinasi = destinasiPublik();

    // Foto tambahan sebelumnya hanya empat gambar kecil di kartu yang tidak bisa
    // diperbesar — padahal itu yang paling menentukan orang tertarik atau tidak.
    $html = Volt::test('public.destinasi.index')->call('buka', $destinasi->id)->html();

    foreach (['/storage/destinasi/utama.webp',
        '/storage/destinasi/tambahan/a.webp',
        '/storage/destinasi/tambahan/b.webp'] as $foto) {
        expect($html)->toContain($foto);
    }
});

test('detail bisa dibuka langsung lewat alamat', function () {
    $destinasi = destinasiPublik();

    // Pengunjung yang menemukan satu destinasi menarik mengirimkannya ke teman
    // seperjalanan; tautan yang selalu mendarat di daftar memaksa penerimanya
    // mencari sendiri.
    $this->get(route('destinasi', ['lihat' => $destinasi->id]))->assertOk()
        ->assertSee('Lautan pasir dan matahari terbit dari Penanjakan.');
});

test('destinasi yang dibuka lewat tautan tetap terbaca walau penyaring menyala', function () {
    $bromo = destinasiPublik();
    destinasiPublik(['destination_name' => 'Nusa Penida', 'wilayah' => 'bali', 'provinsi' => 'Bali']);

    // Detail dicari terpisah dari daftar: destinasi yang dibuka lewat tautan
    // belum tentu ada di halaman yang sedang tampil.
    Volt::test('public.destinasi.index')
        ->set('wilayah', 'bali')
        ->call('buka', $bromo->id)
        ->assertSee('Lautan pasir dan matahari terbit dari Penanjakan.');
});

test('tautan ke destinasi yang sudah dihapus tidak meruntuhkan halaman', function () {
    // Tautan yang dibagikan berumur panjang; destinasinya bisa saja sudah
    // dihapus admin. Yang benar: halamannya tetap terbuka tanpa detail.
    $this->get(route('destinasi', ['lihat' => 9999]))->assertOk()
        ->assertSee('Destinasi Populer');
});

test('menutup detail mengosongkan alamatnya', function () {
    $destinasi = destinasiPublik();

    Volt::test('public.destinasi.index')
        ->call('buka', $destinasi->id)
        ->call('tutupDetail')
        ->assertSet('lihat', null)
        ->assertDontSee('pengunjung diantar');
});
