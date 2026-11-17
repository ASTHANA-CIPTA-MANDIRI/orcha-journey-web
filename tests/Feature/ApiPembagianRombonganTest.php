<?php

/**
 * Jalur API pembagian kursi & kamar satu rombongan, dan jalur baru untuk
 * membetulkan serta memindahkan pendaftaran.
 */

use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\OpenTrip\RiwayatKesehatan;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\TravelPackage;
use Illuminate\Support\Facades\DB;

function pbgApiKepala(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-pembagian');

    return ['X-Orcha-Key' => 'kunci-uji-pembagian', 'Accept' => 'application/json'];
}

function pbgApiPaket(): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Trip Uji Pembagian',
        'category' => 'open_trip',
        'status' => 'terbit',
        'price' => 1_000_000,
        'tanggal_berangkat' => now()->addDays(30)->toDateString(),
    ]);
}

function pbgApiRombongan(TravelPackage $paket, ?int $kuota = 40): Keberangkatan
{
    return Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => now()->addDays(30)->toDateString(),
        'tanggal_pulang' => now()->addDays(32)->toDateString(),
        'kuota' => $kuota,
        'aktif' => true,
    ]);
}

function pbgApiDaftar(TravelPackage $paket, ?Keberangkatan $rombongan, array $peserta, string $nama = 'Pemesan'): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => $nama,
        'whatsapp' => '081200000000',
        'jumlah_peserta' => count($peserta),
        'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name,
        'keberangkatan_id' => $rombongan?->id,
        'tanggal_berangkat' => $rombongan?->tanggal_berangkat,
        'daftar_peserta' => $peserta,
    ])->fresh();
}

function pbgApiKesehatan(string $kode, string $nama, ?string $kelamin = null): void
{
    RiwayatKesehatan::create([
        'kode_pendaftaran' => $kode,
        'nama_peserta' => $nama,
        'usia' => 30,
        'jenis_kelamin' => $kelamin,
        'kontak_darurat_nama' => 'Kontak',
        'kontak_darurat_hp' => '08123456789',
        'kontak_darurat_hubungan' => 'Saudara',
        'setuju_data_kesehatan' => true,
    ]);
}

function pbgApiBayar(string $kode, string $status, int $nominal = 500_000): void
{
    KonfirmasiPembayaran::create([
        'kode' => $kode,
        'jenis' => 'dp',
        'nominal' => $nominal,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Pengirim',
        'status' => $status,
    ]);
}

test('pembagian memuat peserta dari semua pendaftaran satu rombongan', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);

    pbgApiDaftar($paket, $rombongan, [
        ['nama' => 'Budi', 'jenis_kelamin' => 'Laki-laki'],
        ['nama' => 'Siti', 'jenis_kelamin' => 'Perempuan'],
    ], 'Pemesan A');

    pbgApiDaftar($paket, $rombongan, [['nama' => 'Agus']], 'Pemesan B');

    $balasan = $this->withHeaders(pbgApiKepala())
        ->getJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian")
        ->assertOk();

    $peserta = $balasan->json('data.peserta');

    expect($peserta)->toHaveCount(3);
    expect(collect($peserta)->pluck('nama')->all())->toBe(['Budi', 'Siti', 'Agus']);
    expect($balasan->json('data.ringkasan.belum_jenis_kelamin'))->toBe(1);
    expect($balasan->json('data.ringkasan.laki'))->toBe(1);
});

test('pendaftaran yang batal tidak ikut dibagi', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);

    pbgApiDaftar($paket, $rombongan, [['nama' => 'Ikut']], 'Aktif');
    $batal = pbgApiDaftar($paket, $rombongan, [['nama' => 'Mundur']], 'Batal');
    $batal->update(['status' => 'batal']);

    $balasan = $this->withHeaders(pbgApiKepala())
        ->getJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian")
        ->assertOk();

    expect(collect($balasan->json('data.peserta'))->pluck('nama')->all())->toBe(['Ikut']);
});

test('jenis kelamin dari riwayat kesehatan dipakai mengisi awal dan ditandai', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $daftar = pbgApiDaftar($paket, $rombongan, [['nama' => 'Siti Aminah']]);

    pbgApiKesehatan($daftar->kode, 'siti aminah', 'Perempuan');

    $balasan = $this->withHeaders(pbgApiKepala())
        ->getJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian")
        ->assertOk();

    expect($balasan->json('data.peserta.0.jenis_kelamin'))->toBe('Perempuan');
    expect($balasan->json('data.peserta.0.kelamin_dari_kesehatan'))->toBeTrue();
});

test('kendaraan dan kamar bisa didaftarkan, dan petak kursinya ikut terbentuk', function () {
    $rombongan = pbgApiRombongan(pbgApiPaket());

    $balasan = $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/keberangkatan/{$rombongan->id}/fasilitas", [
            'kendaraan' => [['nama' => 'Bus 1', 'tata' => '2-2', 'jumlah_kursi' => 8]],
            'kamar' => [['nama' => '201', 'kapasitas' => 2], ['nama' => '202', 'kapasitas' => 2]],
        ])
        ->assertOk();

    expect($balasan->json('data.petak.0.baris.0'))->toBe(['1', '2', null, '3', '4']);
    expect($rombongan->fresh()->kamar)->toHaveCount(2);
});

test('nama kamar kembar ditolak', function () {
    $rombongan = pbgApiRombongan(pbgApiPaket());

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/keberangkatan/{$rombongan->id}/fasilitas", [
            'kendaraan' => [],
            'kamar' => [['nama' => '201', 'kapasitas' => 2], ['nama' => '201', 'kapasitas' => 2]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, 'dipakai dua kali'));
});

test('bagi otomatis memisahkan kamar laki-laki dan perempuan', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $rombongan->update([
        'kamar' => [
            ['nama' => '201', 'kapasitas' => 2],
            ['nama' => '202', 'kapasitas' => 2],
            ['nama' => '203', 'kapasitas' => 2],
        ],
    ]);

    $daftar = pbgApiDaftar($paket, $rombongan, [
        ['nama' => 'Budi', 'jenis_kelamin' => 'Laki-laki'],
        ['nama' => 'Siti', 'jenis_kelamin' => 'Perempuan'],
        ['nama' => 'Agus', 'jenis_kelamin' => 'Laki-laki'],
        ['nama' => 'Rina', 'jenis_kelamin' => 'Perempuan'],
    ]);

    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian/otomatis", ['apa' => 'kamar'])
        ->assertOk();

    $peserta = collect($daftar->fresh()->peserta)->keyBy('nama');

    expect($peserta['Budi']['kamar'])->toBe($peserta['Agus']['kamar']);
    expect($peserta['Siti']['kamar'])->toBe($peserta['Rina']['kamar']);
    expect($peserta['Budi']['kamar'])->not->toBe($peserta['Siti']['kamar']);
});

test('bagi otomatis tidak menimpa penempatan yang sudah dibuat tangan', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $rombongan->update(['kamar' => [
        ['nama' => '201', 'kapasitas' => 2],
        ['nama' => '202', 'kapasitas' => 2],
    ]]);

    $daftar = pbgApiDaftar($paket, $rombongan, [
        // Suami-istri yang sengaja disatukan admin di kamar 202.
        ['nama' => 'Pak Dedi', 'jenis_kelamin' => 'Laki-laki', 'kamar' => '202'],
        ['nama' => 'Bu Dedi', 'jenis_kelamin' => 'Perempuan', 'kamar' => '202'],
        ['nama' => 'Agus', 'jenis_kelamin' => 'Laki-laki'],
    ]);

    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian/otomatis", ['apa' => 'kamar'])
        ->assertOk();

    $peserta = collect($daftar->fresh()->peserta)->keyBy('nama');

    expect($peserta['Pak Dedi']['kamar'])->toBe('202');
    expect($peserta['Bu Dedi']['kamar'])->toBe('202');
    // Agus TIDAK boleh ikut masuk 202 yang sudah dipakai pasangan itu.
    expect($peserta['Agus']['kamar'])->toBe('201');
});

test('bagi otomatis kursi mengisi berurutan per pemesanan', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $rombongan->update(['kendaraan' => [['nama' => 'Bus 1', 'tata' => '2-2', 'jumlah_kursi' => 8]]]);

    $a = pbgApiDaftar($paket, $rombongan, [['nama' => 'A1'], ['nama' => 'A2']], 'Pemesan A');
    $b = pbgApiDaftar($paket, $rombongan, [['nama' => 'B1']], 'Pemesan B');

    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian/otomatis", ['apa' => 'kursi'])
        ->assertOk();

    expect(collect($a->fresh()->peserta)->pluck('kursi')->all())->toBe(['1', '2']);
    expect(collect($b->fresh()->peserta)->pluck('kursi')->all())->toBe(['3']);
    expect($b->fresh()->peserta[0]['bus'])->toBe('Bus 1');
});

test('penempatan buatan tangan bisa disimpan lewat satu jalur', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $daftar = pbgApiDaftar($paket, $rombongan, [['nama' => 'Budi'], ['nama' => 'Siti']]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian", [
            'peserta' => [
                ['kunci' => $daftar->id.':0', 'bus' => 'Bus 1', 'kursi' => '12', 'kamar' => '201', 'jenis_kelamin' => 'Laki-laki'],
                ['kunci' => $daftar->id.':1', 'bus' => 'Bus 1', 'kursi' => '13', 'kamar' => '202', 'jenis_kelamin' => 'Perempuan'],
            ],
        ])
        ->assertOk();

    $peserta = collect($daftar->fresh()->peserta)->keyBy('nama');

    expect($peserta['Budi']['kursi'])->toBe('12');
    expect($peserta['Budi']['jenis_kelamin'])->toBe('Laki-laki');
    expect($peserta['Siti']['kamar'])->toBe('202');
});

test('dua orang di satu kursi dilaporkan sebagai peringatan, bukan ditolak', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $daftar = pbgApiDaftar($paket, $rombongan, [['nama' => 'Budi'], ['nama' => 'Agus']]);

    $balasan = $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian", [
            'peserta' => [
                ['kunci' => $daftar->id.':0', 'bus' => 'Bus 1', 'kursi' => '5'],
                ['kunci' => $daftar->id.':1', 'bus' => 'Bus 1', 'kursi' => '5'],
            ],
        ])
        ->assertOk();

    expect(collect($balasan->json('data.peringatan'))->pluck('jenis')->all())
        ->toContain('kursi_kembar');
});

test('pendaftaran bisa dipindahkan ke rombongan lain', function () {
    $paket = pbgApiPaket();
    $asal = pbgApiRombongan($paket);
    $tujuan = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => now()->addDays(60)->toDateString(),
        'tanggal_pulang' => now()->addDays(62)->toDateString(),
        'kuota' => 40,
        'aktif' => true,
    ]);

    $daftar = pbgApiDaftar($paket, $asal, [['nama' => 'Budi']]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/pendaftaran/{$daftar->id}", ['keberangkatan_id' => $tujuan->id])
        ->assertOk()
        ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, 'Dipindahkan'));

    $segar = $daftar->fresh();

    expect($segar->keberangkatan_id)->toBe($tujuan->id);
    // tanggal_berangkat ikut bergeser — belasan tempat masih membacanya.
    expect($segar->tanggal_berangkat->toDateString())->toBe($tujuan->tanggal_berangkat->toDateString());
});

test('pindah ke tanggal milik paket lain ditolak', function () {
    $paketA = pbgApiPaket();
    $paketB = TravelPackage::create([
        'name' => 'Paket Lain', 'category' => 'open_trip', 'status' => 'terbit',
        'price' => 2_000_000, 'tanggal_berangkat' => now()->addDays(40)->toDateString(),
    ]);

    $daftar = pbgApiDaftar($paketA, pbgApiRombongan($paketA), [['nama' => 'Budi']]);
    $asing = pbgApiRombongan($paketB);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/pendaftaran/{$daftar->id}", ['keberangkatan_id' => $asing->id])
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, 'bukan milik paket'));
});

test('pindah ke rombongan penuh ditolak dulu, lalu bisa dipaksa', function () {
    $paket = pbgApiPaket();
    $asal = pbgApiRombongan($paket);
    $penuh = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => now()->addDays(50)->toDateString(),
        'kuota' => 2,
        'aktif' => true,
    ]);

    // Rombongan tujuan sudah terisi 2 dari 2.
    pbgApiDaftar($paket, $penuh, [['nama' => 'X'], ['nama' => 'Y']], 'Sudah di sana');

    $daftar = pbgApiDaftar($paket, $asal, [['nama' => 'Budi']]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/pendaftaran/{$daftar->id}", ['keberangkatan_id' => $penuh->id])
        ->assertStatus(422)
        ->assertJsonPath('butuh_paksa', true);

    expect($daftar->fresh()->keberangkatan_id)->toBe($asal->id);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/pendaftaran/{$daftar->id}", [
            'keberangkatan_id' => $penuh->id,
            'paksa' => true,
        ])
        ->assertOk();

    expect($daftar->fresh()->keberangkatan_id)->toBe($penuh->id);
});

test('rombongan tidak boleh dikosongkan', function () {
    $paket = pbgApiPaket();
    $daftar = pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'Budi']]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/pendaftaran/{$daftar->id}", ['keberangkatan_id' => null])
        ->assertStatus(422);
});

test('data pemesan dan jumlah peserta bisa dibetulkan', function () {
    $paket = pbgApiPaket();
    $daftar = pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'Budi']]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/pendaftaran/{$daftar->id}", [
            'nama' => 'Budi Santoso',
            'whatsapp' => '081299998888',
            'jumlah_peserta' => 3,
            'catatan_internal' => 'Sudah ditelepon 2x, minta ditelepon sore.',
        ])
        ->assertOk();

    $segar = $daftar->fresh();

    expect($segar->nama)->toBe('Budi Santoso');
    expect($segar->whatsapp)->toBe('081299998888');
    expect($segar->jumlah_peserta)->toBe(3);
    expect($segar->catatan_internal)->toContain('ditelepon sore');
});

test('medan yang tidak dikirim tidak ikut dikosongkan', function () {
    $paket = pbgApiPaket();
    $daftar = pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'Budi']]);
    $daftar->update(['catatan' => 'Minta kursi depan', 'email' => 'budi@contoh.test']);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/pendaftaran/{$daftar->id}", ['nama' => 'Budi S'])
        ->assertOk();

    $segar = $daftar->fresh();

    expect($segar->catatan)->toBe('Minta kursi depan');
    expect($segar->email)->toBe('budi@contoh.test');
});

test('jumlah peserta di bawah jumlah nama diberi catatan, bukan ditolak', function () {
    $paket = pbgApiPaket();
    $daftar = pbgApiDaftar($paket, pbgApiRombongan($paket), [
        ['nama' => 'A'], ['nama' => 'B'], ['nama' => 'C'],
    ]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/pendaftaran/{$daftar->id}", ['jumlah_peserta' => 2])
        ->assertOk()
        ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, '3 nama peserta terdaftar'));

    expect($daftar->fresh()->jumlah_peserta)->toBe(2);
    // Namanya TIDAK dibuang.
    expect($daftar->fresh()->peserta)->toHaveCount(3);
});

test('pendaftaran tanpa uang masuk bisa dihapus bersama riwayat kesehatannya', function () {
    $paket = pbgApiPaket();
    $daftar = pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'Budi']]);

    pbgApiKesehatan($daftar->kode, 'Budi');

    $this->withHeaders(pbgApiKepala())
        ->deleteJson("/api/v1/pendaftaran/{$daftar->id}")
        ->assertOk();

    expect(PendaftaranOpenTrip::find($daftar->id))->toBeNull();
    expect(RiwayatKesehatan::where('kode_pendaftaran', $daftar->kode)->count())->toBe(0);
});

test('pendaftaran yang uangnya sudah masuk tidak bisa dihapus', function () {
    $paket = pbgApiPaket();
    $daftar = pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'Budi']]);

    pbgApiBayar($daftar->kode, 'diterima');

    $this->withHeaders(pbgApiKepala())
        ->deleteJson("/api/v1/pendaftaran/{$daftar->id}")
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, 'tidak bisa dihapus'));

    expect(PendaftaranOpenTrip::find($daftar->id))->not->toBeNull();
});

test('bukti yang belum dicek juga menahan penghapusan', function () {
    $paket = pbgApiPaket();
    $daftar = pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'Budi']]);

    pbgApiBayar($daftar->kode, 'menunggu');

    $this->withHeaders(pbgApiKepala())
        ->deleteJson("/api/v1/pendaftaran/{$daftar->id}")
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, 'belum dicek'));
});

test('daftar bisa diurutkan, dan urutannya punya pemecah seri', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);

    pbgApiDaftar($paket, $rombongan, [['nama' => 'x']], 'Zulkifli');
    pbgApiDaftar($paket, $rombongan, [['nama' => 'y']], 'Ahmad');

    $balasan = $this->withHeaders(pbgApiKepala())
        ->getJson('/api/v1/pendaftaran?urut=nama')
        ->assertOk();

    expect(collect($balasan->json('data'))->pluck('nama')->all())->toBe(['Ahmad', 'Zulkifli']);

    $terbaru = $this->withHeaders(pbgApiKepala())
        ->getJson('/api/v1/pendaftaran?urut=terbaru')
        ->assertOk();

    expect(collect($terbaru->json('data'))->pluck('nama')->all())->toBe(['Ahmad', 'Zulkifli']);
});

test('kunci urut yang tidak dikenal diabaikan, bukan menjatuhkan daftar', function () {
    $paket = pbgApiPaket();
    pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'x']]);

    $this->withHeaders(pbgApiKepala())
        ->getJson('/api/v1/pendaftaran?urut=nama);DROP+TABLE')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('total tagihan tersimpan ikut terisi tiap pendaftaran disimpan', function () {
    /*
     | Kolomnya salinan omzet, supaya daftar bisa diurut menurut sisa tagihan
     | tanpa menulis ulang hitungannya sebagai SQL. Diisi kait saved pada
     | modelnya — bukan oleh pemanggilnya, supaya tidak ada jalan masuk yang
     | bisa lupa.
     */
    $paket = pbgApiPaket();
    $daftar = pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'Budi'], ['nama' => 'Siti']]);

    expect($daftar->fresh()->total_tagihan)->toBe(2_000_000);

    // Jumlah peserta berubah → tagihannya ikut, tanpa disentuh siapa pun.
    $daftar->update(['jumlah_peserta' => 3]);

    expect($daftar->fresh()->total_tagihan)->toBe(3_000_000);
});

test('daftar bisa diurut menurut sisa tagihan', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);

    // Dua orang, tagihan sama; yang satu sudah membayar separuh.
    $banyak = pbgApiDaftar($paket, $rombongan, [['nama' => 'a'], ['nama' => 'b']], 'Belum Bayar');
    $sedikit = pbgApiDaftar($paket, $rombongan, [['nama' => 'c'], ['nama' => 'd']], 'Sudah DP');
    pbgApiBayar($sedikit->kode, 'diterima', 1_500_000);

    $balasan = $this->withHeaders(pbgApiKepala())
        ->getJson('/api/v1/pendaftaran?urut=sisa_besar')
        ->assertOk();

    expect(collect($balasan->json('data'))->pluck('nama')->all())
        ->toBe(['Belum Bayar', 'Sudah DP']);

    $kecil = $this->withHeaders(pbgApiKepala())
        ->getJson('/api/v1/pendaftaran?urut=sisa_kecil')
        ->assertOk();

    expect(collect($kecil->json('data'))->pluck('nama')->all())
        ->toBe(['Sudah DP', 'Belum Bayar']);
});

test('bukti yang belum dicek tidak mengurangi sisa tagihan', function () {
    /*
     | Bukti yang belum dicek belum uang. Kalau ikut dihitung, orang yang baru
     | mengunggah gambar langsung turun ke dasar daftar tagihan — dan berhenti
     | ditelepon.
     */
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);

    $menunggu = pbgApiDaftar($paket, $rombongan, [['nama' => 'a'], ['nama' => 'b']], 'Bukti Menunggu');
    pbgApiBayar($menunggu->kode, 'menunggu', 1_900_000);
    $polos = pbgApiDaftar($paket, $rombongan, [['nama' => 'c']], 'Tanpa Bukti');

    $balasan = $this->withHeaders(pbgApiKepala())
        ->getJson('/api/v1/pendaftaran?urut=sisa_besar')
        ->assertOk();

    // Tagihan 2 jt yang buktinya belum dicek tetap di atas tagihan 1 jt.
    expect(collect($balasan->json('data'))->pluck('nama')->all())
        ->toBe(['Bukti Menunggu', 'Tanpa Bukti']);
});

test('pendaftaran yang belum dihitung tagihannya didorong ke belakang', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);

    $lama = pbgApiDaftar($paket, $rombongan, [['nama' => 'a']], 'Data Lama');
    // Meniru baris sebelum kolomnya ada: ditulis langsung, tanpa kait model.
    DB::table('tbl_pendaftaran_open_trip')
        ->where('id', $lama->id)->update(['total_tagihan' => null]);

    pbgApiDaftar($paket, $rombongan, [['nama' => 'b']], 'Data Baru');

    $balasan = $this->withHeaders(pbgApiKepala())
        ->getJson('/api/v1/pendaftaran?urut=sisa_besar')
        ->assertOk();

    expect(collect($balasan->json('data'))->pluck('nama')->last())->toBe('Data Lama');
});

/*
|--------------------------------------------------------------------------
| Gabung pendaftaran ganda
|--------------------------------------------------------------------------
*/

test('dua pendaftaran satu rombongan bisa digabungkan beserta pembayarannya', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);

    $tujuan = pbgApiDaftar($paket, $rombongan, [['nama' => 'Budi']], 'Budi Santoso');
    $sumber = pbgApiDaftar($paket, $rombongan, [['nama' => 'Siti'], ['nama' => 'Rina']], 'Budi Santoso');

    pbgApiBayar($sumber->kode, 'diterima', 750_000);
    pbgApiKesehatan($sumber->kode, 'Siti', 'Perempuan');

    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/pendaftaran/{$sumber->id}/gabungkan", ['ke' => $tujuan->id])
        ->assertOk()
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'Rp 750.000'));

    $segar = $tujuan->fresh();

    expect(PendaftaranOpenTrip::find($sumber->id))->toBeNull();
    expect(collect($segar->peserta)->pluck('nama')->all())->toBe(['Budi', 'Siti', 'Rina']);
    expect($segar->jumlah_peserta)->toBe(3);
    // Uangnya ikut, dan sekarang terbaca di bawah kode tujuan.
    expect((int) KonfirmasiPembayaran::where('kode', $segar->kode)->sum('nominal'))->toBe(750_000);
    expect(RiwayatKesehatan::where('kode_pendaftaran', $segar->kode)->count())->toBe(1);
    // Kode yang hilang dicatat, karena kwitansi yang dipegang pemesan menyebutnya.
    expect($segar->catatan_internal)->toContain($sumber->kode);
});

test('nama yang kembar tidak membuat pesertanya dihitung dua kali', function () {
    /*
     | Pemesan yang mengisi borang dua kali hampir selalu menuliskan dirinya
     | sendiri di keduanya. Tanpa pembuangan, rombongan gabungan menagihnya
     | untuk dirinya sendiri dua kali.
     */
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);

    $tujuan = pbgApiDaftar($paket, $rombongan, [['nama' => 'Budi Santoso']], 'Budi');
    $sumber = pbgApiDaftar($paket, $rombongan, [['nama' => 'budi santoso'], ['nama' => 'Siti']], 'Budi');

    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/pendaftaran/{$sumber->id}/gabungkan", ['ke' => $tujuan->id])
        ->assertOk();

    $segar = $tujuan->fresh();

    expect(collect($segar->peserta)->pluck('nama')->all())->toBe(['Budi Santoso', 'Siti']);
    expect($segar->jumlah_peserta)->toBe(2);
});

test('gabung ditolak kalau paket atau rombongannya berbeda', function () {
    $paket = pbgApiPaket();
    $satu = pbgApiRombongan($paket);
    $dua = Keberangkatan::create([
        'travel_package_id' => $paket->id,
        'tanggal_berangkat' => now()->addDays(60)->toDateString(),
        'kuota' => 40, 'aktif' => true,
    ]);

    $a = pbgApiDaftar($paket, $satu, [['nama' => 'A']]);
    $b = pbgApiDaftar($paket, $dua, [['nama' => 'B']]);

    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/pendaftaran/{$a->id}/gabungkan", ['ke' => $b->id])
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'bukan rombongan yang sama'));
});

test('gabung ditolak selama sumbernya punya rencana angsuran berjalan', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $tujuan = pbgApiDaftar($paket, $rombongan, [['nama' => 'A']]);
    $sumber = pbgApiDaftar($paket, $rombongan, [['nama' => 'B']]);

    Angsuran::create([
        'kode' => $sumber->kode, 'jumlah_termin' => 3, 'total' => 1_000_000,
    ]);

    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/pendaftaran/{$sumber->id}/gabungkan", ['ke' => $tujuan->id])
        ->assertStatus(422)
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'angsuran'));
});

/*
|--------------------------------------------------------------------------
| Keadaan massal
|--------------------------------------------------------------------------
*/

test('keadaan beberapa pendaftaran bisa diubah sekaligus', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $a = pbgApiDaftar($paket, $rombongan, [['nama' => 'A']]);
    $b = pbgApiDaftar($paket, $rombongan, [['nama' => 'B']]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson('/api/v1/pendaftaran/status-massal', [
            'id' => [$a->id, $b->id],
            'status' => 'dihubungi',
        ])
        ->assertOk();

    expect($a->fresh()->status)->toBe('dihubungi');
    expect($b->fresh()->status)->toBe('dihubungi');
});

test('membatalkan banyak sekaligus TIDAK diizinkan', function () {
    /*
     | Membatalkan melepas kursi dan mengabari daftar tunggu. Dua puluh
     | pembatalan yang dipicu satu salah klik tidak bisa dibatalkan, dan tidak
     | ada layar yang bisa menjelaskan apa yang baru saja terjadi.
     */
    $paket = pbgApiPaket();
    $a = pbgApiDaftar($paket, pbgApiRombongan($paket), [['nama' => 'A']]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson('/api/v1/pendaftaran/status-massal', ['id' => [$a->id], 'status' => 'batal'])
        ->assertStatus(422);

    expect($a->fresh()->status)->not->toBe('batal');
});

test('yang sudah batal dilewati, bukan dihidupkan lagi', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $hidup = pbgApiDaftar($paket, $rombongan, [['nama' => 'A']]);
    $batal = pbgApiDaftar($paket, $rombongan, [['nama' => 'B']]);
    $batal->update(['status' => 'batal']);

    $this->withHeaders(pbgApiKepala())
        ->patchJson('/api/v1/pendaftaran/status-massal', [
            'id' => [$hidup->id, $batal->id],
            'status' => 'dihubungi',
        ])
        ->assertOk()
        ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'dilewati'));

    expect($batal->fresh()->status)->toBe('batal');
});

/*
|--------------------------------------------------------------------------
| Riwayat pembagian
|--------------------------------------------------------------------------
*/

test('pembagian bisa dikembalikan ke keadaan sebelum bagi otomatis', function () {
    /*
     | Satu tekanan "bagi otomatis" dengan centang timpa menghapus satu jam
     | penyusunan tangan, dan hasilnya tetap berupa pembagian yang rapi.
     */
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $rombongan->update(['kamar' => [
        ['nama' => '201', 'kapasitas' => 2], ['nama' => '202', 'kapasitas' => 2],
    ]]);

    $daftar = pbgApiDaftar($paket, $rombongan, [
        ['nama' => 'Pak Dedi', 'jenis_kelamin' => 'Laki-laki', 'kamar' => '202'],
        ['nama' => 'Bu Dedi', 'jenis_kelamin' => 'Perempuan', 'kamar' => '202'],
    ]);

    // Dibagi ulang MENIMPA: pasangan itu terpisah.
    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian/otomatis",
            ['apa' => 'kamar', 'timpa' => true])
        ->assertOk();

    $sesudah = collect($daftar->fresh()->peserta)->keyBy('nama');
    expect($sesudah['Pak Dedi']['kamar'])->not->toBe($sesudah['Bu Dedi']['kamar']);

    // Dikembalikan ke potret terakhir.
    $this->withHeaders(pbgApiKepala())
        ->postJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian/kembalikan", ['urutan' => 0])
        ->assertOk();

    $pulih = collect($daftar->fresh()->peserta)->keyBy('nama');
    expect($pulih['Pak Dedi']['kamar'])->toBe('202');
    expect($pulih['Bu Dedi']['kamar'])->toBe('202');
});

test('riwayat ikut dikirim dan menyebut sebab tiap potret', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $daftar = pbgApiDaftar($paket, $rombongan, [['nama' => 'Budi']]);

    $this->withHeaders(pbgApiKepala())
        ->patchJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian", [
            'peserta' => [['kunci' => $daftar->id.':0', 'bus' => 'Bus 1', 'kursi' => '3']],
        ])->assertOk();

    $balasan = $this->withHeaders(pbgApiKepala())
        ->getJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian")
        ->assertOk();

    expect($balasan->json('data.riwayat'))->toHaveCount(1);
    expect($balasan->json('data.riwayat.0.sebab'))->toBe('diatur tangan');
    // Isinya TIDAK ikut: sepuluh salinan rombongan yang sama tidak pernah
    // dilihat siapa pun.
    expect($balasan->json('data.riwayat.0'))->not->toHaveKey('peserta');
});

test('riwayat hanya menyimpan sepuluh potret terakhir', function () {
    $paket = pbgApiPaket();
    $rombongan = pbgApiRombongan($paket);
    $daftar = pbgApiDaftar($paket, $rombongan, [['nama' => 'Budi']]);

    foreach (range(1, 12) as $n) {
        $this->withHeaders(pbgApiKepala())
            ->patchJson("/api/v1/keberangkatan/{$rombongan->id}/pembagian", [
                'peserta' => [['kunci' => $daftar->id.':0', 'bus' => 'Bus 1', 'kursi' => (string) $n]],
            ])->assertOk();
    }

    expect($rombongan->fresh()->pembagian_riwayat)->toHaveCount(10);
});
