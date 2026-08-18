<?php

namespace App\Http\Controllers\Api\Etalase;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\MenyimpanGambar;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use Illuminate\Http\JsonResponse;
use App\Support\GambarWebp;
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
        return response()->json([
            'data' => DestinationPopuler::query()
                ->when($request->string('wilayah')->toString(), fn ($q, $wilayah) => $q->where('wilayah', $wilayah))
                // Yang baru dicatat di atas. Diurutkan menurut abjad, destinasi
                // yang baru saja ditambahkan admin bisa mendarat di halaman
                // mana pun — dan yang pertama diperiksa orang selalu yang baru
                // saja dikerjakannya.
                //
                // id sebagai pemutus: dua puluh satu destinasi bawaan tercatat
                // pada detik yang sama, dan urutan tanpa pemutus berarti urutan
                // yang berubah-ubah tanpa sebab.
                ->latest()
                ->latest('id')
                ->get()
                ->map(fn ($destinasi) => $this->bentukDestinasi($destinasi))
                ->all(),
        ]);
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

    public function testimoni(): JsonResponse
    {
        return response()->json([
            'data' => Testimoni::latest('id')->get()->map(fn ($testimoni) => [
                'id' => $testimoni->id,
                'nama' => $testimoni->customer_name,
                'isi' => $testimoni->testimonial,
                'rating' => $testimoni->rating,
                'foto' => $testimoni->avatar,
            ])->all(),
        ]);
    }

    public function simpanTestimoni(Request $request): JsonResponse
    {
        $data = $this->validasiTestimoni($request);

        Testimoni::create([
            'customer_name' => $data['nama'],
            'rating' => $data['rating'],
            'testimonial' => $data['isi'],
            'avatar' => $this->simpanGambar($request, 'testimoni'),
        ]);

        $this->catat($request, 'tambah testimoni', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Testimoni ditambahkan.'], 201);
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

    public function partner(): JsonResponse
    {
        return response()->json([
            'data' => Partner::orderBy('partner_name')->get()->map(fn ($partner) => [
                'id' => $partner->id,
                'nama' => $partner->partner_name,
                'logo' => $partner->foto,
            ])->all(),
        ]);
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

    private function validasiPartner(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:191',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }
}
