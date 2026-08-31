<?php

namespace App\Http\Controllers\Api\Etalase;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\MenyimpanGambar;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Galeri;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Support\GambarWebp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Etalase pelengkap website: destinasi populer, testimoni, dan partner.
 *
 * Ketiganya isiannya sedikit dan bentuknya mirip, jadi ditangani satu berkas
 * — memecahnya jadi tiga controller hanya menyalin kerangka yang sama.
 */
class EtalaseController extends ApiController
{
    use MenyimpanGambar;

    /* ----------------------------- DESTINASI ----------------------------- */

    /**
     * Kartu destinasi di halaman publik hanya menampung tiga gambar tambahan.
     *
     * Angkanya dipakai untuk validasi DAN dikirim ke admin lemon supaya tulisan
     * "sisa sekian" di sana tidak pernah berbeda dari aturan yang sebenarnya
     * berlaku di sini.
     */
    public const BATAS_SUB_FOTO = 3;

    public function destinasi(Request $request): JsonResponse
    {
        $daftar = DestinationPopuler::query()
            ->when($request->string('wilayah')->toString(), fn ($q, $wilayah) => $q->where('wilayah', $wilayah))
            // Pencariannya di sini, bukan di lemon. Selama seluruh isi tabel
            // dikirim sekaligus, menyaringnya di sana sama saja hasilnya;
            // begitu daftarnya dipenggal per halaman, penyaring di sana hanya
            // melihat sembilan baris yang kebetulan sedang tampil — dan
            // destinasi yang dicari admin akan "tidak ditemukan" padahal ada.
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('destination_name', 'like', "%{$cari}%")
                    ->orWhere('provinsi', 'like', "%{$cari}%")
                    ->orWhere('daerah', 'like', "%{$cari}%")
            ))
            // Yang baru dicatat di atas. Diurutkan menurut abjad, destinasi
            // yang baru saja ditambahkan admin bisa mendarat di halaman mana
            // pun — dan yang pertama diperiksa orang selalu yang baru saja
            // dikerjakannya.
            //
            // id sebagai pemutus: dua puluh satu destinasi bawaan tercatat pada
            // detik yang sama, dan urutan tanpa pemutus berarti urutan yang
            // berubah-ubah tanpa sebab. Pada daftar berhalaman akibatnya lebih
            // buruk daripada sekadar acak: baris yang sama bisa muncul di dua
            // halaman sekaligus sementara yang lain tidak muncul di mana pun.
            ->latest()
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta(
            $daftar,
            fn () => $daftar->getCollection()
                ->map(fn ($destinasi) => $this->bentukDestinasi($destinasi))
                ->all(),
        );
    }

    /**
     * Satu destinasi, untuk halaman ubah di admin lemon.
     *
     * Formulir yang mengambil seluruh daftar lalu menyaring sendiri akan
     * membaca data yang makin besar untuk memakai satu baris saja, dan diam-
     * diam bergantung pada daftar itu tidak berhalaman.
     */
    public function satuDestinasi(DestinationPopuler $destinasi): JsonResponse
    {
        return response()->json(['data' => $this->bentukDestinasi($destinasi)]);
    }

    /**
     * Satu bentuk untuk daftar maupun satuan.
     *
     * Dua pemetaan sejajar untuk data yang sama pasti berselisih suatu saat —
     * dan yang membacanya formulir yang justru harus menampilkan apa adanya.
     */
    private function bentukDestinasi(DestinationPopuler $destinasi): array
    {
        return [
            'id' => $destinasi->id,
            'nama' => $destinasi->destination_name,
            'provinsi' => $destinasi->provinsi,
            'daerah' => $destinasi->daerah,
            'alamat_singkat' => $destinasi->alamat_singkat,
            'wilayah' => $destinasi->wilayah,
            'wilayah_label' => $destinasi->wilayah_label,
            'deskripsi' => $destinasi->deskripsi,
            'total_pengunjung' => $destinasi->total_visitor,
            'foto' => $destinasi->main_photo,
            // Gambar tambahan ikut dikirim: kartu destinasi di halaman publik
            // menampilkannya, jadi admin harus bisa melihat dan mengubahnya
            // dari sini — bukan hanya dari admin bawaan.
            'sub_foto' => array_values($destinasi->others_photo ?? []),
            'batas_sub_foto' => self::BATAS_SUB_FOTO,
        ];
    }

    public function simpanDestinasi(Request $request): JsonResponse
    {
        $data = $this->validasiDestinasi($request);

        $destinasi = DestinationPopuler::create($this->siapkanDestinasi($data, $request));

        $this->catat($request, 'tambah destinasi', ['nama' => $destinasi->destination_name]);

        return response()->json(['pesan' => 'Destinasi ditambahkan.'], 201);
    }

    public function perbaruiDestinasi(DestinationPopuler $destinasi, Request $request): JsonResponse
    {
        $data = $this->validasiDestinasi($request);

        $destinasi->update($this->siapkanDestinasi($data, $request, $destinasi));

        $this->catat($request, 'ubah destinasi', ['nama' => $destinasi->destination_name]);

        return response()->json(['pesan' => 'Destinasi diperbarui.']);
    }

    public function hapusDestinasi(DestinationPopuler $destinasi, Request $request): JsonResponse
    {
        $this->hapusGambar($destinasi->main_photo);

        // Gambar tambahan ikut dibuang. Sebelumnya hanya foto utama yang
        // dihapus, jadi tiap destinasi yang dihapus meninggalkan berkas yang
        // tidak dirujuk siapa pun di penyimpanan.
        foreach ($destinasi->others_photo ?? [] as $tambahan) {
            $this->hapusGambar($tambahan);
        }

        $destinasi->delete();

        $this->catat($request, 'hapus destinasi', ['nama' => $destinasi->destination_name]);

        return response()->json(['pesan' => 'Destinasi dihapus.']);
    }

    private function validasiDestinasi(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:191',
            'wilayah' => 'required|in:'.implode(',', array_keys(\App\Models\Etalase\WilayahTambahan::gabungan())),
            'provinsi' => 'nullable|string|max:100',
            'daerah' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string|max:1000',
            'total_pengunjung' => 'nullable|integer|min:0',
            'gambar' => 'nullable|image|max:4096',
            // Gambar tambahan: yang BARU diunggah, dan yang lama dipertahankan.
            // Keduanya dikirim terpisah karena yang menentukan isi akhir kolom
            // adalah daftar yang dipertahankan — bukan isi lama di basis data.
            // Tanpa itu, menghapus satu gambar mustahil dinyatakan.
            'sub_foto' => 'nullable|array',
            'sub_foto.*' => 'image|max:2048',
            'sub_foto_tetap' => 'nullable|array',
            'sub_foto_tetap.*' => 'string|max:255',
        ]);
    }

    private function siapkanDestinasi(array $data, Request $request, ?DestinationPopuler $lama = null): array
    {
        return [
            'destination_name' => $data['nama'],
            'wilayah' => $data['wilayah'],
            'provinsi' => $data['provinsi'] ?? null,
            'daerah' => $data['daerah'] ?? null,
            'deskripsi' => $data['deskripsi'] ?? null,
            'total_visitor' => $data['total_pengunjung'] ?? 0,
            'main_photo' => $this->simpanGambar($request, 'destinasi', $lama?->main_photo),
            'others_photo' => $this->subFotoDestinasi($data, $request, $lama),
        ];
    }

    /**
     * Gambar tambahan yang tersimpan sesudah perubahan ini.
     *
     * Yang menentukan isinya adalah daftar yang DIPERTAHANKAN, bukan isi lama
     * di basis data: hanya dengan begitu menghapus satu gambar bisa dinyatakan.
     * Unggahan baru DITAMBAHKAN, tidak menggantikan — sebelum aturan ini ada di
     * admin bawaan, menambah gambar ketiga justru menyisakan satu.
     *
     * Berkas yang tidak lagi dirujuk baru dihapus di sini, sesudah admin benar-
     * benar menyimpan keputusannya.
     *
     * @return list<string>
     */
    private function subFotoDestinasi(array $data, Request $request, ?DestinationPopuler $lama): array
    {
        $sebelumnya = array_values($lama?->others_photo ?? []);

        // Permintaan yang tidak menyebut gambar tambahan sama sekali TIDAK
        // menghapusnya. Pemanggil lama hanya mengirim medan yang dikenalnya,
        // dan menganggap diamnya sebagai "hapus semua" akan membuang gambar
        // yang tidak pernah diminta dibuang siapa pun.
        if (! $request->has('sub_foto_tetap') && ! $request->hasFile('sub_foto')) {
            return $sebelumnya;
        }

        // Hanya jalur yang memang milik destinasi ini yang boleh dipertahankan.
        // Tanpa saringan ini, permintaan yang dirakit tangan bisa menautkan
        // berkas milik destinasi lain — dan menghapus salah satunya kemudian
        // ikut merusak yang satunya.
        $tetap = array_values(array_intersect($data['sub_foto_tetap'] ?? [], $sebelumnya));

        foreach ($request->file('sub_foto', []) as $berkas) {
            $tetap[] = GambarWebp::simpan($berkas, 'destinasi/tambahan');
        }

        if (count($tetap) > self::BATAS_SUB_FOTO) {
            throw ValidationException::withMessages([
                'sub_foto' => 'Gambar tambahan maksimal '.self::BATAS_SUB_FOTO
                    .'. Hapus dulu salah satu sebelum menambah.',
            ]);
        }

        foreach (array_diff($sebelumnya, $tetap) as $dibuang) {
            $this->hapusGambar($dibuang);
        }

        return $tetap;
    }

    /* ----------------------------- TESTIMONI ----------------------------- */

    /**
     * Daftar testimoni, dipenggal per halaman dan bisa dicari.
     *
     * Sebelumnya dikirim seluruhnya sekaligus dan disaring di lemon. Selama
     * jumlahnya belasan itu tidak terasa; begitu ratusan, tiap membuka halaman
     * berarti mengangkut seluruh isi tabel — dan pencarian yang dikerjakan di
     * lemon hanya melihat apa yang kebetulan sudah terkirim.
     *
     * Sekarang keduanya dikerjakan di sini, jadi yang dicari admin tetap
     * ketemu walau barisnya ada di halaman kelima.
     */
    public function testimoni(Request $request): JsonResponse
    {
        $daftar = Testimoni::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('customer_name', 'like', "%{$cari}%")
                    ->orWhere('testimonial', 'like', "%{$cari}%")
            ))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta($daftar, fn () => $daftar->getCollection()
            ->map(fn (Testimoni $testimoni) => [
                'id' => $testimoni->id,
                'nama' => $testimoni->customer_name,
                'isi' => $testimoni->testimonial,
                'rating' => $testimoni->rating,
                'foto' => $testimoni->avatar,

                // Yang menunggu persetujuan harus bisa dibedakan di daftar
                // admin — kalau tidak, testimoni yang dikirim pelanggan
                // menumpuk tanpa ada yang tahu ia menunggu.
                'status' => $testimoni->status,
                'kode_pesanan' => $testimoni->kode_pesanan,
                'terverifikasi' => $testimoni->terverifikasi,
            ])->all());
    }

    public function simpanTestimoni(Request $request): JsonResponse
    {
        $data = $this->validasiTestimoni($request);

        Testimoni::create([
            'customer_name' => $data['nama'],
            'rating' => $data['rating'],
            'testimonial' => $data['isi'],
            'avatar' => $this->simpanGambar($request, 'testimoni'),
            // Admin yang mengetikkan testimoni bermaksud menayangkannya —
            // tidak ada gunanya ia menyetujui tulisannya sendiri. Bawaan
            // 'menunggu' di basis data hanya untuk yang dikirim pelanggan.
            'status' => 'tayang',
        ]);

        $this->catat($request, 'tambah testimoni', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Testimoni ditambahkan.'], 201);
    }

    /**
     * Menayangkan atau menolak testimoni yang dikirim pelanggan.
     *
     * Terpisah dari perbaruiTestimoni: yang ini keputusan tayang/tidak, bukan
     * penyuntingan isi. Digabung, tiap persetujuan ikut mengirim seluruh isi
     * testimoni kembali ke server — dan isi yang ikut terkirim adalah isi yang
     * bisa berubah tanpa disengaja.
     */
    public function ubahStatusTestimoni(Testimoni $testimoni, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:menunggu,tayang,ditolak',
        ]);

        $sebelum = $testimoni->status;
        $testimoni->update($data);

        $this->catat($request, 'ubah status testimoni', [
            'nama' => $testimoni->customer_name,
            'kode' => $testimoni->kode_pesanan,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        return response()->json(['pesan' => 'Status testimoni diperbarui.']);
    }

    public function perbaruiTestimoni(Testimoni $testimoni, Request $request): JsonResponse
    {
        $data = $this->validasiTestimoni($request);

        $testimoni->update([
            'customer_name' => $data['nama'],
            'rating' => $data['rating'],
            'testimonial' => $data['isi'],
            'avatar' => $this->simpanGambar($request, 'testimoni', $testimoni->avatar),
        ]);

        $this->catat($request, 'ubah testimoni', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Testimoni diperbarui.']);
    }

    public function hapusTestimoni(Testimoni $testimoni, Request $request): JsonResponse
    {
        $this->hapusGambar($testimoni->avatar);
        $testimoni->delete();

        $this->catat($request, 'hapus testimoni', ['id' => $testimoni->id]);

        return response()->json(['pesan' => 'Testimoni dihapus.']);
    }

    private function validasiTestimoni(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:191',
            'rating' => 'required|integer|min:1|max:5',
            'isi' => 'required|string|max:1000',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }

    /* ------------------------------ PARTNER ------------------------------ */

    /**
     * Daftar partner, dipenggal per halaman dan bisa dicari.
     *
     * Sebabnya sama dengan testimoni: yang dikirim sekaligus harus disaring di
     * lemon, dan penyaring di sana hanya melihat baris yang kebetulan sedang
     * tampil. Begitu daftarnya dipenggal, pencarian yang tertinggal di lemon
     * akan menjawab "tidak ditemukan" untuk partner yang ada di halaman lain.
     *
     * Urutannya tetap menurut nama — daftar partner dibaca untuk mencari satu
     * nama, bukan untuk melihat mana yang paling baru ditambahkan.
     */
    public function partner(Request $request): JsonResponse
    {
        $daftar = Partner::query()
            ->when($request->string('cari')->toString(),
                fn ($q, $cari) => $q->where('partner_name', 'like', "%{$cari}%"))
            ->orderBy('partner_name')
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta($daftar, fn () => $daftar->getCollection()
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'nama' => $partner->partner_name,
                'logo' => $partner->foto,
            ])->all());
    }

    public function simpanPartner(Request $request): JsonResponse
    {
        $data = $this->validasiPartner($request);

        Partner::create([
            'partner_name' => $data['nama'],
            'foto' => $this->simpanGambar($request, 'partner'),
        ]);

        $this->catat($request, 'tambah partner', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Partner ditambahkan.'], 201);
    }

    public function perbaruiPartner(Partner $partner, Request $request): JsonResponse
    {
        $data = $this->validasiPartner($request);

        $partner->update([
            'partner_name' => $data['nama'],
            'foto' => $this->simpanGambar($request, 'partner', $partner->foto),
        ]);

        $this->catat($request, 'ubah partner', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Partner diperbarui.']);
    }

    public function hapusPartner(Partner $partner, Request $request): JsonResponse
    {
        $this->hapusGambar($partner->foto);
        $partner->delete();

        $this->catat($request, 'hapus partner', ['id' => $partner->id]);

        return response()->json(['pesan' => 'Partner dihapus.']);
    }

    /* ------------------------------- GALERI ------------------------------- */

    public function galeri(): JsonResponse
    {
        return response()->json([
            'data' => Galeri::orderBy('urutan')->orderByDesc('id')->get()->map(fn ($satu) => [
                'id' => $satu->id,
                'foto' => $satu->foto,
                'keterangan' => $satu->keterangan,
                'urutan' => $satu->urutan,
                'tampil' => $satu->tampil,
            ])->all(),
        ]);
    }

    public function simpanGaleri(Request $request): JsonResponse
    {
        $data = $this->validasiGaleri($request, wajibGambar: true);

        Galeri::create([
            'foto' => $this->simpanGambar($request, 'galeri'),
            'keterangan' => $data['keterangan'] ?? null,
            // Foto baru masuk ke belakang barisan, bukan menyerobot ke depan:
            // urutan yang sudah disusun admin tidak boleh berubah sendiri
            // hanya karena ada unggahan baru.
            'urutan' => $data['urutan'] ?? ((int) Galeri::max('urutan') + 1),
            'tampil' => $data['tampil'] ?? true,
        ]);

        $this->catat($request, 'tambah foto galeri', []);

        return response()->json(['pesan' => 'Foto galeri ditambahkan.'], 201);
    }

    public function perbaruiGaleri(Galeri $galeri, Request $request): JsonResponse
    {
        $data = $this->validasiGaleri($request);

        $galeri->update([
            'foto' => $this->simpanGambar($request, 'galeri', $galeri->foto),
            'keterangan' => $data['keterangan'] ?? null,
            'urutan' => $data['urutan'] ?? $galeri->urutan,
            'tampil' => $data['tampil'] ?? $galeri->tampil,
        ]);

        $this->catat($request, 'ubah foto galeri', ['id' => $galeri->id]);

        return response()->json(['pesan' => 'Foto galeri diperbarui.']);
    }

    public function hapusGaleri(Galeri $galeri, Request $request): JsonResponse
    {
        $this->hapusGambar($galeri->foto);
        $galeri->delete();

        $this->catat($request, 'hapus foto galeri', ['id' => $galeri->id]);

        return response()->json(['pesan' => 'Foto galeri dihapus.']);
    }

    private function validasiGaleri(Request $request, bool $wajibGambar = false): array
    {
        return $request->validate([
            // Fotonya yang wajib, keterangannya tidak. Admin yang diminta
            // mengarang judul untuk dua puluh foto rombongan akan berhenti
            // mengunggah di foto kelima.
            'gambar' => ($wajibGambar ? 'required' : 'nullable').'|image|max:4096',
            'keterangan' => 'nullable|string|max:191',
            'urutan' => 'nullable|integer|min:0|max:9999',
            'tampil' => 'nullable|boolean',
        ]);
    }

    private function validasiPartner(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:191',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }
}
