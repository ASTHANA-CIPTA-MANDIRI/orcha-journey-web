<?php

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabItinerary;
use App\Support\Rab\BerkasRab;

/**
 * "Makan 8x" di penawaran.
 *
 * Dihitung dari baris konsumsi saja, angkanya kurang: sarapan hotel sudah
 * termasuk harga kamar, jadi tidak pernah punya baris biaya sendiri — padahal
 * peserta tetap memakannya. Dihitung dari jadwal saja juga kurang: makan yang
 * dibeli tetapi lupa ditulis di jadwal jadi hilang.
 *
 * Angka yang salah di sini bukan soal rapi-rapian. Pelanggan membandingkan
 * penawaran dari beberapa penyedia, dan "makan 6x" melawan "makan 8x" untuk
 * perjalanan yang isinya sama persis adalah kalah yang tidak perlu.
 */
function rabMakan(array $kegiatan, array $biaya): Rab
{
    $rab = Rab::create([
        'judul' => 'Private Trip Banyuwangi', 'nama_pelanggan' => 'Rombongan Uji',
        'provinsi' => 'Jawa Timur', 'jumlah_hari' => 3, 'jumlah_malam' => 2,
        'jumlah_peserta' => 5, 'margin_jenis' => 'persen', 'margin_nilai' => 5,
    ]);

    foreach ($kegiatan as $i => [$hari, $nama, $ket]) {
        RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => $hari, 'jam' => '09:00',
            'nama' => $nama, 'keterangan' => $ket, 'urutan' => $i]);
    }
    foreach ($biaya as $i => $nama) {
        RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'konsumsi', 'nama' => $nama,
            'satuan' => 'orang', 'harga_satuan' => 40_000, 'jumlah' => 1, 'urutan' => $i]);
    }

    return $rab->fresh();
}

function kalimatTermasuk(Rab $rab): array
{
    [, $data] = BerkasRab::bahan($rab, 'penawaran');

    return collect($data['termasuk'])->pluck('judul')->all();
}

test('kegiatan yang namanya diawali kata gerak adalah perjalanan, bukan makan', function () {
    // "Menuju tempat makan" mengandung kata makan, dan tangga penggolongnya
    // memeriksa makan lebih dulu. Di satu RAB nyata ada empat langkah
    // perjalanan yang tertukar begitu — titiknya ikut salah warna di jadwal.
    expect(BerkasRab::jenis('Menuju tempat makan', 'menuju tempat makan chen chen'))->toBe('perjalanan')
        ->and(BerkasRab::jenis('Menuju destinasi', 'menuju bj pool makan malam & belanja'))->toBe('perjalanan')
        ->and(BerkasRab::jenis('Perjalanan pulang', null))->toBe('perjalanan');
});

test('kegiatan yang memang makan tetap terbaca makan', function () {
    expect(BerkasRab::jenis('Makan di Restoku Chen-Chen Ceria', 'sarapan pagi'))->toBe('makan')
        ->and(BerkasRab::jenis('Sarapan', 'Sarapan di hotel'))->toBe('makan')
        // Namanya tidak menyebut makan sama sekali; keterangannya yang menolong.
        ->and(BerkasRab::jenis('Grafika Restorant', 'makan siang & ishoma di grafika'))->toBe('makan')
        // Kata gerak di TENGAH nama tidak menyeretnya jadi perjalanan.
        ->and(BerkasRab::jenis('Makan siang di perjalanan', null))->toBe('makan');
});

test('sarapan hotel tanpa baris biaya tetap ikut terhitung', function () {
    // Sudah termasuk harga kamar, jadi tidak pernah punya baris sendiri.
    $rab = rabMakan(
        kegiatan: [
            [1, 'Makan di Restoku Chen-Chen Ceria', 'sarapan pagi'],
            [2, 'Sarapan', 'Sarapan di hotel'],
            [3, 'Sarapan', 'sarapan pagi di hotel'],
        ],
        biaya: ['Makan di Restoku Chen-Chen Ceria'],
    );

    expect(kalimatTermasuk($rab))->toContain('Makan 3×');
});

test('makan yang dibeli tetapi belum masuk jadwal tetap ikut terhitung', function () {
    $rab = rabMakan(
        kegiatan: [[1, 'Sarapan', 'di hotel']],
        biaya: ['Sarapan', 'makan siang di alas purwo nasi box'],
    );

    // Satu dari jadwal + satu baris yang tidak cocok kegiatan mana pun.
    expect(kalimatTermasuk($rab))->toContain('Makan 2×');
});

test('yang cocok antara jadwal dan biaya tidak dihitung dua kali', function () {
    $rab = rabMakan(
        kegiatan: [[1, 'Grafika Restorant', 'makan siang & ishoma di grafika']],
        biaya: ['Grafika Restorant'],
    );

    expect(kalimatTermasuk($rab))->toContain('Makan 1×');
});

test('langkah perjalanan bernama "menuju tempat makan" tidak menambah jatah makan', function () {
    $rab = rabMakan(
        kegiatan: [
            [1, 'Menuju tempat makan', 'menuju tempat makan chen chen'],
            [1, 'Makan di Restoku Chen-Chen Ceria', 'sarapan pagi'],
        ],
        biaya: ['Makan di Restoku Chen-Chen Ceria'],
    );

    expect(kalimatTermasuk($rab))->toContain('Makan 1×');
});

test('RAB tanpa jadwal tetap menyebut angka, dihitung dari baris biayanya', function () {
    $rab = rabMakan(kegiatan: [], biaya: ['Makan siang', 'Makan malam']);

    expect(kalimatTermasuk($rab))->toContain('Makan 2×');
});

test('snack tidak ikut terhitung sebagai jatah makan', function () {
    $rab = rabMakan(
        kegiatan: [[1, 'Makan siang', null]],
        biaya: ['Makan siang', 'Snack perjalanan'],
    );

    $judul = kalimatTermasuk($rab);

    expect($judul)->toContain('Makan 1×')
        ->and($judul)->toContain('Snack & air mineral');
});

test('jadwal lengkap seperti RAB nyata menghasilkan delapan kali makan', function () {
    // Disalin dari RAB-2610-WDR7: tujuh kegiatan makan di jadwal, ditambah
    // nasi box yang dibayar tetapi tidak pernah ditulis di jadwal.
    $rab = rabMakan(
        kegiatan: [
            [1, 'Penjemputan peserta', 'penjemputan di stasiun ketapang'],
            [1, 'Menuju tempat makan', 'menuju tempat makan chen chen'],
            [1, 'Makan di Restoku Chen-Chen Ceria', 'sarapan pagi'],
            [1, 'Menuju tempat makan', 'perjalanan menuju loji'],
            [1, 'Loji Breakfast Banyuwangi', 'makan siang & ishoma'],
            [1, 'makan di pulau merah', 'makan malam di pulau merah'],
            [2, 'Sarapan', 'Sarapan di hotel'],
            [2, 'Menuju tempat makan', 'menuju grafika restorant'],
            [2, 'Grafika Restorant', 'makan siang & ishoma di grafika'],
            [2, 'Menuju destinasi', 'menju bj poll makan malam & belanja'],
            [2, 'BJ Pool & Resto', 'makan malam dan belanja oleh-oleh'],
            [3, 'Sarapan', 'sarapan pagi di hotel'],
        ],
        biaya: [
            'Makan di Restoku Chen-Chen Ceria', 'makan di pulau merah', 'Grafika Restorant',
            'BJ Pool & Resto', 'Loji Breakfast Banyuwangi', 'makan siang di alas purwo nasi box',
        ],
    );

    expect(kalimatTermasuk($rab))->toContain('Makan 8×');
});
