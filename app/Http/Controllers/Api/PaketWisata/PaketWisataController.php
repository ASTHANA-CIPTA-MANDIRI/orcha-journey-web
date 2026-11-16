<?php

namespace App\Http\Controllers\Api\PaketWisata;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\MenyimpanGambar;
use App\Http\Resources\PaketWisata\PaketWisataResource;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\SaranPaket;
use App\Models\PaketWisata\TravelPackage;
use App\Support\GambarWebp;
use App\Support\PaketWisata\ItineraryTeks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Paket wisata: dibaca dan ditulis dari dashboard lemon.
 */
class PaketWisataController extends ApiController
{
    use MenyimpanGambar;

    public function index(Request $request): JsonResponse
    {
        $daftar = TravelPackage::query()
            // Kursi terpakai dihitung sekali untuk seluruh halaman, bukan satu
            // query per baris — lihat scope-nya di model.
            ->denganKursiTerpakai()
            ->withCount(['pendaftaran', 'keberangkatan'])
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where('name', 'like', "%{$cari}%"))
            ->ofCategory($request->string('kategori')->toString() ?: null)

            // Saringan keadaan penayangan & pengurutan: keduanya milik layar
            // admin, dan keduanya dihitung di SQL supaya penomoran halamannya
            // tetap benar. Menyaring sesudah paginate berarti halaman kedua
            // bisa kosong padahal datanya ada.
            ->statusTayang($request->string('tampil')->toString() ?: null)
            ->urutkanDaftar($request->string('urut')->toString() ?: null)
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PaketWisataResource::class);
    }

    public function show(TravelPackage $paket): JsonResponse
    {
        return response()->json([
            'data' => (new PaketWisataResource($paket->loadCount('pendaftaran')->load('keberangkatan')))->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $paket = TravelPackage::create($this->siapkan($data, $request));

        $this->catatSaran($data);
        $this->catat($request, 'tambah paket wisata', ['nama' => $paket->name]);

        return response()->json([
            'data' => (new PaketWisataResource($paket))->resolve(),
            'pesan' => 'Paket wisata ditambahkan.',
        ], 201);
    }

    public function update(TravelPackage $paket, Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $paket->update($this->siapkan($data, $request, $paket->foto, $paket->foto_galeri));

        $this->catatSaran($data);
        $this->catat($request, 'ubah paket wisata', ['nama' => $paket->name]);

        return response()->json([
            'data' => (new PaketWisataResource($paket->fresh()))->resolve(),
            'pesan' => 'Paket wisata diperbarui.',
        ]);
    }

    public function destroy(TravelPackage $paket, Request $request): JsonResponse
    {
        // Paket yang sudah punya pendaftar tidak boleh hilang — datanya masih
        // dipakai untuk melayani peserta yang sudah membayar.
        if ($paket->pendaftaran()->exists()) {
            return response()->json([
                'pesan' => 'Paket ini sudah punya pendaftar, jadi tidak bisa dihapus. Ubah saja isinya.',
            ], 422);
        }

        $this->hapusGambar($paket->foto);
        $paket->delete();

        $this->catat($request, 'hapus paket wisata', ['nama' => $paket->name]);

        return response()->json(['pesan' => 'Paket wisata dihapus.']);
    }

    /* ===================== KEBERANGKATAN ===================== */

    /**
     * Menduplikat seluruh paket sebagai modal paket berikutnya.
     *
     * Menyalin TANGGAL saja tidak cukup untuk musim baru: harga naik,
     * fasilitas berubah, dan itinerary-nya digeser — yang semuanya berarti
     * paket baru, bukan tanggal baru pada paket lama. Tanpa ini, satu-satunya
     * cara adalah mengetik ulang itinerary tiga hari beserta destinasi dan
     * fasilitasnya.
     *
     * Yang SENGAJA tidak ikut tersalin:
     *
     * - status: salinan selalu lahir sebagai draf. Paket yang menggandakan
     *   diri langsung tayang berarti dua paket serupa berdiri di katalog
     *   sampai ada yang menyadarinya.
     * - keberangkatan & pendaftar: tanggalnya pasti berbeda — itu alasan
     *   menyalinnya — dan pendaftar jelas milik paket aslinya.
     * - jadwal penayangan: jendela milik paket lama tidak ada hubungannya
     *   dengan yang baru, dan menyalinnya bisa membuat salinan itu berakhir
     *   sebelum sempat disunting.
     *
     * Fotonya DIPAKAI BERSAMA, bukan digandakan: keduanya menunjuk berkas
     * yang sama, dan menghapus salah satu paket tidak boleh membuat foto
     * paket satunya hilang — karena itu hapusGambar tidak dipanggil di sini.
     */
    public function salin(TravelPackage $paket, Request $request): JsonResponse
    {
        $salinan = $paket->replicate([
            'uuid',
            'status',
            'tayang_mulai',
            'tayang_sampai',
            'tanggal_berangkat',
            'tanggal_pulang',
        ]);

        $salinan->name = $this->namaSalinan($paket->name);
        $salinan->status = 'draf';
        $salinan->tayang_mulai = null;
        $salinan->tayang_sampai = null;
        $salinan->tanggal_berangkat = null;
        $salinan->tanggal_pulang = null;
        $salinan->save();

        $this->catat($request, 'duplikat paket wisata', [
            'dari' => $paket->name,
            'jadi' => $salinan->name,
        ]);

        return response()->json([
            'data' => (new PaketWisataResource($salinan))->resolve(),
            'pesan' => 'Paket disalin jadi draf "'.$salinan->name.'". Ganti tanggal & harganya, lalu terbitkan.',
        ], 201);
    }

    /**
     * "Open Trip Ijen" -> "Open Trip Ijen (salinan)" -> "... (salinan 2)".
     *
     * Dinomori, bukan dibiarkan kembar: dua baris bernama persis sama di
     * katalog admin hanya bisa dibedakan dengan membuka keduanya.
     */
    private function namaSalinan(string $asal): string
    {
        $dasar = preg_replace('/\s*\(salinan(?:\s+\d+)?\)$/u', '', $asal);
        $nama = $dasar.' (salinan)';

        $nomor = 2;

        while (TravelPackage::where('name', $nama)->exists()) {
            $nama = $dasar.' (salinan '.$nomor.')';
            $nomor++;

            if ($nomor > 50) {
                $nama = $dasar.' (salinan '.now()->format('dmy-His').')';

                break;
            }
        }

        return mb_substr($nama, 0, 255);
    }

    /**
     * Menambah satu tanggal keberangkatan pada paket yang sudah ada.
     *
     * Yang diminta HANYA tanggalnya. Isi paketnya — itinerary, destinasi,
     * fasilitas, foto — dipakai bersama, dan kuota serta harga dibiarkan
     * kosong supaya ikut paketnya. Admin yang memang perlu membedakan tinggal
     * mengisinya; yang tidak, tidak perlu memikirkannya sama sekali.
     */
    public function tambahKeberangkatan(TravelPackage $paket, Request $request): JsonResponse
    {
        $data = $this->validasiKeberangkatan($request);

        if ($bentrok = $this->tanggalBentrok($paket, $data['tanggal_berangkat'])) {
            return $bentrok;
        }

        $k = $paket->keberangkatan()->create($data);

        $this->catat($request, 'tambah keberangkatan paket', [
            'paket' => $paket->name, 'tanggal' => $k->tanggal_berangkat?->toDateString(),
        ]);

        return response()->json([
            'pesan' => 'Keberangkatan '.$k->rentang_tanggal.' ditambahkan.',
            'data' => ['id' => $k->id],
        ], 201);
    }

    /**
     * Menyalin satu keberangkatan ke tanggal baru.
     *
     * Yang disalin kuota, harga, dan catatannya — hal-hal yang biasanya sama
     * antara dua tanggal yang berdekatan. Admin cukup menyebut tanggal
     * barunya, dan itulah satu-satunya yang wajib diisi.
     */
    public function salinKeberangkatan(TravelPackage $paket, Keberangkatan $keberangkatan, Request $request): JsonResponse
    {
        if ($keberangkatan->travel_package_id !== $paket->id) {
            return response()->json(['pesan' => 'Keberangkatan itu bukan milik paket ini.'], 404);
        }

        $data = $this->validasiKeberangkatan($request);

        if ($bentrok = $this->tanggalBentrok($paket, $data['tanggal_berangkat'])) {
            return $bentrok;
        }

        /*
         | Tanggal pulang ikut BERGESER sejauh yang sama bila tidak disebut.
         |
         | Menyalin 27–29 November ke 4 Desember hampir selalu berarti 4–6
         | Desember. Membiarkannya kosong memaksa admin menghitung sendiri
         | lama tripnya tiap kali menyalin — dan salah hitung di sana tidak
         | terlihat salah sampai ada yang menanyakan tanggal pulangnya.
         */
        if (blank($data['tanggal_pulang'] ?? null) && $keberangkatan->tanggal_pulang) {
            $lama = $keberangkatan->tanggal_berangkat->diffInDays($keberangkatan->tanggal_pulang);
            $data['tanggal_pulang'] = Carbon::parse($data['tanggal_berangkat'])
                ->addDays($lama)->toDateString();
        }

        $baru = $paket->keberangkatan()->create(array_merge([
            'kuota' => $keberangkatan->kuota,
            'price' => $keberangkatan->price,
            'original_price' => $keberangkatan->original_price,
            'harga_modal' => $keberangkatan->harga_modal,
            'catatan' => $keberangkatan->catatan,
            'aktif' => true,
        ], array_filter($data, fn ($v) => $v !== null)));

        $this->catat($request, 'salin keberangkatan paket', [
            'paket' => $paket->name,
            'dari' => $keberangkatan->tanggal_berangkat?->toDateString(),
            'ke' => $baru->tanggal_berangkat?->toDateString(),
        ]);

        return response()->json([
            'pesan' => 'Disalin jadi keberangkatan '.$baru->rentang_tanggal.'.',
            'data' => ['id' => $baru->id],
        ], 201);
    }

    public function ubahKeberangkatan(TravelPackage $paket, Keberangkatan $keberangkatan, Request $request): JsonResponse
    {
        if ($keberangkatan->travel_package_id !== $paket->id) {
            return response()->json(['pesan' => 'Keberangkatan itu bukan milik paket ini.'], 404);
        }

        $data = $this->validasiKeberangkatan($request, wajibTanggal: false);

        if (filled($data['tanggal_berangkat'] ?? null)
            && $bentrok = $this->tanggalBentrok($paket, $data['tanggal_berangkat'], $keberangkatan->id)) {
            return $bentrok;
        }

        $keberangkatan->update($data);

        $this->catat($request, 'ubah keberangkatan paket', [
            'paket' => $paket->name, 'tanggal' => $keberangkatan->tanggal_berangkat?->toDateString(),
        ]);

        return response()->json(['pesan' => 'Keberangkatan diperbarui.']);
    }

    /**
     * Menghapus satu keberangkatan — DITOLAK bila sudah ada yang mendaftar.
     *
     * Pendaftarnya sudah membayar atas tanggal itu. Menghapus barisnya
     * membuat pendaftaran kehilangan rombongannya, dan kuota tanggal lain
     * ikut salah hitung. Yang benar: tutup, jangan hapus.
     */
    public function hapusKeberangkatan(TravelPackage $paket, Keberangkatan $keberangkatan, Request $request): JsonResponse
    {
        if ($keberangkatan->travel_package_id !== $paket->id) {
            return response()->json(['pesan' => 'Keberangkatan itu bukan milik paket ini.'], 404);
        }

        if ($keberangkatan->pendaftaran()->where('status', '!=', 'batal')->exists()) {
            return response()->json([
                'pesan' => 'Tanggal ini sudah punya pendaftar, jadi tidak bisa dihapus. Tutup saja supaya tidak menerima pendaftar baru.',
            ], 422);
        }

        $tanggal = $keberangkatan->rentang_tanggal;
        $keberangkatan->delete();

        $this->catat($request, 'hapus keberangkatan paket', [
            'paket' => $paket->name, 'tanggal' => $tanggal,
        ]);

        return response()->json(['pesan' => 'Keberangkatan '.$tanggal.' dihapus.']);
    }

    /** @return array<string, mixed> */
    private function validasiKeberangkatan(Request $request, bool $wajibTanggal = true): array
    {
        return $request->validate([
            'tanggal_berangkat' => [$wajibTanggal ? 'required' : 'sometimes', 'date'],
            'tanggal_pulang' => ['nullable', 'date', 'after_or_equal:tanggal_berangkat'],
            // Kosong berarti ikut paketnya — itu yang paling sering benar.
            'kuota' => ['nullable', 'integer', 'min:1', 'max:999'],
            'price' => ['nullable', 'integer', 'min:0'],
            'original_price' => ['nullable', 'integer', 'min:0'],
            'harga_modal' => ['nullable', 'integer', 'min:0'],
            'aktif' => ['sometimes', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:191'],
        ], [], [
            'tanggal_berangkat' => 'tanggal berangkat',
            'tanggal_pulang' => 'tanggal pulang',
        ]);
    }

    /**
     * Dua keberangkatan bertanggal sama pada satu paket DITOLAK.
     *
     * Bukan kerapian: pendaftar melihat dua baris yang bunyinya persis sama
     * dan memilih dengan menebak, lalu kuotanya terpecah dua tanpa ada yang
     * menyadarinya.
     */
    private function tanggalBentrok(TravelPackage $paket, string $tanggal, ?int $kecuali = null): ?JsonResponse
    {
        $ada = $paket->keberangkatan()
            ->whereDate('tanggal_berangkat', $tanggal)
            ->when($kecuali, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if (! $ada) {
            return null;
        }

        return response()->json([
            'pesan' => 'Paket ini sudah punya keberangkatan di tanggal itu.',
        ], 422);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'required|in:'.implode(',', array_keys(config('orcha.kategori_paket'))),
            'status' => 'nullable|in:'.implode(',', array_keys(config('orcha.status_paket'))),
            'tayang_mulai' => 'nullable|date',
            'tayang_sampai' => 'nullable|date|after_or_equal:tayang_mulai',
            'berakhir_otomatis' => 'nullable|boolean',
            'durasi' => 'nullable|string|max:60',
            'tanggal_berangkat' => 'nullable|date',
            'tanggal_pulang' => 'nullable|date|after_or_equal:tanggal_berangkat',
            'titik_jemput' => 'nullable|string|max:191',
            'minimal_peserta' => 'required|integer|min:1|max:200',

            /*
             | Kuota boleh kosong, dan kosong berarti "belum ditetapkan" —
             | bukan nol. Paket lama seluruhnya belum punya angka ini, dan
             | memperlakukan kosong sebagai nol akan menutup pendaftaran semua
             | paket yang sedang tayang.
             |
             | Batas bawahnya minimal_peserta: kuota yang lebih kecil daripada
             | jumlah minimum keberangkatan berarti tripnya tidak akan pernah
             | bisa berangkat, dan itu pasti salah ketik.
             */
            'kuota' => 'nullable|integer|min:1|max:500|gte:minimal_peserta',
            'catatan_promo' => 'nullable|string|max:191',
            'promo_rombongan' => 'nullable|boolean',
            'harga' => 'required|numeric|min:0',
            'harga_asli' => 'nullable|numeric|min:0',
            // Modal boleh kosong: sebagian paket masih dihitung manual saat
            // dibuat. Kosong berarti belum dihitung, dan laporan keuntungan
            // menyebutnya begitu alih-alih menganggapnya nol.
            'harga_modal' => 'nullable|numeric|min:0',
            'diskon_persen' => 'nullable|numeric|min:0|max:100',
            'pilihan_terbaik' => 'nullable|boolean',
            'destinasi' => 'nullable|array',
            'destinasi.*' => 'string|max:191',
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => 'string|max:191',
            'tidak_termasuk' => 'nullable|array',
            'tidak_termasuk.*' => 'string|max:191',

            /*
             | Galeri dikirim DUA bagian, bukan satu.
             |
             | `galeri_tetap` adalah alamat foto lama yang masih dipakai, dan
             | `galeri_baru` berkas yang baru diunggah. Tanpa bagian pertama,
             | satu-satunya cara menghapus satu foto adalah mengunggah ulang
             | seluruh galeri — dan permintaan yang tidak membawa berkas sama
             | sekali akan terbaca sebagai "kosongkan galerinya".
             */
            'galeri_disentuh' => 'nullable|boolean',
            'galeri_tetap' => 'nullable|array',
            'galeri_tetap.*' => 'string|max:255',
            'galeri_baru' => 'nullable|array|max:12',
            'galeri_baru.*' => 'image|max:8192',
            'itinerary_teks' => 'nullable|string|max:4000',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }

    /**
     * Galeri foto paket: yang dipertahankan + yang baru diunggah.
     *
     * Dipanggil SELALU, termasuk saat permintaannya tidak menyinggung galeri
     * sama sekali — dan justru itu yang harus hati-hati. Borang yang tidak
     * mengirim `galeri_tetap` maupun `galeri_baru` berarti "jangan sentuh",
     * bukan "kosongkan": permintaan yang hanya mengubah harga tidak boleh
     * menghapus dua belas foto.
     *
     * Foto lama yang TIDAK disebut lagi di galeri_tetap dihapus dari cakram.
     * Tanpa itu berkasnya menumpuk selamanya tanpa ada satu pun halaman yang
     * menunjuknya.
     *
     * @param  array<int, string>|null  $lama
     * @return array<int, string>|null
     */
    private function simpanGaleri(Request $request, ?array $lama): ?array
    {
        /*
         | Penanda EKSPLISIT, bukan sekadar "apakah galeri_tetap ada".
         |
         | Perataan multipart membuang senarai kosong: admin yang menghapus
         | SELURUH foto galeri mengirim galeri_tetap yang kosong, kuncinya
         | lenyap dari muatan, dan permintaan itu terbaca persis seperti
         | permintaan yang tidak menyinggung galeri sama sekali. Akibatnya
         | foto yang baru saja dihapus tetap tampil, dan tidak ada satu pun
         | galat yang menyebutkannya.
         */
        $menyinggung = $request->boolean('galeri_disentuh')
            || $request->has('galeri_tetap')
            || $request->hasFile('galeri_baru');

        if (! $menyinggung) {
            return $lama;
        }

        $tetap = array_values(array_filter(
            (array) $request->input('galeri_tetap', []),
            fn ($jalur) => is_string($jalur) && $jalur !== ''
        ));

        foreach ((array) ($lama ?? []) as $jalur) {
            if (! in_array($jalur, $tetap, true)) {
                $this->hapusGambar($jalur);
            }
        }

        foreach ((array) $request->file('galeri_baru', []) as $berkas) {
            $tetap[] = GambarWebp::simpan($berkas, 'paket');
        }

        return $tetap ?: null;
    }

    private function siapkan(array $data, Request $request, ?string $fotoLama = null, ?array $galeriLama = null): array
    {
        return [
            'name' => $data['nama'],
            'category' => $data['kategori'],
            'status' => $data['status'] ?? 'terbit',
            'tayang_mulai' => $data['tayang_mulai'] ?? null,
            'tayang_sampai' => $data['tayang_sampai'] ?? null,
            'berakhir_otomatis' => (bool) ($data['berakhir_otomatis'] ?? true),
            'duration' => $data['durasi'] ?? null,
            'tanggal_berangkat' => $data['tanggal_berangkat'] ?? null,
            'tanggal_pulang' => $data['tanggal_pulang'] ?? null,
            'titik_jemput' => $data['titik_jemput'] ?? null,
            'minimal_peserta' => $data['minimal_peserta'],
            'kuota' => ($data['kuota'] ?? null) === null ? null : (int) $data['kuota'],
            'catatan_promo' => $data['catatan_promo'] ?? null,
            'price' => $data['harga'],
            'original_price' => $data['harga_asli'] ?? $data['harga'],
            'harga_modal' => ($data['harga_modal'] ?? null) === null ? null : (int) $data['harga_modal'],
            'discount_percentage' => $data['diskon_persen'] ?? 0,
            'is_best_choice' => (bool) ($data['pilihan_terbaik'] ?? false),
            'promo_rombongan' => (bool) ($data['promo_rombongan'] ?? false),
            'destination_list' => $data['destinasi'] ?? null,
            'fasilitas' => $data['fasilitas'] ?? null,
            'tidak_termasuk' => $data['tidak_termasuk'] ?? null,
            'itinerary' => ItineraryTeks::keArray($data['itinerary_teks'] ?? '') ?: null,
            'foto' => $this->simpanGambar($request, 'paket', $fotoLama),
            'foto_galeri' => $this->simpanGaleri($request, $galeriLama),
        ];
    }

    /**
     * Isian baru ikut masuk daftar saran, jadi paket berikutnya dengan
     * destinasi atau fasilitas yang sama tidak perlu diketik ulang.
     */
    private function catatSaran(array $data): void
    {
        SaranPaket::catat('destinasi', $data['destinasi'] ?? []);
        SaranPaket::catat('fasilitas', $data['fasilitas'] ?? []);
        SaranPaket::catat('tidak_termasuk', $data['tidak_termasuk'] ?? []);

        // Titik jemput datang sebagai satu kalimat berkoma, bukan senarai —
        // bentuknya dipertahankan karena belasan tempat lain membacanya
        // begitu. Yang dicatat potongannya.
        SaranPaket::catat('titik_jemput', array_filter(array_map(
            'trim', explode(',', (string) ($data['titik_jemput'] ?? ''))
        )));
    }

    /* ------------------------- SARAN ISIAN PAKET ------------------------- */

    public function saran(): JsonResponse
    {
        return response()->json([
            'data' => collect(SaranPaket::JENIS)
                ->mapWithKeys(fn ($jenis) => [
                    $jenis => SaranPaket::jenis($jenis)->orderBy('nama')->get(['id', 'nama'])->all(),
                ])
                ->all(),
        ]);
    }

    public function simpanSaran(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis' => 'required|in:'.implode(',', SaranPaket::JENIS),
            'nama' => 'required|string|max:191',
        ]);

        $saran = SaranPaket::firstOrCreate([
            'jenis' => $data['jenis'],
            'nama' => trim($data['nama']),
        ]);

        return response()->json([
            'data' => ['id' => $saran->id, 'nama' => $saran->nama],
            'pesan' => 'Ditambahkan ke daftar pilihan.',
        ], $saran->wasRecentlyCreated ? 201 : 200);
    }

    public function hapusSaran(SaranPaket $saran, Request $request): JsonResponse
    {
        $this->catat($request, 'hapus saran paket', ['jenis' => $saran->jenis, 'nama' => $saran->nama]);

        $saran->delete();

        // Sengaja tidak menyentuh paket yang sudah tersimpan: yang hilang hanya
        // pilihan cepatnya, bukan isi paketnya.
        return response()->json(['pesan' => 'Dihapus dari daftar pilihan.']);
    }
}
