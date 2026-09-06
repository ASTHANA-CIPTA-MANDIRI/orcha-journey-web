<?php

namespace App\Http\Controllers\Api\OpenTrip;

use App\Http\Controllers\Api\ApiController;
use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\RencanaAngsuran;
use App\Support\TagihanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rencana angsuran, dikelola admin dari lemon.
 *
 * BERAPA KALI DITENTUKAN SISTEM. Admin memilih dari yang diizinkan, tidak
 * pernah mengetik angkanya sendiri — dan batas itu ditegakkan di sini, bukan
 * hanya di layar. Layar bisa dilewati; endpoint tidak.
 */
class AngsuranController extends ApiController
{
    /**
     * Apa yang boleh ditawarkan untuk pesanan ini, berikut rencana yang
     * sedang berlaku bila ada.
     *
     * Jadwalnya ikut dihitung untuk SETIAP jumlah yang diizinkan, supaya admin
     * bisa menyebutkan nominalnya saat berbicara dengan pelanggan. Menjanjikan
     * "boleh 3x" tanpa menyebut angkanya adalah janji yang tidak bisa dinilai
     * orang yang sedang menghitung kemampuannya.
     */
    public function show(PendaftaranOpenTrip $pendaftaran): JsonResponse
    {
        $maks = RencanaAngsuran::maksTermin($pendaftaran);

        $pilihan = [];

        for ($n = 2; $n <= $maks; $n++) {
            $jadwal = RencanaAngsuran::susun($pendaftaran, $n);

            if ($jadwal === []) {
                continue;
            }

            $pilihan[] = [
                'jumlah_termin' => $n,
                'termin' => array_map(fn ($b) => [
                    'urutan' => $b['urutan'],
                    'label' => $b['label'],
                    'nominal' => $b['nominal'],
                    'nominal_teks' => 'Rp '.number_format($b['nominal'], 0, ',', '.'),
                    'jatuh_tempo' => $b['jatuh_tempo']->toDateString(),
                ], $jadwal),
            ];
        }

        $tagihan = TagihanPesanan::untuk($pendaftaran);

        return response()->json([
            'data' => [
                'maks_termin' => $maks,
                'boleh_diangsur' => $maks >= 2,

                /*
                 | Lunas dikirim terpisah dari boleh_diangsur.
                 |
                 | Keduanya sama-sama menghasilkan "tidak boleh", tetapi
                 | artinya berlawanan: yang satu belum memenuhi syarat, yang
                 | satu sudah selesai. Layar yang cuma menerima "tidak boleh"
                 | terpaksa menampilkan penolakan untuk pesanan yang justru
                 | tidak punya masalah apa pun.
                 */
                'lunas' => (bool) ($tagihan['lunas'] ?? false),

                /*
                 | Total tagihan ikut dikirim sebagai KONTEKS keputusan.
                 |
                 | Admin yang memilih "3x" sedang membagi sebuah angka, dan
                 | angka itu harus terlihat di layar yang sama — bukan diingat
                 | dari kartu lain yang sudah tergulung ke atas. Dihitung di
                 | sini, bukan dijumlahkan dari terminnya di Blade: penjumlahan
                 | di tampilan adalah tempat selisih receh lahir tanpa ada yang
                 | menyadarinya.
                 */
                'total' => (int) ($tagihan['total'] ?? 0),
                'total_teks' => $tagihan['total_teks'] ?? null,
                'sisa_teks' => $tagihan['sisa_teks'] ?? null,
                'ingatkan_hari_sebelum' => (int) config('orcha.pembayaran.angsuran.ingatkan_hari_sebelum', 3),
                // Alasan penolakan disebut, bukan sekadar "tidak boleh". Admin
                // yang harus menjelaskannya ke pelanggan butuh kalimatnya.
                'alasan' => $maks >= 2 ? null : self::alasanTolak($pendaftaran),
                'pilihan' => $pilihan,
                'rencana' => self::bentukRencana(Angsuran::aktifUntuk($pendaftaran->kode)),
            ],
        ]);
    }

    public function store(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'jumlah_termin' => ['required', 'integer', 'min:2'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $jadwal = RencanaAngsuran::susun($pendaftaran, (int) $data['jumlah_termin']);

        /*
         | Ditolak di sini, bukan hanya di layar.
         |
         | Batas terminnya bergantung pada tanggal berangkat, dan tanggal itu
         | bisa berubah antara layar dibuka dan tombol ditekan. Layar yang
         | menampilkan "boleh 3x" lima menit lalu bukan alasan untuk
         | menerbitkan jadwal yang hari ini sudah tidak muat.
         */
        if ($jadwal === []) {
            return response()->json([
                'pesan' => 'Jumlah termin itu tidak diizinkan untuk pesanan ini. '
                    .'Maksimal '.RencanaAngsuran::maksTermin($pendaftaran).' termin.',
            ], 422);
        }

        $rencana = DB::transaction(function () use ($pendaftaran, $data, $jadwal, $request) {
            // Satu pesanan hanya boleh punya satu rencana aktif. Yang lama
            // ditandai dibatalkan, tidak dihapus: saat ada sengketa yang perlu
            // dijawab adalah "dulu dijanjikan apa".
            Angsuran::aktif()->where('kode', $pendaftaran->kode)
                ->update(['dibatalkan_pada' => now()]);

            $rencana = Angsuran::create([
                'kode' => $pendaftaran->kode,
                'jumlah_termin' => count($jadwal),
                'total' => $pendaftaran->omzet,
                'dibuat_oleh' => $request->attributes->get('admin_pemanggil'),
                'catatan' => $data['catatan'] ?? null,
            ]);

            foreach ($jadwal as $baris) {
                $rencana->termin()->create([
                    'urutan' => $baris['urutan'],
                    'nominal' => $baris['nominal'],
                    'jatuh_tempo' => $baris['jatuh_tempo'],
                ]);
            }

            return $rencana;
        });

        $this->catat($request, 'membuat rencana angsuran', [
            'kode' => $pendaftaran->kode,
            'termin' => $rencana->jumlah_termin,
        ]);

        return response()->json([
            'data' => self::bentukRencana($rencana->fresh('termin')),
            'pesan' => 'Rencana angsuran '.$rencana->jumlah_termin.' termin dibuat.',
        ], 201);
    }

    public function destroy(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $rencana = Angsuran::aktifUntuk($pendaftaran->kode);

        if (! $rencana) {
            return response()->json(['pesan' => 'Tidak ada rencana angsuran yang berlaku.'], 404);
        }

        /*
         | Rencana yang seluruh terminnya sudah tertutup TIDAK bisa dibatalkan.
         |
         | Bukan sekadar tidak berguna — merusak. Yang tersisa dari rencana
         | yang sudah tuntas cuma catatannya: bukti bahwa pelanggan diberi
         | keringanan, jadwalnya apa, dan ia menyelesaikannya. Itu persis yang
         | dicari saat belakangan ada yang dipersoalkan, dan sekali ditandai
         | batal ia tidak lagi terbaca sebagai jadwal yang berjalan.
         |
         | Dijaga DI SINI, bukan cuma dengan menyembunyikan tombolnya. Layar
         | boleh dilewati; endpoint tidak.
         */
        if (collect(RencanaAngsuran::posisi($rencana))->every(fn ($t) => $t['status'] === 'lunas')) {
            return response()->json([
                'pesan' => 'Rencana ini sudah selesai — seluruh terminnya lunas, '
                    .'jadi tidak ada jadwal yang bisa dibatalkan.',
            ], 422);
        }

        $rencana->update(['dibatalkan_pada' => now()]);

        $this->catat($request, 'membatalkan rencana angsuran', ['kode' => $pendaftaran->kode]);

        /*
         | Tagihannya TIDAK berubah, dan itu perlu disebut.
         |
         | Membatalkan jadwal hanya menghapus jadwalnya; uang yang sudah masuk
         | tetap masuk, sisanya tetap sisa, dan tenggatnya kembali ke H-5
         | seperti pesanan biasa.
         */
        return response()->json([
            'pesan' => 'Rencana angsuran dibatalkan. Sisa tagihan kembali jatuh tempo H-'
                .config('orcha.pembayaran.pelunasan_hari_sebelum').' sebelum berangkat.',
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function bentukRencana(?Angsuran $rencana): ?array
    {
        if (! $rencana) {
            return null;
        }

        return [
            'id' => $rencana->id,
            'jumlah_termin' => $rencana->jumlah_termin,
            'catatan' => $rencana->catatan,
            'dibuat_oleh' => $rencana->dibuat_oleh,
            'dibuat_pada' => $rencana->created_at?->toIso8601String(),
            'termin' => array_map(fn ($t) => [
                'urutan' => $t['urutan'],
                'nominal' => $t['nominal'],
                'nominal_teks' => 'Rp '.number_format($t['nominal'], 0, ',', '.'),
                'jatuh_tempo' => $t['jatuh_tempo']->toDateString(),
                'kurang' => $t['kurang'],
                'status' => $t['status'],
            ], RencanaAngsuran::posisi($rencana)),
        ];
    }

    private static function alasanTolak(PendaftaranOpenTrip $pendaftaran): string
    {
        $tagihan = TagihanPesanan::untuk($pendaftaran);

        /*
         | Diperiksa PALING DULU, sebelum harga dan sebelum kalender.
         |
         | Pesanan lunas ditolak oleh maksTermin() lewat cabangnya sendiri,
         | tetapi alasannya dulu jatuh ke pemeriksaan berikutnya — dan yang
         | terbaca admin adalah "waktu sampai tenggat tidak cukup" untuk
         | pesanan yang berangkatnya masih puluhan hari lagi. Alasan yang
         | dikarang lebih buruk daripada tidak ada alasan: yang membacanya
         | mulai membetulkan hal yang tidak rusak.
         */
        if ($tagihan === [] || ($tagihan['lunas'] ?? false)) {
            return 'Pesanan ini sudah lunas — tidak ada sisa tagihan yang bisa diangsur.';
        }

        $total = $pendaftaran->omzet;
        $ambang = collect(config('orcha.pembayaran.angsuran.tangga', []))->min('min_total') ?? 0;

        if ($total < $ambang) {
            return 'Total tagihan Rp '.number_format($total, 0, ',', '.')
                .' di bawah ambang angsuran (Rp '.number_format($ambang, 0, ',', '.').').';
        }

        return 'Waktu sampai tenggat pelunasan tidak cukup untuk satu termin pun. '
            .'Angsuran butuh jarak minimal '
            .config('orcha.pembayaran.angsuran.jarak_hari').' hari antar termin.';
    }
}
