<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu paket, banyak tanggal keberangkatan.
 *
 * Sebelumnya paket hanya punya SATU tanggal. Open trip yang sama dijual untuk
 * tiga tanggal berarti tiga paket: tiga kali mengetik itinerary, tiga kartu di
 * katalog, dan tiga tempat yang harus diubah saat satu kegiatan bergeser.
 * Admin menyiasatinya dengan menulis ketiga tanggalnya di GAMBAR SAMPUL —
 * sehingga sistemnya sendiri tidak tahu apa-apa: pendaftar tidak bisa memilih,
 * dan kuota tidak bisa dihitung per tanggal.
 *
 * Kuota dan harga boleh KOSONG, artinya ikut paketnya. Diisi hanya bila
 * tanggal itu memang berbeda — akhir pekan lebih mahal, atau kursinya lebih
 * sedikit karena armadanya lain. Null, bukan menyalin angka paket: angka yang
 * disalin akan tertinggal diam-diam begitu harga paketnya diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_paket_keberangkatan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('travel_package_id')
                ->constrained('tbl_travel_package')
                ->cascadeOnDelete();

            $t->date('tanggal_berangkat');
            $t->date('tanggal_pulang')->nullable();

            // Kosong = ikut paketnya.
            $t->unsignedSmallInteger('kuota')->nullable();
            $t->unsignedBigInteger('price')->nullable();
            $t->unsignedBigInteger('original_price')->nullable();
            $t->unsignedBigInteger('harga_modal')->nullable();

            /*
             | Ditutup, bukan dihapus. Keberangkatan yang batal tetap dipegang
             | pendaftaran yang sudah masuk; menghapus barisnya membuat
             | pendaftaran itu kehilangan tanggalnya.
             */
            $t->boolean('aktif')->default(true);
            $t->string('catatan', 191)->nullable();

            $t->timestamps();

            $t->index(['travel_package_id', 'tanggal_berangkat']);
            $t->index(['aktif', 'tanggal_berangkat']);
        });

        /*
         | Tanggal yang sudah ada dipindahkan, bukan ditinggalkan.
         |
         | Kolom lama di tbl_travel_package SENGAJA dibiarkan: ia masih dibaca
         | belasan tempat (kartu katalog, PDF, pendaftaran), dan mencabutnya
         | dalam migrasi yang sama berarti satu rilis yang tidak bisa dibatalkan
         | setengah jalan. Yang di sini cuma menyalin.
         */
        DB::table('tbl_travel_package')
            ->whereNotNull('tanggal_berangkat')
            ->orderBy('id')
            ->chunkById(200, function ($paket) {
                $baris = [];

                foreach ($paket as $p) {
                    $baris[] = [
                        'travel_package_id' => $p->id,
                        'tanggal_berangkat' => $p->tanggal_berangkat,
                        'tanggal_pulang' => $p->tanggal_pulang,
                        // Null, supaya ia ikut paketnya dan tidak tertinggal
                        // saat harga atau kuota paketnya diubah.
                        'kuota' => null,
                        'price' => null,
                        'original_price' => null,
                        'harga_modal' => null,
                        'aktif' => true,
                        'catatan' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($baris !== []) {
                    DB::table('tbl_paket_keberangkatan')->insert($baris);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_paket_keberangkatan');
    }
};
