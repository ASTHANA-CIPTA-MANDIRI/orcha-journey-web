<?php

use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\PenandaAngsuran;
use App\Support\RencanaAngsuran;
use App\Support\TagihanPesanan;

/**
 * Jalur admin untuk memberikan angsuran.
 *
 * Yang dijaga di sini bukan tampilannya melainkan batasnya: layar boleh
 * dilewati, endpoint tidak. Batas termin bergantung pada tanggal berangkat,
 * dan tanggal itu bisa berubah antara layar dibuka dan tombol ditekan.
 */
function kepalaAngsuran(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-angsuran');

    return ['X-Orcha-Key' => 'kunci-uji-angsuran', 'Accept' => 'application/json'];
}

function pendaftaranAngsuran(int $hargaSatuan, int $peserta, int $berangkatHariLagi): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => 'Siti Aminah',
        'whatsapp' => '081298765432',
        'jumlah_peserta' => $peserta,
        'harga_jual' => $hargaSatuan,
        'nama_paket' => 'Study Tour Bromo',
        'tanggal_berangkat' => now()->addDays($berangkatHariLagi)->toDateString(),
    ])->fresh();
}

test('admin melihat pilihan berikut nominalnya', function () {
    /*
     | Nominalnya ikut dikirim untuk SETIAP pilihan. Menjanjikan "boleh 3x"
     | tanpa menyebut angkanya adalah janji yang tidak bisa dinilai orang yang
     | sedang menghitung kemampuannya — dan admin yang harus menjelaskannya
     | butuh angka itu di layarnya, bukan di kepalanya.
     */
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);

    $data = $this->getJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', kepalaAngsuran())
        ->assertOk()
        ->json('data');

    expect($data['boleh_diangsur'])->toBeTrue()
        ->and($data['maks_termin'])->toBe(3)
        // 2x dan 3x, keduanya lengkap dengan jadwalnya.
        ->and($data['pilihan'])->toHaveCount(2)
        ->and($data['pilihan'][1]['jumlah_termin'])->toBe(3)
        ->and($data['pilihan'][1]['termin'][0]['nominal'])->toBe(858_000)
        ->and($data['pilihan'][1]['termin'][0]['nominal_teks'])->toBe('Rp 858.000');
});

test('penolakan menyebutkan alasannya, bukan sekadar tidak boleh', function () {
    // Admin yang harus menjelaskan ke pelanggan butuh kalimatnya.
    $kecil = pendaftaranAngsuran(750_000, 2, 90);

    $data = $this->getJson('/api/v1/pendaftaran/'.$kecil->id.'/angsuran', kepalaAngsuran())
        ->assertOk()
        ->json('data');

    expect($data['boleh_diangsur'])->toBeFalse()
        ->and($data['alasan'])->toContain('di bawah ambang angsuran');

    $mepet = pendaftaranAngsuran(3_000_000, 4, 12);

    expect($this->getJson('/api/v1/pendaftaran/'.$mepet->id.'/angsuran', kepalaAngsuran())
        ->json('data.alasan'))->toContain('tidak cukup');
});

test('rencana tersimpan berikut jadwalnya', function () {
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', [
        'jumlah_termin' => 3,
        'catatan' => 'Diminta wali murid, dibayar per gajian.',
    ], kepalaAngsuran())->assertCreated();

    $rencana = Angsuran::aktifUntuk($pendaftaran->kode);

    expect($rencana)->not->toBeNull()
        ->and($rencana->jumlah_termin)->toBe(3)
        ->and($rencana->termin)->toHaveCount(3)
        ->and($rencana->termin->sum('nominal'))->toBe($pendaftaran->omzet);
});

test('jumlah termin di luar yang diizinkan ditolak endpoint, bukan hanya layar', function () {
    /*
     | Batasnya bergantung pada tanggal berangkat, dan tanggal itu bisa berubah
     | antara layar dibuka dan tombol ditekan. Layar yang menampilkan "boleh 3x"
     | lima menit lalu bukan alasan menerbitkan jadwal yang hari ini tidak muat.
     */
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran',
        ['jumlah_termin' => 5], kepalaAngsuran())
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'Maksimal 3 termin'));

    expect(Angsuran::count())->toBe(0);
});

test('rencana baru membatalkan yang lama, tidak menghapusnya', function () {
    /*
     | Saat ada sengketa, yang perlu dijawab adalah "dulu dijanjikan apa" —
     | dan baris yang hilang tidak menjawab apa pun.
     */
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);
    $kepala = kepalaAngsuran();

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', ['jumlah_termin' => 2], $kepala);
    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', ['jumlah_termin' => 3], $kepala);

    expect(Angsuran::count())->toBe(2)
        ->and(Angsuran::aktif()->count())->toBe(1)
        ->and(Angsuran::aktifUntuk($pendaftaran->kode)->jumlah_termin)->toBe(3);
});

test('membatalkan rencana tidak mengubah tagihannya', function () {
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);
    $kepala = kepalaAngsuran();

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', ['jumlah_termin' => 3], $kepala);

    $this->deleteJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', [], $kepala)
        ->assertOk()
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'Sisa tagihan kembali jatuh tempo'));

    expect(Angsuran::aktifUntuk($pendaftaran->kode))->toBeNull()
        // Uang dan tagihannya tidak tersentuh — yang dihapus cuma jadwalnya.
        ->and(TagihanPesanan::untuk($pendaftaran->fresh())['sisa'])->toBe(2_860_000);
});

test('tanpa kunci api, angsuran tidak bisa disentuh', function () {
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);

    $this->getJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', ['Accept' => 'application/json'])
        ->assertStatus(401);
});

test('pesanan lunas menyebut alasan yang sebenarnya', function () {
    /*
     | Pernah salah, dan salahnya jenis yang paling mahal: alasannya DIKARANG.
     |
     | maksTermin() menolak pesanan lunas lewat cabangnya sendiri, tetapi
     | alasannya jatuh ke pemeriksaan berikutnya — dan yang terbaca admin
     | adalah "waktu sampai tenggat tidak cukup" untuk pesanan yang
     | berangkatnya masih puluhan hari lagi. Yang membacanya mulai
     | membetulkan hal yang tidak rusak: menggeser tanggal, menghitung ulang
     | jadwal, bertanya ke rekan.
     */
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);

    KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'pelunasan', 'nominal' => 2_860_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    $data = $this->getJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', kepalaAngsuran())
        ->assertOk()
        ->json('data');

    expect($data['lunas'])->toBeTrue()
        ->and($data['boleh_diangsur'])->toBeFalse()
        ->and($data['alasan'])->toContain('sudah lunas')
        // Waktunya sebenarnya CUKUP — 90 hari lagi. Menyebut kalender di sini
        // adalah menuduh hal yang tidak bersalah.
        ->and($data['alasan'])->not->toContain('tidak cukup');
});

test('pesanan yang belum lunas tidak dikira lunas', function () {
    // Penjaga arah sebaliknya: kalau lunas diperiksa terlalu longgar, seluruh
    // pesanan berhenti bisa diangsur tanpa satu pun galat.
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);

    $data = $this->getJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', kepalaAngsuran())
        ->json('data');

    expect($data['lunas'])->toBeFalse()
        ->and($data['boleh_diangsur'])->toBeTrue()
        ->and($data['alasan'])->toBeNull();
});

/*
 |--------------------------------------------------------------------------
 | Penanda angsuran di daftar pendaftaran
 |--------------------------------------------------------------------------
 |
 | Yang membaca daftar sedang memilih siapa yang ditelepon hari ini. Pesanan
 | belum lunas pada H-20 berarti dua hal yang sangat berbeda: menunggak, atau
 | sedang menjalani jadwal yang kita sendiri berikan. Tanpa penanda ini
 | keduanya tergambar sama persis.
 */

test('daftar pendaftaran membawa penanda angsuran', function () {
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);
    $kepala = kepalaAngsuran();

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran',
        ['jumlah_termin' => 3], $kepala)->assertCreated();

    // Termin pertama tertutup, sisanya belum.
    KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 953_334,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    $baris = collect($this->getJson('/api/v1/pendaftaran', $kepala)->assertOk()->json('data'))
        ->firstWhere('kode', $pendaftaran->kode);

    expect($baris['angsuran'])->not->toBeNull()
        ->and($baris['angsuran']['jumlah_termin'])->toBe(3)
        ->and($baris['angsuran']['lunas'])->toBe(1)
        ->and($baris['angsuran']['telat'])->toBe(0)
        ->and($baris['angsuran']['selesai'])->toBeFalse()
        // Tanggal termin BERIKUTNYA, bukan termin pertama: yang sudah dibayar
        // tidak perlu ditagih lagi, dan menampilkan tanggalnya membuat admin
        // menelepon orang yang sudah membayar tepat waktu.
        ->and($baris['angsuran']['berikutnya'])->not->toBeNull();
});

test('pesanan tanpa rencana tidak diberi penanda', function () {
    // Penjaga arah sebaliknya. Penanda yang muncul di semua baris tidak
    // membedakan apa pun, dan admin berhenti mempercayainya dalam sehari.
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);

    $baris = collect($this->getJson('/api/v1/pendaftaran', kepalaAngsuran())->json('data'))
        ->firstWhere('kode', $pendaftaran->kode);

    expect($baris)->toHaveKey('angsuran')
        ->and($baris['angsuran'])->toBeNull();
});

test('rencana yang dibatalkan tidak lagi menandai barisnya', function () {
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);
    $kepala = kepalaAngsuran();

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', ['jumlah_termin' => 3], $kepala);
    $this->deleteJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', [], $kepala)->assertOk();

    $baris = collect($this->getJson('/api/v1/pendaftaran', $kepala)->json('data'))
        ->firstWhere('kode', $pendaftaran->kode);

    // Barisnya kembali biasa. Rencananya sendiri TIDAK hilang dari basis data
    // — yang berhenti cuma penandanya.
    expect($baris['angsuran'])->toBeNull()
        ->and(Angsuran::where('kode', $pendaftaran->kode)->count())->toBe(1);
});

test('termin yang lewat jatuh tempo ditandai telat di daftar', function () {
    /*
     | Ini satu-satunya keadaan di daftar yang menuntut tindakan hari ini.
     | Yang lain cuma kabar.
     */
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);
    $kepala = kepalaAngsuran();

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', ['jumlah_termin' => 3], $kepala);

    // Dua termin pertama dimundurkan ke belakang hari ini, tanpa satu pun
    // pembayaran masuk.
    Angsuran::aktifUntuk($pendaftaran->kode)->termin()
        ->whereIn('urutan', [1, 2])
        ->update(['jatuh_tempo' => now()->subDay()->toDateString()]);

    $baris = collect($this->getJson('/api/v1/pendaftaran', $kepala)->json('data'))
        ->firstWhere('kode', $pendaftaran->kode);

    expect($baris['angsuran']['telat'])->toBe(2)
        ->and($baris['angsuran']['lunas'])->toBe(0);
});

test('penanda daftar tidak pernah berbeda jawabannya dengan posisi()', function () {
    /*
     | PenandaAngsuran menyalin aturan lunas-per-termin dari
     | RencanaAngsuran::posisi() supaya daftar tidak perlu mengueri per baris.
     | Salinan yang tidak dijaga akan menyimpang, dan menyimpangnya diam:
     | daftar bilang satu hal, halaman detail bilang hal lain, dan yang salah
     | tidak bisa ditebak dari layar mana pun.
     */
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);
    $kepala = kepalaAngsuran();

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', ['jumlah_termin' => 3], $kepala);

    // Diuji pada beberapa posisi pembayaran, termasuk yang persis di batas
    // termin dan yang menggantung di tengahnya.
    foreach ([0, 953_334, 1_000_000, 1_906_667, 2_860_000] as $dibayar) {
        KonfirmasiPembayaran::where('kode', $pendaftaran->kode)->delete();

        if ($dibayar > 0) {
            KonfirmasiPembayaran::create([
                'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => $dibayar,
                'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
                'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
            ]);
        }

        $rencana = Angsuran::aktifUntuk($pendaftaran->kode);

        $menurutPosisi = collect(RencanaAngsuran::posisi($rencana));
        $penanda = PenandaAngsuran::untuk($rencana, $dibayar);

        expect($penanda['lunas'])->toBe($menurutPosisi->where('status', 'lunas')->count(), "dibayar {$dibayar}")
            ->and($penanda['telat'])->toBe($menurutPosisi->where('status', 'telat')->count(), "dibayar {$dibayar}");
    }
});

test('penanda angsuran tidak menambah kueri per baris', function () {
    /*
     | Penanda ini digambar sekali per baris. Membaca rencananya di dalam
     | perulangan berarti dua puluh kueri untuk satu halaman — jenis
     | perlambatan yang tidak pernah terlihat di data uji dan baru terasa di
     | basis data sungguhan.
     |
     | Yang diukur SELISIHNYA, bukan jumlah mutlaknya. Halaman ini sudah punya
     | biaya per baris dari tempat lain (accessor kesehatan), dan uji yang
     | mematok angka mutlak akan ikut merah saat biaya itu diperbaiki —
     | lalu diperlonggar oleh orang yang tidak tahu ia sedang menjaga apa.
     */
    $kepala = kepalaAngsuran();

    $hitung = function () use ($kepala) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/pendaftaran', $kepala)->assertOk();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    $daftar = collect(range(1, 6))->map(fn () => pendaftaranAngsuran(1_430_000, 2, 90));

    $tanpaRencana = $hitung();

    foreach ($daftar as $satu) {
        $this->postJson('/api/v1/pendaftaran/'.$satu->id.'/angsuran', ['jumlah_termin' => 3], $kepala);
    }

    // Enam baris berencana menambah DUA kueri, bukan dua belas: satu untuk
    // rencananya, satu untuk terminnya, keduanya untuk seluruh halaman.
    expect($hitung() - $tanpaRencana)->toBeLessThanOrEqual(2);
});

test('rencana yang seluruh terminnya lunas tidak bisa dibatalkan', function () {
    /*
     | Bukan sekadar tidak berguna — merusak.
     |
     | Yang tersisa dari rencana yang sudah tuntas cuma catatannya: bukti
     | bahwa pelanggan diberi keringanan, jadwalnya apa, dan ia
     | menyelesaikannya. Itu persis yang dicari saat belakangan ada yang
     | dipersoalkan, dan sekali ditandai batal ia tidak lagi terbaca sebagai
     | jadwal yang berjalan.
     |
     | Dijaga di ENDPOINT, bukan cuma dengan menyembunyikan tombolnya. Layar
     | boleh dilewati.
     */
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);
    $kepala = kepalaAngsuran();

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran',
        ['jumlah_termin' => 3], $kepala)->assertCreated();

    KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'pelunasan', 'nominal' => 2_860_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    $this->deleteJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', [], $kepala)
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'sudah selesai'));

    // Rencananya tetap utuh sebagai riwayat.
    expect(Angsuran::aktifUntuk($pendaftaran->kode))->not->toBeNull();
});

test('rencana yang masih berjalan tetap bisa dibatalkan', function () {
    // Penjaga arah sebaliknya: penjagaan di atas tidak boleh mengunci rencana
    // yang memang perlu dibatalkan karena keadaan pelanggan berubah.
    $pendaftaran = pendaftaranAngsuran(1_430_000, 2, 90);
    $kepala = kepalaAngsuran();

    $this->postJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran',
        ['jumlah_termin' => 3], $kepala)->assertCreated();

    // Baru uang mukanya yang masuk.
    KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 858_000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'diterima',
    ]);

    $this->deleteJson('/api/v1/pendaftaran/'.$pendaftaran->id.'/angsuran', [], $kepala)
        ->assertOk();

    expect(Angsuran::aktifUntuk($pendaftaran->kode))->toBeNull();
});
