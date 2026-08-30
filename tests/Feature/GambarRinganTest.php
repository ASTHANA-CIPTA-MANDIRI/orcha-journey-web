<?php

use App\Support\GambarWebp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Menjaga dua hal yang membuat halaman publik tetap ringan meski admin
 * mengunggah banyak foto:
 *
 *   1. Berkasnya kecil    — apa pun yang diunggah disimpan sebagai WebP.
 *   2. Tidak semua diunduh sekaligus — gambar di luar layar dimuat belakangan.
 *
 * Keduanya gampang jebol tanpa ketahuan: unggahan yang lolos konversi tetap
 * "berhasil" di layar admin, dan gambar tanpa lazy tetap tampil normal. Yang
 * berubah cuma bobot halaman, dan itu tidak pernah muncul sebagai galat.
 */

/* -------------------------------------------------------------------------
 | 1. Unggahan selalu jadi WebP
 | ---------------------------------------------------------------------- */

test('jpg dan png yang diunggah admin tersimpan sebagai webp', function (string $nama) {
    Storage::fake('public');

    $jalur = GambarWebp::simpan(UploadedFile::fake()->image($nama, 800, 600), 'uji');

    expect($jalur)->toEndWith('.webp')
        ->and($jalur)->toStartWith('/storage/uji/');

    $isi = Storage::disk('public')->get(str_replace('/storage/', '', $jalur));

    // Bukan sekadar berganti nama: isinya harus benar-benar WebP.
    expect(substr($isi, 0, 4))->toBe('RIFF')
        ->and(substr($isi, 8, 4))->toBe('WEBP');
})->with(['sampul.jpg', 'sampul.jpeg', 'logo.png']);

test('gambar yang terlalu besar dikecilkan ke 1920 px', function () {
    Storage::fake('public');

    $jalur = GambarWebp::simpan(UploadedFile::fake()->image('raksasa.jpg', 4000, 2500), 'uji');
    $isi = Storage::disk('public')->get(str_replace('/storage/', '', $jalur));

    [$lebar, $tinggi] = getimagesizefromstring($isi);

    expect($lebar)->toBe(1920)
        // Nisbahnya ikut terjaga, bukan dipaksa jadi kotak.
        ->and($tinggi)->toBe(1200);
});

/*
 | Penjaga kode sumber.
 |
 | Panel admin bawaan sedang dimatikan (404), jadi tidak bisa diuji lewat HTTP.
 | Padahal justru ia yang pernah lolos: seluruh jalur API sudah WebP sementara
 | komponen Volt-nya masih menyimpan berkas mentah, dan tidak ada satu pun uji
 | yang menyadarinya karena halamannya memang tidak bisa dibuka.
 |
 | Yang diperiksa: tidak ada lagi ->store() langsung atas berkas unggahan.
 | Berkas bukan-gambar (surat bertanda tangan) memang boleh mentah, jadi
 | pemeriksaannya dibatasi pada komponen Volt.
 */
test('tidak ada komponen yang menyimpan gambar tanpa lewat GambarWebp', function () {
    $pelanggar = [];

    foreach (\Symfony\Component\Finder\Finder::create()
        ->files()->in(resource_path('views/livewire'))->name('*.blade.php') as $berkas) {
        foreach (explode("\n", $berkas->getContents()) as $i => $baris) {
            if (preg_match('/->store(As)?\(/', $baris)) {
                $pelanggar[] = str_replace(resource_path('views/'), '', $berkas->getPathname()).':'.($i + 1);
            }
        }
    }

    expect($pelanggar)->toBe([], 'Simpan lewat GambarWebp::simpan() supaya jadi WebP: '.implode(', ', $pelanggar));
});

/* -------------------------------------------------------------------------
 | 2. Gambar di luar layar dimuat belakangan
 | ---------------------------------------------------------------------- */

/**
 * Ambil semua tag <img> dari sebuah berkas Blade.
 *
 * Ekspresi Blade ditutup dulu sebelum tagnya dicari: '>' pada '$dest->foto'
 * memotong tag di tengah kalau tidak, dan tag terpotong itu terlihat seperti
 * tidak punya loading= padahal punya.
 *
 * @return array<int, array{baris: int, tag: string}>
 */
function tagGambar(string $isi): array
{
    $bersih = preg_replace_callback('/\{\{.*?\}\}/s', fn ($m) => str_repeat("\0", strlen($m[0])), $isi);

    $hasil = [];
    preg_match_all('/<img\b[^>]*>/s', $bersih, $cocok, PREG_OFFSET_CAPTURE);

    foreach ($cocok[0] as [$_, $posisi]) {
        $hasil[] = [
            'baris' => substr_count(substr($isi, 0, $posisi), "\n") + 1,
            'tag' => substr($isi, $posisi, strlen($_)),
        ];
    }

    return $hasil;
}

test('setiap gambar di halaman publik menyatakan cara muatnya', function () {
    /*
     | Gambar yang di layar pertama TIDAK dibuat lazy — ia menyatakan
     | fetchpriority="high" sebagai gantinya. Jadi yang ditolak di sini bukan
     | "tanpa lazy", melainkan gambar yang DIAM: tanpa loading maupun
     | fetchpriority, sehingga tidak ada yang tahu itu keputusan atau kelalaian.
     |
     | Satu-satunya yang dilewati: piksel penanda statistik 1x1 milik Meta.
     | Ia bukan gambar yang dilihat siapa pun, dan cara memuatnya ditentukan
     | Meta, bukan kita. Dikenali dari ukurannya, bukan dari nomor baris —
     | nomor baris bergeser setiap kali tata letaknya disunting, dan
     | pengecualian yang menunjuk baris salah diam-diam melewatkan
     | gambar yang keliru.
     */
    $diam = [];

    foreach (\Symfony\Component\Finder\Finder::create()->files()
        ->in([
            resource_path('views/livewire/public'),
            resource_path('views/components'),
            resource_path('views/partials'),
        ])
        ->name('*.blade.php') as $berkas) {

        $nama = str_replace(resource_path('views/'), '', $berkas->getPathname());

        // Tata letak admin & auth tidak dilihat pengunjung.
        if (str_contains($nama, 'layouts/admin') || str_contains($nama, 'layouts/empty')
            || str_contains($nama, 'layouts/auth') || str_contains($nama, 'components/settings')) {
            continue;
        }

        foreach (tagGambar($berkas->getContents()) as $img) {
            // Piksel penanda 1x1, bukan gambar yang dilihat pengunjung.
            if (str_contains($img['tag'], 'width="1"') && str_contains($img['tag'], 'height="1"')) {
                continue;
            }

            if (! str_contains($img['tag'], 'loading=') && ! str_contains($img['tag'], 'fetchpriority=')) {
                $diam[] = $nama.':'.$img['baris'];
            }
        }
    }

    expect($diam)->toBe([], 'Beri loading="lazy" (atau fetchpriority="high" bila di layar pertama): '.implode(', ', $diam));
});

test('foto hero tidak pernah dibuat lazy', function () {
    // Menjadikannya lazy adalah "perbaikan" yang paling gampang terjadi saat
    // seseorang menyisir semua gambar sekaligus — padahal hasilnya kebalikannya.
    // Yang diperiksa tagnya, bukan seluruh berkas: komentar di atasnya justru
    // memuat kata "loading=lazy" untuk melarangnya.
    $tag = tagGambar(file_get_contents(resource_path('views/components/page-hero.blade.php')))[0]['tag'];

    expect($tag)->toContain('fetchpriority="high"')
        ->and($tag)->not->toContain('loading=');
});

test('kartu yang isinya diunggah admin dimuat belakangan', function (string $komponen) {
    // Kartu paket, armada, dan destinasi tampil berpuluh-puluh dalam satu
    // halaman. Justru di sinilah lazy paling menentukan bobot halaman.
    expect(file_get_contents(resource_path("views/components/$komponen")))
        ->toContain('loading="lazy"');
})->with(['paket-wisata/kartu.blade.php', 'sewa-kendaraan/kartu.blade.php']);
