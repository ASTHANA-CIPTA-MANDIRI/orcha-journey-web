<?php

namespace App\Support;

/**
 * Keterangan surat salinan untuk pelanggan.
 *
 * Isi surat kantor dan surat pelanggan berbeda cukup jauh — judulnya,
 * langkah berikutnya, tombolnya, bahkan baris rincian yang boleh tampil.
 * Dikumpulkan di satu objek supaya penambahan berikutnya tidak terus
 * memanjangkan daftar parameter KirimPemberitahuan::kirim().
 */
class SalinanPelanggan
{
    /**
     * @param  string|null  $email  alamat pelanggan; kosong = salinannya tidak dikirim
     * @param  string  $judul  judul dari sudut pandang pelanggan
     * @param  string|null  $langkah  apa yang perlu dilakukan setelah ini
     * @param  array<string, string|null>|null  $rincian  bila salinannya perlu memuat lebih sedikit baris
     * @param  string|null  $tautan  tujuan tombol utama surat
     * @param  string|null  $labelTautan  tulisan pada tombolnya
     */
    public function __construct(
        public ?string $email,
        public string $judul,
        public ?string $langkah = null,
        public ?array $rincian = null,
        public ?string $tautan = null,
        public ?string $labelTautan = null,
    ) {}
}
