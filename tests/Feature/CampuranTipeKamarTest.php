<?php

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Support\Rab\HitungRab;

/**
 * Dua tipe kamar di hotel yang sama.
 *
 * Satuan berkapasitas menurunkan jumlah unitnya sendiri: ceil(peserta ÷
 * kapasitas). Itu benar selama seluruh rombongan memakai satu jenis unit.
 *
 * Begitu pelanggan minta campuran — dua kamar Family dan tiga Standard di
 * hotel yang sama — kedua barisnya masing-masing menginapkan SELURUH
 * rombongan. Kolom `jumlah` tidak menolong: ia mengalikan, bukan
 * menggantikan, jadi "2" terbaca sebagai dua kali lima kamar.
 *
 * Rp 3.800.000 yang sebenarnya terhitung Rp 28.000.000, dan tidak ada satu
 * pun tanda — bukan galat, bukan angka merah, cuma modal yang salah.
 */
function rabKamar(): Rab
{
    return Rab::create([
        'judul' => 'Trip Dieng', 'nama_pelanggan' => 'Rombongan Uji', 'provinsi' => 'Jawa Tengah',
        'jumlah_hari' => 3, 'jumlah_malam' => 2, 'jumlah_peserta' => 20,
        'margin_jenis' => 'persen', 'margin_nilai' => 20,
    ]);
}

function kamar(Rab $rab, string $nama, int $harga, int $kapasitas, ?int $unitManual): RabBiaya
{
    return RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'akomodasi', 'nama' => $nama,
        'satuan' => 'kamar_malam', 'harga_satuan' => $harga,
        'kapasitas' => $kapasitas, 'unit_manual' => $unitManual, 'jumlah' => 1,
    ]);
}

test('campuran tipe kamar dihitung dari jumlah kamar yang ditentukan', function () {
    $rab = rabKamar();
    kamar($rab, 'Hotel X — Family', 500_000, 4, 2);
    kamar($rab, 'Hotel X — Standard', 300_000, 2, 3);

    $baris = collect(HitungRab::ringkas($rab->fresh())['baris']);

    expect($baris->firstWhere('nama', 'Hotel X — Family')['subtotal'])->toBe(2_000_000)
        ->and($baris->firstWhere('nama', 'Hotel X — Standard')['subtotal'])->toBe(1_800_000)
        ->and((int) $baris->sum('subtotal'))->toBe(3_800_000);
});

test('penjelasannya menyebut jumlah kamarnya, bukan pengali di atas pengali', function () {
    $rab = rabKamar();
    kamar($rab, 'Hotel X — Family', 500_000, 4, 2);

    expect(collect(HitungRab::ringkas($rab->fresh())['baris'])->first()['penjelasan'])
        ->toBe('Rp 500.000 × 2 kamar × 2 malam');
});

test('baris yang tidak mengisinya tetap dihitung dari jumlah peserta', function () {
    // Seluruh baris yang sudah ada di produksi berperilaku persis seperti dulu.
    $rab = rabKamar();
    kamar($rab, 'Homestay', 350_000, 4, null);

    $b = collect(HitungRab::ringkas($rab->fresh())['baris'])->first();

    // 20 peserta ÷ 4 = 5 kamar × 2 malam × Rp 350.000
    expect($b['penjelasan'])->toBe('Rp 350.000 × 5 kamar × 2 malam')
        ->and($b['subtotal'])->toBe(3_500_000);
});

test('kamar yang dipesan kurang dari jumlah peserta disebut angkanya', function () {
    // Dua Family (8 orang) untuk rombongan 20 adalah kesalahan yang tidak
    // menampakkan diri di satu angka pun.
    $rab = rabKamar();
    kamar($rab, 'Hotel X — Family', 500_000, 4, 2);

    $r = HitungRab::ringkas($rab->fresh());

    expect($r['daya_tampung']['muat'])->toBe(8)
        ->and($r['daya_tampung']['peserta'])->toBe(20)
        ->and($r['daya_tampung']['kurang'])->toBe(12);
});

test('kamar yang cukup tidak memunculkan kekurangan', function () {
    $rab = rabKamar();
    kamar($rab, 'Hotel X — Family', 500_000, 4, 2);   // 8
    kamar($rab, 'Hotel X — Standard', 300_000, 2, 6); // 12

    expect(HitungRab::ringkas($rab->fresh())['daya_tampung']['kurang'])->toBe(0);
});

test('RAB tanpa kamar yang diisi sendiri tidak memasang penanda sama sekali', function () {
    // Penanda yang menyala di tempat yang tidak mungkin kurang akan membuat
    // orang berhenti membacanya.
    $rab = rabKamar();
    kamar($rab, 'Homestay', 350_000, 4, null);

    expect(HitungRab::ringkas($rab->fresh())['daya_tampung'])->toBeNull();
});

test('jumlah unit bisa diisi dan dikembalikan lewat API', function () {
    config()->set('orcha.api.kunci', 'kunci-kamar');
    $kepala = ['X-Orcha-Key' => 'kunci-kamar', 'Accept' => 'application/json'];

    $rab = rabKamar();

    $baris = $this->postJson("/api/v1/rab/{$rab->id}/biaya", [
        'kategori' => 'akomodasi', 'nama' => 'Hotel X — Family', 'satuan' => 'kamar_malam',
        'harga_satuan' => 500_000, 'kapasitas' => 4, 'unit_manual' => 2,
    ], $kepala)->assertOk()->json('data.ringkasan.baris.0');

    expect($baris['unit_manual'])->toBe(2)
        ->and($baris['subtotal'])->toBe(2_000_000);

    // null mengembalikannya ke penurunan otomatis.
    $lagi = $this->patchJson("/api/v1/rab/{$rab->id}/biaya/{$baris['id']}",
        ['unit_manual' => null], $kepala)->assertOk()->json('data.ringkasan.baris.0');

    expect($lagi['unit_manual'])->toBeNull()
        ->and($lagi['subtotal'])->toBe(5_000_000);
});

test('satuan per orang tidak terpengaruh jumlah unit', function () {
    $rab = rabKamar();
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'konsumsi', 'nama' => 'Makan siang',
        'satuan' => 'orang', 'harga_satuan' => 40_000, 'unit_manual' => 2, 'jumlah' => 1]);

    expect(collect(HitungRab::ringkas($rab->fresh())['baris'])->first()['subtotal'])
        ->toBe(800_000);
});

/*
|--------------------------------------------------------------------------
| Beberapa tipe kamar yang belum dipilah
|--------------------------------------------------------------------------
|
| Keadaan yang persis dialami admin sesudah menarik dua tipe kamar dari master:
| keduanya masih menghitung diri dari jumlah peserta, jadi masing-masing
| menginapkan SELURUH rombongan. Rp 3.800.000 terhitung Rp 11.000.000, dan
| tidak ada satu pun tanda.
*/
test('dua tipe kamar yang belum diisi jumlahnya disebut namanya', function () {
    $rab = rabKamar();
    kamar($rab, 'Hotel X — Family', 500_000, 4, null);
    kamar($rab, 'Hotel X — Standard', 300_000, 2, null);

    expect(HitungRab::ringkas($rab->fresh())['kamar_belum_dipilah'])
        ->toBe(['Hotel X — Family', 'Hotel X — Standard']);
});

test('yang sudah diisi tidak ikut disebut', function () {
    $rab = rabKamar();
    kamar($rab, 'Hotel X — Family', 500_000, 4, 2);
    kamar($rab, 'Hotel X — Standard', 300_000, 2, null);

    expect(HitungRab::ringkas($rab->fresh())['kamar_belum_dipilah'])
        ->toBe(['Hotel X — Standard']);
});

test('keduanya terisi, penandanya diam', function () {
    $rab = rabKamar();
    kamar($rab, 'Hotel X — Family', 500_000, 4, 2);
    kamar($rab, 'Hotel X — Standard', 300_000, 2, 6);

    expect(HitungRab::ringkas($rab->fresh())['kamar_belum_dipilah'])->toBe([]);
});

test('satu tipe kamar saja tidak pernah dilaporkan', function () {
    // Di sana penurunan otomatis memang benar, dan penanda yang menyala di
    // tempat yang tidak mungkin salah akan membuat orang berhenti membacanya.
    $rab = rabKamar();
    kamar($rab, 'Homestay', 350_000, 4, null);

    expect(HitungRab::ringkas($rab->fresh())['kamar_belum_dipilah'])->toBe([]);
});
