<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda bahwa satu termin sudah diingatkan.
 *
 * Ditaruh pada terminnya, bukan pada rencananya: satu rencana punya beberapa
 * jatuh tempo, dan penanda di tingkat rencana hanya bisa menjawab "sudah
 * pernah diingatkan" — bukan "sudah diingatkan untuk termin YANG INI".
 * Akibatnya pelanggan diingatkan sekali lalu tidak pernah lagi sampai lunas.
 *
 * Dua penanda, karena dua peristiwa yang berbeda pembacanya: satu untuk surat
 * ke pelanggan menjelang jatuh tempo, satu untuk laporan ke kantor sesudah
 * terlewat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_angsuran_termin', function (Blueprint $table) {
            $table->timestamp('diingatkan_pada')->nullable()->after('jatuh_tempo');
            $table->timestamp('dilaporkan_telat_pada')->nullable()->after('diingatkan_pada');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_angsuran_termin', function (Blueprint $table) {
            $table->dropColumn(['diingatkan_pada', 'dilaporkan_telat_pada']);
        });
    }
};
