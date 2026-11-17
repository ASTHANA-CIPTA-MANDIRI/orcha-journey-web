<?php

/**
 * Pembagian kamar dan kursi satu rombongan.
 *
 * Yang dijaga di sini terutama satu hal: pembagian otomatis TIDAK PERNAH
 * menaruh laki-laki dan perempuan di kamar yang sama, dan tidak pernah
 * menebak jenis kelamin orang yang belum mengisinya.
 */

use App\Support\PembagianKelompok;
use App\Support\TataKursi;

/** Nama penolong diawali "pbg" supaya tidak bertabrakan dengan berkas uji lain. */
function pbgPeserta(string $nama, string $kelamin = '', int $pendaftaran = 1, int $urutan = 0): array
{
    return [
        'kunci' => $pendaftaran.':'.$urutan,
        'nama' => $nama,
        'jenis_kelamin' => $kelamin,
        'pendaftaran_id' => $pendaftaran,
        'urutan' => $urutan,
    ];
}

function pbgKamar(int $jumlah, int $kapasitas = 2): array
{
    return collect(range(1, $jumlah))
        ->map(fn ($n) => ['nama' => (string) (200 + $n), 'kapasitas' => $kapasitas])
        ->all();
}

test('kamar tidak pernah dicampur laki-laki dan perempuan', function () {
    $peserta = [
        pbgPeserta('Budi', PembagianKelompok::LAKI, 1, 0),
        pbgPeserta('Siti', PembagianKelompok::PEREMPUAN, 1, 1),
        pbgPeserta('Agus', PembagianKelompok::LAKI, 2, 0),
        pbgPeserta('Rina', PembagianKelompok::PEREMPUAN, 2, 1),
    ];

    $hasil = PembagianKelompok::kamar($peserta, pbgKamar(3));

    // Semua kebagian.
    expect($hasil['isi'])->toHaveCount(4);
    expect($hasil['sisa'])->toBe([]);

    // Tiap kamar hanya satu jenis kelamin.
    $perKamar = collect($peserta)
        ->groupBy(fn ($satu) => $hasil['isi'][$satu['kunci']])
        ->map(fn ($isi) => $isi->pluck('jenis_kelamin')->unique()->values()->all());

    foreach ($perKamar as $nama => $kelamin) {
        expect($kelamin)->toHaveCount(1, "Kamar {$nama} tercampur.");
    }
});

test('jenis kelamin yang belum diisi tidak ditebak', function () {
    $peserta = [
        pbgPeserta('Sri', '', 1, 0),
        pbgPeserta('Nur', '', 1, 1),
        pbgPeserta('Budi', PembagianKelompok::LAKI, 1, 2),
    ];

    $hasil = PembagianKelompok::kamar($peserta, pbgKamar(4));

    expect($hasil['isi'])->toHaveKey('1:2');
    expect($hasil['isi'])->not->toHaveKey('1:0');
    expect($hasil['isi'])->not->toHaveKey('1:1');

    expect(collect($hasil['sisa'])->pluck('nama')->all())->toBe(['Sri', 'Nur']);
    expect($hasil['sisa'][0]['sebab'])->toContain('belum diisi');
});

test('sisa tempat di kamar perempuan tidak dipakai laki-laki', function () {
    // 1 perempuan, 3 laki-laki, kamar kapasitas 2.
    // Kamar pertama milik perempuan dan menyisakan 1 tempat — tempat itu TIDAK
    // boleh diisi laki-laki, jadi laki-laki butuh 2 kamar sendiri.
    $peserta = [
        pbgPeserta('Rina', PembagianKelompok::PEREMPUAN, 1, 0),
        pbgPeserta('Budi', PembagianKelompok::LAKI, 1, 1),
        pbgPeserta('Agus', PembagianKelompok::LAKI, 1, 2),
        pbgPeserta('Doni', PembagianKelompok::LAKI, 1, 3),
    ];

    $hasil = PembagianKelompok::kamar($peserta, pbgKamar(3));

    expect($hasil['isi']['1:0'])->toBe('201');
    expect($hasil['isi']['1:1'])->toBe('202');
    expect($hasil['isi']['1:2'])->toBe('202');
    expect($hasil['isi']['1:3'])->toBe('203');
});

test('kamar yang kurang dilaporkan, bukan diisi berlebih', function () {
    $peserta = collect(range(1, 5))
        ->map(fn ($n) => pbgPeserta("Orang {$n}", PembagianKelompok::LAKI, 1, $n))
        ->all();

    $hasil = PembagianKelompok::kamar($peserta, pbgKamar(2, 2));

    expect($hasil['isi'])->toHaveCount(4);
    expect($hasil['sisa'])->toHaveCount(1);
    expect(implode(' ', $hasil['catatan']))->toContain('Kamar kurang');
});

test('satu pemesanan tidak dipecah ke kamar berbeda tanpa sebab', function () {
    // Dua pemesanan, masing-masing 2 perempuan, kamar kapasitas 2.
    // Hasil yang benar: tiap pemesanan satu kamar, bukan saling bersilang.
    $peserta = [
        pbgPeserta('A1', PembagianKelompok::PEREMPUAN, 1, 0),
        pbgPeserta('B1', PembagianKelompok::PEREMPUAN, 2, 0),
        pbgPeserta('A2', PembagianKelompok::PEREMPUAN, 1, 1),
        pbgPeserta('B2', PembagianKelompok::PEREMPUAN, 2, 1),
    ];

    $hasil = PembagianKelompok::kamar($peserta, pbgKamar(2));

    expect($hasil['isi']['1:0'])->toBe($hasil['isi']['1:1']);
    expect($hasil['isi']['2:0'])->toBe($hasil['isi']['2:1']);
    expect($hasil['isi']['1:0'])->not->toBe($hasil['isi']['2:0']);
});

test('tanpa kamar yang didaftarkan, hasilnya kosong dengan sebab yang disebut', function () {
    $hasil = PembagianKelompok::kamar([pbgPeserta('Budi', PembagianKelompok::LAKI)], []);

    expect($hasil['isi'])->toBe([]);
    expect(implode(' ', $hasil['catatan']))->toContain('Belum ada kamar');
});

test('petak kursi 2-2 memberi lorong di tengah', function () {
    $petak = TataKursi::petak('Bus 1', '2-2', 44);

    expect($petak['jumlah_kursi'])->toBe(44);
    expect($petak['kursi'])->toHaveCount(44);
    expect($petak['baris'])->toHaveCount(11);
    // Sel ketiga adalah lorong, bukan kursi.
    expect($petak['baris'][0])->toBe(['1', '2', null, '3', '4']);
});

test('kursi yang tidak habis dibagi jadi bangku belakang', function () {
    $petak = TataKursi::petak('Bus 2', '2-2', 45);

    expect($petak['kursi'])->toHaveCount(45);
    expect($petak['baris'])->toHaveCount(12);
    // Baris terakhir: satu kursi sisa, tanpa lorong, lebarnya tetap 5.
    expect($petak['baris'][11])->toBe(['45', null, null, null, null]);
});

test('satu pemesanan duduk berurutan', function () {
    $peserta = [
        pbgPeserta('B1', '', 2, 0),
        pbgPeserta('A1', '', 1, 0),
        pbgPeserta('B2', '', 2, 1),
        pbgPeserta('A2', '', 1, 1),
    ];

    $hasil = PembagianKelompok::kursi($peserta, TataKursi::semua([
        ['nama' => 'Bus 1', 'tata' => '2-2', 'jumlah_kursi' => 8],
    ]));

    expect($hasil['isi']['1:0']['kursi'])->toBe('1');
    expect($hasil['isi']['1:1']['kursi'])->toBe('2');
    expect($hasil['isi']['2:0']['kursi'])->toBe('3');
    expect($hasil['isi']['2:1']['kursi'])->toBe('4');
});

test('kursi yang kurang dilaporkan sebagai sisa', function () {
    $peserta = collect(range(1, 6))
        ->map(fn ($n) => pbgPeserta("Orang {$n}", '', 1, $n))
        ->all();

    $hasil = PembagianKelompok::kursi($peserta, TataKursi::semua([
        ['nama' => 'Elf', 'tata' => '2-1', 'jumlah_kursi' => 4],
    ]));

    expect($hasil['isi'])->toHaveCount(4);
    expect($hasil['sisa'])->toHaveCount(2);
    expect(implode(' ', $hasil['catatan']))->toContain('Kursi kurang');
});

test('kamar campur buatan tangan ditandai, bukan dilarang', function () {
    $peringatan = PembagianKelompok::periksa([
        ['nama' => 'Budi', 'jenis_kelamin' => PembagianKelompok::LAKI, 'kamar' => '201', 'bus' => '', 'kursi' => ''],
        ['nama' => 'Siti', 'jenis_kelamin' => PembagianKelompok::PEREMPUAN, 'kamar' => '201', 'bus' => '', 'kursi' => ''],
    ]);

    expect(collect($peringatan)->pluck('jenis')->all())->toContain('kamar_campur');
    expect($peringatan[0]['pesan'])->toContain('pasangan');
});

test('dua orang di satu kursi selalu jadi peringatan', function () {
    $peringatan = PembagianKelompok::periksa([
        ['nama' => 'Budi', 'jenis_kelamin' => '', 'kamar' => '', 'bus' => 'Bus 1', 'kursi' => '5'],
        ['nama' => 'Agus', 'jenis_kelamin' => '', 'kamar' => '', 'bus' => 'Bus 1', 'kursi' => '5'],
    ]);

    expect(collect($peringatan)->pluck('jenis')->all())->toContain('kursi_kembar');
    expect($peringatan[0]['pesan'])->toContain('Budi, Agus');
});

test('kursi yang sama di bus berbeda bukan bentrok', function () {
    $peringatan = PembagianKelompok::periksa([
        ['nama' => 'Budi', 'jenis_kelamin' => '', 'kamar' => '', 'bus' => 'Bus 1', 'kursi' => '5'],
        ['nama' => 'Agus', 'jenis_kelamin' => '', 'kamar' => '', 'bus' => 'Bus 2', 'kursi' => '5'],
    ]);

    expect($peringatan)->toBe([]);
});
