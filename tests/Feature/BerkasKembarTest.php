<?php

/**
 * Salinan kembar buatan OneDrive tidak boleh ada di folder migrasi.
 *
 * `htdocs` berada di dalam folder sinkron OneDrive, yang rutin menggandakan
 * berkas jadi "Nama 2.php". Dampaknya tidak seragam, dan itu yang membuatnya
 * mahal:
 *
 *   - Kelas PHP kembar TIDAK pernah ter-autoload (PSR-4 mencocokkan nama
 *     berkas), jadi ia cuma mengotori hasil pencarian.
 *   - MIGRASI kembar IKUT dijalankan, karena Laravel memindai isi direktori.
 *     Migrasinya jalan dua kali dan seluruh suite mati dengan "table already
 *     exists" atau "duplicate column".
 *
 * Yang mahal bukan perbaikannya — memindahkan berkasnya selesai dalam sedetik
 * — melainkan gejalanya: 40 tes merah sekaligus dengan pesan yang menunjuk
 * skema basis data, sehingga yang membacanya mengira kode migrasinya yang
 * salah. Uji ini menukarnya dengan satu pesan yang menyebut penyebabnya.
 */
test('tidak ada migrasi kembar buatan onedrive', function () {
    $kembar = collect(glob(database_path('migrations/*')) ?: [])
        ->filter(fn ($jalur) => preg_match('/ \d+\.php$/', $jalur))
        ->map(fn ($jalur) => basename($jalur))
        ->values()
        ->all();

    expect($kembar)->toBe([], implode("\n", [
        'Ada migrasi kembar buatan OneDrive. Laravel menjalankan KEDUANYA.',
        'Pindahkan berkasnya keluar dari database/migrations, lalu jalankan tesnya lagi:',
        '',
        '  find database/migrations -name "* [0-9].php"',
        '',
    ]));
});
