<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\PerkiraanPotongan;

function bayar(string $kode, int $nominal, string $status = 'diterima'): void
{
    KonfirmasiPembayaran::create([
        'kode' => $kode, 'jenis' => 'dp', 'nominal' => $nominal,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Uji', 'status' => $status,
    ]);
}

function tripBerangkat(int $hariLagi): PendaftaranOpenTrip
{
    $paket = TravelPackage::create([
        'name' => 'Open Trip Uji', 'category' => 'open_trip', 'price' => 1000000,
        'tanggal_berangkat' => now()->addDays($hariLagi)->toDateString(),
    ]);

    return PendaftaranOpenTrip::create([
        'travel_package_id' => $paket->id, 'nama' => 'Budi', 'whatsapp' => '0812',
        'jumlah_peserta' => 2, 'nama_paket' => 'Open Trip Uji',
        'tanggal_berangkat' => now()->addDays($hariLagi)->toDateString(),
    ]);
}

function sewaMulai(string $waktu): PenyewaanKendaraan
{
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil', 'transmission' => 'Matic',
        'capacity' => 7, 'price_per_day' => 500000, 'is_available' => true,
        'transmisi_tersedia' => ['Matic'],
    ]);

    $mulai = \Carbon\Carbon::parse($waktu);

    return PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => 'Avanza Uji', 'nama' => 'Rina',
        'whatsapp' => '0812', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 2,
        'tanggal_mulai' => $mulai->toDateString(), 'jam_mulai' => $mulai->format('H:i'),
        'estimasi_biaya' => 1000000, 'status' => 'dp_masuk',
    ]);
}

test('yang sudah lunas tetap terkena potongan saat batal mendadak', function () {
    // Inilah kasus yang menjadi sebab seluruh aturan ini diubah. Dengan aturan
    // lama, orang ini hanya kehilangan uang mukanya.
    $trip = tripBerangkat(3);
    bayar($trip->kode, 2000000);

    $p = PerkiraanPotongan::untuk($trip->fresh());

    expect($p['persen'])->toBe(100)
        ->and($p['potongan'])->toBe(2000000)
        ->and($p['kembali'])->toBe(0)
        ->and($p['batas'])->toContain('Kurang dari 7 hari');
});

test('yang baru bayar dp tidak berutang saat batal mendadak', function () {
    // Arah sebaliknya, dan sama pentingnya: potongan 100% dari total Rp 2 juta
    // tidak boleh menagih orang yang baru menyetor Rp 600 ribu.
    $trip = tripBerangkat(3);
    bayar($trip->kode, 600000);

    $p = PerkiraanPotongan::untuk($trip->fresh());

    expect($p['persen'])->toBe(100)
        ->and($p['potongan'])->toBe(600000)
        ->and($p['kembali'])->toBe(0);
});

test('pembatalan jauh hari mengembalikan seluruh pembayaran', function () {
    $trip = tripBerangkat(40);
    bayar($trip->kode, 2000000);

    $p = PerkiraanPotongan::untuk($trip->fresh());

    expect($p['persen'])->toBe(0)
        ->and($p['potongan'])->toBe(0)
        ->and($p['kembali'])->toBe(2000000);
});

test('tangga tengah memotong sebagian dari total, bukan dari uang muka', function () {
    $trip = tripBerangkat(20);
    bayar($trip->kode, 2000000);

    // 25% dari total Rp 2 juta = Rp 500 ribu, bukan 25% dari uang muka
    $p = PerkiraanPotongan::untuk($trip->fresh());

    expect($p['persen'])->toBe(25)
        ->and($p['potongan'])->toBe(500000)
        ->and($p['kembali'])->toBe(1500000);
});

test('bukti yang masih menunggu belum dihitung sebagai pembayaran', function () {
    $trip = tripBerangkat(40);
    bayar($trip->kode, 2000000, 'menunggu');

    // Uang yang belum diperiksa belum tentu uang; mengembalikannya berarti
    // mengirim dana yang belum tentu pernah masuk.
    expect(PerkiraanPotongan::untuk($trip->fresh())['dibayar'])->toBe(0);
});

test('sewa kendaraan memakai jam, bukan hari', function () {
    // Selisih terpenting tangga sewa justru ada di bawah 24 jam; pembulatan
    // ke hari akan meratakannya.
    $besok = sewaMulai(now()->addHours(20)->format('Y-m-d H:i'));
    bayar($besok->kode, 1000000);
    expect(PerkiraanPotongan::untuk($besok->fresh())['persen'])->toBe(100);

    $lusa = sewaMulai(now()->addHours(40)->format('Y-m-d H:i'));
    bayar($lusa->kode, 1000000);
    expect(PerkiraanPotongan::untuk($lusa->fresh())['persen'])->toBe(50);

    $pekanDepan = sewaMulai(now()->addDays(10)->format('Y-m-d H:i'));
    bayar($pekanDepan->kode, 1000000);
    expect(PerkiraanPotongan::untuk($pekanDepan->fresh())['persen'])->toBe(0);
});

test('waktu mulai yang sudah lewat dihitung sebagai tidak datang', function () {
    $lewat = sewaMulai(now()->subHours(5)->format('Y-m-d H:i'));
    bayar($lewat->kode, 1000000);

    $p = PerkiraanPotongan::untuk($lewat->fresh());

    expect($p['lewat'])->toBeTrue()
        ->and($p['batas'])->toBe('Tidak datang tanpa kabar')
        ->and($p['persen'])->toBe(100);
});

test('tanpa tanggal mulai tidak ada perkiraan yang ditampilkan', function () {
    // Menebak angka pengembalian lebih buruk daripada tidak menampilkannya.
    $trip = PendaftaranOpenTrip::create([
        'nama' => 'Budi', 'whatsapp' => '0812', 'jumlah_peserta' => 1,
        'nama_paket' => 'Menyusul',
    ]);

    expect(PerkiraanPotongan::untuk($trip))->toBeNull()
        ->and(PerkiraanPotongan::untuk(null))->toBeNull();
});
