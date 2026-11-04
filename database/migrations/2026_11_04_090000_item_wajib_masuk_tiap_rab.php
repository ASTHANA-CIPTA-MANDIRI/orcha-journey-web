<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dokumentasi, P3K, banner, dan tour leader selalu ikut di setiap RAB.
 *
 * Keempatnya memang selalu kami berikan, tetapi selama ini bergantung pada
 * admin mengingat menambahkannya satu per satu. Yang lupa tidak ketahuan di
 * layar — RAB-nya tampak lengkap — dan baru terasa di lapangan saat banner
 * harus dicetak dengan uang yang tidak pernah dianggarkan.
 *
 * Mekanismenya sudah ada sejak awal: master harga bertanda `otomatis` ikut
 * masuk ke setiap RAB baru, dan ikut tertarik saat "Tarik biaya" ditekan.
 * Tanda itu belum pernah dipakai satu pun baris. Migrasi ini mengisinya.
 *
 * TIGA ATURAN YANG DIPEGANG MIGRASI INI:
 *
 * 1. Harga yang sudah ada TIDAK PERNAH disentuh. Yang dibuat baru berharga
 *    nol supaya admin mengisinya sendiri — angka uang karangan jauh lebih
 *    berbahaya daripada kolom kosong yang terlihat.
 * 2. Bisa dijalankan berkali-kali tanpa menggandakan apa pun: dijodohkan
 *    dengan nama yang sudah ada, bukan asal membuat.
 * 3. "Tour Leader Internal" DIPINDAH dari kategori `operasional` ke
 *    `pemandu`. Bukan kerapian: kategori `operasional` sengaja tidak punya
 *    kalimat di PDF penawaran — supaya "Operasional Tim Internal" tidak
 *    bocor ke pelanggan — sehingga tour leader yang tertinggal di sana ikut
 *    tertelan dan tidak pernah tercetak sebagai yang sudah termasuk.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{nama: string, kategori: string, satuan: string, cari: string}>
     */
    private array $wajib = [
        ['nama' => 'Tour Leader Internal', 'kategori' => 'pemandu', 'satuan' => 'hari', 'cari' => 'tour leader internal'],
        ['nama' => 'Dokumentasi Perjalanan', 'kategori' => 'dokumentasi', 'satuan' => 'rombongan', 'cari' => 'dokumentasi perjalanan'],
        ['nama' => 'Perlengkapan P3K', 'kategori' => 'lainnya', 'satuan' => 'rombongan', 'cari' => 'perlengkapan p3k'],
        ['nama' => 'Banner Kegiatan', 'kategori' => 'lainnya', 'satuan' => 'rombongan', 'cari' => 'banner kegiatan'],
    ];

    public function up(): void
    {
        foreach ($this->wajib as $w) {
            $ada = DB::table('tbl_master_harga')
                ->whereRaw('LOWER(TRIM(nama)) = ?', [$w['cari']])
                ->first();

            if ($ada) {
                // Harga, vendor, dan catatannya milik admin — yang diubah
                // hanya dua hal yang memang keputusan sistem.
                DB::table('tbl_master_harga')->where('id', $ada->id)->update([
                    'kategori' => $w['kategori'],
                    'otomatis' => true,
                    'aktif' => true,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('tbl_master_harga')->insert([
                'kategori' => $w['kategori'],
                'nama' => $w['nama'],
                // Tanpa provinsi: berlaku di mana pun perjalanannya.
                'provinsi' => null,
                'satuan' => $w['satuan'],
                'harga' => 0,
                'otomatis' => true,
                'aktif' => true,
                'catatan' => 'Selalu ikut di setiap RAB. Harganya masih kosong — isi lewat layar Master Harga.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Tandanya dicabut, barisnya TIDAK dihapus: begitu admin mengisi
        // harganya, baris itu jadi datanya — bukan lagi milik migrasi ini.
        DB::table('tbl_master_harga')
            ->whereIn(DB::raw('LOWER(TRIM(nama))'), array_column($this->wajib, 'cari'))
            ->update(['otomatis' => false, 'updated_at' => now()]);
    }
};
