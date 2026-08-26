<?php

use App\Models\SewaKendaraan\BagianPemeriksaan;
use App\Support\Pemeriksaan;
use Illuminate\Database\Migrations\Migration;

/**
 * Menanam dua belas bagian yang selama ini dipatok di config.
 *
 * Ditanam lewat migrasi, bukan seeder: seeder harus diingat untuk dijalankan,
 * dan yang lupa menjalankannya mendapat ceklis serah terima kosong tanpa satu
 * pun pesan yang menjelaskan sebabnya. Migrasi jalan sendiri saat deploy.
 *
 * Kunci dan tarifnya disalin apa adanya, jadi kondisi unit dan lembar serah
 * terima yang sudah tersimpan tetap menunjuk ke baris yang benar — tidak ada
 * data lama yang perlu diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dijaga supaya tidak menimpa: bila tabelnya sudah diisi admin, migrasi
        // ini tidak punya urusan apa pun di sana.
        if (BagianPemeriksaan::query()->exists()) {
            return;
        }

        foreach (Pemeriksaan::dariConfig() as $bagian) {
            BagianPemeriksaan::create($bagian);
        }
    }

    public function down(): void
    {
        BagianPemeriksaan::whereIn('kunci', array_keys(config('orcha.pemeriksaan_kendaraan', [])))->delete();
    }
};
