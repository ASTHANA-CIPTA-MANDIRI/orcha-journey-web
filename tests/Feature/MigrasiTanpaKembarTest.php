<?php

/**
 * Tidak boleh ada berkas migrasi kembar di folder migrations.
 *
 * OneDrive membuat salinan bentrok bernama "…_nama 2.php" tanpa diminta. Di
 * folder uji ia cuma sampah, tetapi di folder MIGRASI ia merusak: Laravel
 * memuat setiap berkas .php di sana, jadi salinannya dijalankan sebagai
 * migrasi kedua yang isinya sama persis.
 *
 * Yang terlihat saat itu terjadi:
 *
 *     SQLSTATE[HY000]: duplicate column name: bukti_riwayat
 *
 * Seluruh suite tumbang — 70 uji sekaligus — dan pesannya tidak menyebut
 * "berkas kembar" sama sekali. Waktu habis untuk mencari bug di kode yang
 * sebenarnya baik-baik saja.
 *
 * .gitignore sudah menahannya agar tidak ikut ter-commit, dan itu terbukti
 * menyelamatkan produksi. Tetapi .gitignore tidak menghapusnya dari cakram,
 * jadi penjaga ini yang menerjemahkan gejalanya jadi sebab.
 */
test('tidak ada berkas migrasi kembar buatan OneDrive', function () {
    $kembar = collect(glob(database_path('migrations/*.php')))
        ->filter(fn ($f) => preg_match('/ \d+\.php$/', basename($f)))
        ->map(fn ($f) => basename($f))
        ->values()
        ->all();

    expect($kembar)->toBe([], "Berkas migrasi kembar ditemukan — hapus dari cakram:\n  ".implode("\n  ", $kembar));
});
