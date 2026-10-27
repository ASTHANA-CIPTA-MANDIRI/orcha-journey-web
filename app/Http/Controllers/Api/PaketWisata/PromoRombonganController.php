<?php

namespace App\Http\Controllers\Api\PaketWisata;

use App\Http\Controllers\Api\ApiController;
use App\Models\PaketWisata\PromoRombonganTingkat;
use App\Support\SaranPromoRombongan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tingkat promo rombongan, dikelola dari panel admin.
 *
 * Angka-angka ini yang paling sering diutak-atik — mengikuti musim liburan,
 * sisa kursi, dan tawaran pesaing. Selama masih di berkas config, tiap
 * perubahan kecil berarti menunggu ada yang menyunting kode.
 */
class PromoRombonganController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PromoRombonganTingkat::orderBy('min_peserta')->get()->map(fn ($t) => [
                'id' => $t->id,
                'min_peserta' => $t->min_peserta,
                'potongan_persen' => $t->potongan_persen,
                'gratis_orang' => $t->gratis_orang,
                'label' => $t->label,
                'ajakan' => $t->ajakan,
                'aktif' => $t->aktif,
            ])->all(),
        ]);
    }

    /**
     * Batas aman potongan untuk sebuah tingkat.
     *
     * Angka promo selama ini ditebak — "lima persen terdengar wajar". Yang
     * dipulangkan di sini batasnya, bukan keputusannya: admin tetap boleh
     * memilih angka lain, yang tidak boleh adalah memilih tanpa tahu
     * akibatnya.
     */
    public function saran(Request $request): JsonResponse
    {
        $request->validate(['min_peserta' => ['required', 'integer', 'min:2', 'max:100']]);

        return response()->json(['data' => SaranPromoRombongan::untuk($request->integer('min_peserta'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $tingkat = PromoRombonganTingkat::create($this->periksa($request));

        $this->catatTingkat($request, 'tambah tingkat promo', $tingkat);

        return response()->json(['data' => $tingkat], 201);
    }

    public function update(PromoRombonganTingkat $tingkat, Request $request): JsonResponse
    {
        $tingkat->update($this->periksa($request, $tingkat->id));

        $this->catatTingkat($request, 'ubah tingkat promo', $tingkat);

        return response()->json(['data' => $tingkat->fresh()]);
    }

    public function destroy(PromoRombonganTingkat $tingkat, Request $request): JsonResponse
    {
        $this->catatTingkat($request, 'hapus tingkat promo', $tingkat);

        $tingkat->delete();

        return response()->json(['pesan' => 'Tingkat promo dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function periksa(Request $request, ?int $kecuali = null): array
    {
        $data = $request->validate([
            /*
             | Minimal peserta unik: dua tingkat dengan syarat yang sama membuat
             | "tingkat terbaik" jadi tidak menentu — yang menang tergantung
             | urutan baris, dan itu berubah sendiri saat salah satunya
             | disunting.
             */
            'min_peserta' => ['required', 'integer', 'min:2', 'max:100',
                Rule::unique('tbl_promo_rombongan')->ignore($kecuali)],
            'potongan_persen' => ['nullable', 'integer', 'min:0', 'max:100'],
            'gratis_orang' => ['nullable', 'integer', 'min:0', 'max:20'],
            /*
             | Tulisan dan ajakannya TIDAK lagi diterima dari pemanggil.
             |
             | Keduanya dirakit model dari angka yang berlaku, supaya kalimat
             | yang dibaca pelanggan tidak pernah berbeda dari potongan yang
             | benar-benar ditagih. Sebelumnya admin mengetiknya sendiri, dan
             | mengubah 5% jadi 7% tanpa menyunting kalimat "hemat 5%" di
             | sebelahnya adalah satu langkah yang mudah terlewat.
             |
             | Dibiarkan lewat validasi (bukan ditolak) supaya pemanggil lama
             | yang masih mengirimnya tidak mendadak gagal — nilainya diabaikan
             | dan ditimpa model.
             */
            'label' => ['nullable', 'string', 'max:120'],
            'ajakan' => ['nullable', 'string', 'max:160'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $data['potongan_persen'] = (int) ($data['potongan_persen'] ?? 0);
        $data['gratis_orang'] = (int) ($data['gratis_orang'] ?? 0);

        /*
         | Tingkat tanpa keuntungan apa pun ditolak.
         |
         | Ia lolos seluruh aturan di atas, tersimpan rapi, dan tampil di daftar
         | — tetapi tidak mengubah harga sepeser pun. Yang membuatnya berbahaya:
         | ia MENGGESER tingkat di bawahnya. Rombongan 10 orang yang seharusnya
         | dapat gratis 1 akan berhenti di tingkat kosong ini kalau
         | min_peserta-nya lebih tinggi, dan tidak ada yang tahu sampai ada
         | pelanggan menghitung sendiri.
         */
        if ($data['potongan_persen'] === 0 && $data['gratis_orang'] === 0) {
            abort(422, 'Tingkat ini tidak memberi potongan maupun orang gratis, jadi tidak mengubah harga apa pun.');
        }

        $this->tolakBilaLebihMahal($data, $kecuali);

        return $data;
    }

    /**
     * Menolak tingkat yang justru menaikkan tagihan.
     *
     * Tingkat TIDAK bertumpuk — yang berlaku satu saja, yang paling tinggi
     * syaratnya. Akibatnya tingkat baru bisa diam-diam lebih mahal daripada
     * tingkat di bawahnya: "ajak 10 rekan, gratis 1 orang" yang potongan
     * persennya nol mencabut potongan 5% milik tingkat 6, dan rombongan
     * yang menambah satu orang justru membayar lebih banyak.
     *
     * Cacatnya tidak terlihat dari layar mana pun: tiap tingkat benar
     * sendiri-sendiri, dan yang salah cuma urutannya. Yang menemukannya
     * pelanggan, saat menghitung sendiri.
     */
    private function tolakBilaLebihMahal(array $data, ?int $kecuali): void
    {
        $n = (int) $data['min_peserta'];

        // Harga satu kursi tidak penting di sini — yang dibandingkan
        // bentuknya, jadi dipakai angka bulat mana pun.
        $satuan = 1_000_000;

        $hitung = fn (int $orang, int $gratis, int $persen) => $satuan * max(1, $orang - $gratis)
            - (int) round($satuan * $persen / 100);

        $diBawah = PromoRombonganTingkat::query()
            ->where('aktif', true)
            ->when($kecuali, fn ($q) => $q->where('id', '!=', $kecuali))
            ->where('min_peserta', '<', $n)
            ->orderByDesc('min_peserta')
            ->first();

        if (! $diBawah) {
            return;
        }

        /*
         | Yang dibandingkan DUA UKURAN ROMBONGAN yang bersebelahan, bukan
         | dua tingkat pada ukuran yang sama.
         |
         | Jurangnya muncul tepat di batas: rombongan n-1 orang masih memakai
         | tingkat di bawah, rombongan n orang sudah memakai tingkat ini.
         | Kalau yang n orang membayar lebih banyak, menambah satu orang
         | justru menaikkan tagihan — dan orang itu "gratis".
         */
        $sebelum = $hitung($n - 1, $diBawah->gratis_orang, $diBawah->potongan_persen);
        $sesudah = $hitung($n, (int) $data['gratis_orang'], (int) $data['potongan_persen']);

        if ($sesudah > $sebelum) {
            abort(422, 'Tingkat ini justru lebih mahal: rombongan '.($n - 1).' orang membayar lebih sedikit '
                .'daripada rombongan '.$n.' orang, jadi menambah satu orang malah menaikkan tagihan. '
                .'Tingkat tidak bertumpuk, jadi potongan tingkat di bawahnya ikut hilang: '
                .'tambahkan potongan persennya juga, atau turunkan syaratnya.');
        }
    }

    /**
     * Namanya BUKAN catat().
     *
     * ApiController sudah punya catat() sendiri dengan bentuk parameter yang
     * berbeda, dan menimpanya membuat seluruh kelas gagal dimuat — PHP menolak
     * metode anak yang lebih tertutup daripada induknya. Bugnya muncul sebagai
     * seluruh suite mati, bukan sebagai satu uji merah.
     *
     * Yang dipanggil di dalamnya tetap catat() milik induk, supaya jejaknya
     * seragam dengan pemanggilan lain di API ini.
     */
    private function catatTingkat(Request $request, string $aksi, PromoRombonganTingkat $tingkat): void
    {
        $this->catat($request, $aksi, [
            'minimal peserta' => $tingkat->min_peserta.' orang',
            'keuntungan' => $tingkat->gratis_orang > 0
                ? 'gratis '.$tingkat->gratis_orang.' orang'
                : 'potongan '.$tingkat->potongan_persen.'%',
        ]);
    }
}
