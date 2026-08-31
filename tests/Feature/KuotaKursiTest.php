<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use Livewire\Volt\Volt;

/**
 * Batas ATAS jumlah peserta sebuah paket.
 *
 * Selama ini paket hanya punya minimal_peserta — batas bawah, jumlah minimum
 * supaya tripnya jadi berangkat. Batas atasnya tidak ada di mana pun, sehingga
 * yang menahan pendaftaran melewati daya angkut armada cuma ingatan admin —
 * dan ingatan itu bekerja paling buruk justru saat pendaftaran ramai.
 */
function paketBerkuota(int $kuota = 10): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Bromo',
        'category' => 'open_trip',
        'price' => 750000,
        'status' => 'terbit',
        'kuota' => $kuota,
    ]);
}

function daftarkan(TravelPackage $paket, int $jumlah, string $status = 'baru'): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => 'Peserta',
        'whatsapp' => '081234567890',
        'travel_package_id' => $paket->id,
        'jumlah_peserta' => $jumlah,
        'status' => $status,
    ]);
}

test('sisa kursi dihitung dari pendaftaran yang belum batal', function () {
    $paket = paketBerkuota(10);

    daftarkan($paket, 3);
    daftarkan($paket, 2, 'lunas');

    expect($paket->fresh()->sisa_kursi)->toBe(5);
});

test('pendaftaran yang batal mengembalikan kursinya', function () {
    $paket = paketBerkuota(10);

    daftarkan($paket, 4);
    daftarkan($paket, 3, 'batal');

    expect($paket->fresh()->sisa_kursi)->toBe(6);
});

test('kursi yang menunggu pembayaran tetap dihitung terpakai', function () {
    /*
     | Termasuk yang statusnya masih "baru". Kursi yang sedang ditunggu
     | pembayarannya tetap kursi yang tidak boleh dijual dua kali — menghitung
     | hanya yang sudah lunas berarti menjual kursi yang sama kepada orang
     | kedua selama orang pertama belum sempat mentransfer.
     */
    $paket = paketBerkuota(5);

    daftarkan($paket, 5, 'baru');

    expect($paket->fresh()->kursi_habis)->toBeTrue();
});

test('paket tanpa kuota tidak pernah penuh', function () {
    /*
     | Paket lama seluruhnya belum punya angka kuota. Memperlakukan null
     | sebagai nol akan menutup pendaftaran SEMUA paket yang sudah tayang pada
     | detik migrasinya jalan.
     */
    $paket = TravelPackage::create([
        'name' => 'Open Trip Lama', 'category' => 'open_trip',
        'price' => 500000, 'status' => 'terbit',
    ]);

    daftarkan($paket, 99);

    expect($paket->fresh()->sisa_kursi)->toBeNull()
        ->and($paket->fresh()->kursi_habis)->toBeFalse();
});

test('sisa kursi yang masih banyak tidak diumumkan', function () {
    /*
     | "Sisa 38 kursi" tidak mendorong siapa pun, dan justru memberi tahu bahwa
     | tripnya sepi. Angkanya hanya berguna ketika sisanya benar-benar sedikit.
     */
    $lega = paketBerkuota(40);
    $tipis = paketBerkuota(4);

    expect($lega->sisa_kursi_mendesak)->toBeNull()
        ->and($tipis->sisa_kursi_mendesak)->toBe(4);
});

test('pendaftaran melebihi sisa kursi ditolak di server', function () {
    /*
     | Tulisan "sisa 2 kursi" di layar sudah basi sejak digambar: dua orang
     | bisa membuka halaman yang sama pada menit yang sama dan melihat angka
     | yang sama. Yang menentukan bukan yang dilihat, melainkan yang tersisa
     | saat tombolnya ditekan.
     */
    $paket = paketBerkuota(5);
    daftarkan($paket, 3);

    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('jumlahPeserta', 3)
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('peserta', [['nama' => 'Budi Santoso'], ['nama' => 'Siti Aminah'], ['nama' => 'Joko Susilo']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasErrors('jumlahPeserta');

    // Yang muat tetap boleh masuk.
    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('jumlahPeserta', 2)
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('peserta', [['nama' => 'Budi Santoso'], ['nama' => 'Siti Aminah']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors('jumlahPeserta');
});

test('halaman paket yang penuh mengganti tombol daftar, bukan mematikannya', function () {
    /*
     | Tombol mati yang tetap terlihat seperti tombol ditekan berkali-kali oleh
     | orang yang mengira halamannya rusak. Dan kursi habis bukan jalan buntu:
     | pembatalan memang terjadi.
     */
    $paket = paketBerkuota(3);
    daftarkan($paket, 3);

    $this->get(route('paket-detail', $paket->uuid))
        ->assertOk()
        ->assertSee('Kursi trip ini sudah habis')
        ->assertDontSee('Daftar Sekarang');
});

test('sisa kursi yang tinggal sedikit tampil di halaman paket', function () {
    $paket = paketBerkuota(5);
    daftarkan($paket, 3);

    $this->get(route('paket-detail', $paket->uuid))
        ->assertOk()
        ->assertSee('Tinggal 2 kursi')
        ->assertSee('Daftar Sekarang');
});
