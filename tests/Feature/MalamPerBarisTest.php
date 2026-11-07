<?php

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\VersiPenawaran;
use App\Support\Rab\HitungRab;

/**
 * Berapa MALAM sebuah baris dipakai — tidak selalu sepanjang perjalanan.
 *
 * Yang dijaga di sini: rombongan yang PINDAH menginap tidak membayar kedua
 * hotelnya sepanjang trip, dan akomodasi yang tidak menutup seluruh malam
 * perjalanan disebut alih-alih dibiarkan lewat.
 *
 * Contoh angkanya dari RAB Dieng: trip 3 malam, Homestay Rp 800.000 semalam
 * untuk 1 malam, lalu Sans Hotel Rp 450.000 semalam untuk 2 malam.
 */
function kepalaMalam(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-malam');

    return ['X-Orcha-Key' => 'kunci-uji-malam', 'Accept' => 'application/json'];
}

function rabMalam(int $hari = 4, int $malam = 3, int $peserta = 11): Rab
{
    return Rab::create([
        'judul' => 'Private Trip Dieng',
        'nama_pelanggan' => 'Pak Hendra',
        'provinsi' => 'Jawa Tengah',
        'tanggal_mulai' => '2026-11-20',
        'jumlah_hari' => $hari,
        'jumlah_malam' => $malam,
        'jumlah_peserta' => $peserta,
        'margin_jenis' => 'persen',
        'margin_nilai' => 5,
    ])->fresh();
}

function kamarMalam(Rab $rab, string $nama, int $harga, ?int $malam, array $ubah = []): RabBiaya
{
    return RabBiaya::create(array_merge([
        'rab_id' => $rab->id,
        'kategori' => 'akomodasi',
        'nama' => $nama,
        'satuan' => 'kamar_malam',
        'harga_satuan' => $harga,
        'kapasitas' => 2,
        'unit_manual' => 2,
        'malam_manual' => $malam,
        'jumlah' => 1,
        'urutan' => 1,
    ], $ubah));
}

/* =========================== PENGALINYA =========================== */

it('memakai malam perjalanan saat malamnya tidak diisi', function () {
    $rab = rabMalam();
    $b = kamarMalam($rab, 'Homestay Rizkia', 800_000, null);

    // 2 kamar × 3 malam × Rp 800.000 — perilaku lama, tidak boleh berubah.
    expect(HitungRab::subtotal($b, $rab))->toBe(4_800_000);
});

it('memakai malam yang diisi tangan, bukan malam perjalanan', function () {
    $rab = rabMalam();
    $b = kamarMalam($rab, 'Homestay Rizkia', 800_000, 1);

    expect(HitungRab::subtotal($b, $rab))->toBe(1_600_000, '2 kamar × 1 malam');
});

it('tidak menagih kedua hotel sepanjang perjalanan saat rombongan pindah', function () {
    $rab = rabMalam();
    kamarMalam($rab, 'Homestay Rizkia', 800_000, 1);
    kamarMalam($rab, 'Sans Hotel', 450_000, 2, ['urutan' => 2]);

    $total = collect(HitungRab::ringkas($rab->fresh())['baris'])->sum('subtotal');

    /*
     | 1.600.000 + 1.800.000. Tanpa malam_manual keduanya tertagih 3 malam:
     | 4.800.000 + 2.700.000 = 7.500.000 — dua kali lipat untuk menginap
     | yang sama.
     */
    expect($total)->toBe(3_400_000);
});

it('nol malam berarti nol, bukan sehari', function () {
    $rab = rabMalam();
    $b = kamarMalam($rab, 'Homestay Rizkia', 800_000, 0);

    expect(HitungRab::subtotal($b, $rab))->toBe(0);
});

it('berlaku juga untuk armada per hari', function () {
    $rab = rabMalam();
    $b = RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'transportasi', 'nama' => 'Sewa Hiace',
        'satuan' => 'unit_hari', 'harga_satuan' => 1_100_000,
        'kapasitas' => 14, 'unit_manual' => 1, 'malam_manual' => 2,
        'jumlah' => 1, 'urutan' => 1,
    ]);

    // Rombongan naik kereta di hari terakhir: bus 2 hari, bukan 4.
    expect(HitungRab::subtotal($b, $rab))->toBe(2_200_000);
});

it('nol hari pada armada juga berarti nol', function () {
    $rab = rabMalam();
    $b = RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'transportasi', 'nama' => 'Sewa Hiace',
        'satuan' => 'unit_hari', 'harga_satuan' => 1_100_000,
        'kapasitas' => 14, 'unit_manual' => 1, 'malam_manual' => 0,
        'jumlah' => 1, 'urutan' => 1,
    ]);

    /*
     | Kalau hari dinaikkan ke 1 sementara malam dibiarkan 0, angka "0" yang
     | sama berarti gratis di kartu hotel dan sehari penuh di kartu armada.
     */
    expect(HitungRab::subtotal($b, $rab))->toBe(0);
});

it('tidak menyentuh satuan yang tidak dikali durasi', function () {
    $rab = rabMalam();
    $b = RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'tiket', 'nama' => 'Tiket Dieng',
        'satuan' => 'orang', 'harga_satuan' => 25_000,
        'malam_manual' => 1, 'jumlah' => 1, 'urutan' => 1,
    ]);

    // 11 peserta × Rp 25.000 — malam tidak punya arti di sini.
    expect(HitungRab::subtotal($b, $rab))->toBe(275_000);
});

/* =========================== KALIMATNYA =========================== */

it('menyebut malam yang benar-benar dikalikan', function () {
    $rab = rabMalam();
    $b = kamarMalam($rab, 'Sans Hotel', 450_000, 2);

    /*
     | Kalimat yang menyebut 3 malam di bawah subtotal yang dihitung dari 2
     | membantah angkanya sendiri — dan yang dipercaya orang adalah
     | kalimatnya, lalu subtotalnya dikira salah hitung.
     */
    expect(HitungRab::penjelasan($b, $rab))->toBe('Rp 450.000 × 2 kamar × 2 malam');
});

it('menyebut hari untuk armada, bukan malam', function () {
    $rab = rabMalam();
    $b = RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'transportasi', 'nama' => 'Sewa Hiace',
        'satuan' => 'unit_hari', 'harga_satuan' => 1_100_000,
        'kapasitas' => 14, 'unit_manual' => 1, 'malam_manual' => 2,
        'jumlah' => 1, 'urutan' => 1,
    ]);

    expect(HitungRab::penjelasan($b, $rab))->toBe('Rp 1.100.000 × 1 unit × 2 hari');
});

/* ===================== PENJAGA MALAM TERTUTUP ===================== */

it('menyebut malam yang tidak tertutup akomodasi', function () {
    $rab = rabMalam();
    kamarMalam($rab, 'Homestay Rizkia', 800_000, 1);
    kamarMalam($rab, 'Sans Hotel', 450_000, 1, ['urutan' => 2]);

    $m = HitungRab::ringkas($rab->fresh())['malam_akomodasi'];

    /*
     | Satu malam rombongan tidak tidur di mana pun — dan tidak ada satu pun
     | angka yang salah, jadi tanpa penjaga ini tidak ada yang berbunyi.
     */
    expect($m['tertutup'])->toBe(2);
    expect($m['perjalanan'])->toBe(3);
    expect($m['cukup'])->toBeFalse();
});

it('diam saat malamnya sudah tertutup', function () {
    $rab = rabMalam();
    kamarMalam($rab, 'Homestay Rizkia', 800_000, 1);
    kamarMalam($rab, 'Sans Hotel', 450_000, 2, ['urutan' => 2]);

    $m = HitungRab::ringkas($rab->fresh())['malam_akomodasi'];

    expect($m['tertutup'])->toBe(3);
    expect($m['cukup'])->toBeTrue();
});

it('tidak menghitung dua kali tipe kamar di hotel yang sama', function () {
    $rab = rabMalam();
    // Dua Family + tiga Standard di Sans Hotel, malam yang SAMA.
    kamarMalam($rab, 'Sans Hotel', 450_000, 3, ['unit_manual' => 2]);
    kamarMalam($rab, 'Sans Hotel', 300_000, 3, ['unit_manual' => 3, 'urutan' => 2]);

    $m = HitungRab::ringkas($rab->fresh())['malam_akomodasi'];

    /*
     | Dijumlahkan lurus hasilnya 6 malam untuk perjalanan 3 malam — penjaga
     | yang memberi lampu hijau pada angka yang justru dua kali lipat.
     */
    expect($m['tertutup'])->toBe(3);
    expect($m['cukup'])->toBeTrue();
});

it('tidak berkata apa-apa saat belum ada akomodasi sama sekali', function () {
    $rab = rabMalam();

    /*
     | Penawaran yang belum diisi biayanya belum punya apa pun yang bisa
     | salah. Peringatan yang muncul sebelum itu adalah peringatan yang
     | diabaikan — termasuk nanti saat ia benar.
     */
    expect(HitungRab::ringkas($rab->fresh())['malam_akomodasi'])->toBeNull();
});

it('tidak berkata apa-apa pada perjalanan sehari', function () {
    $rab = rabMalam(hari: 1, malam: 0);
    kamarMalam($rab, 'Homestay Rizkia', 800_000, null);

    expect(HitungRab::ringkas($rab->fresh())['malam_akomodasi'])->toBeNull();
});

it('menghitung baris tanpa malam manual sebagai sepanjang perjalanan', function () {
    $rab = rabMalam();
    kamarMalam($rab, 'Homestay Rizkia', 800_000, null);

    $m = HitungRab::ringkas($rab->fresh())['malam_akomodasi'];

    expect($m['tertutup'])->toBe(3);
    expect($m['cukup'])->toBeTrue();
});

/* ============================== API ============================== */

it('menyimpan malam dari layar RAB', function () {
    $rab = rabMalam();
    $b = kamarMalam($rab, 'Homestay Rizkia', 800_000, null);

    $this->patchJson('/api/v1/rab/'.$rab->id.'/biaya/'.$b->id,
        ['malam_manual' => 1], kepalaMalam())->assertOk();

    expect($b->fresh()->malam_manual)->toBe(1);
});

it('mengembalikan ke otomatis saat malamnya dikosongkan', function () {
    $rab = rabMalam();
    $b = kamarMalam($rab, 'Homestay Rizkia', 800_000, 1);

    $this->patchJson('/api/v1/rab/'.$rab->id.'/biaya/'.$b->id,
        ['malam_manual' => null], kepalaMalam())->assertOk();

    expect($b->fresh()->malam_manual)->toBeNull();
    expect(HitungRab::subtotal($b->fresh(), $rab))->toBe(4_800_000);
});

it('menolak malam yang lebih panjang daripada setahun', function () {
    $rab = rabMalam();
    $b = kamarMalam($rab, 'Homestay Rizkia', 800_000, 1);

    $this->patchJson('/api/v1/rab/'.$rab->id.'/biaya/'.$b->id,
        ['malam_manual' => 400], kepalaMalam())
        ->assertStatus(422)->assertJsonValidationErrors('malam_manual');
});

it('ringkasan baris memulangkan malam apa adanya, bukan diisi angka perjalanan', function () {
    $rab = rabMalam();
    kamarMalam($rab, 'Homestay Rizkia', 800_000, null);

    $baris = HitungRab::ringkas($rab->fresh())['baris'][0];

    /*
     | Layar perlu membedakan "sengaja 3 malam" dari "ikut perjalanan yang
     | kebetulan 3 malam": keduanya berperilaku beda saat panjang
     | perjalanannya diubah.
     */
    expect($baris['malam_manual'])->toBeNull();
    expect($baris['pakai_durasi'])->toBeTrue();
    expect($baris['durasi_sebutan'])->toBe('malam');
});

it('menandai satuan yang tidak dikali durasi', function () {
    $rab = rabMalam();
    RabBiaya::create([
        'rab_id' => $rab->id, 'kategori' => 'tiket', 'nama' => 'Tiket Dieng',
        'satuan' => 'orang', 'harga_satuan' => 25_000, 'jumlah' => 1, 'urutan' => 1,
    ]);

    expect(HitungRab::ringkas($rab->fresh())['baris'][0]['pakai_durasi'])->toBeFalse();
});

it('potret versi penawaran ikut menyimpan unit dan malamnya', function () {
    $rab = rabMalam();
    kamarMalam($rab, 'Homestay Rizkia', 800_000, 1, ['unit_manual' => 3]);

    $this->patchJson('/api/v1/rab/'.$rab->id, ['status' => 'dikirim'], kepalaMalam())->assertOk();

    $versi = VersiPenawaran::where('rab_id', $rab->id)->first();
    $baris = $versi->rincian['baris'][0];

    /*
     | Tanpa keduanya, versi yang dipulihkan menghitung ulang otomatis: tiga
     | kamar jadi enam, satu malam jadi tiga, dan totalnya tidak lagi sama
     | dengan yang dilihat pelanggan.
     */
    expect($baris['unit_manual'])->toBe(3);
    expect($baris['malam_manual'])->toBe(1);
});

it('memulihkan versi tanpa kehilangan unit dan malamnya', function () {
    $rab = rabMalam();
    kamarMalam($rab, 'Homestay Rizkia', 800_000, 1, ['unit_manual' => 3]);

    $this->patchJson('/api/v1/rab/'.$rab->id, ['status' => 'dikirim'], kepalaMalam())->assertOk();
    $versi = VersiPenawaran::where('rab_id', $rab->id)->first();

    // Diubah, lalu dipulihkan.
    $rab->fresh()->biaya()->update(['malam_manual' => 3, 'unit_manual' => 6]);

    $this->postJson('/api/v1/rab/'.$rab->id.'/versi/'.$versi->id.'/pulihkan', [], kepalaMalam())
        ->assertOk();

    $b = $rab->fresh()->biaya->first();

    expect($b->malam_manual)->toBe(1);
    expect($b->unit_manual)->toBe(3);
});
