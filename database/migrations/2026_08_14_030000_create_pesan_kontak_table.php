<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesan yang masuk lewat form di halaman Kontak.
     */
    public function up(): void
    {
        Schema::create('tbl_pesan_kontak', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 120);
            $table->string('whatsapp', 30);
            $table->string('email', 150)->nullable();
            $table->string('keperluan', 40);
            $table->text('pesan');
            $table->timestamp('dibaca_pada')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('dibaca_pada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_pesan_kontak');
    }
};
