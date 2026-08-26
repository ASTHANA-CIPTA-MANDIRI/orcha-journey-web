<?php

namespace App\Http\Controllers\Api\SewaKendaraan;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SewaKendaraan\PenyewaanResource;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\BerkasKwitansi;
use App\Support\GambarWebp;
use App\Support\KirimPemberitahuan;
use App\Support\NotaSewa;
use App\Support\SalinanPelanggan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenyewaanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = PenyewaanKendaraan::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('nama_kendaraan', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PenyewaanResource::class);
    }

    /**
     * Sewa yang menuntut tindakan admin.
     *
     * Tiga hal, dan sengaja dipisah karena beda urusannya:
     *
     *   baru  — pemesanan yang belum disentuh siapa pun. Pelanggan sudah
     *           mengirim formulir dan sedang menunggu dijawab; selama ini ia
     *           hanya ketahuan kalau admin kebetulan membuka daftarnya.
     *
     *   telat — unit yang sudah lewat tenggat DAN BELUM dicatat kembali. Yang
     *           sudah kembali tidak ikut, sekalipun kembalinya telat: pekerjaan
     *           menagihnya sudah selesai. Ini yang paling mahal dibiarkan —
     *           dendanya terus berjalan tanpa pernah ditetapkan, dan unitnya
     *           tidak bisa disewakan lagi karena di sistem masih dianggap ada
     *           di luar.
     *
     *   denda — unit SUDAH kembali, sistem punya usulan dendanya, tetapi tidak
     *           satu rupiah pun ditetapkan. Ini yang tersisa dari perkara di
     *           atas: unitnya sudah aman, tetapi uangnya belum ditagihkan dan
     *           nota yang dikirim ke penyewa masih menyebut Rp 0.
     *
     * Jalur tersendiri dan semurah mungkin: dipanggil bilah samping lemon di
     * setiap halaman admin.
     */
    public function perhatian(): JsonResponse
    {
        $baru = PenyewaanKendaraan::where('status', 'baru')->count();

        /*
         | Tenggat dan usulan denda sama-sama dihitung lewat aksesor, bukan
         | kolom, jadi penyaringannya diselesaikan di PHP. Yang ditarik hanya
         | sewa yang belum tuntas — dibatasi status, jadi jumlahnya kecil.
         */
        $belumKembali = PenyewaanKendaraan::whereNull('dikembalikan_pada')
            ->whereNotIn('status', ['selesai', 'batal'])
            ->get();

        $sudahKembali = PenyewaanKendaraan::whereNotNull('dikembalikan_pada')
            ->where('status', '!=', 'batal')
            ->get();

        return response()->json(['data' => [
            'baru' => (int) $baru,
            'telat' => $belumKembali->filter(fn (PenyewaanKendaraan $sewa) => $sewa->terlambat)->count(),
            'denda' => $sudahKembali->filter(
                fn (PenyewaanKendaraan $sewa) => $sewa->total_denda === 0
                    && ($sewa->denda_keterlambatan_usulan + $sewa->denda_kerusakan_usulan) > 0
            )->count(),
        ]]);
    }

    public function show(PenyewaanKendaraan $penyewaan): JsonResponse
    {
        $data = (new PenyewaanResource($penyewaan))->resolve();

        /*
         | Tautan tempat penyewa mengirim bukti transfer.
         |
         | Dibuatkan di sini, bukan di lemon: yang tahu alamat situs publiknya
         | adalah aplikasi ini. Admin yang menyusun sendiri alamatnya lewat
         | tempel-menempel pernah mengirimkan alamat localhost ke pelanggan.
         |
         | Alamatnya sengaja dibiarkan terbaca — bukan dipendekkan. Yang diminta
         | di sini bukan mengunduh melainkan mengunggah bukti transfer, dan orang
         | yang diminta menyerahkan bukti pembayaran pantas melihat ke mana ia
         | dibawa sebelum mengetuk.
         */
        $data['konfirmasi_pembayaran_tautan'] = route('konfirmasi-pembayaran', [
            'kode' => $penyewaan->kode,
        ]);

        return response()->json(['data' => $data]);
    }

    public function ubahStatus(PenyewaanKendaraan $penyewaan, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('orcha.status_penyewaan'))),
        ]);

        $sebelum = $penyewaan->status;
        $penyewaan->update($data);

        $this->catat($request, 'ubah status penyewaan', [
            'kode' => $penyewaan->kode,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        return response()->json([
            'data' => (new PenyewaanResource($penyewaan->fresh()))->resolve(),
            'pesan' => 'Status pemesanan sewa diperbarui.',
        ]);
    }

    /**
     * Kwitansi sewa kendaraan.
     *
     * Isinya mengikuti keadaan pesanannya: sebelum unit kembali yang tampil
     * estimasi biaya sewa, sesudahnya yang tampil total termasuk denda. Denda
     * dirinci per jenis supaya penyewa bisa melihat asal angkanya — nota yang
     * hanya menyebut satu total selalu berakhir jadi perdebatan.
     */
    public function kwitansi(PenyewaanKendaraan $penyewaan, Request $request)
    {
        $sudahKembali = (bool) $penyewaan->dikembalikan_pada;

        $rincian = array_filter([
            'Kendaraan' => $penyewaan->nama_kendaraan.' ('.$penyewaan->transmisi.')',
            'Sopir' => $penyewaan->dengan_sopir ? 'Dengan sopir' : 'Lepas kunci',
            'Mulai' => $penyewaan->jadwal_mulai?->translatedFormat('j F Y, H:i'),
            'Ditunggu kembali' => $penyewaan->jadwal_selesai?->translatedFormat('j F Y, H:i'),
            'Kembali pada' => $penyewaan->dikembalikan_pada?->translatedFormat('j F Y, H:i'),
            'Durasi' => $penyewaan->durasi_label,
            'Lokasi pengantaran' => $penyewaan->lokasi_antar,
            'Lokasi pengembalian' => $penyewaan->lokasi_kembali,
            'Penyewa' => $penyewaan->nama,
            'WhatsApp' => $penyewaan->whatsapp,
        ]);

        $nota = NotaSewa::untuk($penyewaan);

        /*
         | Angka besar di kepala nota adalah yang HARUS DIBAYAR penyewa, bukan
         | total tagihannya.
         |
         | Selama yang dipajang totalnya, penyewa yang sudah menyetor uang muka
         | membaca angka penuh dan mengira diminta membayar DP untuk kedua
         | kalinya. Totalnya tetap ada — dirinci lengkap di bawah, lalu
         | dikurangi pembayarannya baris demi baris.
         */
        $adaBayar = ! empty($nota['pembayaran']);

        $isi = BerkasKwitansi::buat(
            $sudahKembali ? 'Nota Akhir Sewa Kendaraan' : 'Rincian Pemesanan Sewa Kendaraan',
            $penyewaan->kode,
            $rincian,
            $penyewaan->catatan_denda ?: $penyewaan->catatan,
            $adaBayar ? $nota['sisa'] : 'Rp '.number_format($penyewaan->total_tagihan, 0, ',', '.'),
            match (true) {
                $adaBayar && ($nota['lunas'] ?? false) => 'Sudah lunas',
                $adaBayar => 'Sisa yang harus dibayar',
                $sudahKembali => 'Total termasuk denda',
                default => 'Estimasi biaya sewa',
            },
            $sudahKembali ? 'Nota Akhir' : 'Belum Dibayar',
            // Biaya dan denda dijumlahkan di notanya sendiri. Sebelumnya denda
            // hanya jadi baris keterangan di antara data lain dan tidak pernah
            // ditambahkan, jadi penyewa harus menjumlahkan sendiri dari berkas
            // yang seharusnya menjawab itu.
            nota: $nota,
        );

        abort_if($isi === null, 503, 'Kwitansi gagal dibuat.');

        $this->catat($request, 'unduh kwitansi sewa', ['kode' => $penyewaan->kode]);

        return response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                .BerkasKwitansi::namaBerkas($sudahKembali ? 'nota-sewa' : 'rincian-sewa', $penyewaan->kode).'"',
        ]);
    }

    /**
     * Mengunggah foto berkas jaminan penyewa (KTP, SIM, dan sejenisnya).
     *
     * Jalur tersendiri karena bentuknya berkas, bukan JSON — dan karena ini
     * data pribadi, pengunggahannya dicatat seperti pembukaan riwayat
     * kesehatan.
     */
    public function berkasJaminan(PenyewaanKendaraan $penyewaan, Request $request): JsonResponse
    {
        // Medannya bernama "gambar", sama seperti unggahan foto paket dan
        // armada. Sempat bernama "berkas" di sini saja, dan akibatnya setiap
        // unggahan ditolak dengan "Kolom berkas wajib diisi" padahal berkasnya
        // memang terkirim — hanya namanya yang tidak dicari.
        $request->validate(['gambar' => 'required|image|max:8192']);

        $penyewaan->update([
            'berkas_jaminan' => GambarWebp::simpan($request->file('gambar'), 'jaminan'),
        ]);

        $this->catat($request, 'unggah berkas jaminan penyewa', ['kode' => $penyewaan->kode]);

        return response()->json([
            'data' => (new PenyewaanResource($penyewaan->fresh()))->resolve(),
            'pesan' => 'Berkas jaminan tersimpan.',
        ]);
    }

    /**
     * Mengirim nota akhir ke penyewa sebagai bukti penagihan.
     *
     * Berkasnya sama persis dengan yang bisa diunduh admin, jadi tidak ada dua
     * versi angka yang beredar. Kegagalan mengirim tidak membatalkan catatan
     * serah terimanya — itu sudah tersimpan sebelum ini dipanggil.
     */
    private static function kirimNotaAkhir(PenyewaanKendaraan $penyewaan): void
    {
        if (blank($penyewaan->email)) {
            return;
        }

        $rincian = array_filter([
            'Kendaraan' => $penyewaan->nama_kendaraan.' ('.$penyewaan->transmisi.')',
            'Kembali pada' => $penyewaan->dikembalikan_pada?->translatedFormat('j F Y, H:i'),
            'Ditunggu kembali' => $penyewaan->jadwal_selesai?->translatedFormat('j F Y, H:i'),
            'Lokasi pengembalian' => $penyewaan->lokasi_kembali,
            'Total tagihan' => 'Rp '.number_format($penyewaan->total_tagihan, 0, ',', '.'),
        ]);

        $berkas = BerkasKwitansi::buat(
            'Nota Akhir Sewa Kendaraan',
            $penyewaan->kode,
            $rincian,
            $penyewaan->catatan_denda,
            'Rp '.number_format($penyewaan->total_tagihan, 0, ',', '.'),
            'Total termasuk denda',
            'Nota Akhir',
            nota: NotaSewa::untuk($penyewaan),
        );

        $adaDenda = $penyewaan->total_denda > 0;

        KirimPemberitahuan::kirim(
            'Unit Kembali & Nota Akhir',
            $penyewaan->kode,
            $rincian,
            $penyewaan->catatan_denda,
            [],
            $berkas ? [BerkasKwitansi::namaBerkas('nota-sewa', $penyewaan->kode) => $berkas] : [],
            pelanggan: new SalinanPelanggan(
                email: $penyewaan->email,
                judul: $adaDenda ? 'Nota Akhir Sewa — Ada Denda' : 'Terima Kasih, Unit Sudah Kembali',
                langkah: $adaDenda
                    ? 'Unit sudah kembali dan diperiksa. Ada denda yang perlu diselesaikan, '
                        ."rinciannya ada di lampiran surat ini — bagian mana, alasannya, dan berapa.\n\n"
                        .'Bila menurut Anda ada yang tidak sesuai, hubungi kami lewat WhatsApp sebelum '
                        .'membayar; hasil pemeriksaan saat unit diserahkan kami simpan dan bisa dibandingkan.'
                    : 'Unit sudah kembali dan diperiksa, tidak ada denda. Terima kasih sudah menjaga '
                        .'kendaraannya. Nota akhirnya terlampir untuk arsip Anda.',
            ),
        );
    }

    /**
     * Serah terima unit: saat diserahkan, dan saat kembali.
     *
     * Satu jalur untuk dua kejadian, karena bentuk datanya sama — kilometer,
     * bahan bakar, dan hasil pemeriksaan per bagian. Yang membedakan hanya
     * kapan diisinya.
     *
     * Denda TIDAK dihitung sendiri lalu langsung ditagihkan. Sistem hanya
     * mengusulkan angkanya; yang menetapkan tetap admin, karena alasan telat
     * kadang memang di luar kuasa penyewa. Yang penting angkanya bisa
     * dijelaskan asal-usulnya, bukan muncul begitu saja.
     */
    public function serahTerima(PenyewaanKendaraan $penyewaan, Request $request): JsonResponse
    {
        /*
         | Bagian yang berlaku untuk jenis unit yang disewa.
         |
         | Yang SUDAH tersimpan di lembar ini ikut diizinkan walaupun bagiannya
         | belakangan dinonaktifkan atau dicabut dari jenis ini. Tanpa itu,
         | membuka lembar lama lalu menekan simpan akan menghapus diam-diam
         | hasil pemeriksaan yang sudah dicatat — dan itulah satu-satunya bukti
         | ketika penyewa membantah adanya kerusakan.
         */
        $bagian = array_unique(array_merge(
            \App\Support\Pemeriksaan::kunci($penyewaan->kendaraan?->type),
            array_keys($penyewaan->kondisi_awal ?? []),
            array_keys($penyewaan->kondisi_akhir ?? []),
        ));
        $kondisi = implode(',', array_keys(config('orcha.kondisi_pemeriksaan')));

        $data = $request->validate([
            'diserahkan_pada' => 'nullable|date',
            'dikembalikan_pada' => 'nullable|date',
            'kilometer_awal' => 'nullable|integer|min:0|max:9999999',
            'kilometer_akhir' => 'nullable|integer|min:0|max:9999999',
            'bahan_bakar_awal' => 'nullable|string|max:20',
            'bahan_bakar_akhir' => 'nullable|string|max:20',
            'jaminan' => 'nullable|string|max:191',
            'kondisi_awal' => 'nullable|array',
            'kondisi_awal.*' => 'in:'.$kondisi,
            'kondisi_akhir' => 'nullable|array',
            'kondisi_akhir.*' => 'in:'.$kondisi,
            'denda_keterlambatan' => 'nullable|integer|min:0',
            'denda_kerusakan' => 'nullable|integer|min:0',
            'denda_lain' => 'nullable|integer|min:0',
            'catatan_denda' => 'nullable|string|max:1000',
            // Rincian yang DITETAPKAN admin, bukan usulan sistem. Disimpan
            // supaya alasan tiap rupiah tetap bisa ditunjukkan setelah kondisi
            // unit diperbarui — lihat catatan di bawah soal kondisi awal.
            'rincian_denda' => 'nullable|array|max:30',
            'rincian_denda.*.bagian' => 'required|string|max:100',
            'rincian_denda.*.dari' => 'nullable|string|max:50',
            'rincian_denda.*.jadi' => 'nullable|string|max:50',
            'rincian_denda.*.biaya' => 'required|integer|min:0',
        ]);

        // Bagian yang tidak dikenal ditolak diam-diam, supaya perbandingan
        // kondisi awal dan akhir selalu memakai daftar yang sama.
        foreach (['kondisi_awal', 'kondisi_akhir'] as $kunci) {
            if (isset($data[$kunci])) {
                $data[$kunci] = array_intersect_key($data[$kunci], array_flip($bagian));
            }
        }

        // Kondisi saat unit DISERAHKAN dibekukan begitu tercatat.
        //
        // Lembar serah terima mengisi kolom itu dari kondisi terkini unit bila
        // masih kosong. Wajar untuk pengisian pertama — tapi bila lembarnya
        // dibuka lagi setelah pemeriksaan, "kondisi terkini" sudah termasuk
        // kerusakan yang baru saja dicatat, dan menyimpannya kembali membuat
        // awal dan akhir jadi sama persis. Selisihnya hilang, usulan dendanya
        // ikut hilang, dan tidak ada lagi bukti bahwa unit tadinya mulus.
        //
        // Yang sudah terjadi tidak boleh ditulis ulang oleh keadaan sesudahnya.
        if (filled($penyewaan->kondisi_awal)) {
            unset($data['kondisi_awal']);
        }

        $penyewaan->update(array_filter($data, fn ($nilai) => $nilai !== null));

        // Unit membawa keadaannya sendiri ke sewa berikutnya. Tanpa ini, admin
        // mengetik ulang daftar lecet lama setiap kali unit disewakan — dan
        // yang lupa diketik akan tertagih ke penyewa berikutnya.
        if (filled($data['kondisi_akhir'] ?? null) && $penyewaan->kendaraan) {
            $penyewaan->kendaraan->update([
                'kondisi_terkini' => $data['kondisi_akhir'],
                'kondisi_diperiksa_pada' => now(),
            ]);
        }

        // Unit yang sudah kembali berarti sewanya selesai. Status yang harus
        // diingat sendiri adalah status yang paling sering tertinggal.
        if (filled($data['dikembalikan_pada'] ?? null) && ! in_array($penyewaan->status, ['selesai', 'batal'], true)) {
            $penyewaan->update(['status' => 'selesai']);
        }

        // Nota akhir dikirim ke penyewa begitu unitnya kembali. Denda yang
        // hanya disebut lisan di loket gampang jadi perdebatan seminggu
        // kemudian; yang dikirim ini menyebut bagian mana, kenapa, dan berapa.
        if (filled($data['dikembalikan_pada'] ?? null)) {
            self::kirimNotaAkhir($penyewaan->fresh());
        }

        $this->catat($request, 'catat serah terima kendaraan', [
            'kode' => $penyewaan->kode,
            'dikembalikan' => $penyewaan->dikembalikan_pada?->toDateTimeString(),
            'total_denda' => $penyewaan->fresh()->total_denda,
        ]);

        return response()->json([
            'data' => (new PenyewaanResource($penyewaan->fresh()))->resolve(),
            'pesan' => 'Catatan serah terima kendaraan tersimpan.',
        ]);
    }
}
