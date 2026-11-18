<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor dan modal untuk sewa kendaraan.
 *
 * Orcha belum punya kendaraan sendiri: begitu ada yang memesan, unitnya
 * dicarikan ke vendor rekanan. Kenyataan itu sudah tertulis di komentar
 * PenyewaanKendaraan::getPerluDicarikanAttribute(), tetapi tidak pernah sampai
 * ke basis data — tidak ada satu kolom pun yang menyebut vendor, dan tidak ada
 * satu kolom pun yang menyebut berapa yang dibayarkan kepadanya.
 *
 * Akibatnya dua pertanyaan yang paling sering ditanyakan tidak bisa dijawab
 * sistem sama sekali: "unit ini dari vendor siapa" dan "sewa ini untung
 * berapa". Paket wisata sudah punya disiplin itu lewat RAB + master harga;
 * sewa kendaraan tertinggal, padahal marginnya lebih tipis.
 *
 * MILIK SENDIRI DIBERI PENANDANYA SENDIRI, bukan disimpulkan dari vendor_id
 * yang kosong. Hari ini keempat belas unit di katalog semuanya milik vendor
 * dan vendor_id-nya belum terisi — kalau "kosong" diartikan "milik sendiri",
 * seluruh katalog langsung salah terbaca pada hari migrasi ini jalan. Nanti
 * saat Orcha benar-benar membeli unit, yang berubah cukup satu centang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $t) {
            // Vendor pemasok unit ini. Kosong = belum ditentukan.
            $t->unsignedBigInteger('vendor_id')->nullable()->after('uuid');
            $t->boolean('milik_sendiri')->default(false)->after('vendor_id');

            /*
             | Harga modal dibuat sebentuk dengan harga jualnya, satu lawan
             | satu. Tanpa itu, modal per jam harus ditebak dari modal harian
             | dengan pembagi karangan sendiri — dan tebakan itu masuk ke
             | angka untung yang dibaca sebagai fakta.
             |
             | Semua boleh kosong: kosong berarti BELUM DICATAT, bukan nol.
             | Bedanya penting, karena modal nol membuat untungnya terbaca
             | sebesar seluruh harga jual.
             */
            $t->unsignedBigInteger('modal_per_hari')->nullable()->after('price_per_day');
            $t->unsignedBigInteger('modal_luar_kota')->nullable()->after('harga_luar_kota');
            $t->unsignedBigInteger('modal_per_jam')->nullable()->after('harga_per_jam');
            $t->unsignedBigInteger('modal_12_jam')->nullable()->after('harga_12_jam');
            $t->unsignedBigInteger('modal_sopir')->nullable()->after('harga_sopir');

            $t->index('vendor_id', 'cars_vendor_idx');
            $t->foreign('vendor_id', 'cars_vendor_fk')
                ->references('id')->on('tbl_vendor')->nullOnDelete();
        });

        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $t) {
            /*
             | Vendor dan modal DISALIN ke pesanannya, tidak dibaca ulang dari
             | katalog. Harga vendor naik tahun depan; pesanan tahun ini tidak
             | boleh ikut berubah untungnya. Pola yang sama sudah dipakai
             | master harga RAB dan nama_kendaraan di tabel ini sendiri.
             |
             | vendor_id yang masih kosong juga berarti sesuatu: unitnya belum
             | dicarikan. Itu satu-satunya langkah di alur sewa yang paling
             | mungkin gagal, dan selama ini tidak terekam di mana pun.
             */
            $t->unsignedBigInteger('vendor_id')->nullable()->after('car_id');
            $t->unsignedBigInteger('modal')->nullable()->after('rincian_estimasi');
            $t->json('rincian_modal')->nullable()->after('modal');

            $t->index('vendor_id', 'sewa_vendor_idx');
            $t->foreign('vendor_id', 'sewa_vendor_fk')
                ->references('id')->on('tbl_vendor')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $t) {
            $t->dropForeign('sewa_vendor_fk');
            $t->dropIndex('sewa_vendor_idx');
            $t->dropColumn(['vendor_id', 'modal', 'rincian_modal']);
        });

        Schema::table('cars', function (Blueprint $t) {
            $t->dropForeign('cars_vendor_fk');
            $t->dropIndex('cars_vendor_idx');
            $t->dropColumn([
                'vendor_id', 'milik_sendiri', 'modal_per_hari', 'modal_luar_kota',
                'modal_per_jam', 'modal_12_jam', 'modal_sopir',
            ]);
        });
    }
};
