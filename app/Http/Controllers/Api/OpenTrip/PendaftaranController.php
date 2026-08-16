<?php

namespace App\Http\Controllers\Api\OpenTrip;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OpenTrip\PendaftaranResource;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\BerkasKwitansi;
use App\Support\RincianBiaya;
use App\Support\TagihanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendaftaranController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = PendaftaranOpenTrip::query()
            ->withCount('riwayatKesehatan')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('nama_paket', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PendaftaranResource::class);
    }

    /**
     * Satu pendaftaran, selengkapnya.
     *
     * Admin yang membuka satu pelanggan biasanya sedang menjawab satu
     * pertanyaan: sudah bayar berapa, siapa saja yang ikut, dan apakah ada
     * pengajuan pembatalan. Ketiganya dikirim sekalian di sini supaya lemon
     * tidak perlu memanggil tiga jalur untuk menggambar satu halaman.
     *
     * Riwayat kesehatan TETAP tidak ikut — jalurnya sendiri, dan setiap
     * pembukaannya dicatat.
     */
    public function show(PendaftaranOpenTrip $pendaftaran): JsonResponse
    {
        $data = (new PendaftaranResource($pendaftaran->loadCount('riwayatKesehatan')))->resolve();

        $data['tagihan'] = TagihanPesanan::untuk($pendaftaran);

        $data['pembayaran'] = KonfirmasiPembayaran::where('kode', $pendaftaran->kode)
            ->latest('id')
            ->get()
            ->map(fn ($bayar) => [
                'id' => $bayar->id,
                'jenis' => $bayar->jenis,
                'jenis_label' => $bayar->jenis_label,
                'nominal' => $bayar->nominal,
                'nominal_formatted' => $bayar->nominal_formatted,
                'tanggal_transfer' => $bayar->tanggal_transfer?->toDateString(),
                'bank_pengirim' => $bayar->bank_pengirim,
                'atas_nama_pengirim' => $bayar->atas_nama_pengirim,
                'bukti' => $bayar->bukti,
                'catatan' => $bayar->catatan,
                'status' => $bayar->status,
                'status_label' => $bayar->status_label,
                'catatan_admin' => $bayar->catatan_admin,
                'dibuat_pada' => $bayar->created_at?->toIso8601String(),
            ])
            ->all();

        $pembatalan = Pembatalan::where('kode_pendaftaran', $pendaftaran->kode)->latest('id')->first();

        $data['pembatalan'] = $pembatalan ? [
            'id' => $pembatalan->id,
            'nama_pemohon' => $pembatalan->nama_pemohon,
            'alasan_label' => $pembatalan->alasan_label,
            'penjelasan' => $pembatalan->penjelasan,
            'jumlah_dibatalkan' => $pembatalan->jumlah_dibatalkan,
            'rekening' => $pembatalan->bank.' · '.$pembatalan->nomor_rekening
                .' a.n. '.$pembatalan->atas_nama_rekening,
            'status' => $pembatalan->status,
            'dibuat_pada' => $pembatalan->created_at?->toIso8601String(),
        ] : null;

        return response()->json(['data' => $data]);
    }

    /**
     * Riwayat kesehatan sengaja dipisah ke jalur sendiri. Data ini sensitif,
     * jadi Phoenix harus memintanya secara khusus, bukan ikut terbawa daftar.
     */
    public function riwayatKesehatan(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $this->catat($request, 'membuka riwayat kesehatan', ['kode' => $pendaftaran->kode]);

        return response()->json([
            'data' => $pendaftaran->riwayatKesehatan->map(fn ($riwayat) => [
                'id' => $riwayat->id,
                'nama_peserta' => $riwayat->nama_peserta,
                'usia' => $riwayat->usia,
                'jenis_kelamin' => $riwayat->jenis_kelamin,
                'tinggi_badan' => $riwayat->tinggi_badan,
                'berat_badan' => $riwayat->berat_badan,
                'golongan_darah' => $riwayat->golongan_darah,
                'riwayat_penyakit' => $riwayat->riwayat_penyakit,
                'kondisi_khusus' => $riwayat->kondisi_khusus ?? [],
                'riwayat_operasi' => $riwayat->riwayat_operasi,
                'alergi' => $riwayat->alergi,
                'pantangan_makanan' => $riwayat->pantangan_makanan,
                'obat_rutin' => $riwayat->obat_rutin,
                'pantangan_kegiatan' => $riwayat->pantangan_kegiatan,
                'kemampuan_renang' => $riwayat->kemampuan_renang,
                'asuransi' => $riwayat->asuransi,
                'kontak_darurat' => [
                    'nama' => $riwayat->kontak_darurat_nama,
                    'hubungan' => $riwayat->kontak_darurat_hubungan,
                    'hp' => $riwayat->kontak_darurat_hp,
                ],
                'catatan_tambahan' => $riwayat->catatan_tambahan,
                'ada_catatan_khusus' => $riwayat->ada_catatan_khusus,
                // Bukan sekadar "ada catatan": tinggi menuntut kesiapan sebelum
                // berangkat, sedang cukup diingat di lapangan.
                'tingkat_perhatian' => $riwayat->tingkat_perhatian,
                'alasan_perhatian' => $riwayat->alasan_perhatian,
                'alasan_catatan' => $riwayat->alasan_catatan,
                'dibuat_pada' => $riwayat->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Kwitansi pendaftaran, berkas yang sama persis dengan yang dikirim ke
     * pelanggan lewat surat.
     *
     * Dibuat di sini, bukan digambar ulang di lemon: kalau ada dua tempat yang
     * membuat kwitansi, cepat atau lambat keduanya berbeda isi — dan yang
     * dipegang pelanggan berbeda dengan yang dipegang admin.
     *
     * Gunanya untuk jaga-jaga saat surat tidak sampai: admin bisa mengunduh
     * lalu mengirimkannya lewat WhatsApp.
     */
    public function kwitansi(PendaftaranOpenTrip $pendaftaran, Request $request)
    {
        $biaya = RincianBiaya::untuk($pendaftaran->paket, (int) $pendaftaran->jumlah_peserta);

        $rincian = [
            'Paket' => $pendaftaran->nama_paket,
            'Keberangkatan' => $pendaftaran->tanggal_berangkat?->translatedFormat('j F Y') ?: '—',
            'Pemesan' => $pendaftaran->nama,
            'WhatsApp' => $pendaftaran->whatsapp,
            'Email' => $pendaftaran->email,
            'Jumlah peserta' => $pendaftaran->jumlah_peserta.' orang',
            'Peserta & titik jemput' => collect($pendaftaran->peserta)
                ->map(fn ($satu) => $satu['nama'].' — '.($satu['titik_jemput'] ?: 'belum dipilih'))
                ->implode("\n"),
        ];

        $isi = BerkasKwitansi::buat(
            'Rincian Biaya Pendaftaran',
            $pendaftaran->kode,
            $rincian,
            $pendaftaran->catatan,
            $biaya ? $biaya['dp_teks'] : null,
            $biaya ? 'Dibayar sekarang · DP '.$biaya['dp_persen'].'%' : null,
            'Belum Dibayar',
            biaya: $biaya,
        );

        abort_if($isi === null, 503, 'Kwitansi gagal dibuat.');

        $this->catat($request, 'unduh kwitansi pendaftaran', ['kode' => $pendaftaran->kode]);

        return response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                .BerkasKwitansi::namaBerkas('rincian-biaya', $pendaftaran->kode).'"',
        ]);
    }

    public function ubahStatus(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('orcha.status_pendaftaran'))),
        ]);

        $sebelum = $pendaftaran->status;
        $pendaftaran->update($data);

        $this->catat($request, 'ubah status pendaftaran', [
            'kode' => $pendaftaran->kode,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        return response()->json([
            'data' => (new PendaftaranResource($pendaftaran->fresh()))->resolve(),
            'pesan' => 'Status pendaftaran diperbarui.',
        ]);
    }
}
