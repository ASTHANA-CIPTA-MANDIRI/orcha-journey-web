<?php

use App\Models\JejakAudit;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\OpenTrip\RiwayatKesehatan;
use Illuminate\Support\Facades\Storage;

/**
 * Batas simpan data kesehatan, dan dua janji di Kebijakan Privasi.
 *
 * Formulir kesehatan menyimpan riwayat penyakit, riwayat operasi, alergi, obat
 * rutin, dan golongan darah. Bocor sekali, akibatnya melekat pada orangnya
 * seumur hidup — dan sebelum ini tidak ada batas apa pun.
 *
 * Halaman Kebijakan Privasi juga sudah menjanjikan hak atas salinan data dan
 * penghapusan data, tanpa satu pun mekanisme untuk memenuhinya.
 */
function pesananUji(string $berangkat = '-200 days'): PendaftaranOpenTrip
{
    $daftar = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'email' => 'budi@contoh.test',
        'jumlah_peserta' => 1,
        'nama_paket' => 'Open Trip Bromo',
        'tanggal_berangkat' => now()->modify($berangkat)->toDateString(),
    ]);

    RiwayatKesehatan::create([
        'kode_pendaftaran' => $daftar->kode,
        'nama_peserta' => 'Budi Santoso',
        'usia' => 30,
        'jenis_kelamin' => 'Laki-laki',
        'kemampuan_renang' => 'bisa',
        'kontak_darurat_nama' => 'Siti',
        'kontak_darurat_hp' => '081298765432',
        'setuju_data_kesehatan' => true,
        // Justru bagian inilah yang paling perlu punya batas simpan.
        'riwayat_penyakit' => 'Asma sejak kecil',
        'alergi' => 'Udang',
    ]);

    return $daftar;
}

/* --------------------------- BATAS SIMPAN --------------------------- */

test('data kesehatan yang tripnya sudah lama dihapus', function () {
    pesananUji('-200 days');

    $this->artisan('orcha:bersihkan-kesehatan')->assertSuccessful();

    expect(RiwayatKesehatan::count())->toBe(0);
});

test('data kesehatan trip yang belum berangkat tidak disentuh', function () {
    /*
     | Dihitung dari TANGGAL KEBERANGKATAN, bukan tanggal pengisian. Peserta
     | yang mengisi formulir tiga bulan sebelum berangkat tidak boleh datanya
     | terhapus justru sebelum tripnya jalan.
     */
    pesananUji('+30 days');

    $this->artisan('orcha:bersihkan-kesehatan')->assertSuccessful();

    expect(RiwayatKesehatan::count())->toBe(1);
});

test('data kesehatan trip yang baru selesai belum dihapus', function () {
    // Masih di dalam 90 hari: klaim asuransi dan pertanyaan susulan masih bisa
    // muncul.
    pesananUji('-10 days');

    $this->artisan('orcha:bersihkan-kesehatan')->assertSuccessful();

    expect(RiwayatKesehatan::count())->toBe(1);
});

test('penghapusan tercatat tanpa menyebut nama pesertanya', function () {
    /*
     | Mencatat siapa saja yang datanya dihapus akan menyalin sebagian data itu
     | ke tabel lain yang justru dibaca lebih banyak orang — dan penghapusan
     | yang meninggalkan salinan bukan penghapusan.
     */
    pesananUji('-200 days');

    $this->artisan('orcha:bersihkan-kesehatan')->assertSuccessful();

    $jejak = JejakAudit::where('aksi', 'hapus data kesehatan kedaluwarsa')->first();

    expect($jejak)->not->toBeNull()
        ->and($jejak->ringkasan)->toContain('1 data kesehatan')
        ->and($jejak->ringkasan)->not->toContain('Budi Santoso');
});

test('percobaan tidak menghapus apa pun', function () {
    pesananUji('-200 days');

    $this->artisan('orcha:bersihkan-kesehatan', ['--percobaan' => true])->assertSuccessful();

    expect(RiwayatKesehatan::count())->toBe(1);
});

/* ------------------------ SALINAN & PENGHAPUSAN ------------------------ */

test('salinan data pelanggan memuat seluruh tabelnya', function () {
    Storage::fake('local');

    $daftar = pesananUji();

    KonfirmasiPembayaran::create([
        'kode' => $daftar->kode, 'jenis' => 'dp', 'nominal' => 500000,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA', 'atas_nama_pengirim' => 'Budi Santoso',
    ]);

    $this->artisan('orcha:data-pelanggan', ['kode' => $daftar->kode])->assertSuccessful();

    $berkas = Storage::disk('local')->files();
    expect($berkas)->toHaveCount(1);

    $isi = json_decode(Storage::disk('local')->get($berkas[0]), true);

    expect($isi)->toHaveKeys(['pesanan', 'pembayaran', 'pembatalan', 'riwayat_kesehatan'])
        ->and($isi['riwayat_kesehatan'])->toHaveCount(1)
        ->and($isi['pembayaran'])->toHaveCount(1);
});

test('penghapusan membuang data pribadi tetapi mempertahankan angka pembukuan', function () {
    /*
     | Kebijakan privasi menyebutnya sendiri: "penghapusan data, sepanjang tidak
     | bertentangan dengan kewajiban pembukuan kami". Nilai transaksi dan
     | tanggalnya wajib disimpan; yang menjadikannya data pribadi adalah nama,
     | nomor, dan surelnya.
     */
    $daftar = pesananUji();

    $this->artisan('orcha:data-pelanggan', ['kode' => $daftar->kode, '--hapus' => true, '--paksa' => true])
        ->assertSuccessful();

    $sesudah = $daftar->fresh();

    expect(RiwayatKesehatan::count())->toBe(0)
        ->and($sesudah)->not->toBeNull()
        ->and($sesudah->nama)->toBe('[dihapus atas permintaan]')
        ->and($sesudah->email)->toBeNull()
        ->and($sesudah->whatsapp)->toBe('')
        // Barisnya masih ada — pembukuannya tidak ikut hilang.
        ->and($sesudah->jumlah_peserta)->toBe(1);
});

test('penghapusan tercatat di jejak audit', function () {
    $daftar = pesananUji();

    $this->artisan('orcha:data-pelanggan', ['kode' => $daftar->kode, '--hapus' => true, '--paksa' => true])
        ->assertSuccessful();

    expect(JejakAudit::where('aksi', 'hapus data pelanggan')->where('kode', $daftar->kode)->exists())
        ->toBeTrue();
});

test('kode yang tidak dikenal ditolak', function () {
    $this->artisan('orcha:data-pelanggan', ['kode' => 'OT-0000-XXXXXX'])->assertFailed();
});
