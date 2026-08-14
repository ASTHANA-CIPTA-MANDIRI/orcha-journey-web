<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Landing page memisahkan layanan jadi Open Trip / Private Trip / Study Tour
     * untuk paket, dan Mobil / HiAce / Bus untuk armada. Dua kolom ini yang
     * jadi sumber tab filter di landing page sekaligus dropdown di admin.
     */
    public function up(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->string('category', 30)->default('open_trip')->after('name')->index();
            $table->string('duration', 60)->nullable()->after('category');
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->string('type', 20)->default('mobil')->after('brand')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'duration']);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
