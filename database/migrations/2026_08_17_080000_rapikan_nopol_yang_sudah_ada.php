<?php

use App\Models\SewaKendaraan\Car;
use App\Support\SewaKendaraan\NomorPolisi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Merapikan nomor polisi unit yang sudah tersimpan.
 *
 * Penormalannya dipasang sebagai mutator, jadi hanya berlaku saat menyimpan.
 * Baris yang sudah ada tetap memuat "ab-4169-te" sampai unitnya disunting —
 * dan selama itu daftar armada memuat campuran huruf besar-kecil, yang persis
 * keadaan yang hendak dihilangkan.
 *
 * Diproses per potongan, bukan sekali muat: tabel armada memang tidak besar,
 * tetapi migrasi yang memuat seluruh tabel ke memori adalah kebiasaan yang buruk
 * untuk ditiru pada tabel yang besar.
 *
 * Tidak ada down(): bentuk aslinya tidak disimpan, dan mengembalikan huruf
 * kecil secara serentak bukan pembalikan yang benar — hanya kerusakan baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Car::query()
            ->whereNotNull('nopol')
            ->chunkById(200, function ($daftar) {
                foreach ($daftar as $unit) {
                    $rapi = NomorPolisi::rapikan($unit->nopol);

                    if ($rapi !== $unit->nopol) {
                        // DB::table, BUKAN Car::query(): Eloquent tetap ikut
                        // memperbarui updated_at pada update massal, dan itu
                        // membuat seluruh armada tampak baru disunting pada hari
                        // deploy. Ini pembetulan bentuk, bukan perubahan datanya.
                        DB::table('cars')->where('id', $unit->getKey())->update(['nopol' => $rapi]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Sengaja dibiarkan kosong — lihat keterangan di atas.
    }
};
