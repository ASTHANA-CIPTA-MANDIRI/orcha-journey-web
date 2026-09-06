<?php

namespace App\Http\Controllers\Api\PaketWisata;

use App\Http\Controllers\Api\ApiController;
use App\Models\PaketWisata\DaftarTunggu;
use App\Models\PaketWisata\TravelPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Peminat yang menunggu kursi terbuka.
 *
 * Sistem mengabari mereka sendiri saat kursi dilepas, tetapi admin tetap perlu
 * melihat daftarnya: untuk tahu seberapa besar permintaan yang tertahan pada
 * satu trip, dan untuk menghubungi yang tidak mencantumkan surel — nomor
 * WhatsApp yang wajib di formulir, bukan surelnya.
 */
class DaftarTungguController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = DaftarTunggu::query()
            ->with('paket:id,name,uuid')
            ->when($request->integer('paket_id'), fn ($q, $id) => $q->where('travel_package_id', $id))
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($w) => $w->where('nama', 'like', "%{$cari}%")->orWhere('whatsapp', 'like', "%{$cari}%")
            ))
            /*
             | Yang BELUM dikabari didahulukan, lalu yang paling lama menunggu.
             |
             | Urutan ini yang dipakai admin bekerja: yang di atas adalah orang
             | yang masih menunggu jawaban, bukan yang sudah selesai diurus.
             */
            ->orderByRaw('dikabari_pada IS NOT NULL')
            ->orderBy('created_at')
            ->paginate($this->perHalaman($request));

        /*
         | Metanya dirakit pembungkus bersama, bukan ditulis ulang di sini.
         |
         | Sebelumnya keempat kuncinya disalin tangan — persis yang dilarang
         | komentar di halamanDipeta(). Penomoran halaman di lemon membaca meta
         | itu apa adanya, jadi selisih sekecil apa pun antara dua bentuk
         | langsung terasa di layar admin, dan tidak ada galat yang
         | menunjukkannya.
         */
        return $this->halamanDipeta(
            $daftar,
            fn () => collect($daftar->items())->map(fn (DaftarTunggu $a) => [
                'id' => $a->id,
                'nama' => $a->nama,
                'whatsapp' => $a->whatsapp,
                'email' => $a->email,
                'jumlah_peserta' => $a->jumlah_peserta,
                'paket' => $a->paket?->name,
                'paket_id' => $a->travel_package_id,
                'menunggu_sejak' => $a->created_at?->toIso8601String(),
                'dikabari_pada' => $a->dikabari_pada?->toIso8601String(),
                'dihubungi_pada' => $a->dihubungi_pada?->toIso8601String(),
                'dihubungi_oleh' => $a->dihubungi_oleh,
            ])->all(),
            [
                // Dipakai penyaring di layar admin.
                'paket' => TravelPackage::query()
                    ->whereIn('id', DaftarTunggu::select('travel_package_id'))
                    ->pluck('name', 'id'),

                /*
                 | Angka yang SAMA dengan penanda di bilah samping.
                 |
                 | Dikirim bersama daftarnya supaya layar bisa menyebut keduanya
                 | berdampingan. Tanpa itu admin melihat "1 menunggu kursi" di
                 | layar sementara penanda di menu kosong, dan menyimpulkan
                 | keduanya tidak sinkron — padahal keduanya menjawab pertanyaan
                 | yang berbeda.
                 |
                 | Dihitung dari SELURUH antrean, bukan dari baris yang sedang
                 | tampil: penomoran halaman dan saringan trip tidak boleh
                 | mengubah angka yang dibandingkan dengan penanda di menu.
                 */
                'perlu_dihubungi' => $this->perluDihubungi(),
            ],
        );
    }

    /**
     * Hitungan untuk penanda di bilah samping lemon.
     *
     * Yang dijadikan ANGKA penandanya adalah 'perlu_dihubungi': kursinya sudah
     * terbuka, orangnya tanpa surel, dan belum ada yang menghubunginya.
     *
     * Versi pertama menghitung yang BELUM dikabari dan tanpa surel — dan itu
     * salah orang. Selama kursinya belum terbuka tidak ada apa pun yang bisa
     * dikabarkan, jadi orang itu tidak menuntut perbuatan siapa pun. Yang
     * benar-benar menunggu telepon justru kebalikannya: kursinya SUDAH
     * terbuka, dan sistem tidak bisa menjangkaunya.
     *
     * Antrean yang panjang sendiri bukan pekerjaan — sistem mengabari mereka
     * sendiri begitu ada kursi. Penanda yang menghitung seluruhnya menyala
     * terus tanpa pernah bisa dinolkan, dan penanda yang tidak pernah padam
     * berhenti dibaca orang.
     */
    public function perhatian(): JsonResponse
    {
        return response()->json(['data' => [
            'perlu_dihubungi' => $this->perluDihubungi(),

            // Dua sisanya untuk judul tempel di menu: angka kecil tanpa
            // keterangan terbaca sebagai "cuma segini yang menunggu".
            'menunggu' => DaftarTunggu::query()->whereNull('dikabari_pada')->count(),
            'dikabari' => DaftarTunggu::query()->whereNotNull('dikabari_pada')->count(),
        ]]);
    }

    /**
     * Berapa yang menunggu ditelepon; nol bila kolomnya belum ada.
     *
     * Hitungan ini cuma mengisi sebuah lencana — tetapi karena ia dirakit di
     * dalam meta yang sama dengan daftarnya, kegagalannya menjatuhkan SELURUH
     * jawaban. Layar Daftar Tunggu mati total dengan kode 500 padahal daftar
     * pesertanya sendiri baik-baik saja; yang gagal cuma angka di pojok.
     *
     * Itu terjadi sungguhan: migrasi dihubungi_pada belum jalan di sebuah
     * lingkungan, dan admin melihat halaman galat alih-alih antreannya.
     *
     * Yang dijaga di sini bukan migrasinya — itu tetap harus dijalankan supaya
     * fiturnya berguna. Yang dijaga: hiasan tidak boleh menjatuhkan isi.
     */
    private function perluDihubungi(): int
    {
        try {
            return DaftarTunggu::query()->perluDihubungi()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Menandai bahwa seseorang sudah dihubungi lewat WhatsApp.
     *
     * Dipanggil saat admin menekan tombol WhatsApp di barisnya, bukan lewat
     * tombol tersendiri. Langkah tambahan yang harus diingat adalah langkah
     * yang akhirnya terlewat — dan penanda yang tidak pernah dipasang membuat
     * antrean tampak belum diurus padahal seluruhnya sudah ditelepon.
     *
     * Menekan tombolnya memang tidak membuktikan percakapannya terjadi.
     * Tetapi ia membuktikan seseorang sudah MENCOBA, dan itu yang membedakan
     * antrean yang sudah diurus dari yang belum disentuh sama sekali.
     *
     * Orangnya TIDAK dikeluarkan dari antrean: ia bisa saja menjawab "nanti
     * saya kabari lagi", dan mengeluarkannya berarti kehilangan jejaknya.
     */
    public function dihubungi(DaftarTunggu $tunggu, Request $request): JsonResponse
    {
        $tunggu->update([
            'dihubungi_pada' => now(),
            'dihubungi_oleh' => $request->attributes->get('admin_pemanggil') ?: 'admin',
        ]);

        $this->catat($request, 'hubungi daftar tunggu', [
            'nama' => $tunggu->nama,
            'whatsapp' => $tunggu->whatsapp,
        ]);

        return response()->json(['data' => [
            'dihubungi_pada' => $tunggu->dihubungi_pada?->toIso8601String(),
            'dihubungi_oleh' => $tunggu->dihubungi_oleh,
        ]]);
    }

    /**
     * Mengeluarkan satu orang dari antrean.
     *
     * Dipakai saat orangnya sudah jadi mendaftar, atau menyatakan batal lewat
     * WhatsApp. Tanpa ini antreannya cuma menumpuk, dan kabar kursi terbuka
     * dikirim ke orang yang sudah tidak menunggu.
     */
    public function destroy(DaftarTunggu $tunggu, Request $request): JsonResponse
    {
        // Lewat catat() milik ApiController, bukan JejakAudit langsung —
        // supaya bentuk jejaknya seragam dengan pemanggilan lain di API ini.
        $this->catat($request, 'keluarkan dari daftar tunggu', [
            'nama' => $tunggu->nama,
            'jumlah' => $tunggu->jumlah_peserta.' orang',
            'trip' => $tunggu->paket?->name ?? '—',
        ]);

        $tunggu->delete();

        return response()->json(['pesan' => 'Dikeluarkan dari daftar tunggu.']);
    }
}
