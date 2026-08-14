<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tarif sewa tidak bisa diwakili satu angka per hari saja: sewa 6 jam,
     * 12 jam, dan 24 jam punya harga sendiri, dan sopir dihitung terpisah.
     *
     * Satu unit juga bisa tersedia dalam dua transmisi, jadi transmisi yang
     * tersedia disimpan sebagai daftar — bukan satu nilai tunggal.
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->decimal('harga_per_jam', 12, 0)->nullable()->after('price_per_day');
            $table->decimal('harga_12_jam', 12, 0)->nullable()->after('harga_per_jam');
            $table->decimal('harga_sopir', 12, 0)->nullable()->after('harga_12_jam');
            $table->json('transmisi_tersedia')->nullable()->after('transmission');
        });

        DB::table('cars')->whereNull('uuid')->orderBy('id')->each(function ($mobil) {
            DB::table('cars')->where('id', $mobil->id)->update([
                'uuid' => (string) Str::uuid(),
                'transmisi_tersedia' => json_encode([$mobil->transmission]),
            ]);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });

        Schema::create('tbl_penyewaan_kendaraan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->foreignId('car_id')->nullable();
            $table->string('nama_kendaraan', 191)->nullable();
            $table->string('nama', 120);
            $table->string('whatsapp', 30);
            $table->string('email', 150)->nullable();
            $table->string('transmisi', 20);
            $table->string('satuan', 10)->default('hari');
            $table->unsignedSmallInteger('durasi')->default(1);
            $table->date('tanggal_mulai');
            $table->time('jam_mulai')->nullable();
            $table->boolean('dengan_sopir')->default(true);
            $table->string('lokasi_antar', 191)->nullable();
            $table->decimal('estimasi_biaya', 12, 0)->nullable();
            $table->text('catatan')->nullable();
            $table->string('status', 20)->default('baru');
            $table->timestamps();

            $table->index('status');
            $table->index('tanggal_mulai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_penyewaan_kendaraan');

        Schema::table('cars', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'harga_per_jam', 'harga_12_jam', 'harga_sopir', 'transmisi_tersedia']);
        });
    }
};
