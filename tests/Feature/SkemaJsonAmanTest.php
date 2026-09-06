<?php

use App\Models\Blog\Artikel;
use App\Models\PaketWisata\TravelPackage;
use App\Support\SkemaJson;

/**
 * Data terstruktur (JSON-LD) tidak boleh bisa keluar dari blok skripnya.
 *
 * Pernah bisa, dan terbukti dengan eksploitasi sungguhan: judul artikel berisi
 * "</script><script>alert(document.cookie)</script>" menutup blok JSON-LD, dan
 * sisanya dijalankan peramban sebagai skrip.
 *
 * Yang mengisi judul artikel dan nama paket adalah admin, jadi ini bukan jalan
 * masuk bagi orang asing. Tetapi satu akun staf yang jatuh cukup untuk
 * menjalankan skrip di peramban SETIAP pengunjung — dan yang dicuri di halaman
 * pembayaran bukan sekadar cookie.
 */
test('judul artikel tidak bisa menutup blok JSON-LD', function () {
    // Dibuat di sini, bukan lewat helper BlogTest: penjaga keamanan tidak
    // boleh diam-diam bergantung pada berkas uji lain yang bisa dihapus.
    $artikel = Artikel::create([
        'judul' => 'Trip </script><script>alert(document.cookie)</script> Bromo',
        'ringkasan' => 'Ringkasan uji.',
        'isi' => '<p>Isi uji.</p>',
        'kategori' => 'panduan',
        'penulis' => 'Tim Orcha',
        'status' => 'tayang',
        'terbit_pada' => now()->subDay(),
    ]);

    $html = $this->get(route('blog.detail', $artikel))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(document.cookie)</script>')
        // Tag penutupnya DISANDI, bukan dibuang: "<" jadi \u003C, sehingga
        // judulnya tetap utuh bagi Google dan yang hilang cuma kemampuannya
        // menutup tag.
        ->toContain('\u003C')
        ->toContain('script');
});

test('nama paket tidak bisa menutup blok JSON-LD', function () {
    // Jalur kedua, dan yang paling sering disunting: nama paket diketik ulang
    // tiap kali ada keberangkatan baru.
    $paket = TravelPackage::create([
        'name' => 'Open Trip </script><script>alert(1)</script> Bromo',
        'category' => 'open_trip',
        'price' => 500000,
        'minimal_peserta' => 6,
        'status' => 'aktif',
    ]);

    $html = $this->get(route('paket-detail', $paket->uuid))->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>');
});

test('penyandi skema menutup tag, kutip, dan ampersand', function () {
    /*
     | Diuji pada penyandinya sendiri, bukan cuma lewat halaman: halaman bisa
     | berubah, dan penjaga yang hanya melihat satu halaman tidak menjaga empat
     | halaman lain yang memakai penyandi yang sama.
     */
    $hasil = SkemaJson::tag(['name' => '</script> & "kutip" \'tunggal\'']);

    expect($hasil)->not->toContain('</script>')
        ->and($hasil)->not->toContain('"kutip"')
        ->and(json_decode($hasil, true)['name'])
        // Isinya tetap utuh setelah dibaca kembali — penyandian, bukan
        // pembuangan.
        ->toBe('</script> & "kutip" \'tunggal\'');
});

test('badan permintaan DOKU tetap disandi apa adanya', function () {
    /*
     | Penjaga terhadap perbaikan yang kebablasan.
     |
     | Tanda tangan DOKU dihitung atas rangkaian byte badan permintaan yang
     | PERSIS. Menyeret penyandi aman-HTML ke sana akan membuat tanda
     | tangannya tidak lagi cocok, dan seluruh pembayaran ditolak dengan galat
     | yang tidak menyebut sebabnya.
     */
    $sumber = file_get_contents(base_path('app/Services/DokuCheckout.php'));

    expect($sumber)->toContain('JSON_UNESCAPED_SLASHES')
        ->and($sumber)->not->toContain('SkemaJson');
});
