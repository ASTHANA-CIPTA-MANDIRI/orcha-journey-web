<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * UUID dipakai sebagai kunci di alamat halaman publik (/paket/{uuid})
     * supaya nomor urut data tidak terekspos ke pengunjung. Panel admin tetap
     * memakai id numerik.
     *
     * `foto` menyimpan sampul paket, dipakai jadi latar hero halaman detail.
     */
    public function up(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('foto', 191)->nullable()->after('itinerary');
        });

        // Isi UUID untuk baris yang sudah ada lebih dulu
        DB::table('tbl_travel_package')->whereNull('uuid')->orderBy('id')->each(function ($paket) {
            DB::table('tbl_travel_package')->where('id', $paket->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'foto']);
        });
    }
};
