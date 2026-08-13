<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tbl_destinasi_populer', function (Blueprint $table) {
            $table->id();
            $table->string('destination_name');
            $table->bigInteger('total_visitor')->default(0);
            $table->string('main_photo')->nullable();
            $table->json('others_photo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_destinasi_populer');
    }
};
