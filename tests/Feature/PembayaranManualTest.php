<?php

use App\Models\JejakAudit;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\TagihanPesanan;

/**
 * Pembayaran yang diterima admin sendiri, tanpa lewat formulir publik.
 *
 * Private trip dan study tour mentransfer lalu mengabari lewat WhatsApp,
 * kadang cuma dengan kalimat "sudah ditransfer ya" tanpa tangkapan layar. Yang
 * memastikan uangnya benar-benar masuk adalah admin yang membuka mutasi
 * rekening — dan sampai sekarang tidak ada satu pun tempat mencatat
 * pemeriksaan itu.
 *
 * Akibatnya uang yang sudah diterima tidak pernah tercatat: statusnya tertahan
 * di "Baru", formulir riwayat kesehatannya tetap tertutup, dan laporan
 * keuangan menyebut nol untuk rombongan yang sudah membayar penuh.
 */
function kepalaBayar(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-bayar');

    return ['X-Orcha-Key' => 'kunci-uji-bayar', 'Accept' => 'application/json'];
}

function rombonganBayar(int $orang = 10, int $harga = 500000): PendaftaranOpenTrip
{
    $paket = TravelPackage::create([
        'name' => 'Study Tour Uji', 'category' => 'study_tour', 'status' => 'terbit',
        'tanggal_berangkat' => now()->addDays(30)->toDateString(),
    ]);

    return PendaftaranOpenTrip::create([
        'nama' => 'Panitia', 'whatsapp' => '081234567890',
        'jumlah_peserta' => $orang, 'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name, 'harga_jual' => $harga, 'status' => 'baru',
    ])->fresh();
}

function isianBayar(array $ubah = []): array
{
    return array_merge([
        'nominal' => 1250000,
        'tanggal_transfer' => now()->subDay()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'SMA Negeri 3',
        'jenis' => 'dp',
    ], $ubah);
}

test('pembayaran yang dicatat admin langsung terhitung', function () {
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar(), kepalaBayar())
        ->assertCreated();

    $tagihan = TagihanPesanan::untuk($daftar->fresh(), hanyaDiterima: true);

    expect($tagihan['sudah'])->toBe(1250000)
        ->and($tagihan['sisa'])->toBe(5000000 - 1250000);
});

test('statusnya langsung diterima, bukan menunggu diperiksa', function () {
    /*
     | Bukti dari pelanggan menunggu diperiksa karena siapa pun bisa mengunggah
     | gambar. Yang dicatat di sini adalah HASIL pemeriksaan itu sendiri —
     | menaruhnya di antrean berarti admin menyetujui catatannya sendiri,
     | langkah yang tidak memeriksa apa pun dan hanya menunda uangnya tercatat.
     */
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar(), kepalaBayar());

    expect(KonfirmasiPembayaran::first()->status)->toBe('diterima');
});

test('status pendaftarannya ikut maju sendiri', function () {
    /*
     | Satu kejadian, satu langkah. Sebelumnya admin mengubah dua tempat untuk
     | satu kejadian, dan langkah kedua itu yang paling sering terlewat —
     | akibatnya daftar menunjukkan "Baru" untuk rombongan yang uangnya sudah
     | diterima seminggu lalu, dan formulir kesehatannya tetap tertutup.
     */
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar(), kepalaBayar());

    expect($daftar->fresh()->status)->toBe('dp_masuk');
});

test('pelunasan penuh membuat statusnya lunas', function () {
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran",
        isianBayar(['nominal' => 5000000, 'jenis' => 'pelunasan']), kepalaBayar());

    expect($daftar->fresh()->status)->toBe('lunas');
});

test('sekolah bisa mencicil berkali-kali lewat jalur ini', function () {
    // Komite mencairkan dana bertahap. Sistem menyebutnya "uang muka" dan
    // "pelunasan" yang cuma dua tahap; cicilan ketiga tetap harus terhitung.
    $daftar = rombonganBayar();

    foreach ([2000000, 1500000, 1500000] as $nominal) {
        $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran",
            isianBayar(['nominal' => $nominal]), kepalaBayar())->assertCreated();
    }

    $tagihan = TagihanPesanan::untuk($daftar->fresh(), hanyaDiterima: true);

    expect($tagihan['sudah'])->toBe(5000000)
        ->and($tagihan['lunas'])->toBeTrue()
        ->and($daftar->fresh()->status)->toBe('lunas');
});

test('dicatat sebagai catatan admin, bukan bukti pelanggan', function () {
    /*
     | Yang membaca daftar pembayaran setahun kemudian perlu bisa membedakan
     | keduanya: yang satu punya gambar yang bisa ditelusuri, yang satu
     | bersandar pada seseorang yang membuka mutasi rekening pada hari itu.
     | Tanpa penanda, keduanya terlihat sama persis dan pertanyaan "buktinya
     | mana?" tidak bisa dijawab.
     */
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar(), kepalaBayar());

    expect(KonfirmasiPembayaran::first()->catatan_admin)->toContain('Dicatat manual')
        ->and(KonfirmasiPembayaran::first()->bukti)->toBeNull();
});

test('pencatatannya masuk jejak audit', function () {
    // Ini catatan UANG yang tidak punya gambar untuk ditelusuri. Satu-satunya
    // yang bisa menjawab "siapa yang memasukkan ini" adalah jejaknya.
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar(), kepalaBayar());

    expect(JejakAudit::where('aksi', 'catat pembayaran manual')->exists())->toBeTrue();
});

test('tanggal transfer di masa depan ditolak', function () {
    // Transfer yang belum terjadi bukan transfer. Angka yang masuk lebih awal
    // membuat laporan bulan ini menyebut uang yang baru akan datang.
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran",
        isianBayar(['tanggal_transfer' => now()->addDays(3)->toDateString()]), kepalaBayar())
        ->assertStatus(422);
});

test('nominal nol atau minus ditolak', function () {
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran",
        isianBayar(['nominal' => 0]), kepalaBayar())->assertStatus(422);
});

test('bank dan atas nama pengirim wajib', function () {
    /*
     | Keduanya yang dipakai mencocokkan dengan mutasi rekening saat ada yang
     | mempersoalkan. Catatan uang tanpa asal-usulnya cuma angka yang harus
     | dipercaya.
     */
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran",
        isianBayar(['bank_pengirim' => '', 'atas_nama_pengirim' => '']), kepalaBayar())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_pengirim', 'atas_nama_pengirim']);
});

test('tanpa kunci API, jalurnya tertutup', function () {
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar(),
        ['Accept' => 'application/json'])->assertStatus(401);
});

/* ------------------------------ BUKTI ------------------------------ */

test('bukti transfer bisa ikut diunggah', function () {
    \Illuminate\Support\Facades\Storage::fake('rahasia');
    \Illuminate\Support\Facades\Storage::fake('public');

    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar([
        'bukti' => \Illuminate\Http\UploadedFile::fake()->image('mutasi.jpg'),
    ]), kepalaBayar())->assertCreated();

    expect(KonfirmasiPembayaran::first()->bukti)->not->toBeNull();
});

test('bukti BOLEH kosong — itu bedanya dengan formulir publik', function () {
    /*
     | Di formulir publik bukti wajib karena tanpa gambar tidak ada yang bisa
     | dicek. Di sini yang mencatat justru orang yang SUDAH mengecek — ia
     | menatap mutasi rekening, bukan menunggu dikirimi gambar. Dan sebagian
     | panitia memang cuma menulis "sudah ditransfer ya".
     |
     | Mewajibkannya berarti pembayaran yang nyata tidak bisa dicatat karena
     | kurang sebuah gambar — dan yang terjadi berikutnya bukan admin mengejar
     | gambarnya, melainkan pembayarannya tidak dicatat sama sekali.
     */
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar(), kepalaBayar())
        ->assertCreated();

    expect(KonfirmasiPembayaran::first()->bukti)->toBeNull();
});

test('buktinya masuk folder rahasia, bukan disk publik', function () {
    /*
     | Bukti transfer memuat nomor rekening dan nama orang. Yang tersimpan di
     | disk publik bisa dibuka siapa pun yang menebak alamatnya — dan alamatnya
     | tidak perlu ditebak kalau pernah bocor sekali.
     */
    \Illuminate\Support\Facades\Storage::fake('rahasia');
    \Illuminate\Support\Facades\Storage::fake('public');

    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar([
        'bukti' => \Illuminate\Http\UploadedFile::fake()->image('mutasi.jpg'),
    ]), kepalaBayar());

    $jalur = \App\Support\BerkasRahasia::relatif(KonfirmasiPembayaran::first()->bukti);

    \Illuminate\Support\Facades\Storage::disk('rahasia')->assertExists($jalur);
    \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($jalur);
});

test('berkas yang bukan gambar ditolak', function () {
    $daftar = rombonganBayar();

    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/pembayaran", isianBayar([
        'bukti' => \Illuminate\Http\UploadedFile::fake()->create('daftar.pdf', 100, 'application/pdf'),
    ]), kepalaBayar())->assertStatus(422);
});
