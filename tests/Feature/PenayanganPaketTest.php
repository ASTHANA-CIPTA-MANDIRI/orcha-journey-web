<?php

use App\Models\PaketWisata\TravelPackage;
use Illuminate\Support\Facades\DB;

function buatPaket(array $ubah = []): TravelPackage
{
    return TravelPackage::create(array_merge([
        'name' => 'Open Trip Uji',
        'category' => 'open_trip',
        'price' => 1000000,
        'minimal_peserta' => 6,
    ], $ubah));
}

/* --------------------------- ATURAN TAYANG --------------------------- */

test('paket terbit tanpa jadwal langsung tayang', function () {
    $paket = buatPaket();

    expect($paket->sedang_tayang)->toBeTrue()
        ->and($paket->status_tayang_label)->toBe('Tayang');
});

test('draf dan arsip tidak pernah tayang', function (string $status, string $label) {
    $paket = buatPaket(['status' => $status]);

    expect($paket->sedang_tayang)->toBeFalse()
        ->and($paket->status_tayang_label)->toBe($label);
})->with([['draf', 'Draf'], ['arsip', 'Arsip']]);

test('paket terjadwal belum tayang sampai waktunya tiba', function () {
    $paket = buatPaket(['tayang_mulai' => now()->addDays(3)]);

    expect($paket->sedang_tayang)->toBeFalse()
        ->and($paket->status_tayang_label)->toBe('Terjadwal');

    // Begitu waktunya lewat, tayang sendiri tanpa ada yang menekan apa pun
    $this->travelTo(now()->addDays(4));

    expect($paket->fresh()->sedang_tayang)->toBeTrue();
});

test('paket berhenti tayang setelah batas waktunya', function () {
    $paket = buatPaket(['tayang_sampai' => now()->addDay()]);

    expect($paket->sedang_tayang)->toBeTrue();

    $this->travelTo(now()->addDays(2));

    expect($paket->fresh()->sedang_tayang)->toBeFalse()
        ->and($paket->fresh()->status_tayang_label)->toBe('Berakhir');
});

test('trip berhenti tayang begitu hari keberangkatan tiba', function () {
    $paket = buatPaket([
        'tanggal_berangkat' => now()->addDays(2)->toDateString(),
        'tanggal_pulang' => now()->addDays(4)->toDateString(),
    ]);

    expect($paket->sedang_tayang)->toBeTrue();

    // Sehari sebelum berangkat masih boleh mendaftar
    $this->travelTo(now()->addDay()->setTime(23, 0));
    expect($paket->fresh()->sedang_tayang)->toBeTrue();

    // Hari keberangkatan: pendaftaran tutup, paket tidak tampil lagi
    $this->travelTo(now()->addDays(2)->setTime(6, 0));
    expect($paket->fresh()->sedang_tayang)->toBeFalse()
        ->and($paket->fresh()->status_tayang_label)->toBe('Berakhir');
});

test('paket dengan tanggal contoh bisa dibebaskan dari berakhir otomatis', function () {
    $paket = buatPaket([
        'tanggal_berangkat' => now()->subMonth()->toDateString(),
        'berakhir_otomatis' => false,
    ]);

    expect($paket->sedang_tayang)->toBeTrue();
});

/* ------------------------- HALAMAN PUBLIK ------------------------- */

test('hanya paket tayang yang muncul di halaman publik', function () {
    $tampil = buatPaket(['name' => 'Trip Yang Tayang']);
    buatPaket(['name' => 'Trip Masih Draf', 'status' => 'draf']);
    buatPaket(['name' => 'Trip Belum Waktunya', 'tayang_mulai' => now()->addWeek()]);

    $this->get(route('paket-wisata'))
        ->assertOk()
        ->assertSee('Trip Yang Tayang')
        ->assertDontSee('Trip Masih Draf')
        ->assertDontSee('Trip Belum Waktunya');

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Trip Masih Draf');

    expect($tampil->sedang_tayang)->toBeTrue();
});

test('halaman detail paket yang belum tayang tidak bisa dibuka', function () {
    $draf = buatPaket(['status' => 'draf']);
    $tayang = buatPaket(['name' => 'Trip Tayang']);

    $this->get(route('paket-detail', $draf->uuid))->assertNotFound();
    $this->get(route('paket-detail', $tayang->uuid))->assertOk();
});

test('paket yang belum tayang tidak bisa dipilih di pendaftaran open trip', function () {
    buatPaket(['name' => 'Trip Draf', 'status' => 'draf']);
    buatPaket(['name' => 'Trip Siap']);

    $this->get(route('pendaftaran-open-trip'))
        ->assertOk()
        ->assertSee('Trip Siap')
        ->assertDontSee('Trip Draf');
});

test('lencana dan penyaring selalu sepakat, termasuk pada data yang baru dibuat', function () {
    foreach ([
        ['besok', now()->addDay(), true],
        ['hari ini', now(), false],
        ['kemarin', now()->subDay(), false],
    ] as [$kapan, $tanggal, $harusTayang]) {
        $paket = buatPaket([
            'name' => "Trip berangkat {$kapan}",
            'tanggal_berangkat' => $tanggal->toDateString(),
        ]);

        expect($paket->sedang_tayang)->toBe($harusTayang, "penyaring salah untuk {$kapan}")
            ->and($paket->status_tayang === 'tayang')->toBe($harusTayang, "lencana salah untuk {$kapan}");
    }
});

/* ---------------------------- DISKON DIPAJANG ---------------------------- */

test('persen hemat di kartu dan halaman detail selalu sama', function () {
    $paket = buatPaket([
        'name' => 'Open Trip Banyuwangi',
        'price' => 1430000,
        'original_price' => 1700000,
        'discount_percentage' => 16,
    ]);

    // Angka simpanan yang dipakai — admin boleh membulatkannya untuk promo
    expect($paket->diskon_tampil)->toBe(16)
        ->and($paket->hemat_rupiah)->toBe(270000);

    $daftar = $this->get(route('paket-wisata'))->assertOk();
    $detail = $this->get(route('paket-detail', $paket->uuid))->assertOk();

    // Keduanya menyebut angka yang sama persis. "15%" sengaja tidak diuji
    // lewat assertDontSee karena angka itu juga muncul di nilai CSS halaman.
    $daftar->assertSee('Hemat 16%');
    $detail->assertSee('>16%<', false);
});

test('persen dihitung sendiri bila simpanannya kosong', function () {
    $paket = buatPaket(['price' => 1430000, 'original_price' => 1700000, 'discount_percentage' => 0]);

    // 15,88% dibulatkan ke bawah
    expect($paket->diskon_tampil)->toBe(15);
});

test('harga jual tidak lebih murah berarti tidak ada lencana hemat', function () {
    $paket = buatPaket([
        'name' => 'Paket Tanpa Diskon',
        'price' => 500000,
        'original_price' => 400000,
        // Data lama sempat menyimpan persen ngawur seperti ini
        'discount_percentage' => 75,
    ]);

    expect($paket->ada_diskon)->toBeFalse()
        ->and($paket->diskon_tampil)->toBe(0);

    $this->get(route('paket-wisata'))
        ->assertOk()
        ->assertSee('Paket Tanpa Diskon')
        ->assertDontSee('Hemat 75%');
});

test('sedang_tayang menjawab sama persis dengan scopeTayang', function () {
    /*
     | sedang_tayang tidak lagi menembak query sendiri — ia menghitung dari
     | atribut yang sudah dimuat, sebab bentuk lamanya menghasilkan satu query
     | untuk TIAP baris yang dibaca.
     |
     | Konsekuensinya aturan yang sama tertulis di dua tempat. Uji ini yang
     | menahannya: kalau salah satu diubah tanpa yang lain, ia merah sebelum
     | sampai ke pengguna.
     */
    $keadaan = [
        ['terbit', null, null, false, null],
        ['draf', null, null, false, null],
        ['arsip', null, null, false, null],
        ['terbit', now()->addWeek(), null, false, null],          // dijadwalkan
        ['terbit', now()->subWeek(), null, false, null],           // sudah mulai
        ['terbit', null, now()->subDay(), false, null],            // sudah lewat
        ['terbit', null, null, true, now()->addWeek()],            // berangkat nanti
        ['terbit', null, null, true, now()],                       // berangkat hari ini
        ['terbit', null, null, true, now()->subDay()],             // sudah berangkat
    ];

    foreach ($keadaan as [$status, $mulai, $sampai, $otomatis, $berangkat]) {
        $paket = App\Models\PaketWisata\TravelPackage::create([
            'name' => 'Uji', 'category' => 'open_trip', 'price' => 100000,
            'status' => $status, 'tayang_mulai' => $mulai, 'tayang_sampai' => $sampai,
            'berakhir_otomatis' => $otomatis, 'tanggal_berangkat' => $berangkat,
        ]);

        $lewatScope = App\Models\PaketWisata\TravelPackage::whereKey($paket->id)->tayang()->exists();

        expect($paket->fresh()->sedang_tayang)->toBe(
            $lewatScope,
            "Beda jawaban untuk status=$status, otomatis=".var_export($otomatis, true)
        );
    }
});

test('sedang_tayang tidak menembak query tambahan', function () {
    $paket = collect(range(1, 5))->map(fn () => App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Uji', 'category' => 'open_trip', 'price' => 100000, 'status' => 'terbit',
    ]));

    $dimuat = App\Models\PaketWisata\TravelPackage::whereIn('id', $paket->pluck('id'))->get();

    DB::enableQueryLog();
    $dimuat->each(fn ($p) => $p->sedang_tayang);
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($n)->toBe(0);
});
