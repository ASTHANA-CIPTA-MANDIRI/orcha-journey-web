<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi penyewaan kendaraan dengan hal-hal yang menentukan uang.
 *
 * Selama ini yang tercatat hanya kapan sewanya MULAI. Kapan seharusnya
 * kembali dihitung ulang di kepala admin setiap kali ditanya, dan ketika
 * unit telat pulang tidak ada angka yang bisa ditunjukkan ke penyewa —
 * hanya perdebatan.
 *
 * Yang ditambahkan di sini semuanya menjawab pertanyaan yang muncul di
 * loket saat unit kembali:
 *
 *   Jam berapa seharusnya kembali?     → tanggal_selesai + jam_selesai
 *   Kapan benar-benar kembali?          → dikembalikan_pada
 *   Telat berapa lama, dendanya berapa? → denda_keterlambatan
 *   Ada lecet baru? Siapa penyebabnya?  → kondisi_awal vs kondisi_akhir
 *   Jaminan apa yang dititipkan?        → jaminan
 *
 * Kondisi kendaraan disimpan sebagai daftar per bagian, bukan satu kalimat
 * bebas: "baret di pintu kanan" yang ditulis saat serah terima harus bisa
 * dibandingkan apa adanya dengan catatan saat unit kembali. Kalimat bebas
 * tidak pernah bisa dibandingkan, dan di situlah sengketa bermula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $table) {
            // Kapan seharusnya kembali — dihitung dari mulai + durasi, lalu
            // disimpan supaya tidak berubah sendiri saat tarif atau aturan
            // durasi diubah di kemudian hari.
            $table->date('tanggal_selesai')->nullable()->after('jam_mulai');
            $table->time('jam_selesai')->nullable()->after('tanggal_selesai');

            // Lokasi pengembalian sering berbeda dengan lokasi pengantaran.
            $table->string('lokasi_kembali')->nullable()->after('lokasi_antar');

            // Serah terima
            $table->timestamp('diserahkan_pada')->nullable()->after('estimasi_biaya');
            $table->timestamp('dikembalikan_pada')->nullable()->after('diserahkan_pada');
            $table->unsignedInteger('kilometer_awal')->nullable()->after('dikembalikan_pada');
            $table->unsignedInteger('kilometer_akhir')->nullable()->after('kilometer_awal');
            $table->string('bahan_bakar_awal', 20)->nullable()->after('kilometer_akhir');
            $table->string('bahan_bakar_akhir', 20)->nullable()->after('bahan_bakar_awal');

            // Pemeriksaan fisik: daftar per bagian, bentuknya sama di kedua sisi
            $table->json('kondisi_awal')->nullable()->after('bahan_bakar_akhir');
            $table->json('kondisi_akhir')->nullable()->after('kondisi_awal');

            // Jaminan yang dititipkan penyewa (KTP, SIM, motor, dan sejenisnya)
            $table->string('jaminan')->nullable()->after('kondisi_akhir');

            // Denda dipisah supaya nota akhirnya bisa menjelaskan asal angkanya
            $table->unsignedBigInteger('denda_keterlambatan')->default(0)->after('jaminan');
            $table->unsignedBigInteger('denda_kerusakan')->default(0)->after('denda_keterlambatan');
            $table->unsignedBigInteger('denda_lain')->default(0)->after('denda_kerusakan');
            $table->text('catatan_denda')->nullable()->after('denda_lain');
        });

        Schema::table('cars', function (Blueprint $table) {
            // Kerusakan yang sudah ada sebelum unit disewakan. Tanpa ini,
            // setiap lecet lama berpotensi ditagihkan ke penyewa berikutnya.
            $table->json('kondisi_terkini')->nullable()->after('is_available');
            $table->timestamp('kondisi_diperiksa_pada')->nullable()->after('kondisi_terkini');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $table) {
            $table->dropColumn([
                'tanggal_selesai', 'jam_selesai', 'lokasi_kembali',
                'diserahkan_pada', 'dikembalikan_pada',
                'kilometer_awal', 'kilometer_akhir',
                'bahan_bakar_awal', 'bahan_bakar_akhir',
                'kondisi_awal', 'kondisi_akhir', 'jaminan',
                'denda_keterlambatan', 'denda_kerusakan', 'denda_lain', 'catatan_denda',
            ]);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['kondisi_terkini', 'kondisi_diperiksa_pada']);
        });
    }
};
