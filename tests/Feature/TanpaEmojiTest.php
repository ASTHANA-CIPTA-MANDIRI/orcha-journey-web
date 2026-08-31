<?php

/**
 * Halaman publik tidak memakai emoji.
 *
 * Emoji di antarmuka membuat situsnya terbaca seperti dirakit cepat — dan
 * untuk jasa yang menerima uang muka jutaan rupiah, kesan itu langsung
 * memotong kepercayaan sebelum satu kalimat pun dibaca.
 *
 * Yang dipakai ikon: bentuknya ditentukan berkas ikon kita sendiri, jadi ia
 * sama di semua perangkat. Emoji digambar oleh fon sistem — bentuk, warna,
 * dan tebalnya berbeda antara Android, iPhone, dan Windows, sehingga tata
 * letak yang rapi di satu layar berantakan di layar lain.
 */
function berkasPublik(): array
{
    $semua = array_merge(
        glob(resource_path('views/livewire/public/*.blade.php')) ?: [],
        glob(resource_path('views/livewire/public/*/*.blade.php')) ?: [],
        glob(resource_path('views/components/*.blade.php')) ?: [],
        glob(resource_path('views/components/*/*.blade.php')) ?: [],
        glob(resource_path('views/partials/*.blade.php')) ?: [],
    );

    // Salinan kembar buatan OneDrive ("index.blade 2.php") tidak pernah
    // dirender Blade — melaporkannya hanya kebisingan.
    return array_values(array_filter($semua, fn ($f) => ! preg_match('/ \d+\.php$/', $f)));
}

/**
 * Isi berkas TANPA komentar Blade.
 *
 * Komentar {{-- --}} tidak pernah sampai ke layar, dan justru di situlah
 * lambang yang dilarang paling sering ditulis — untuk menerangkan bahwa ia
 * tidak boleh dipakai. Penjaga yang membaca komentar akan menyalahkan
 * penjelasan yang isinya benar, lalu dimatikan orang karena dianggap cerewet.
 */
function isiTerrender(string $berkas): string
{
    return preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($berkas));
}

test('tidak ada karakter emoji di halaman publik', function () {
    /*
     | Rentangnya sengaja TIDAK menyapu seluruh non-ASCII. Tipografi Indonesia
     | memakai tanda pisah (—), kutip lengkung, dan titik tengah sebagai
     | pemisah; menyalahkan semuanya akan membuat penjaga ini dimatikan orang,
     | bukan dipatuhi.
     */
    $pola = '/[\x{1F000}-\x{1FAFF}\x{1F1E6}-\x{1F1FF}\x{FE0F}]/u';

    $pelanggar = [];

    foreach (berkasPublik() as $berkas) {
        if (preg_match_all($pola, isiTerrender($berkas), $cocok)) {
            $pelanggar[] = basename($berkas).': '.implode(' ', array_unique($cocok[0]));
        }
    }

    expect($pelanggar)->toBe([], "Pakai ikon, bukan emoji:\n".implode("\n", $pelanggar));
});

test('lambang tidak ditulis sebagai entitas html', function () {
    /*
     | &#9733; (★) dan sejenisnya digambar fon sistem, jadi bentuk dan tebalnya
     | berubah antar perangkat — di sebagian ponsel bahkan dirender sebagai
     | emoji berwarna. Bintang penilaian sempat ditulis begitu di formulir
     | testimoni, berdampingan dengan bintang ikon di daftar ulasannya sendiri:
     | dua bintang berbeda bentuk di satu halaman.
     */
    $pelanggar = [];

    foreach (berkasPublik() as $berkas) {
        if (preg_match_all('/&#(?:x[0-9a-f]{4}|9\d{3}|1\d{4});/i', isiTerrender($berkas), $cocok)) {
            $pelanggar[] = basename($berkas).': '.implode(' ', array_unique($cocok[0]));
        }
    }

    expect($pelanggar)->toBe([], "Pakai komponen ikon, bukan entitas lambang:\n".implode("\n", $pelanggar));
});
