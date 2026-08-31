<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\JejakAudit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa mengubah apa, kapan.
 *
 * Sudah ada ApiController::catat() yang dipanggil 43 kali dari sebelas
 * controller — tetapi tujuannya berkas log, dan berkas log bukan tempatnya:
 * tidak terbaca tanpa akses server, berputar lalu terhapus, dan tidak bisa
 * disaring per pesanan. Yang bertanya "siapa yang mengubah nominal
 * pengembalian ini?" adalah orang keuangan, bukan pemegang kunci server.
 */
beforeEach(function () {
    // Kuncinya disetel di sini, bukan diambil dari .env: tanpa ini seluruh
    // permintaan dijawab 401 dan kegagalannya terbaca seolah rutenya salah.
    config()->set('orcha.api.kunci', 'kunci-uji-jejak');
});

function kepalaOrcha(string $admin = 'Rina Admin'): array
{
    return [
        'X-Orcha-Key' => 'kunci-uji-jejak',
        'X-Orcha-Admin' => $admin,
        'Accept' => 'application/json',
    ];
}

test('perubahan lewat api meninggalkan jejak berikut nama adminnya', function () {
    $destinasi = DestinationPopuler::create([
        'destination_name' => 'Kawah Ijen', 'wilayah' => 'jawa',
    ]);

    $this->withHeaders(kepalaOrcha('Rina Admin'))
        ->deleteJson('/api/v1/destinasi/'.$destinasi->id)
        ->assertOk();

    $jejak = JejakAudit::latest()->first();

    expect($jejak)->not->toBeNull()
        ->and($jejak->admin)->toBe('Rina Admin')
        ->and($jejak->ip)->not->toBeNull();
});

test('jejak menyimpan perpindahan status sebagai sebelum dan sesudah', function () {
    /*
     | Kunci 'dari' dan 'ke' yang sudah dipakai seluruh pemanggilan yang ada
     | dikenali apa adanya, sehingga keempat puluh tiga pemanggilan menghasilkan
     | jejak yang layak dibaca tanpa satu pun disentuh.
     */
    $permintaan = request();
    $permintaan->attributes->set('admin_pemanggil', 'Budi Admin');

    JejakAudit::catat($permintaan, 'ubah status pembayaran',
        'ubah status pembayaran (menunggu → diterima)', 'OT-3108-K7QMXV', 'menunggu', 'diterima');

    $jejak = JejakAudit::first();

    expect($jejak->sebelum)->toBe('menunggu')
        ->and($jejak->sesudah)->toBe('diterima')
        ->and($jejak->kode)->toBe('OT-3108-K7QMXV');
});

test('jejak bisa dicari lewat kode pesanan', function () {
    /*
     | Pertanyaannya hampir selalu "apa yang terjadi pada OT-3108-K7QMXV",
     | bukan "apa saja yang dilakukan admin Rina hari Selasa".
     */
    $permintaan = request();

    JejakAudit::catat($permintaan, 'a', 'satu', 'OT-3108-K7QMXV');
    JejakAudit::catat($permintaan, 'b', 'dua', 'SK-3108-B2MQXV');

    $this->withHeaders(kepalaOrcha())
        ->getJson('/api/v1/jejak-audit?cari=OT-3108')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kode', 'OT-3108-K7QMXV');
});

test('jejak bisa disaring per admin', function () {
    $permintaan = request();

    $permintaan->attributes->set('admin_pemanggil', 'Rina Admin');
    JejakAudit::catat($permintaan, 'a', 'satu');

    $permintaan->attributes->set('admin_pemanggil', 'Budi Admin');
    JejakAudit::catat($permintaan, 'b', 'dua');

    $this->withHeaders(kepalaOrcha())
        ->getJson('/api/v1/jejak-audit?admin='.urlencode('Budi Admin'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.admin', 'Budi Admin');
});

test('jejak audit tidak bisa dihapus lewat api', function () {
    /*
     | Catatan audit yang bisa dihapus lewat API bukan catatan audit — ia cuma
     | daftar yang kebetulan berisi kejadian, dan baris yang pertama dihapus
     | justru yang paling perlu dibaca.
     */
    JejakAudit::catat(request(), 'a', 'satu');

    $this->withHeaders(kepalaOrcha())
        ->deleteJson('/api/v1/jejak-audit/1')
        ->assertNotFound();

    expect(JejakAudit::count())->toBe(1);
});

test('gagalnya pencatatan tidak menggagalkan pekerjaan yang dicatat', function () {
    /*
     | Admin yang menyetujui pengembalian dana tidak boleh gagal hanya karena
     | tabel jejaknya bermasalah. Yang hilang satu baris catatan; yang
     | diselamatkan pekerjaan yang sebenarnya.
     */
    Schema::drop('tbl_jejak_audit');

    $destinasi = DestinationPopuler::create([
        'destination_name' => 'Kawah Ijen', 'wilayah' => 'jawa',
    ]);

    $this->withHeaders(kepalaOrcha())
        ->deleteJson('/api/v1/destinasi/'.$destinasi->id)
        ->assertOk();

    expect(DestinationPopuler::count())->toBe(0);
});

test('surat yang gagal terkirim tercatat di jejak audit', function () {
    /*
     | Sebelum ini kegagalannya cuma masuk berkas log, dan nilai balik false-nya
     | tidak pernah dibaca siapa pun. Saat SMTP mati: pendaftaran tetap
     | tersimpan, pelanggan tidak pernah menerima kode pesanannya, dan tidak ada
     | satu pun tanda di layar admin. Kodenya cuma ada di layar pelanggan saat
     | itu — tertutup tab, hilang.
     */
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP menolak sambungan'));

    config()->set('orcha.email_pemberitahuan', 'kantor@contoh.test');

    App\Support\KirimPemberitahuan::kirim(
        'Pendaftaran Open Trip Baru',
        'OT-3108-K7QMXV',
        ['Nama' => 'Budi Santoso'],
    );

    $jejak = App\Models\JejakAudit::where('aksi', 'surat gagal terkirim')->first();

    expect($jejak)->not->toBeNull()
        ->and($jejak->kode)->toBe('OT-3108-K7QMXV')
        ->and($jejak->ringkasan)->toContain('SMTP menolak sambungan')
        // Pelakunya Sistem: tidak ada admin yang bisa disalahkan untuk ini.
        ->and($jejak->admin)->toBe('Sistem');
});

test('alamat surel pelanggan tidak ikut tercatat di jejak', function () {
    /*
     | Jejak audit dibaca lebih banyak orang daripada yang perlu melihat alamat
     | surel pelanggan. Kode pesanannya sudah cukup untuk menemukan orangnya.
     */
    Mail::shouldReceive('to')->andThrow(new RuntimeException('gagal'));

    config()->set('orcha.email_pemberitahuan', 'kantor@contoh.test');

    App\Support\KirimPemberitahuan::kirim(
        'Uji', 'OT-3108-K7QMXV', [],
        pelanggan: new App\Support\SalinanPelanggan(
            email: 'rahasia@contoh.test',
            judul: 'Uji',
        ),
    );

    $semua = App\Models\JejakAudit::pluck('ringkasan')->implode(' ');

    expect($semua)->not->toContain('rahasia@contoh.test');
});
