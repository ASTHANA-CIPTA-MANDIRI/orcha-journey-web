<?php

use App\Support\CadanganBasisData;
use App\Support\GoogleDrive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Cadangan basis data.
 *
 * Yang paling penting dari sebuah cadangan bukan bahwa ia dibuat, melainkan
 * bahwa ia bisa DIKEMBALIKAN. Cadangan yang gagal diimpor terlihat persis sama
 * dengan cadangan yang baik sampai hari ia dibutuhkan — dan pada hari itu
 * tidak ada lagi yang bisa dikerjakan.
 *
 * Karena itu yang diperiksa di sini isi berkasnya, bukan sekadar bahwa
 * berkasnya ada.
 */
function folderCadanganUji(): string
{
    $folder = storage_path('framework/testing/cadangan-'.uniqid());

    if (! is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    return $folder;
}

function isiCadangan(string $jalur): string
{
    return (string) gzdecode(file_get_contents($jalur));
}

test('cadangannya memuat skema dan isi tiap tabel', function () {
    DB::table('tbl_promo_rombongan')->delete();
    DB::table('tbl_promo_rombongan')->insert([
        'min_peserta' => 6, 'potongan_persen' => 5, 'gratis_orang' => 0,
        'label' => 'Ajak 5 rekan', 'aktif' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $isi = isiCadangan(CadanganBasisData::buat(folderCadanganUji()));

    expect($isi)->toContain('CREATE TABLE')
        ->and($isi)->toContain('tbl_promo_rombongan')
        ->and($isi)->toContain('INSERT INTO')
        ->and($isi)->toContain('Ajak 5 rekan');
});

test('pemeriksaan kunci asing dimatikan selama impor', function () {
    /*
     | Tabel dipulihkan berurutan abjad, bukan berurutan ketergantungan —
     | tanpa ini, tabel yang menunjuk tabel yang belum dibuat ditolak.
     |
     | Yang menemukannya nanti orang yang sedang memulihkan data setelah
     | kehilangan, yaitu saat paling buruk untuk menemukan apa pun.
     */
    $isi = isiCadangan(CadanganBasisData::buat(folderCadanganUji()));

    expect($isi)->toContain('SET FOREIGN_KEY_CHECKS = 0;')
        ->and($isi)->toContain('SET FOREIGN_KEY_CHECKS = 1;');
});

test('apostrof dan baris baru di data pelanggan tidak merusak berkasnya', function () {
    /*
     | Ini yang paling mungkin membuat cadangan gagal diimpor, dan kegagalannya
     | tidak terlihat sampai hari pemulihan.
     |
     | Isi basis data ini termasuk catatan yang diketik pelanggan — apostrof,
     | tanda kutip, baris baru, backslash. Melarikannya dengan addslashes
     | berarti menebak aturan MySQL; satu tebakan yang meleset menghasilkan
     | berkas yang ditolak di tengah impor.
     */
    DB::table('tbl_promo_rombongan')->delete();
    DB::table('tbl_promo_rombongan')->insert([
        'min_peserta' => 9, 'potongan_persen' => 5, 'gratis_orang' => 0,
        'label' => "O'Brien \"kutip\" \\ miring\nbaris baru",
        'aktif' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $isi = isiCadangan(CadanganBasisData::buat(folderCadanganUji()));

    // Apostrofnya harus dilarikan, bukan ditulis apa adanya di tengah kutipan.
    expect($isi)->not->toContain("VALUES (9, 5, 0, 'O'Brien");
    expect($isi)->toContain('INSERT INTO `tbl_promo_rombongan`');
});

test('nilai kosong ditulis NULL, bukan tanda kutip kosong', function () {
    /*
     | Bedanya nyata: kolom yang NULL dan kolom yang berisi teks kosong
     | berperilaku berbeda pada whereNull, pada penjumlahan, dan pada kolom
     | unik. Cadangan yang menukar keduanya memulihkan data yang MIRIP, bukan
     | data yang sama.
     */
    DB::table('tbl_promo_rombongan')->delete();
    DB::table('tbl_promo_rombongan')->insert([
        'min_peserta' => 9, 'potongan_persen' => 5, 'gratis_orang' => 0,
        'label' => 'Tanpa ajakan', 'ajakan' => null,
        'aktif' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $isi = isiCadangan(CadanganBasisData::buat(folderCadanganUji()));

    expect($isi)->toContain("'Tanpa ajakan', NULL");
});

test('berkasnya benar-benar gzip yang sah', function () {
    // gzopen yang gagal separuh jalan menghasilkan berkas yang ada tetapi
    // tidak bisa dibuka — dan ukurannya tetap terlihat masuk akal.
    $jalur = CadanganBasisData::buat(folderCadanganUji());

    expect(gzdecode(file_get_contents($jalur)))->toBeString()
        ->and(file_get_contents($jalur, false, null, 0, 2))->toBe("\x1f\x8b");
});

test('cadangan lama dibuang, yang terbaru disisakan', function () {
    $folder = folderCadanganUji();

    foreach (['orcha-2026-01-01-000000', 'orcha-2026-02-01-000000', 'orcha-2026-03-01-000000'] as $nama) {
        file_put_contents($folder.'/'.$nama.'.sql.gz', gzencode('uji'));
    }

    $dibuang = CadanganBasisData::rapikan($folder, 2);

    expect($dibuang)->toHaveCount(1)
        ->and($dibuang[0])->toContain('2026-01-01')
        // Yang terbaru harus selamat — membuang yang salah berarti membuang
        // satu-satunya cadangan yang masih menyerupai keadaan sekarang.
        ->and(file_exists($folder.'/orcha-2026-03-01-000000.sql.gz'))->toBeTrue();
});

test('berkas selain cadangan tidak ikut terbuang', function () {
    // Folder cadangan bisa berisi hal lain — catatan, berkas yang sengaja
    // disimpan seseorang. Menyapu isinya berarti perintah cadangan menghapus
    // barang orang tiap malam.
    $folder = folderCadanganUji();
    file_put_contents($folder.'/catatan-penting.txt', 'jangan dihapus');
    file_put_contents($folder.'/orcha-2026-01-01-000000.sql.gz', gzencode('uji'));

    CadanganBasisData::rapikan($folder, 0);

    expect(file_exists($folder.'/catatan-penting.txt'))->toBeTrue();
});

/* --------------------------- GOOGLE DRIVE --------------------------- */

test('drive dianggap belum siap saat kredensialnya kosong', function () {
    config()->set('orcha.drive.client_id', null);

    expect(GoogleDrive::siap())->toBeFalse();
});

test('unggahannya bersambung dan memakai refresh token', function () {
    /*
     | Sekali kirim menuntut seluruh berkas berada di memori, dan cadangan
     | basis data adalah justru berkas yang paling mungkin tidak muat.
     */
    config()->set('orcha.drive', [
        'client_id' => 'id-uji', 'client_secret' => 'rahasia-uji',
        'refresh_token' => 'segar-uji', 'folder_id' => 'folder-uji',
    ]);

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'akses-uji']),
        '*uploadType=resumable*' => Http::response('', 200, ['Location' => 'https://unggah.test/lanjut']),
        'unggah.test/*' => Http::response(['id' => 'berkas-123']),
    ]);

    $folder = folderCadanganUji();
    $jalur = $folder.'/orcha-2026-01-01-000000.sql.gz';
    file_put_contents($jalur, gzencode('isi cadangan'));

    expect(GoogleDrive::unggah($jalur))->toBe('berkas-123');

    Http::assertSent(fn ($p) => str_contains($p->url(), 'oauth2.googleapis.com')
        && $p['grant_type'] === 'refresh_token');

    Http::assertSent(fn ($p) => str_contains($p->url(), 'uploadType=resumable'));
});

test('kegagalan Drive dilemparkan, bukan ditelan diam-diam', function () {
    /*
     | Cadangan luar yang berhenti diam-diam adalah cadangan yang tidak ada.
     | Perintahnya menangkap ini dan melaporkan gagal supaya cron berkirim
     | surat kegagalan.
     */
    config()->set('orcha.drive', [
        'client_id' => 'id-uji', 'client_secret' => 'rahasia-uji',
        'refresh_token' => 'segar-uji', 'folder_id' => null,
    ]);

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $folder = folderCadanganUji();
    $jalur = $folder.'/orcha-2026-01-01-000000.sql.gz';
    file_put_contents($jalur, gzencode('isi'));

    expect(fn () => GoogleDrive::unggah($jalur))
        ->toThrow(RuntimeException::class, 'Google menolak refresh token');
});

test('perintahnya menyebutkan bahwa cadangan hanya di server saat Drive kosong', function () {
    /*
     | Bukan galat — Drive memang boleh tidak disiapkan. Tetapi disebutkan
     | setiap kali, supaya "sudah ada cadangan" tidak diam-diam berarti "hanya
     | di mesin yang sama dengan data aslinya".
     */
    config()->set('orcha.drive.client_id', null);

    $this->artisan('orcha:cadangan')
        ->expectsOutputToContain('mesin yang sama dengan data aslinya')
        ->assertSuccessful();
});

test('tabel milik basis data LAIN tidak ikut tercadangkan', function () {
    /*
     | Bug nyata, dan yang paling merusak dari seluruh berkas ini.
     |
     | Schema::getTables() tanpa argumen mengembalikan tabel dari SELURUH skema
     | yang bisa dilihat sambungannya. Di server, satu mesin MySQL menampung
     | Orcha dan aplikasi lain sekaligus — sehingga perintah cadangan mencoba
     | membaca tabel yang bukan miliknya, gagal di tabel pertama, dan tidak
     | menghasilkan cadangan sama sekali.
     |
     | Kegagalannya masih termasuk yang beruntung. Yang benar-benar berbahaya
     | kalau ia BERHASIL: berkas cadangan Orcha memuat isi basis data aplikasi
     | lain, dan berkas itu kita unggah ke Google Drive.
     |
     | Ditiru di sini dengan menempelkan basis data kedua — sqlite melaporkan
     | skema tempelan persis seperti MySQL melaporkan skema tetangga.
     */
    $skema = 'tetangga_'.uniqid();
    $tetangga = tempnam(sys_get_temp_dir(), 'tetangga-').'.sqlite';
    touch($tetangga);

    DB::statement("ATTACH DATABASE '".$tetangga."' AS ".$skema);
    DB::statement('CREATE TABLE '.$skema.'.rahasia_aplikasi_lain (id integer, isi text)');
    DB::statement('INSERT INTO '.$skema.".rahasia_aplikasi_lain VALUES (1, 'jangan sampai ikut')");

    try {
        $isi = isiCadangan(CadanganBasisData::buat(folderCadanganUji()));

        expect($isi)->not->toContain('rahasia_aplikasi_lain')
            ->and($isi)->not->toContain('jangan sampai ikut')
            // Tabel sendiri tetap ikut — saringannya menyaring tetangga, bukan
            // mengosongkan cadangannya.
            ->and($isi)->toContain('tbl_promo_rombongan');
    } finally {
        // Tidak di-DETACH: berkasnya masih terkunci oleh pembacaan barusan,
        // dan sambungan ujinya memang dibuang utuh setelah tes ini.
        @unlink($tetangga);
    }
});

test('tabel yang skemanya tidak terbaca menggagalkan cadangan, bukan dilewati', function () {
    /*
     | Melewatinya menghasilkan berkas yang ukurannya masuk akal, isinya rapi,
     | dan kehilangan satu tabel penuh. Cadangan seperti itu tidak bisa
     | dibedakan dari cadangan yang utuh sampai hari pemulihan — dan pada hari
     | itu tabel yang hilang tidak ada di mana pun lagi.
     |
     | Ditiru dengan tabel yang terdaftar di skema lain: namanya terbaca,
     | skemanya tidak.
     */
    /*
     | Nama skemanya dibuat unik.
     |
     | Uji sebelumnya sengaja TIDAK melepas tempelannya — berkasnya masih
     | terkunci pembacaan. Memakai nama yang sama di sini membuat tesnya lulus
     | sendirian dan gagal saat dijalankan berurutan, dan kegagalan yang
     | bergantung urutan jauh lebih lama dicari daripada diperbaiki.
     */
    $skema = 'tetangga_'.uniqid();
    $tetangga = tempnam(sys_get_temp_dir(), 'tetangga-').'.sqlite';
    touch($tetangga);

    DB::statement("ATTACH DATABASE '".$tetangga."' AS ".$skema);
    DB::statement('CREATE TABLE '.$skema.'.tak_terbaca (id integer)');

    try {
        // Saringan skemanya dilepas paksa lewat pemanggilan langsung pada
        // tabel yang bukan milik skema ini.
        $kelas = new ReflectionClass(CadanganBasisData::class);
        $tulis = $kelas->getMethod('tulisTabel');
        $tulis->setAccessible(true);

        $berkas = gzopen(folderCadanganUji().'/coba.sql.gz', 'wb');

        expect(fn () => $tulis->invoke(null, $berkas, 'tak_terbaca'))
            ->toThrow(RuntimeException::class, 'tidak terbaca');

        gzclose($berkas);
    } finally {
        @unlink($tetangga);
    }
});

test('perintahnya melaporkan GAGAL saat unggahan ke Drive gagal', function () {
    /*
     | Kode keluarnya harus bukan nol supaya cron mengirim surat kegagalan.
     |
     | Salinan servernya memang sudah jadi, jadi ini bukan kegagalan penuh —
     | tetapi diam di sini berarti cadangan LUAR berhenti berbulan-bulan tanpa
     | ada yang tahu, dan cadangan yang hanya ada di mesin yang sama dengan
     | data aslinya bukan cadangan.
     */
    config()->set('orcha.drive', [
        'client_id' => 'id-uji', 'client_secret' => 'rahasia-uji',
        'refresh_token' => 'segar-uji', 'folder_id' => null,
    ]);

    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->artisan('orcha:cadangan')
        ->expectsOutputToContain('Unggahan gagal')
        // Tetap menyebutkan bahwa salinan servernya ada — yang membaca surat
        // kegagalan perlu tahu apa yang MASIH dimilikinya.
        ->expectsOutputToContain('Salinan di server tetap ada')
        ->assertFailed();
});
