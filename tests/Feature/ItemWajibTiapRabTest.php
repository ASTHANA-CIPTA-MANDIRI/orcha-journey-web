<?php

use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Support\Rab\BerkasRab;
use App\Support\Rab\HitungRab;
use Illuminate\Support\Facades\DB;

/**
 * Dokumentasi, P3K, banner, dan tour leader selalu ikut.
 *
 * Keempatnya memang selalu kami berikan, tetapi dulu bergantung pada admin
 * mengingat menambahkannya satu per satu. Yang lupa tidak ketahuan di layar —
 * RAB-nya tampak lengkap — dan baru terasa di lapangan saat banner harus
 * dicetak dengan uang yang tidak pernah dianggarkan.
 *
 * tests/Pest.php mengosongkan master harga bertanda `otomatis` dari DB uji
 * supaya ia tidak jadi latar di mana-mana. Berkas ini menanamnya kembali —
 * dengan menjalankan ulang migrasinya, bukan menulis ulang daftarnya — supaya
 * yang diuji benar-benar yang akan berjalan di produksi.
 */
function migrasiItemWajib(): object
{
    // Dipanggil langsung, bukan lewat `migrate --path`: migrasinya sudah
    // tercatat jalan saat RefreshDatabase menyiapkan DB, jadi artisan akan
    // menjawab "Nothing to migrate" dan tidak menanam apa pun.
    return require base_path('database/migrations/2026_11_04_090000_item_wajib_masuk_tiap_rab.php');
}

function tanamItemWajib(): void
{
    migrasiItemWajib()->up();
}

function kepalaWajib(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-wajib');

    return ['X-Orcha-Key' => 'kunci-uji-wajib', 'Accept' => 'application/json'];
}

function rabWajib(array $ubah = []): Rab
{
    return Rab::create(array_merge([
        'judul' => 'Private Trip Banyuwangi',
        'nama_pelanggan' => 'Rombongan Uji',
        'provinsi' => 'Jawa Timur',
        'jumlah_hari' => 3,
        'jumlah_malam' => 2,
        'jumlah_peserta' => 20,
        'margin_jenis' => 'persen',
        'margin_nilai' => 20,
    ], $ubah));
}

test('migrasi menanam empat item wajib dan semuanya bertanda otomatis', function () {
    tanamItemWajib();

    $wajib = MasterHarga::where('otomatis', true)->get();

    expect($wajib->pluck('nama')->sort()->values()->all())->toBe([
        'Banner Kegiatan', 'Dokumentasi Perjalanan', 'Perlengkapan P3K', 'Tour Leader Internal',
    ])
        // Tanpa provinsi: berlaku di perjalanan mana pun, bukan Jawa Timur saja.
        ->and($wajib->pluck('provinsi')->unique()->all())->toBe([null])
        ->and($wajib->every(fn ($m) => $m->aktif))->toBeTrue();
});

test('tour leader dipindah ke kategori pemandu, bukan ditinggal di operasional', function () {
    // Kategori `operasional` sengaja tidak punya kalimat di PDF penawaran —
    // supaya "Operasional Tim Internal" tidak bocor. Tour leader yang
    // tertinggal di sana ikut tertelan dan tidak pernah tercetak.
    MasterHarga::create([
        'kategori' => 'operasional', 'nama' => 'Tour Leader Internal',
        'satuan' => 'hari', 'harga' => 300_000, 'aktif' => true,
    ]);

    tanamItemWajib();

    $tl = MasterHarga::where('nama', 'Tour Leader Internal')->sole();

    expect($tl->kategori)->toBe('pemandu')
        ->and($tl->otomatis)->toBeTrue()
        // Harga yang sudah diisi admin tidak boleh ikut berubah.
        ->and((int) $tl->harga)->toBe(300_000);
});

test('migrasi tidak menggandakan dan tidak menimpa harga yang sudah diisi', function () {
    tanamItemWajib();

    MasterHarga::where('nama', 'Perlengkapan P3K')->update(['harga' => 175_000]);

    tanamItemWajib();
    tanamItemWajib();

    expect(MasterHarga::where('nama', 'Perlengkapan P3K')->count())->toBe(1)
        ->and((int) MasterHarga::where('nama', 'Perlengkapan P3K')->value('harga'))->toBe(175_000)
        ->and(MasterHarga::where('otomatis', true)->count())->toBe(4);
});

test('RAB baru lahir sudah membawa keempatnya', function () {
    tanamItemWajib();

    $rab = $this->postJson('/api/v1/rab', [
        'judul' => 'Private Trip Banyuwangi', 'nama_pelanggan' => 'Rombongan Uji',
        'whatsapp' => '081234567890', 'provinsi' => 'Jawa Timur',
        'jumlah_hari' => 3, 'jumlah_malam' => 2, 'jumlah_peserta' => 20,
        'margin_jenis' => 'persen', 'margin_nilai' => 20,
    ], kepalaWajib())->assertCreated()->json('data');

    expect(collect($rab['ringkasan']['baris'])->pluck('nama')->sort()->values()->all())->toBe([
        'Banner Kegiatan', 'Dokumentasi Perjalanan', 'Perlengkapan P3K', 'Tour Leader Internal',
    ]);
});

test('keempatnya tercetak di penawaran sebagai bahasa pelanggan', function () {
    tanamItemWajib();

    $rab = rabWajib();
    foreach (MasterHarga::where('otomatis', true)->get() as $m) {
        RabBiaya::create([
            'rab_id' => $rab->id, 'master_harga_id' => $m->id, 'kategori' => $m->kategori,
            'nama' => $m->nama, 'satuan' => $m->satuan, 'harga_satuan' => $m->harga, 'jumlah' => 1,
        ]);
    }

    [$view, $data] = BerkasRab::bahan($rab->fresh(), 'penawaran');
    $html = view($view, $data)->render();

    expect($html)->toContain('Dokumentasi perjalanan')
        ->and($html)->toContain('Perlengkapan P3K')
        ->and($html)->toContain('Banner kegiatan')
        ->and($html)->toContain('Tour leader &amp; pemandu')
        // Nama barisnya sendiri tetap tidak ikut: "Tour Leader Internal"
        // adalah istilah dapur kami.
        ->and($html)->not->toContain('Tour Leader Internal');
});

test('keempatnya ikut tercetak di RAB internal dengan nama aslinya', function () {
    tanamItemWajib();

    $rab = rabWajib();
    foreach (MasterHarga::where('otomatis', true)->get() as $m) {
        RabBiaya::create([
            'rab_id' => $rab->id, 'master_harga_id' => $m->id, 'kategori' => $m->kategori,
            'nama' => $m->nama, 'satuan' => $m->satuan, 'harga_satuan' => $m->harga, 'jumlah' => 1,
        ]);
    }

    [$view, $data] = BerkasRab::bahan($rab->fresh(), 'internal');
    $html = view($view, $data)->render();

    foreach (['Tour Leader Internal', 'Dokumentasi Perjalanan', 'Perlengkapan P3K', 'Banner Kegiatan'] as $nama) {
        expect($html)->toContain($nama);
    }
});

test('baris yang harganya masih nol disebut namanya, bukan didiamkan', function () {
    // Nol bukan kesalahan — gratis itu ada — tetapi penawaran yang
    // menjanjikan banner dengan harga jual yang tidak pernah menghitungnya
    // adalah kerugian yang tidak terlihat sampai uangnya kurang.
    $rab = rabWajib();
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'lainnya', 'nama' => 'Banner Kegiatan',
        'satuan' => 'rombongan', 'harga_satuan' => 0, 'jumlah' => 1]);
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'transportasi', 'nama' => 'Hiace',
        'satuan' => 'unit_hari', 'harga_satuan' => 1_400_000, 'kapasitas' => 14, 'jumlah' => 1]);

    expect(HitungRab::ringkas($rab->fresh())['harga_nol'])->toBe(['Banner Kegiatan']);
});

test('RAB yang seluruh harganya sudah terisi tidak memunculkan peringatan', function () {
    $rab = rabWajib();
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'lainnya', 'nama' => 'Banner Kegiatan',
        'satuan' => 'rombongan', 'harga_satuan' => 150_000, 'jumlah' => 1]);

    expect(HitungRab::ringkas($rab->fresh())['harga_nol'])->toBe([]);
});

test('tarik biaya memasukkan item wajib ke RAB lama, tanpa menggandakan', function () {
    // RAB yang dibuat SEBELUM item wajib ada tidak boleh tertinggal — dan
    // menekan tombolnya dua kali tidak boleh menambah baris kedua.
    $rab = rabWajib();
    tanamItemWajib();

    $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaWajib())->assertOk();
    $this->postJson("/api/v1/rab/{$rab->id}/tarik-biaya", [], kepalaWajib())->assertOk();

    expect(RabBiaya::where('rab_id', $rab->id)->pluck('nama')->sort()->values()->all())->toBe([
        'Banner Kegiatan', 'Dokumentasi Perjalanan', 'Perlengkapan P3K', 'Tour Leader Internal',
    ]);
});

test('migrasi bisa dibatalkan tanpa menghapus baris yang sudah jadi data admin', function () {
    tanamItemWajib();
    MasterHarga::where('nama', 'Banner Kegiatan')->update(['harga' => 200_000]);

    migrasiItemWajib()->down();

    $banner = MasterHarga::where('nama', 'Banner Kegiatan')->sole();

    expect($banner->otomatis)->toBeFalse()
        ->and((int) $banner->harga)->toBe(200_000)
        ->and(DB::table('tbl_master_harga')->count())->toBe(4);
});
