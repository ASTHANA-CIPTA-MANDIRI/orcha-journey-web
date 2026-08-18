<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daerah pada entri katalog destinasi yang ditambahkan admin.
 *
 * Katalog bawaan sudah membawa provinsi DAN daerah, supaya sekali pilih empat
 * isian terisi. Entri tambahan harus bisa membawa keduanya juga — kalau tidak,
 * nama yang ditulis admin sendiri selamanya cuma mengisi separuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('katalog_destinasi_tambahan', function (Blueprint $tabel) {
            $tabel->string('daerah', 100)->nullable()->after('provinsi');
        });
    }

    public function down(): void
    {
        Schema::table('katalog_destinasi_tambahan', function (Blueprint $tabel) {
            $tabel->dropColumn('daerah');
        });
    }
};
