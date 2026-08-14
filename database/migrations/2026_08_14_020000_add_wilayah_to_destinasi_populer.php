<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Destinasi tidak lagi hanya seputar Yogyakarta, jadi tiap tempat perlu
     * keterangan wilayah (untuk tab filter) dan provinsi (untuk ditampilkan),
     * plus deskripsi singkat di kartu destinasi.
     */
    public function up(): void
    {
        Schema::table('tbl_destinasi_populer', function (Blueprint $table) {
            $table->string('wilayah', 20)->default('jawa')->after('destination_name')->index();
            $table->string('provinsi', 60)->nullable()->after('wilayah');
            $table->text('deskripsi')->nullable()->after('provinsi');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_destinasi_populer', function (Blueprint $table) {
            $table->dropIndex(['wilayah']);
            $table->dropColumn(['wilayah', 'provinsi', 'deskripsi']);
        });
    }
};
