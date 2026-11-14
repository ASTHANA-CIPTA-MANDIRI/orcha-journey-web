<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran menyebut KEBERANGKATAN mana yang dipilihnya.
 *
 * `tanggal_berangkat` sudah lama ada di pendaftaran dan tetap dipertahankan —
 * ia yang dibaca surat, kwitansi, dan pengingat. Tetapi tanggal saja tidak
 * cukup untuk menghitung kuota: dua keberangkatan bisa dipindah tanggalnya,
 * dan pendaftaran yang hanya memegang tanggal kehilangan jejak rombongannya.
 *
 * nullOnDelete, bukan cascade: keberangkatan yang terlanjur dihapus tidak
 * boleh ikut menghapus pendaftaran yang sudah membayar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $t) {
            $t->foreignId('keberangkatan_id')->nullable()->after('travel_package_id')
                ->constrained('tbl_paket_keberangkatan')->nullOnDelete();
        });

        /*
         | Pendaftaran lama dijodohkan ke keberangkatan paketnya.
         |
         | Dicocokkan lewat TANGGALNYA bila ada, dan jatuh ke keberangkatan
         | satu-satunya milik paket itu bila tanggalnya kosong — sesudah
         | migrasi sebelumnya, tiap paket bertanggal memang baru punya satu.
         */
        DB::table('tbl_pendaftaran_open_trip')
            ->whereNotNull('travel_package_id')
            ->orderBy('id')
            ->chunkById(200, function ($daftar) {
                foreach ($daftar as $p) {
                    $cocok = DB::table('tbl_paket_keberangkatan')
                        ->where('travel_package_id', $p->travel_package_id)
                        ->when(
                            $p->tanggal_berangkat,
                            fn ($q) => $q->whereDate('tanggal_berangkat', $p->tanggal_berangkat)
                        )
                        ->orderBy('tanggal_berangkat')
                        ->first();

                    if ($cocok) {
                        DB::table('tbl_pendaftaran_open_trip')
                            ->where('id', $p->id)
                            ->update(['keberangkatan_id' => $cocok->id]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $t) {
            $t->dropForeign(['keberangkatan_id']);
            $t->dropColumn('keberangkatan_id');
        });
    }
};
