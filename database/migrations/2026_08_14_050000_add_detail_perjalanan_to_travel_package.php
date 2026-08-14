<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detail perjalanan yang sebelumnya hanya ada di poster: tanggal
     * keberangkatan yang sudah ditetapkan, titik jemput, minimal peserta,
     * daftar fasilitas, dan itinerary per hari.
     *
     * Tanggal dan titik jemput ditetapkan admin — peserta tidak memilih
     * sendiri saat mendaftar, hanya melihat apa yang sudah ditentukan.
     */
    public function up(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->date('tanggal_berangkat')->nullable()->after('duration');
            $table->date('tanggal_pulang')->nullable()->after('tanggal_berangkat');
            $table->string('titik_jemput', 191)->nullable()->after('tanggal_pulang');
            $table->unsignedSmallInteger('minimal_peserta')->default(1)->after('titik_jemput');
            $table->string('catatan_promo', 191)->nullable()->after('discount_percentage');
            $table->json('fasilitas')->nullable()->after('destination_list');
            $table->json('itinerary')->nullable()->after('fasilitas');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->dropColumn([
                'tanggal_berangkat',
                'tanggal_pulang',
                'titik_jemput',
                'minimal_peserta',
                'catatan_promo',
                'fasilitas',
                'itinerary',
            ]);
        });
    }
};
