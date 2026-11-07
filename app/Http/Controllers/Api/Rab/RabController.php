<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Mail\PenawaranRab;
use App\Models\Etalase\KatalogDestinasi;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\MusimHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabItinerary;
use App\Models\Rab\RiwayatHarga;
use App\Models\Rab\TemplatItinerary;
use App\Models\Rab\Vendor;
use App\Models\Rab\VersiPenawaran;
use App\Support\Rab\BerkasRab;
use App\Support\Rab\HargaHari;
use App\Support\Rab\HitungRab;
use App\Support\Rab\PaketDariRab;
use App\Support\RincianBiaya;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * RAB & itinerary private trip.
 *
 * Alur yang dilayani: admin membuat RAB untuk sebuah provinsi → biaya wajib
 * provinsi itu langsung terisi → admin mencentang destinasi untuk itinerary →
 * "Tarik biaya" menambahkan tiket destinasi yang dipilih → admin memilih bus
 * dan hotel dari master → mengatur margin → mengunduh PDF penawaran →
 * setelah disetujui, "Jadikan Pendaftaran".
 */
class RabController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = Rab::query()
            // Dimuat sekaligus untuk seluruh halaman: tiap baris daftar
            // menampilkan totalnya, dan total diturunkan dari baris biaya.
            ->with('biaya.master')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                function ($s) use ($cari) {
                    $s->where('kode', 'like', "%{$cari}%")
                        ->orWhere('nama_pelanggan', 'like', "%{$cari}%")
                        ->orWhere('judul', 'like', "%{$cari}%")
                        ->orWhere('whatsapp', 'like', "%{$cari}%");

                    /*
                     | Nomor dibandingkan ANGKANYA saja.
                     |
                     | Yang diketik admin datang dari WhatsApp — "+62 812-3400-0111"
                     | — sedangkan yang tersimpan "081234000111". Tanpa ini
                     | pencarian nomor selalu gagal, dan yang terbaca "RAB-nya
                     | tidak ada".
                     */
                    $angka = preg_replace('/\D/', '', $cari);

                    if (strlen($angka) >= 7) {
                        $s->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp,' ',''),'-',''),'(',''),')',''),'+',''),'.','') LIKE ?",
                            ['%'.Vendor::ekorNomor($angka).'%']
                        );
                    }
                }
            ))
            ->when($request->string('status')->toString(), fn ($q, $st) => $q->where('status', $st))
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $q->where('provinsi', $p))
            ->when($request->string('jenis')->toString(), fn ($q, $j) => $q->where('jenis', $j))
            // Penawaran yang masa berlakunya lewat: angkanya sudah dihitung
            // dan ditampilkan per baris, tetapi selama ini tidak bisa dipakai
            // menyaring — jadi "mana yang sudah basi" tetap dipelototi.
            ->when($request->boolean('kedaluwarsa'), fn ($q) => $q
                ->whereNotNull('berlaku_sampai')
                ->whereDate('berlaku_sampai', '<', now()->toDateString())
                ->whereNotIn('status', ['batal', 'disetujui']))
            ->tap(fn ($q) => $this->urutkanRab($q, $request->string('urut')->toString()))
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta(
            $daftar,
            fn () => $daftar->getCollection()->map(function (Rab $rab) {
                $r = HitungRab::ringkas($rab);

                return $this->kepala($rab) + [
                    'harga_total_teks' => $r['harga_total_teks'],
                    'harga_per_orang_teks' => $r['harga_per_orang_teks'],
                    'untung_teks' => $r['untung_teks'],
                    'persen_untung' => $r['persen_untung'],
                    'jumlah_biaya' => count($r['baris']),
                ];
            })->all(),
            ['kosakata' => MasterHargaController::kosakataRab(), 'ringkas' => $this->ringkasRab()],
        );
    }

    /**
     * Bahan penyusun RAB untuk satu provinsi, dalam satu panggilan.
     *
     * Layar penyusun butuh empat hal sekaligus — destinasi untuk itinerary,
     * master harga untuk dipilih, daftar provinsi, dan paket induk untuk
     * "Jadikan Pendaftaran". Empat panggilan terpisah berarti empat kali
     * menunggu setiap layar dibuka.
     */
    /**
     * Mencari TEMPAT di seluruh provinsi, dari dua daftar sekaligus.
     *
     * Kotak cari di penyusun RAB dulu hanya melihat katalog destinasi milik
     * provinsi RAB-nya. Dua pembatasan sekaligus, dan keduanya menyakitkan:
     *
     * - Tempat yang harganya SUDAH ada di master tetapi belum pernah masuk
     *   katalog destinasi tidak ketemu sama sekali. "Baluran" ada tiketnya,
     *   tetapi mengetik "baluran" memulangkan "tidak ada destinasi bernama
     *   baluran" — admin lalu mengetiknya sebagai kegiatan bebas, dan
     *   tiketnya tidak pernah ikut tertarik.
     * - Perjalanan lintas provinsi tidak bisa disusun. RAB hanya punya SATU
     *   kolom provinsi, sementara Jogja–Bromo melewati dua.
     *
     * Nama yang ada di kedua daftar dipulangkan SEKALI, dengan keterangan
     * dari katalog — di sanalah provinsi & daerahnya tercatat.
     */
    public function cariTempat(Request $request): JsonResponse
    {
        $kata = mb_strtolower(trim($request->string('q')->toString()));

        if (mb_strlen($kata) < 2) {
            // Satu huruf mencocokkan hampir segalanya; daftar sepanjang itu
            // bukan hasil pencarian, melainkan katalog yang diacak.
            return response()->json(['data' => []]);
        }

        $cocok = fn (?string ...$teks) => str_contains(
            mb_strtolower(implode(' ', array_filter($teks))), $kata
        );

        // Tiket terusan menandai SELURUH destinasi yang dicakupnya, bukan
        // cuma yang utama — lihat MasterHarga::semuaDestinasi().
        $bertiket = MasterHarga::where('aktif', true)->whereNotNull('destinasi')->get()
            ->flatMap(fn (MasterHarga $m) => $m->semuaDestinasi())
            ->map(fn ($d) => mb_strtolower(trim((string) $d)))->flip();

        $hasil = [];

        foreach (KatalogDestinasi::gabungan() as $nama => $v) {
            if (! $cocok($nama, $v['daerah'] ?? null, $v['provinsi'] ?? null)) {
                continue;
            }

            $hasil[mb_strtolower(trim($nama))] = [
                'nama' => $nama,
                'provinsi' => $v['provinsi'] ?? null,
                'daerah' => $v['daerah'] ?? null,
                'punya_tiket' => $bertiket->has(mb_strtolower(trim($nama))),
                'asal' => 'katalog',
            ];
        }

        /*
         | Destinasi yang hanya hidup di master harga.
         |
         | Tempat yang sudah punya tiket tetapi belum pernah masuk katalog —
         | justru yang paling layak ditawarkan, karena biayanya akan tertarik
         | sendiri begitu dipilih.
         */
        $dariMaster = MasterHarga::query()->where('aktif', true)->whereNotNull('destinasi')
            ->get(['destinasi', 'provinsi', 'daerah']);

        foreach ($dariMaster as $m) {
            $nama = trim((string) $m->destinasi);
            $kunci = mb_strtolower($nama);

            if ($nama === '' || isset($hasil[$kunci]) || ! $cocok($nama, $m->daerah, $m->provinsi)) {
                continue;
            }

            $hasil[$kunci] = [
                'nama' => $nama,
                'provinsi' => $m->provinsi,
                'daerah' => $m->daerah,
                // Selalu true: ia ADA di master harga, itu sebabnya ia di sini.
                'punya_tiket' => true,
                'asal' => 'master',
            ];
        }

        /*
         | Biaya yang BUKAN tempat disebut terpisah, tidak dicampur.
         |
         | Mengetik "hiace" wajar dilakukan, dan menjadikannya kegiatan di
         | itinerary adalah kesalahan yang terlihat benar. Yang dipulangkan
         | cuma namanya, supaya layar bisa menunjuk ke rincian biaya alih-alih
         | mengaku tidak menemukan apa-apa.
         |
         | TETAPI tidak semuanya begitu. "Makan di Restoku Chen-Chen" dan
         | "Check-in Hotel Mawar" adalah biaya SEKALIGUS kejadian berwaktu —
         | keduanya menempati jam di hari itu, dan admin yang mengkliknya
         | memang berharap keduanya ikut. Yang tidak pernah jadi kegiatan cuma
         | yang tidak punya jam: armada, asuransi, operasional tim.
         |
         | Penggolongnya dikirim bersama hasilnya supaya layar tidak perlu
         | menebak sendiri — tebakan kedua di sisi lemon akan berselisih dengan
         | yang di sini suatu hari.
         */
        $kegiatanBerwaktu = ['konsumsi', 'akomodasi'];

        $bukanTempat = MasterHarga::query()->where('aktif', true)->whereNull('destinasi')
            ->get(['id', 'nama', 'kategori', 'catatan'])
            ->filter(fn (MasterHarga $m) => $cocok($m->nama))
            ->map(fn (MasterHarga $m) => [
                'id' => $m->id,
                'nama' => $m->nama,
                'kategori' => $m->kategori,
                'kategori_label' => $m->kategori_label,
                'catatan' => $m->catatan,
                'bisa_jadi_kegiatan' => in_array($m->kategori, $kegiatanBerwaktu, true),
            ])->take(8)->values();

        return response()->json([
            'data' => collect($hasil)->sortBy('nama')->take(30)->values(),
            'bukan_tempat' => $bukanTempat,
        ]);
    }

    public function katalog(Request $request): JsonResponse
    {
        $provinsi = $request->string('provinsi')->toString() ?: null;
        $katalog = KatalogDestinasi::gabungan();

        // Sekali untuk seluruh katalog, bukan satu kueri per destinasi.
        $bertiket = MasterHarga::where('aktif', true)->whereNotNull('destinasi')->get()
            ->flatMap(fn (MasterHarga $m) => $m->semuaDestinasi())->flip();

        $destinasi = collect($katalog)
            ->filter(fn ($v) => ! $provinsi || ($v['provinsi'] ?? null) === $provinsi)
            ->map(fn ($v, $nama) => [
                'nama' => $nama,
                'provinsi' => $v['provinsi'] ?? null,
                'daerah' => $v['daerah'] ?? null,
                // Punya tiket di master? Ditandai supaya admin tahu mana yang
                // biayanya akan tertarik sendiri dan mana yang harus diisi.
                'punya_tiket' => $bertiket->has($nama),
            ])
            ->sortBy('nama')->values();

        $master = MasterHarga::query()->untukProvinsi($provinsi)->with('vendor')
            ->orderBy('kategori')->orderBy('nama')->get()
            ->map(fn (MasterHarga $m) => [
                'id' => $m->id,
                'kategori' => $m->kategori,
                'kategori_label' => $m->kategori_label,
                'nama' => $m->nama,
                'destinasi' => $m->destinasi,
                'destinasi_lain' => $m->destinasi_lain ?? [],
                'daerah' => $m->daerah,
                'satuan' => $m->satuan,
                'satuan_label' => $m->satuan_label,
                'harga' => $m->harga,
                'harga_teks' => RincianBiaya::rupiah($m->harga),
                'kapasitas' => $m->kapasitas,
                'otomatis' => $m->otomatis,
                'umum' => $m->provinsi === null,
                'vendor_id' => $m->vendor_id,
                'vendor' => $m->vendor?->nama,
                'vendor_kontak' => $m->vendor?->kontak,
            ]);

        /*
         | Vendor yang masuk akal untuk provinsi ini, yang paling sering
         | dipakai lebih dulu.
         |
         | Urut abjad membuat vendor yang dipakai tiap minggu terdampar di
         | huruf S, sementara yang sekali setahun duduk di atas. Abjad tetap
         | jadi pemutus bila jumlah pemakaiannya sama.
         */
        $vendor = Vendor::aktif()->untukProvinsi($provinsi)
            ->withCount('harga')
            ->orderByDesc('harga_count')->orderBy('nama')
            ->get(['id', 'nama', 'kontak', 'jenis', 'provinsi'])
            ->map(fn (Vendor $v) => [
                'id' => $v->id, 'nama' => $v->nama, 'kontak' => $v->kontak,
                'jenis' => $v->jenis, 'provinsi' => $v->provinsi,
            ]);

        $daftarProvinsi = collect($katalog)->pluck('provinsi')
            ->merge(MasterHarga::whereNotNull('provinsi')->distinct()->pluck('provinsi'))
            ->filter()->unique()->sort()->values();

        return response()->json(['data' => [
            'provinsi' => $daftarProvinsi,
            'destinasi' => $destinasi,
            'master' => $master,
            'vendor' => $vendor,
            'paket' => TravelPackage::query()->orderBy('name')->get(['id', 'name', 'category'])
                ->map(fn ($p) => ['id' => $p->id, 'nama' => $p->name, 'kategori' => $p->category]),
            /*
             | Musim yang masih berlaku — ikut katalog, bukan dibaca sendiri.
             |
             | Borang master harga perlu satu kotak per musim. Memuatnya lewat
             | panggilan tersendiri berarti satu perjalanan tambahan ke sini
             | tiap kali borang digambar, dan borang itu digambar ulang pada
             | setiap ketikan.
             |
             | Yang DIMATIKAN tidak ikut: kotaknya hanya akan mengundang
             | pengisian tarif yang tidak akan dipakai RAB mana pun.
             */
            'musim' => MusimHarga::berlaku()->map(fn (MusimHarga $m) => [
                'id' => $m->id,
                'nama' => $m->nama,
                'rentang_teks' => $m->rentang_teks,
                'tanggal_mulai' => $m->tanggal_mulai->toDateString(),
                'jumlah_hari' => $m->jumlah_hari,
                'lewat' => $m->lewat,
            ])->values(),
            'kosakata' => MasterHargaController::kosakataRab(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->periksaKepala($request);

        $rab = DB::transaction(function () use ($data, $request) {
            $rab = Rab::create($data + [
                'status' => 'draf',
                'dibuat_oleh' => $request->attributes->get('admin_pemanggil'),
            ]);

            /*
             | Biaya WAJIB provinsinya langsung terisi.
             |
             | Makan, parkir, asuransi — hal yang ada di setiap perjalanan dan
             | selalu lupa ditambahkan satu. Penawaran yang lupa asuransinya
             | tidak ketahuan sampai modalnya habis di lapangan.
             */
            $this->tambahkanDariMaster($rab, MasterHarga::query()->untukProvinsi($rab->provinsi)
                // Lihat tarikBiaya(): yang per kendaraan ditawarkan, tidak
                // ditarik — jenis kendaraannya keputusan per-RAB.
                ->where('otomatis', true)->where('satuan', '!=', 'unit')->get());

            return $rab;
        });

        $this->catat($request, 'buat rab', ['kode' => $rab->kode, 'pelanggan' => $rab->nama_pelanggan]);

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'RAB dibuat.'], 201);
    }

    public function show(Rab $rab): JsonResponse
    {
        return response()->json(['data' => $this->bentuk($rab)]);
    }

    /**
     * Urutan daftar RAB.
     *
     * Bawaannya yang terbaru dulu — itu yang dicari sembilan dari sepuluh
     * kali. Yang lain menjawab pertanyaan tertentu, dan dinamai menurut
     * pertanyaannya.
     */
    private function urutkanRab(Builder $q, string $urut): void
    {
        match ($urut) {
            // Yang berangkatnya paling dekat: daftar yang menuntut
            // keputusan lebih dulu.
            'berangkat' => $q->orderByRaw('tanggal_mulai is null, tanggal_mulai asc'),
            'lama' => $q->oldest('id'),
            'pelanggan' => $q->orderBy('nama_pelanggan'),
            default => $q->latest('id'),
        };
    }

    /**
     * Angka ringkas seluruh tabel, bukan halaman yang terlihat.
     *
     * "Berapa yang sudah dikirim tapi belum dijawab" adalah pertanyaan
     * harian, dan selama ini dijawab dengan menghitung baris sendiri.
     */
    private function ringkasRab(): array
    {
        $perStatus = Rab::query()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->all();

        return [
            'jumlah' => array_sum($perStatus),
            'draf' => (int) ($perStatus['draf'] ?? 0),
            'dikirim' => (int) ($perStatus['dikirim'] ?? 0),
            'disetujui' => (int) ($perStatus['disetujui'] ?? 0),
            'batal' => (int) ($perStatus['batal'] ?? 0),
            'kedaluwarsa' => Rab::whereNotNull('berlaku_sampai')
                ->whereDate('berlaku_sampai', '<', now()->toDateString())
                ->whereNotIn('status', ['batal', 'deal'])
                ->count(),
        ];
    }

    public function update(Rab $rab, Request $request): JsonResponse
    {
        $statusLama = $rab->status;

        $rab->update($this->periksaKepala($request, $rab));

        // Direkam saat BERPINDAH ke "dikirim", bukan tiap kali disimpan:
        // yang ditanyakan saat beda pendapat adalah angka yang dilihat
        // pelanggan, dan itu angka pada saat penawaran keluar.
        if ($rab->status === 'dikirim' && $statusLama !== 'dikirim') {
            $this->rekamVersi($rab->fresh(), $request);
        }

        $this->catat($request, 'ubah rab', ['kode' => $rab->kode]);

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'RAB diperbarui.']);
    }

    /** Menyimpan angka penawaran apa adanya pada saat ia dikirim. */
    private function rekamVersi(Rab $rab, Request $request): void
    {
        $r = HitungRab::ringkas($rab->load('biaya.master'));

        VersiPenawaran::create([
            'rab_id' => $rab->id,
            'urutan' => VersiPenawaran::where('rab_id', $rab->id)->max('urutan') + 1,
            'harga_total' => (int) $r['harga_total'],
            'harga_per_orang' => (int) $r['harga_per_orang'],
            'untung' => (int) $r['untung'],
            'persen_untung' => $r['persen_untung'] ?? null,
            'jumlah_peserta' => (int) $rab->jumlah_peserta,
            'jumlah_baris' => count($r['baris'] ?? []),
            'oleh' => $request->attributes->get('admin_pemanggil'),
            'rincian' => $this->potretRab($rab),
        ]);
    }

    /**
     * Potret utuh RAB untuk disimpan di versi penawaran.
     *
     * Baris biayanya disalin apa adanya, bukan dirujuk lewat id: baris yang
     * dirujuk bisa dihapus atau diubah sesudahnya, dan versi yang isinya
     * ikut berubah bukan lagi catatan tentang apa yang pernah dikirim.
     *
     * @return array<string, mixed>
     */
    private function potretRab(Rab $rab): array
    {
        return [
            'margin_jenis' => $rab->margin_jenis,
            'margin_nilai' => $rab->margin_nilai,
            'pembulatan' => $rab->pembulatan,
            'harga_nego' => $rab->harga_nego,
            'alasan_nego' => $rab->alasan_nego,
            'baris' => $rab->biaya()->orderBy('urutan')->get()->map(fn (RabBiaya $b) => [
                'master_harga_id' => $b->master_harga_id,
                'vendor_id' => $b->vendor_id,
                'kategori' => $b->kategori,
                'nama' => $b->nama,
                'satuan' => $b->satuan,
                'harga_satuan' => $b->harga_satuan,
                'harga_sebab' => $b->harga_sebab,
                'pakai_akhir_pekan' => $b->pakai_akhir_pekan,
                /*
                 | TINGKAT harganya ikut dipotret.
                 |
                 | Tanpa ini, versi yang dipulihkan kehilangan tingkatnya dan
                 | seluruh barisnya kembali terbaca 'hari_kerja' — lalu
                 | "Pakai harga master" menimpa tarif Nataru dengan harga hari
                 | biasa. Penawaran yang dipulihkan harus sama persis dengan
                 | yang dilihat pelanggan, termasuk alasan angkanya.
                 |
                 | Potret LAMA tidak punya kedua kunci ini; RabBiaya::$tingkat
                 | menjatuhkannya kembali ke pakai_akhir_pekan, yang memang
                 | ada di sana.
                 */
                'tingkat_harga' => $b->tingkat_harga,
                'musim_id' => $b->musim_id,
                'kapasitas' => $b->kapasitas,
                /*
                 | Jumlah unit & malam yang ditentukan tangan ikut dipotret.
                 |
                 | Tanpa keduanya, versi yang dipulihkan kembali menghitung
                 | otomatis: tiga kamar jadi lima, satu malam jadi tiga, dan
                 | totalnya tidak lagi sama dengan yang dilihat pelanggan.
                 | Penawaran yang dipulihkan harus sama PERSIS.
                 */
                'unit_manual' => $b->unit_manual,
                'malam_manual' => $b->malam_manual,
                'jumlah' => $b->jumlah,
                'urutan' => $b->urutan,
                'catatan' => $b->catatan,
            ])->all(),
        ];
    }

    /**
     * Memasang kembali penawaran yang pernah dikirim.
     *
     * Seluruh baris biaya yang ada SEKARANG dibuang, lalu diganti dengan
     * salinan dari potret. Bukan digabung: penawaran yang dipulihkan harus
     * sama persis dengan yang dilihat pelanggan, dan baris sisa yang ikut
     * bertahan membuatnya lebih mahal tanpa ada yang menyadarinya.
     *
     * Itinerary TIDAK ikut dipulihkan. Ia rencana perjalanan, bukan angka
     * penawaran — dan memundurkannya berarti membuang kunjungan yang mungkin
     * baru disepakati lewat telepon sesudah penawaran terakhir dikirim.
     */
    public function pulihkanVersi(Rab $rab, VersiPenawaran $versi, Request $request): JsonResponse
    {
        if ($versi->rab_id !== $rab->id) {
            return response()->json(['pesan' => 'Versi itu milik RAB lain.'], 404);
        }

        if (! $versi->bisaDipulihkan()) {
            return response()->json([
                'pesan' => 'Versi ini dicatat sebelum rinciannya ikut disimpan, '
                    .'jadi baris biayanya tidak ada untuk dipasang kembali.',
            ], 422);
        }

        $potret = $versi->rincian;

        DB::transaction(function () use ($rab, $potret) {
            $rab->biaya()->delete();

            foreach ($potret['baris'] as $baris) {
                $rab->biaya()->create($baris);
            }

            $rab->update([
                'margin_jenis' => $potret['margin_jenis'],
                'margin_nilai' => $potret['margin_nilai'],
                'pembulatan' => $potret['pembulatan'],
                'harga_nego' => $potret['harga_nego'],
                'alasan_nego' => $potret['alasan_nego'],
            ]);
        });

        $this->catat($request, 'pulihkan versi rab', [
            'kode' => $rab->kode,
            'versi' => $versi->urutan,
        ]);

        return response()->json([
            'data' => $this->bentuk($rab->fresh()),
            'pesan' => 'Penawaran versi '.$versi->urutan.' dipasang kembali. '
                .'Jadwal perjalanannya tidak ikut diubah.',
        ]);
    }

    /** Daftar versi yang pernah dikirim ke pelanggan. */
    public function versi(Rab $rab): JsonResponse
    {
        $daftar = VersiPenawaran::where('rab_id', $rab->id)->orderByDesc('urutan')->get();
        $kini = HitungRab::ringkas($rab->load('biaya.master'));

        return response()->json([
            'data' => $daftar->map(fn (VersiPenawaran $v) => [
                'id' => $v->id,
                'urutan' => $v->urutan,
                'harga_total_teks' => RincianBiaya::rupiah($v->harga_total),
                'harga_per_orang_teks' => RincianBiaya::rupiah($v->harga_per_orang),
                'untung_teks' => RincianBiaya::rupiah($v->untung),
                'persen_untung' => $v->persen_untung,
                'jumlah_peserta' => $v->jumlah_peserta,
                'jumlah_baris' => $v->jumlah_baris,
                'bisa_dipulihkan' => $v->bisaDipulihkan(),
                'oleh' => $v->oleh,
                'pada' => $v->created_at?->locale('id')->translatedFormat('j M Y, H:i'),
                'pada_teks' => $v->created_at?->locale('id')->diffForHumans(),
                // Selisih terhadap angka SEKARANG — itu yang dicari saat
                // pelanggan menyebut angka yang tidak lagi ada di layar.
                'beda_kini' => $v->harga_total === (int) $kini['harga_total']
                    ? null
                    : (($v->harga_total > (int) $kini['harga_total'] ? 'lebih mahal ' : 'lebih murah ')
                        .RincianBiaya::rupiah(abs($v->harga_total - (int) $kini['harga_total'])).' dari sekarang'),
            ])->all(),
            'meta' => [
                'kode' => $rab->kode,
                'harga_kini_teks' => $kini['harga_total_teks'],
            ],
        ]);
    }

    /**
     * Menyalin sebuah RAB beserta itinerary dan seluruh baris biayanya.
     *
     * Penawaran serupa untuk pelanggan lain adalah pekerjaan yang paling
     * sering diulang di layar ini, dan menyusunnya ulang dari nol bukan
     * cuma lama — tiap baris yang diketik ulang adalah satu kesempatan
     * angka meleset tanpa ada yang menyadarinya.
     *
     * Salinannya selalu DRAFT, dengan kode baru, tanpa kode pendaftaran,
     * dan tanpa riwayat versi: ia penawaran yang belum pernah dikirim.
     */
    public function salin(Rab $rab, Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_pelanggan' => ['nullable', 'string', 'max:120'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'tanggal_mulai' => ['nullable', 'date'],
        ]);

        $salinan = $rab->replicate([
            'kode', 'kode_pendaftaran', 'status', 'created_at', 'updated_at',
        ]);

        $salinan->kode = Rab::kodeBaru();
        $salinan->status = 'draf';
        $salinan->kode_pendaftaran = null;
        $salinan->nama_pelanggan = $data['nama_pelanggan'] ?? $rab->nama_pelanggan;
        $salinan->whatsapp = $data['whatsapp'] ?? $rab->whatsapp;
        $salinan->tanggal_mulai = $data['tanggal_mulai'] ?? $rab->tanggal_mulai;
        // Masa berlaku lama TIDAK ikut: ia dihitung dari tanggal penawaran
        // itu dibuat, dan menyalinnya membuat yang baru lahir sudah
        // kedaluwarsa. Dikosongkan di sini, lalu hook `creating` di model
        // mengisinya dengan tenggat baru terhitung hari ini.
        $salinan->berlaku_sampai = null;
        $salinan->save();

        foreach ($rab->itinerary as $hari) {
            $baru = $hari->replicate(['created_at', 'updated_at']);
            $baru->rab_id = $salinan->id;
            $baru->save();
        }

        foreach ($rab->biaya as $biaya) {
            $baru = $biaya->replicate(['created_at', 'updated_at']);
            $baru->rab_id = $salinan->id;
            $baru->save();
        }

        $this->catat($request, 'salin rab', ['kode' => $rab->kode, 'ke' => $salinan->kode]);

        return response()->json([
            'data' => $this->bentuk($salinan->fresh()),
            'pesan' => "Disalin jadi {$salinan->kode}. Statusnya draf, dengan masa berlaku baru terhitung hari ini.",
        ], 201);
    }

    public function destroy(Rab $rab, Request $request): JsonResponse
    {
        // RAB yang sudah jadi pendaftaran tidak dihapus: ia satu-satunya
        // catatan tentang dari mana angka modal pendaftaran itu datang.
        if ($rab->kode_pendaftaran) {
            return response()->json([
                'pesan' => 'RAB ini sudah dijadikan pendaftaran '.$rab->kode_pendaftaran
                    .' dan menjadi asal-usul angka modalnya — tidak bisa dihapus. Ubah statusnya jadi Batal bila perlu.',
            ], 422);
        }

        $this->catat($request, 'hapus rab', ['kode' => $rab->kode]);
        $rab->delete();

        return response()->json(['pesan' => 'RAB dihapus.']);
    }

    /**
     * Seluruh itinerary diganti sekaligus.
     *
     * Bukan tambah/ubah/hapus per baris: menyusun itinerary adalah memindah
     * dan mengurutkan, dan tiga jenis panggilan untuk satu tarikan tangan
     * berarti tiga kesempatan untuk tersimpan setengah.
     */
    public function simpanItinerary(Rab $rab, Request $request): JsonResponse
    {
        $data = $request->validate([
            'itinerary' => ['present', 'array', 'max:200'],
            'itinerary.*.hari_ke' => ['required', 'integer', 'min:1', 'max:'.max(1, $rab->jumlah_hari)],
            'itinerary.*.jam' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'itinerary.*.jam_selesai' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'itinerary.*.nama' => ['required', 'string', 'max:150'],
            'itinerary.*.keterangan' => ['nullable', 'string', 'max:500'],
            'itinerary.*.destinasi' => ['nullable', 'string', 'max:150'],
        ], [
            'itinerary.*.hari_ke.max' => 'Ada kegiatan di hari ke-:input, padahal perjalanannya hanya '.$rab->jumlah_hari.' hari.',
            'itinerary.*.jam.regex' => 'Jam ditulis seperti 08:30.',
            'itinerary.*.jam_selesai.regex' => 'Jam selesai ditulis seperti 11:00.',
        ]);

        DB::transaction(function () use ($rab, $data) {
            $rab->itinerary()->delete();

            $urutanPerHari = [];

            foreach ($data['itinerary'] as $baris) {
                $hari = (int) $baris['hari_ke'];
                $urutanPerHari[$hari] = ($urutanPerHari[$hari] ?? 0) + 1;

                RabItinerary::create([
                    'rab_id' => $rab->id,
                    'hari_ke' => $hari,
                    'urutan' => $urutanPerHari[$hari],
                    'jam' => $baris['jam'] ?? null,
                    'jam_selesai' => $baris['jam_selesai'] ?? null,
                    'nama' => trim($baris['nama']),
                    'keterangan' => trim((string) ($baris['keterangan'] ?? '')) ?: null,
                    'destinasi' => trim((string) ($baris['destinasi'] ?? '')) ?: null,
                ]);
            }
        });

        $dibuang = $this->buangTiketYatim($rab->fresh());

        return response()->json([
            'data' => $this->bentuk($rab->fresh()),
            'pesan' => $dibuang === []
                ? 'Itinerary disimpan.'
                : 'Itinerary disimpan. Tiket '.implode(', ', $dibuang).' ikut dibuang karena tempatnya tidak lagi dikunjungi.',
            'tiket_dibuang' => $dibuang,
        ]);
    }

    /**
     * Membuang baris tiket yang destinasinya tidak ada lagi di itinerary.
     *
     * Menghapus kunjungan tanpa membuang tiketnya membuat RAB tetap
     * membayar tempat yang tidak didatangi — dan angkanya tetap terlihat
     * masuk akal, jadi tidak ada yang memeriksanya. Tiketnya tertarik
     * sendiri dari itinerary; sudah selayaknya ia juga pergi sendiri.
     *
     * Yang dibuang HANYA baris yang berasal dari master harga dengan
     * destinasi — biaya manual, armada, hotel, dan biaya wajib provinsi
     * tidak pernah disentuh. Itu keputusan admin, bukan turunan itinerary.
     *
     * @return array<int, string> nama baris yang dibuang
     */
    private function buangTiketYatim(Rab $rab): array
    {
        $kunci = fn ($teks) => mb_strtolower(trim((string) $teks));

        $masihDikunjungi = $rab->itinerary()->whereNotNull('destinasi')
            ->pluck('destinasi')->map($kunci)->filter()->flip();

        $dibuang = [];

        foreach ($rab->biaya()->with('master')->get() as $biaya) {
            $tercakup = $biaya->master?->semuaDestinasi() ?? [];

            /*
             | Tiket terusan dibuang HANYA bila seluruh destinasinya sudah
             | tidak dikunjungi.
             |
             | Dulu yang dibaca cuma `destinasi` utamanya. Menggeser Candi
             | Arjuna keluar jadwal membuang tiket Dieng, padahal Kawah
             | Sikidang masih dikunjungi dan masih membayarnya — barisnya
             | hilang tanpa pemberitahuan, RAB-nya tetap terlihat wajar, dan
             | modalnya berkurang diam-diam.
             */
            if ($tercakup === [] || collect($tercakup)->contains(fn ($d) => $masihDikunjungi->has($kunci($d)))) {
                continue;
            }

            $dibuang[] = $biaya->nama;
            $biaya->delete();
        }

        return $dibuang;
    }

    /**
     * Menambah biaya dari master (satu klik) atau secara manual.
     */
    public function tambahBiaya(Rab $rab, Request $request): JsonResponse
    {
        if ($request->filled('master_harga_id')) {
            $master = MasterHarga::findOrFail($request->integer('master_harga_id'));
            $this->tambahkanDariMaster($rab, collect([$master]), bolehGanda: true);
        } else {
            $data = $request->validate([
                'kategori' => ['required', Rule::in(array_keys(config('orcha.rab.kategori')))],
                'nama' => ['required', 'string', 'max:150'],
                'satuan' => ['required', Rule::in(array_keys(config('orcha.rab.satuan')))],
                'harga_satuan' => ['required', 'integer', 'min:0', 'max:1000000000'],
                'kapasitas' => ['nullable', 'integer', 'min:1', 'max:1000',
                    Rule::requiredIf(in_array($request->input('satuan'),
                        config('orcha.rab.satuan_berkapasitas', []), true))],
                'jumlah' => ['nullable', 'integer', 'min:1', 'max:100'],
                /*
                 | Jumlah kamar/unit yang ditentukan sendiri.
                 |
                 | Tanpa ini campuran tipe kamar tidak bisa ditulis: dua
                 | Family dan tiga Standard di hotel yang sama jadi dua baris
                 | yang masing-masing menginapkan SELURUH rombongan.
                 */
                'unit_manual' => ['nullable', 'integer', 'min:1', 'max:500'],
                /*
                 | Berapa malam/hari biaya ini dipakai; kosong = ikut
                 | panjang perjalanan.
                 |
                 | Justru biaya manual yang paling sering lebih pendek
                 | daripada perjalanannya: homestay semalam di tengah trip
                 | tiga malam, jip yang disewa sehari saja.
                 */
                'malam_manual' => ['nullable', 'integer', 'min:0', 'max:365'],
                // Biaya manual pun punya vendornya sendiri — justru di sinilah
                // vendor dadakan muncul: sewa perahu, sewa jip, pemandu lokal.
                'vendor_id' => ['nullable', 'integer', 'exists:tbl_vendor,id'],
            ], ['kapasitas.required' => 'Kapasitas wajib diisi untuk biaya per unit atau per kamar.']);

            RabBiaya::create($data + [
                'rab_id' => $rab->id,
                'jumlah' => $data['jumlah'] ?? 1,
                'urutan' => (int) $rab->biaya()->max('urutan') + 1,
            ]);
        }

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'Biaya ditambahkan.']);
    }

    public function ubahBiaya(Rab $rab, RabBiaya $biaya, Request $request): JsonResponse
    {
        abort_unless((int) $biaya->rab_id === (int) $rab->id, 404);

        /*
         | "Pakai harga master" memperbarui harga beku ke harga master kini.
         |
         | Sengaja tindakan tersendiri, bukan otomatis: penawaran yang sudah
         | dikirim ke pelanggan tidak boleh bergeser diam-diam. Admin yang
         | memutuskan kapan harga baru dipakai — biasanya sebelum mengirim
         | penawaran berikutnya.
         */
        if ($request->boolean('pakai_harga_master') && $biaya->master) {
            /*
             | Sisi yang SAMA dengan yang dibekukan baris ini.
             |
             | Baris bertarif akhir pekan yang ditimpa harga hari kerja
             | kehilangan selisihnya tanpa satu pun tanda — dan "Pakai harga
             | master" terbaca sebagai memperbarui, bukan menurunkan.
             */
            $kini = $biaya->hargaMasterKini();

            /*
             | Sisi yang sudah TIDAK ADA lagi di master ditolak, tidak
             | diturunkan ke harga hari kerja.
             |
             | Baris bertarif Nataru yang tarif Nataru-nya sudah dihapus dari
             | master tidak punya angka sebanding. Menimpanya dengan harga
             | hari kerja adalah penurunan Rp 350.000 yang dilakukan tombol
             | bernama "pakai harga master" — dan penawarannya dikirim tanpa
             | ada yang melihat.
             */
            if ($kini === null) {
                return response()->json([
                    'pesan' => 'Baris ini memakai tarif '.($biaya->musim?->nama ?? 'musim')
                        .', dan tarif itu sudah tidak ada lagi di master harga. '
                        .'Isi kembali tarif musimnya di Master Harga, atau ketik harganya langsung di baris ini.',
                ], 422);
            }

            $biaya->update(['harga_satuan' => $kini]);
        } else {
            $data = $request->validate([
                'nama' => ['sometimes', 'string', 'max:150'],
                'harga_satuan' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
                'jumlah' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'kapasitas' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
                // null mengembalikannya ke penurunan otomatis dari peserta.
                'unit_manual' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
                /*
                 | Berapa malam/hari baris ini dipakai; null = ikut panjang
                 | perjalanan.
                 |
                 | min:0, bukan min:1 — "0 malam" adalah jawaban yang sah
                 | untuk baris yang batal dipakai tetapi sengaja ditahan di
                 | penawaran sebagai catatan. Menolaknya memaksa admin
                 | menghapus barisnya, dan catatannya ikut hilang.
                 */
                'malam_manual' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
                'catatan' => ['sometimes', 'nullable', 'string', 'max:200'],
            ]);
            $biaya->update($data);
        }

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'Biaya diperbarui.']);
    }

    /**
     * Membawa harga temuan tim PULANG ke master harga.
     *
     * Kebalikan dari "pakai harga master". Harga di RAB dibekukan dan boleh
     * disunting, jadi tim yang menemukan hotel lebih murah tinggal
     * menimpanya — tetapi selama ini temuan itu mati di satu penawaran, dan
     * RAB berikutnya menarik harga lama. Negosiasi yang sama diulang bulan
     * depan oleh orang yang tidak tahu bahwa jawabannya sudah pernah
     * ditemukan.
     *
     * SENGAJA tindakan tersendiri, tidak otomatis: harga yang disunting
     * untuk satu rombongan belum tentu harga yang berlaku umum. Rombongan 40
     * orang mendapat tarif yang tidak akan diberikan kepada rombongan 8
     * orang, dan menyalinnya diam-diam ke master membuat seluruh penawaran
     * berikutnya rugi tanpa ada yang menyadari.
     *
     * Riwayatnya ikut tercatat lewat jalur yang sama dengan layar Master
     * Harga, berikut asal-usulnya: kode RAB ini.
     */
    public function keMaster(Rab $rab, RabBiaya $biaya, Request $request): JsonResponse
    {
        abort_unless((int) $biaya->rab_id === (int) $rab->id, 404);

        if (! $biaya->master) {
            return response()->json([
                'pesan' => 'Baris ini tidak berasal dari master harga, jadi tidak ada yang bisa diperbarui. Tambahkan dulu sebagai harga baru di Master Harga.',
            ], 422);
        }

        /*
         | Temuan tim dibawa pulang ke SISI yang dipakai baris ini.
         |
         | Harga Baluran akhir pekan yang ditulis ke kolom hari kerja membuat
         | SELURUH RAB berikutnya memakai Rp 31.000 di hari Selasa — kerugian
         | yang menyebar dari satu kali menekan tombol, dan tidak akan pernah
         | dicurigai karena angkanya memang pernah benar.
         */
        $pilihan = $request->validate([
            'catatan' => ['sometimes', 'nullable', 'string', 'max:200'],
            /*
             | 'hari_kerja', 'akhir_pekan', atau 'musim:<id>'.
             |
             | Daftarnya tidak bisa ditulis sebagai in: — musimnya bertambah
             | sendiri — jadi bentuknya yang dijaga di sini dan keberadaan
             | tarifnya diperiksa di bawah. Menerima musim mana pun tanpa
             | memeriksa berarti tombol "simpan" bisa MEMBUAT tarif musim
             | baru, lalu seluruh RAB berikutnya memakainya.
             */
            'sisi' => ['sometimes', 'nullable', 'string', 'regex:/^(hari_kerja|akhir_pekan|musim:\d+)$/'],
        ], [
            'sisi.regex' => 'Sisi harga tidak dikenali.',
        ]);

        /*
         | Sisinya BOLEH disebut pemanggil, dan itu bukan kemewahan.
         |
         | Penanda pakai_akhir_pekan benar untuk baris yang ditarik sendiri
         | dari jadwal — tanggal kunjungannya diketahui. Ia TIDAK benar untuk
         | baris yang digandakan tangan: admin menggandakan satu baris justru
         | untuk menutup hari yang jenisnya BERBEDA, lalu mengetik harga hari
         | itu. Penandanya tetap menunjuk sisi yang lama, dan "Simpan ke
         | master" menulis harga akhir pekan ke kolom hari kerja — kerugian
         | yang menyebar ke seluruh RAB berikutnya dan tidak akan pernah
         | dicurigai, karena angkanya memang pernah benar.
         */
        $sisi = array_key_exists('sisi', $pilihan) && $pilihan['sisi'] !== null
            ? $pilihan['sisi']
            : ($biaya->tingkat === 'musim' ? 'musim:'.$biaya->musim_id : $biaya->tingkat);

        /*
         | Sisi yang tidak bisa ditulis diturunkan ke hari kerja — KECUALI
         | musim, yang ditolak.
         |
         | Master tanpa kolom akhir pekan yang diminta menulis ke sana memang
         | paling benar diperlakukan sebagai hari kerja: itu satu-satunya
         | angka yang ada, dan admin melihat sebutannya di pesan balasan.
         |
         | Musim lain: menulis tarif Nataru ke kolom hari kerja berarti
         | SELURUH hari biasa sepanjang tahun ikut jadi Rp 900.000. Diam-diam
         | menurunkannya ke hari kerja adalah kerugian yang menyebar dari satu
         | kali menekan tombol, dan tidak akan pernah dicurigai karena
         | angkanya memang pernah benar.
         */
        if ($sisi === 'akhir_pekan' && $biaya->master->harga_akhir_pekan === null) {
            $sisi = 'hari_kerja';
        }

        $musimId = str_starts_with($sisi, 'musim:') ? (int) substr($sisi, 6) : null;
        $barisMusim = $musimId
            ? $biaya->master->musim()->where('musim_id', $musimId)->first()
            : null;

        if ($musimId && ! $barisMusim) {
            return response()->json([
                'pesan' => 'Harga master ini belum punya tarif untuk musim itu, jadi tidak ada yang bisa diperbarui. '
                    .'Isi dulu tarif musimnya di Master Harga — "Simpan ke master" memperbarui tarif yang sudah ada, '
                    .'bukan membuat tarif baru.',
            ], 422);
        }

        $sisiAkhirPekan = $sisi === 'akhir_pekan';
        $hargaLama = (int) match (true) {
            $barisMusim !== null => $barisMusim->harga,
            $sisiAkhirPekan => $biaya->master->harga_akhir_pekan,
            default => $biaya->master->harga,
        };
        $hargaBaru = (int) $biaya->harga_satuan;

        if ($hargaLama === $hargaBaru) {
            return response()->json([
                'pesan' => 'Harga master sudah sama dengan harga di baris ini — tidak ada yang perlu disimpan.',
            ], 422);
        }

        $data = $pilihan;

        $akhirPekanLama = $biaya->master->harga_akhir_pekan === null
            ? null : (int) $biaya->master->harga_akhir_pekan;
        $hargaHariKerja = (int) $biaya->master->harga;

        /*
         | Tarif musim punya BARISNYA sendiri, bukan kolom di master.
         |
         | Karena itu ia tidak bisa ikut update() di sini, dan perubahannya
         | masuk riwayat lewat `perubahan` — bentuk yang sama dipakai layar
         | Master Harga, jadi layar riwayat tidak perlu tahu bahwa harga musim
         | itu hal baru.
         */
        $ubahMusim = [];

        if ($barisMusim) {
            $barisMusim->update(['harga' => $hargaBaru]);
            $ubahMusim[] = [
                'medan' => 'Harga '.($barisMusim->musim?->nama ?? 'musim #'.$musimId),
                'dari' => RincianBiaya::rupiah($hargaLama),
                'ke' => RincianBiaya::rupiah($hargaBaru),
            ];
        } else {
            $biaya->master->update($sisiAkhirPekan
                ? ['harga_akhir_pekan' => $hargaBaru]
                : ['harga' => $hargaBaru]);
        }

        RiwayatHarga::rekam(
            $biaya->master->fresh(),
            // Yang dicatat sebagai "harga lama" selalu harga hari kerjanya;
            // sisi akhir pekan punya kolomnya sendiri di riwayat.
            $hargaHariKerja,
            $request->attributes->get('admin_pemanggil'),
            // Asal-usulnya disebut walau admin tidak menulis apa-apa: riwayat
            // yang tidak bisa ditelusuri kembali ke penawarannya adalah angka
            // yang berubah tanpa sebab.
            trim(($data['catatan'] ?? $biaya->catatan ?? '').' (dari RAB '.$rab->kode.')'),
            akhirPekanLama: $akhirPekanLama,
            perubahan: $ubahMusim,
        );

        $this->catat($request, 'harga master dari rab', [
            'kode' => $rab->kode,
            'master_harga_id' => $biaya->master_harga_id,
            'harga_lama' => $hargaLama,
            'harga_baru' => $hargaBaru,
        ]);

        $arah = $hargaBaru < $hargaLama ? 'turun' : 'naik';

        return response()->json([
            'data' => $this->bentuk($rab->fresh()),
            'pesan' => 'Master harga "'.$biaya->master->nama.'"'
                .match (true) {
                    $barisMusim !== null => ' ('.($barisMusim->musim?->nama ?? 'musim').')',
                    $sisiAkhirPekan => ' (akhir pekan)',
                    default => '',
                }.' '.$arah.' jadi '
                .RincianBiaya::rupiah($hargaBaru).'. RAB berikutnya memakai angka ini.',
        ]);
    }

    /**
     * Paket wisata yang itinerary-nya bisa dipakai sebagai titik mulai.
     *
     * Menyusun ulang dari nol padahal agenda yang mirip sudah tersimpan di
     * katalog paket adalah pekerjaan yang diulang tiap kali penawaran
     * dibuat — dan tiap pengulangan adalah kesempatan satu kegiatan
     * terlewat.
     *
     * Bentuk agenda paket (hari + agenda[jam, kegiatan]) diterjemahkan ke
     * bentuk itinerary RAB di sini, bukan di layar: dua tempat yang
     * menerjemahkan hal yang sama akan berselisih begitu salah satunya
     * diubah.
     */
    public function sumberItinerary(Request $request): JsonResponse
    {
        $daftar = TravelPackage::query()
            ->whereNotNull('itinerary')
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $q->where('provinsi', $p))
            ->orderBy('name')
            ->get()
            ->filter(fn (TravelPackage $p) => is_array($p->itinerary) && $p->itinerary !== [])
            ->map(fn (TravelPackage $p) => [
                'id' => $p->id,
                'nama' => $p->name,
                'jumlah_hari' => count($p->itinerary),
                'jumlah_kegiatan' => collect($p->itinerary)->sum(fn ($h) => count($h['agenda'] ?? [])),
                'itinerary' => $this->terjemahkanAgenda($p->itinerary),
            ])
            ->values()
            ->all();

        /*
         | Templat buatan tim ditaruh DI DEPAN paket katalog: ia disusun untuk
         | pekerjaan ini, sedangkan paket disusun untuk dijual. Keduanya
         | menyebut asalnya supaya admin tahu mana yang boleh dihapus.
         */
        $templat = TemplatItinerary::query()
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $q->where('provinsi', $p))
            ->orderBy('nama')
            ->get()
            ->map(fn (TemplatItinerary $t) => [
                'id' => $t->id,
                'nama' => $t->nama,
                'jumlah_hari' => $t->jumlah_hari,
                'jumlah_kegiatan' => $t->jumlah_kegiatan,
                'asal' => 'templat',
                'oleh' => $t->oleh,
                'itinerary' => $t->itinerary,
            ])
            ->values()
            ->all();

        $daftar = array_map(fn ($p) => $p + ['asal' => 'paket', 'oleh' => null], $daftar);

        return response()->json(['data' => array_merge($templat, $daftar)]);
    }

    /**
     * Menyimpan jadwal RAB ini supaya bisa dipakai lagi.
     *
     * Jalurnya dulu satu arah: jadwal bisa DIAMBIL dari paket wisata, tetapi
     * yang baru disusun tidak bisa disimpan ke mana pun — jadi tiap "Jogja
     * 3H2M" berikutnya disusun dari nol lagi.
     *
     * Yang disimpan hanya bentuk jadwalnya: hari, jam, nama kegiatan,
     * destinasi, keterangan. Harga TIDAK ikut: harga milik master, dan
     * templat yang membawa harga beku akan menua diam-diam.
     */
    public function simpanTemplat(Rab $rab, Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:150'],
        ]);

        $kegiatan = $rab->itinerary()->orderBy('hari_ke')->orderBy('urutan')->get();

        if ($kegiatan->isEmpty()) {
            return response()->json([
                'pesan' => 'Jadwalnya masih kosong — belum ada yang bisa disimpan.',
            ], 422);
        }

        $templat = TemplatItinerary::create([
            'nama' => $data['nama'],
            'provinsi' => $rab->provinsi,
            'jumlah_hari' => (int) $kegiatan->max('hari_ke'),
            'jumlah_kegiatan' => $kegiatan->count(),
            'oleh' => $request->attributes->get('admin_pemanggil'),
            'itinerary' => $kegiatan->map(fn (RabItinerary $k) => [
                'hari_ke' => (int) $k->hari_ke,
                'jam' => $k->jam,
                'jam_selesai' => $k->jam_selesai,
                'nama' => $k->nama,
                'keterangan' => $k->keterangan,
                'destinasi' => $k->destinasi,
            ])->all(),
        ]);

        $this->catat($request, 'simpan templat jadwal', [
            'kode' => $rab->kode,
            'templat' => $templat->nama,
        ]);

        return response()->json([
            'data' => ['id' => $templat->id, 'nama' => $templat->nama],
            'pesan' => 'Jadwal disimpan sebagai "'.$templat->nama.'". '
                .'Mulai sekarang ia muncul di panel "ambil jadwal" RAB mana pun.',
        ], 201);
    }

    /** Membuang templat jadwal yang tidak terpakai lagi. */
    public function hapusTemplat(TemplatItinerary $templat, Request $request): JsonResponse
    {
        $nama = $templat->nama;
        $templat->delete();

        $this->catat($request, 'hapus templat jadwal', ['templat' => $nama]);

        return response()->json(['pesan' => 'Templat "'.$nama.'" dihapus.']);
    }

    /**
     * Agenda paket → baris itinerary RAB.
     *
     * Jam di katalog paket ditulis "18.30" (titik), sedangkan itinerary RAB
     * memakai "18:30". Dibiarkan apa adanya, jamnya ditolak validasi dan
     * seluruh salinan gagal tanpa menyebut sebabnya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function terjemahkanAgenda(array $agendaPaket): array
    {
        $hasil = [];

        foreach (array_values($agendaPaket) as $i => $hari) {
            foreach ($hari['agenda'] ?? [] as $kegiatan) {
                $jam = str_replace('.', ':', trim((string) ($kegiatan['jam'] ?? '')));

                $hasil[] = [
                    'hari_ke' => $i + 1,
                    'jam' => preg_match('/^\d{2}:\d{2}$/', $jam) ? $jam : null,
                    'jam_selesai' => null,
                    'nama' => trim((string) ($kegiatan['kegiatan'] ?? '')),
                    'keterangan' => null,
                    // Destinasi tidak ditebak dari nama kegiatan: salah
                    // tebak akan menarik tiket tempat lain ke penawaran.
                    'destinasi' => null,
                ];
            }
        }

        return array_values(array_filter($hasil, fn ($k) => $k['nama'] !== ''));
    }

    /**
     * Menyusun ulang urutan baris biaya.
     *
     * Kolom `urutan` sudah lama ada di basis data tetapi tidak pernah bisa
     * diubah dari mana pun, jadi urutan di PDF penawaran selalu urutan
     * dimasukkan — dan yang dimasukkan belakangan sering justru yang paling
     * ingin dibaca lebih dulu.
     */
    public function urutkanBiaya(Rab $rab, Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'array', 'min:1'],
            'id.*' => ['integer'],
        ]);

        // Hanya baris MILIK rab ini yang disentuh: id dari RAB lain yang
        // ikut terkirim tidak boleh diam-diam berpindah urutan.
        $milik = $rab->biaya()->pluck('id')->flip();

        foreach ($data['id'] as $urutan => $id) {
            if ($milik->has($id)) {
                RabBiaya::where('id', $id)->update(['urutan' => $urutan + 1]);
            }
        }

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'Urutan biaya disimpan.']);
    }

    public function hapusBiaya(Rab $rab, RabBiaya $biaya, Request $request): JsonResponse
    {
        abort_unless((int) $biaya->rab_id === (int) $rab->id, 404);

        /*
         | Arah sebaliknya: tiket yang dihapus sementara tempatnya MASIH ada
         | di itinerary akan tertarik lagi pada "Tarik ulang" berikutnya —
         | penghapusan yang hidup lagi tanpa ada yang menyadarinya.
         |
         | Itinerary TIDAK diubah diam-diam: ia rencana yang dibaca
         | pelanggan, dan membuang kunjungan karena tiketnya dihapus adalah
         | akibat yang jauh lebih besar daripada sebabnya. Jadi yang
         | dipulangkan keterangannya, dan layar yang menawarkan pilihan.
         */
        /*
         | Tiket terusan menyangkut BEBERAPA kunjungan sekaligus.
         |
         | Yang dibaca dulu cuma destinasi utamanya, jadi menghapus tiket
         | Dieng menawarkan membuang kunjungan Candi Arjuna saja — Kawah
         | Sikidang tertinggal di jadwal tanpa tiket, dan tidak ada yang
         | menyebutnya.
         */
        $tercakup = collect($biaya->master?->semuaDestinasi() ?? []);
        $rapi = fn ($t) => mb_strtolower(trim((string) $t));

        $masih = $tercakup->filter(fn ($d) => $rab->itinerary()
            ->whereRaw('LOWER(TRIM(destinasi)) = ?', [$rapi($d)])->exists())->values();

        $masihDiItinerary = $masih->isNotEmpty();
        $destinasi = $masih->implode(' dan ');

        $biaya->delete();

        // Diminta sekalian: kunjungannya ikut dibuang dari itinerary.
        if ($masihDiItinerary && $request->boolean('sekalian_itinerary')) {
            foreach ($masih as $d) {
                $rab->itinerary()->whereRaw('LOWER(TRIM(destinasi)) = ?', [$rapi($d)])->delete();
            }

            return response()->json([
                'data' => $this->bentuk($rab->fresh()),
                'pesan' => "Biaya dihapus, dan kunjungan ke {$destinasi} ikut dibuang dari itinerary.",
            ]);
        }

        return response()->json([
            'data' => $this->bentuk($rab->fresh()),
            'pesan' => 'Biaya dihapus.',
            'destinasi_tertinggal' => $masihDiItinerary ? $destinasi : null,
        ]);
    }

    /**
     * Menarik biaya dari itinerary: tiket tiap destinasi yang dipilih, plus
     * biaya wajib provinsi yang belum ada.
     *
     * Aman diulang: yang sudah ada tidak ditambahkan dua kali. Admin yang
     * menambah destinasi baru lalu menekan tombol ini lagi mendapat tiket
     * destinasi barunya saja.
     */
    public function tarikBiaya(Rab $rab, Request $request): JsonResponse
    {
        $destinasi = $rab->itinerary()->whereNotNull('destinasi')->pluck('destinasi')->unique();

        /*
         | Dijodohkan tanpa memedulikan huruf besar-kecil dan spasi di ujung.
         |
         | Nama destinasi di master harga pernah diketik bebas, jadi "Candi
         | Prambanan " dan "candi prambanan" memang ada di data. Dicocokkan
         | mentah-mentah, tiketnya tidak pernah ikut tertarik dan RAB-nya
         | kekurangan modal tanpa satu pun tanda — kesalahan yang baru
         | ketahuan saat uangnya kurang.
         |
         | Hanya itu yang dilonggarkan. Nama yang memang BERBEDA ("prambanan"
         | vs "Candi Prambanan") tetap tidak dijodohkan: menebak kemiripan
         | akan memasukkan tiket tempat lain ke penawaran, dan salah tarik
         | jauh lebih mahal daripada tidak tertarik.
         */
        $kunci = fn ($teks) => mb_strtolower(trim((string) $teks));
        $dicari = $destinasi->map($kunci)->filter()->values();

        /*
         | Dijodohkan di PHP, bukan di SQL.
         |
         | Tiket terusan menyebut destinasi keduanya di kolom JSON
         | `destinasi_lain`, dan mencocokkan isi JSON lewat SQL berbeda bentuk
         | antara MySQL dan SQLite — yang berarti uji bisa hijau sementara
         | produksi tidak menarik tiketnya sama sekali. Daftarnya puluhan
         | baris, jadi menyaringnya di PHP tidak berarti apa-apa bagi
         | kecepatan dan menghilangkan seluruh kelas selisih itu.
         */
        $calon = MasterHarga::query()->where('aktif', true)
            // musim.musim ikut: harga tiap calon ditentukan lewat
            // hargaUntuk(), dan tanpa dimuat di sini jadi dua kueri per
            // calon — di jalur yang dipanggil tiap kali layar RAB dibuka.
            ->with('musim.musim')
            ->where(fn ($q) => $q->whereNotNull('destinasi')
                ->orWhere(fn ($o) => $o->where('otomatis', true)
                    ->where(fn ($p) => $p->whereNull('provinsi')->orWhere('provinsi', $rab->provinsi))))
            ->get()
            ->filter(function (MasterHarga $m) use ($dicari, $rab) {
                $perDestinasi = $dicari->contains(fn ($d) => $m->mencakup($d));
                $wajib = $m->otomatis
                    && ($m->provinsi === null || $m->provinsi === $rab->provinsi);

                return $perDestinasi || $wajib;
            })
            ->values();

        /*
         | Biaya PER KENDARAAN tidak ikut tertarik sendiri.
         |
         | Satu destinasi bisa punya beberapa tiket kendaraan sekaligus —
         | Alas Purwo memungut roda 4 Rp 10.000 dan bus Rp 50.000 — dan
         | keduanya berdestinasi sama. Ditarik semua, rombongan bermobil ikut
         | membayar tiket bus yang tidak pernah datang, dan modalnya naik
         | tanpa satu pun salah ketik.
         |
         | Jenis kendaraannya keputusan per-RAB, bukan sifat destinasinya,
         | jadi yang benar menawarkannya — lihat tawaranKendaraan(). Tiket
         | per orang tetap ditarik seperti biasa: tiap peserta membayarnya,
         | tidak ada yang perlu dipilih.
         */
        $ditarik = $calon->reject(fn (MasterHarga $m) => $m->satuan === 'unit');

        $ditambah = $this->tambahkanDariMaster($rab, $ditarik);
        $dibuang = $this->buangTiketYatim($rab->fresh());
        $campuran = (new HargaHari($rab))->campuran($ditarik);
        $tawaran = $this->tawaranKendaraan($rab->fresh());

        // Tiket terusan menutup SELURUH destinasi yang dicakupnya: tanpa ini
        // Kawah Sikidang dilaporkan "belum ada tiketnya" padahal sudah
        // terbayar bersama Candi Arjuna.
        $tanpaTiket = $destinasi
            ->reject(fn ($d) => $calon->contains(fn (MasterHarga $m) => $m->mencakup($d)))
            ->values();

        return response()->json([
            'data' => $this->bentuk($rab->fresh()),
            'pesan' => match (true) {
                $ditambah > 0 && $dibuang !== [] => "{$ditambah} biaya ditarik, ".count($dibuang).' tiket yatim dibuang.',
                $ditambah > 0 => "{$ditambah} biaya ditarik dari itinerary.",
                $dibuang !== [] => count($dibuang).' tiket dibuang karena tempatnya tidak lagi dikunjungi.',
                /*
                 | "Tidak ada biaya baru" sambil menyembunyikan tiket
                 | kendaraan yang menunggu dipilih adalah kalimat yang
                 | menutup pekerjaan yang belum selesai.
                 */
                $tawaran !== [] => 'Tiket per orang sudah lengkap. Tinggal pilih tiket kendaraannya di bawah.',
                default => 'Tidak ada biaya baru — semuanya sudah ada di RAB.',
            },
            'tiket_dibuang' => $dibuang,
            /*
             | Baris yang harinya campuran hari kerja & akhir pekan.
             |
             | Bukan kesalahan, dan bukan sesuatu yang bisa diputuskan
             | sistem: satu baris hanya memegang satu harga. Disebut namanya
             | supaya admin menggandakan barisnya sendiri.
             */
            'harga_campuran' => $campuran,
            // Disebut satu per satu: destinasi tanpa harga tiket di master
            // bukan kesalahan sistem, melainkan data yang belum diisi — dan
            // admin perlu tahu yang mana.
            'tanpa_tiket' => $tanpaTiket,
        ]);
    }

    public function pdf(Rab $rab, Request $request)
    {
        $jenis = $request->string('jenis')->toString() === 'internal' ? 'internal' : 'penawaran';

        $isi = BerkasRab::buat($rab, $jenis);

        return response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$rab->namaBerkas($jenis === 'internal' ? 'rab-internal' : 'penawaran').'"',
        ]);
    }

    /**
     * Mengirim penawaran ke email pelanggan, berikut PDF-nya.
     *
     * Sebelum ini email dikumpulkan di borang RAB lalu tidak pernah dipakai:
     * satu-satunya jalur keluar adalah WhatsApp, dan berkasnya diunduh admin
     * lalu dilampirkan sendiri. Isian yang meminta kerja tanpa memberi hasil.
     *
     * Alamat tujuannya diambil dari RAB, BUKAN dari badan permintaan. Alamat
     * yang boleh disebut pemanggil menjadikan jalur ini alat mengirim berkas
     * berisi harga ke alamat mana pun, dan satu salah ketik sudah cukup.
     */
    public function kirimEmail(Rab $rab, Request $request): JsonResponse
    {
        $tujuan = trim((string) $rab->email);

        if ($tujuan === '' || ! filter_var($tujuan, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'pesan' => 'RAB ini belum punya alamat email pelanggan yang sah. '
                    .'Isi dulu di Data perjalanan.',
            ], 422);
        }

        try {
            Mail::to($tujuan)->send(new PenawaranRab($rab->load('itinerary', 'biaya.master')));
        } catch (\Throwable $e) {
            /*
             | Pesannya TIDAK diteruskan apa adanya ke layar: galat SMTP sering
             | memuat alamat, nama pengguna, bahkan potongan percakapan server.
             | Yang ditulis ke log pun sengaja tanpa alamat tujuan.
             */
            Log::error('Gagal mengirim penawaran RAB', [
                'kode' => $rab->kode,
                'galat' => $e->getMessage(),
            ]);

            return response()->json([
                'pesan' => 'Suratnya gagal dikirim. Coba lagi sebentar; '
                    .'kalau tetap gagal, kirim PDF-nya lewat WhatsApp dulu.',
            ], 502);
        }

        // Alamatnya TIDAK ikut dicatat: jejak audit dibaca banyak orang, dan
        // alamat pelanggan tidak perlu ada di sana untuk menjawab
        // "penawarannya sudah dikirim atau belum?".
        $this->catat($request, 'kirim penawaran lewat email', ['kode' => $rab->kode]);

        return response()->json([
            'pesan' => 'Penawaran terkirim ke email pelanggan, berikut PDF-nya.',
            'data' => $this->bentuk($rab->fresh()),
        ]);
    }

    /**
     * Menjadikan RAB yang disetujui sebuah pendaftaran, dengan angka modalnya
     * terisi sendiri.
     *
     * Pemecahan harga_modal / biaya_tetap datang dari HitungRab, dirakit
     * supaya modal pendaftaran SAMA PERSIS dengan RAB — laporan keuntungan
     * yang berbeda beberapa rupiah dari penawaran yang disetujui pelanggan
     * adalah selisih yang tidak bisa dijelaskan siapa pun kelak.
     */
    /**
     * Merakit paket wisata publik dari RAB ini.
     *
     * Sampai sekarang open trip yang sudah lengkap di RAB tetap harus diketik
     * ulang ke borang paket — dua puluhan kolom berikut seluruh itinerary —
     * supaya bisa didaftari lewat halaman publik. Yang mahal bukan mengetiknya,
     * melainkan dua sumber untuk angka yang sama sesudahnya.
     *
     * Paketnya lahir sebagai DRAF. Foto, kuota, dan titik jemput tidak ada di
     * RAB, dan paket yang tayang tanpa ketiganya terlihat rusak di mata calon
     * pelanggan — sekali terlihat tidak bisa ditarik. Menerbitkan tetap
     * keputusan manusia, satu klik berikutnya di borang paket.
     */
    public function jadikanPaket(Rab $rab, Request $request): JsonResponse
    {
        if ($rab->travel_package_id && TravelPackage::whereKey($rab->travel_package_id)->exists()) {
            return response()->json([
                'pesan' => 'RAB ini sudah punya paket wisata. Perubahan di RAB langsung ikut ke paketnya.',
                'data' => ['id' => $rab->travel_package_id],
            ], 422);
        }

        if ($rab->jenis !== 'open') {
            return response()->json([
                'pesan' => 'Hanya open trip yang bisa dijadikan paket publik. Private trip dipesan atas nama satu rombongan, bukan dibuka untuk umum.',
            ], 422);
        }

        $r = HitungRab::ringkas($rab);

        if ($r['modal_total'] <= 0) {
            return response()->json(['pesan' => 'RAB ini belum berisi biaya apa pun.'], 422);
        }

        if ($rab->itinerary()->count() === 0) {
            return response()->json([
                'pesan' => 'Jadwalnya masih kosong. Halaman paket menampilkan itinerary hari per hari — tanpa itu calon peserta tidak punya apa pun untuk dibaca.',
            ], 422);
        }

        $paket = PaketDariRab::buat($rab);

        $this->catat($request, 'jadikan rab paket wisata', [
            'rab' => $rab->kode, 'paket' => $paket->name, 'paket_id' => $paket->id,
        ]);

        return response()->json([
            'data' => ['id' => $paket->id, 'uuid' => $paket->uuid, 'nama' => $paket->name, 'status' => $paket->status],
            'pesan' => 'Paket "'.$paket->name.'" dibuat sebagai draf. Lengkapi foto, kuota, dan titik jemputnya, lalu terbitkan.',
        ], 201);
    }

    public function jadikanPendaftaran(Rab $rab, Request $request): JsonResponse
    {
        if ($rab->kode_pendaftaran) {
            return response()->json([
                'pesan' => 'RAB ini sudah dijadikan pendaftaran '.$rab->kode_pendaftaran.'.',
            ], 422);
        }

        $data = $request->validate([
            'travel_package_id' => ['required', 'integer', 'exists:tbl_travel_package,id'],
            'tanggal_berangkat' => ['nullable', 'date'],
            // Persetujuan sadar untuk tetap berangkat di bawah titik impas.
            'abaikan_impas' => ['sometimes', 'boolean'],
        ], [], ['travel_package_id' => 'paket']);

        if (blank($rab->whatsapp)) {
            return response()->json([
                'pesan' => 'Nomor WhatsApp pelanggan belum diisi di RAB — pendaftaran membutuhkannya untuk pelacakan pesanan dan pembayaran.',
            ], 422);
        }

        $paket = TravelPackage::find($data['travel_package_id']);
        $r = HitungRab::ringkas($rab);

        if ($r['modal_total'] <= 0) {
            return response()->json(['pesan' => 'RAB ini belum berisi biaya apa pun.'], 422);
        }

        /*
         | Pengaman keberangkatan: open trip yang pesertanya masih di bawah
         | titik impas DITAHAN, bukan dibiarkan lewat dengan pesan kecil.
         |
         | Pada private trip jumlah orangnya sudah pasti di kesepakatan, jadi
         | tidak ada yang perlu ditahan. Pada open trip, yang menekan tombol
         | ini biasanya sedang mengejar tenggat — dan rugi dua juta jauh lebih
         | mudah dicegah di sini daripada dijelaskan di laporan bulan depan.
         |
         | Bisa dilewati, TETAPI harus disebut terang-terangan (abaikan_impas):
         | kadang trip memang sengaja dijalankan merugi demi nama baik, dan
         | keputusan itu milik manusia, bukan milik pemeriksaan ini.
         */
        $impas = $r['bep_peserta'];

        if ($rab->jenis === 'open' && ! ($data['abaikan_impas'] ?? false)
            && $impas !== null && (int) $rab->jumlah_peserta < $impas) {
            return response()->json([
                'pesan' => 'Peserta baru '.$rab->jumlah_peserta.' orang, sementara trip ini baru balik modal mulai '
                    .$impas.' orang — perkiraan rugi '.RincianBiaya::rupiah(abs($r['untung'])).'.',
                'impas' => $impas,
                'peserta' => (int) $rab->jumlah_peserta,
                'rugi' => abs($r['untung']),
            ], 422);
        }

        $pendaftaran = DB::transaction(function () use ($rab, $paket, $r, $data) {
            $p = PendaftaranOpenTrip::create([
                'travel_package_id' => $paket->id,
                'nama_paket' => $paket->name,
                'nama' => $rab->nama_pelanggan,
                'whatsapp' => $rab->whatsapp,
                'email' => $rab->email,
                'jumlah_peserta' => $rab->jumlah_peserta,
                'tanggal_berangkat' => $data['tanggal_berangkat'] ?? $rab->tanggal_mulai?->toDateString() ?? $paket->tanggal_berangkat,
                'catatan' => '[Sistem] Dibuat dari RAB '.$rab->kode.' — '.$rab->judul.'.',
                'harga_jual' => $r['untuk_pendaftaran']['harga_jual'],
                'harga_modal' => $r['untuk_pendaftaran']['harga_modal'],
                'biaya_tetap' => $r['untuk_pendaftaran']['biaya_tetap'],
                'status' => 'baru',
            ]);

            $rab->update(['kode_pendaftaran' => $p->kode, 'status' => 'disetujui']);

            return $p;
        });

        $this->catat($request, 'jadikan rab pendaftaran', [
            'kode' => $pendaftaran->kode, 'rab' => $rab->kode, 'paket' => $paket->name,
        ]);

        return response()->json([
            'data' => ['kode' => $pendaftaran->kode, 'id' => $pendaftaran->id],
            'pesan' => 'Pendaftaran '.$pendaftaran->kode.' dibuat dari RAB ini.',
        ], 201);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Tiket per kendaraan di tempat yang dikunjungi, yang belum ada di RAB.
     *
     * Ditawarkan alih-alih ditarik karena satu destinasi bisa memungut
     * beberapa sekaligus — roda 4, roda 6, bus — dan rombongan ini cuma
     * membawa salah satunya. Lihat alasan panjangnya di tarikBiaya().
     *
     * Harganya sudah memakai tarif tanggal kunjungannya, supaya angka yang
     * diklik sama dengan angka yang masuk. Tawaran yang berubah nominalnya
     * begitu ditekan adalah tawaran yang tidak dipercaya lagi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tawaranKendaraan(Rab $rab): array
    {
        $kunci = fn ($teks) => mb_strtolower(trim((string) $teks));

        $dikunjungi = $rab->itinerary()->whereNotNull('destinasi')
            ->pluck('destinasi')->map($kunci)->filter()->unique()->values();

        if ($dikunjungi->isEmpty()) {
            return [];
        }

        $sudah = $rab->biaya()->whereNotNull('master_harga_id')->pluck('master_harga_id')->all();
        $hari = new HargaHari($rab);

        return MasterHarga::query()
            ->where('aktif', true)
            ->where('satuan', 'unit')
            // Harga tiap tawaran ditentukan lewat hargaUntuk(), yang membaca
            // tarif musimnya.
            ->with('musim.musim')
            ->whereIn(DB::raw('LOWER(TRIM(destinasi))'), $dikunjungi)
            ->whereNotIn('id', $sudah ?: [0])
            ->orderBy('harga')
            ->get()
            ->map(function (MasterHarga $m) use ($hari) {
                $pilih = $hari->untuk($m);

                return [
                    'master_harga_id' => $m->id,
                    'nama' => $m->nama,
                    'destinasi' => $m->destinasi,
                    'kapasitas' => $m->kapasitas,
                    'harga' => $pilih['harga'],
                    'harga_teks' => RincianBiaya::rupiah($pilih['harga']),
                    // Sebab tarifnya ikut, supaya tawaran akhir pekan tidak
                    // terbaca sebagai harga master yang salah.
                    'harga_sebab' => $pilih['sebab'],
                    'satuan_label' => $m->satuan_label,
                ];
            })->values()->all();
    }

    /**
     * @param  Collection<int, MasterHarga>  $master
     * @return int jumlah baris yang ditambahkan
     */
    private function tambahkanDariMaster(Rab $rab, $master, bool $bolehGanda = false): int
    {
        $sudah = $rab->biaya()->whereNotNull('master_harga_id')->pluck('master_harga_id')->all();
        $urutan = (int) $rab->biaya()->max('urutan');
        // Dibuat SEKALI, bukan sekali per baris: daftar hari libur dan peta
        // kunjungan di dalamnya tidak berubah di tengah perulangan.
        $hari = new HargaHari($rab);
        $n = 0;

        foreach ($master as $m) {
            if (! $bolehGanda && in_array($m->id, $sudah, true)) {
                continue;
            }

            $pilih = $hari->untuk($m);

            RabBiaya::create([
                'rab_id' => $rab->id,
                'master_harga_id' => $m->id,
                // Dari siapa biaya ini dibeli ikut tersalin, jadi kontaknya
                // ada di layar yang sedang dipakai — bukan di menu lain.
                'vendor_id' => $m->vendor_id,
                'kategori' => $m->kategori,
                'nama' => $m->nama,
                'satuan' => $m->satuan,
                'harga_satuan' => $pilih['harga'],
                'harga_sebab' => $pilih['sebab'],
                'pakai_akhir_pekan' => $pilih['akhir_pekan'],
                // TINGKAT yang dibekukan, bukan cuma ya/tidak akhir pekan:
                // tarif Nataru dan tarif hari kerja keduanya berpenanda
                // false, dan "Simpan ke master" tidak boleh menebak.
                'tingkat_harga' => $pilih['tingkat'],
                'musim_id' => $pilih['musim_id'],
                'kapasitas' => $m->kapasitas,
                'jumlah' => 1,
                'urutan' => ++$urutan,
            ]);
            $sudah[] = $m->id;
            $n++;
        }

        return $n;
    }

    /** @return array<string, mixed> */
    private function periksaKepala(Request $request, ?Rab $rab = null): array
    {
        $wajib = $rab ? 'sometimes' : 'required';

        /*
         | Open trip tidak punya pelanggan saat RAB-nya disusun.
         |
         | Harganya diumumkan DULU, pesertanya menyusul — itu definisinya.
         | Mewajibkan nama pelanggan di sana memaksa admin mengarang isian:
         | "-", "umum", "open trip desember", apa saja yang membuat tombol
         | simpan mau jalan. Isian karangan lebih buruk daripada kolom
         | kosong, karena ia terlihat seperti data.
         */
        $jenis = $request->input('jenis', $rab?->jenis ?? 'private');
        $pelanggan = $jenis === 'open' ? 'nullable' : $wajib;

        $data = $request->validate([
            'judul' => [$wajib, 'string', 'min:3', 'max:150'],
            'nama_pelanggan' => [$pelanggan, 'string', 'min:2', 'max:120'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:150'],
            'provinsi' => [$wajib, 'string', 'max:80'],
            'jenis' => ['sometimes', Rule::in(['private', 'open'])],
            'peserta_minimum' => ['nullable', 'integer', 'min:1', 'max:500'],
            'harga_dasar' => ['sometimes', Rule::in(['peserta', 'minimum'])],
            'daerah' => ['nullable', 'string', 'max:80'],
            'tanggal_mulai' => ['nullable', 'date'],
            'jumlah_hari' => [$wajib, 'integer', 'min:1', 'max:30'],
            'jumlah_malam' => ['nullable', 'integer', 'min:0', 'max:30'],
            'jumlah_peserta' => [$wajib, 'integer', 'min:1', 'max:500'],
            'margin_jenis' => ['sometimes', Rule::in(array_keys(config('orcha.rab.margin_jenis')))],
            'margin_nilai' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            /*
             | Nilai BEBAS, bukan hanya dari daftar.
             |
             | pembulatan_pilihan tetap ada sebagai saran yang dikirim ke
             | layar, tetapi tim yang butuh kelipatan lain — Rp 2.500,
             | Rp 250.000 — tidak perlu menunggu config diubah dan dideploy
             | lebih dulu. Yang dijaga tinggal batas kewarasannya.
             |
             | Batas atas Rp 1.000.000: kelipatan di atas itu bukan
             | pembulatan lagi, melainkan menetapkan harga.
             */
            'pembulatan' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            // Nol dan null sama-sama berarti "tidak ada kesepakatan" —
            // harganya kembali dihitung dari modal + margin.
            'harga_nego' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'alasan_nego' => ['sometimes', 'nullable', 'string', 'max:200'],
            'catatan' => ['nullable', 'string', 'max:3000'],
            'catatan_penawaran' => ['nullable', 'string', 'max:3000'],
            'status' => ['sometimes', Rule::in(array_keys(config('orcha.rab.status')))],
            'berlaku_sampai' => ['nullable', 'date'],
        ], [
            'jumlah_malam.max' => 'Jumlah malam paling banyak 30.',
        ]);

        // Malam tidak boleh melebihi hari: perjalanan 2 hari 3 malam tidak
        // ada, dan hotelnya akan terhitung satu malam lebih banyak.
        $hari = (int) ($data['jumlah_hari'] ?? $rab?->jumlah_hari ?? 1);
        if (array_key_exists('jumlah_malam', $data) && (int) $data['jumlah_malam'] > $hari) {
            throw ValidationException::withMessages([
                'jumlah_malam' => "Jumlah malam ({$data['jumlah_malam']}) tidak boleh melebihi jumlah hari ({$hari}).",
            ]);
        }

        $jenisMargin = $data['margin_jenis'] ?? $rab?->margin_jenis;
        $nilaiMargin = (int) ($data['margin_nilai'] ?? $rab?->margin_nilai ?? 0);

        if ($jenisMargin === 'persen' && $nilaiMargin > 500) {
            throw ValidationException::withMessages([
                'margin_nilai' => 'Margin persen di atas 500% hampir pasti salah ketik — mungkin maksudnya nominal?',
            ]);
        }

        /*
         | Margin dari HARGA JUAL tidak bisa 100% atau lebih: rumusnya
         | modal / (1 - p), dan pada 100% pembaginya nol. Artinya pun tidak
         | ada — harga jual yang seluruhnya untung berarti modalnya nol.
         */
        if ($jenisMargin === 'persen_harga' && $nilaiMargin >= 100) {
            throw ValidationException::withMessages([
                'margin_nilai' => 'Margin dari harga jual harus di bawah 100%. Pada 100% seluruh harga jual adalah untung, yang hanya mungkin bila modalnya nol — mungkin yang dimaksud "persen dari modal"?',
            ]);
        }

        /*
         | Kolomnya tetap terisi, supaya daftar RAB dan RAB internal tidak
         | menampilkan baris tanpa nama — tetapi yang mengisi SISTEM, bukan
         | admin yang dipaksa mengarang.
         */
        if ($jenis === 'open' && array_key_exists('nama_pelanggan', $data) && blank($data['nama_pelanggan'])) {
            $data['nama_pelanggan'] = 'Umum';
        }

        if ($jenis === 'open' && ! $rab && ! array_key_exists('nama_pelanggan', $data)) {
            $data['nama_pelanggan'] = 'Umum';
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function kepala(Rab $rab): array
    {
        return [
            'id' => $rab->id,
            'kode' => $rab->kode,
            'judul' => $rab->judul,
            'nama_pelanggan' => $rab->nama_pelanggan,
            'whatsapp' => $rab->whatsapp,
            'email' => $rab->email,
            'provinsi' => $rab->provinsi,
            'daerah' => $rab->daerah,
            'tanggal_mulai' => $rab->tanggal_mulai?->toDateString(),
            // Diturunkan, bukan disimpan — lihat Rab::getTanggalPulangAttribute().
            'tanggal_pulang' => $rab->tanggal_pulang?->toDateString(),
            'tanggal_pulang_teks' => $rab->tanggal_pulang?->locale('id')->translatedFormat('j F Y'),
            'jumlah_hari' => $rab->jumlah_hari,
            'jumlah_malam' => $rab->jumlah_malam,
            'jumlah_peserta' => $rab->jumlah_peserta,
            'margin_jenis' => $rab->margin_jenis,
            'margin_nilai' => $rab->margin_nilai,
            'pembulatan' => $rab->pembulatan,
            'harga_nego' => $rab->harga_nego,
            'alasan_nego' => $rab->alasan_nego,
            'status' => $rab->status,
            'status_label' => $rab->status_label,
            'jenis' => $rab->jenis,
            'jenis_label' => $rab->jenis === 'open' ? 'Open Trip' : 'Private Trip',
            'peserta_minimum' => $rab->peserta_minimum,
            'harga_dasar' => $rab->harga_dasar,
            'berlaku_sampai' => $rab->berlaku_sampai?->toDateString(),
            'kedaluwarsa' => $rab->berlaku_sampai?->isPast() && in_array($rab->status, ['draf', 'dikirim'], true),
            'kode_pendaftaran' => $rab->kode_pendaftaran,
            /*
             | Paket wisata yang dirakit dari RAB ini, bila ada.
             |
             | Statusnya ikut dikirim karena itu yang menentukan apakah orang
             | sudah bisa mendaftar: paket draf tidak pernah muncul di halaman
             | publik, dan layar perlu bisa mengatakan itu dengan jelas
             | daripada membiarkan admin mengira trip-nya sudah terbuka.
             */
            'paket' => (function () use ($rab) {
                $paket = $rab->travel_package_id
                    ? TravelPackage::find($rab->travel_package_id)
                    : null;

                return $paket ? [
                    'id' => $paket->id,
                    'uuid' => $paket->uuid,
                    'nama' => $paket->name,
                    'status' => $paket->status,
                    'tayang' => $paket->sedang_tayang,
                ] : null;
            })(),
            'dibuat_oleh' => $rab->dibuat_oleh,
            'dibuat_pada' => $rab->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    /**
     * Catatan master harga per destinasi, untuk ditempelkan ke itinerary.
     *
     * Dikunci nama destinasi apa adanya: itinerary menyimpan destinasinya
     * sebagai teks, bukan sebagai rujukan id, dan menebak-nebak kecocokannya
     * dengan pencarian longgar akan menempelkan catatan tempat lain ke
     * kunjungan yang salah — peringatan palsu lebih buruk daripada tidak ada
     * peringatan.
     *
     * @return array<string, string>
     */
    private function catatanDestinasi(Rab $rab): array
    {
        $nama = $rab->itinerary->pluck('destinasi')->filter()->unique()->values();

        if ($nama->isEmpty()) {
            return [];
        }

        return MasterHarga::query()
            ->whereIn('destinasi', $nama)
            ->whereNotNull('catatan')
            ->where('catatan', '!=', '')
            ->where('aktif', true)
            ->orderBy('id')
            ->get(['destinasi', 'catatan'])
            // Satu destinasi bisa punya beberapa baris master (tiket roda 4,
            // roda 6, …). Catatannya digabung, bukan diambil yang pertama:
            // yang pertama belum tentu yang paling penting.
            ->groupBy('destinasi')
            ->map(fn ($g) => $g->pluck('catatan')->unique()->implode(' · '))
            ->all();
    }

    private function bentuk(Rab $rab): array
    {
        $rab->loadMissing('itinerary', 'biaya.master', 'biaya.vendor');
        $catatanDestinasi = $this->catatanDestinasi($rab);

        return $this->kepala($rab) + [
            // Supaya layar bisa menautkan langsung ke pendaftarannya.
            'pendaftaran_id' => $rab->kode_pendaftaran
                ? PendaftaranOpenTrip::where('kode', $rab->kode_pendaftaran)->value('id')
                : null,
            'catatan' => $rab->catatan,
            'catatan_penawaran' => $rab->catatan_penawaran,
            /*
             | Dikirim di SETIAP pembacaan, bukan cuma sebagai balasan
             | "Tarik biaya".
             |
             | Admin yang membuka kembali RAB kemarin tidak menekan tombol
             | itu lagi — dan tiket kendaraan yang hanya muncul sekali,
             | sesudah tombol ditekan, adalah tiket yang terlupakan.
             */
            'tawaran_kendaraan' => $this->tawaranKendaraan($rab),
            'itinerary' => $rab->itinerary->map(fn (RabItinerary $i) => [
                'id' => $i->id,
                'hari_ke' => $i->hari_ke,
                'urutan' => $i->urutan,
                'jam' => $i->jam,
                'jam_selesai' => $i->jam_selesai,
                /*
                 | Jenis kegiatan ikut dikirim, tidak ditebak ulang di lemon.
                 |
                 | Penggolongnya sudah ada di BerkasRab dan sudah dipakai
                 | kedua PDF. Menyalin aturannya ke lemon berarti dua tebakan
                 | untuk perjalanan yang sama — dan suatu hari layar memberi
                 | warna hijau pada kegiatan yang di PDF-nya biru, tanpa ada
                 | yang bisa menyebut mana yang benar.
                 */
                'jenis' => BerkasRab::jenis($i->nama, $i->keterangan, $i->destinasi),
                // Lamanya ikut dikirim supaya lemon dan PDF menulis angka
                // yang sama; dihitung di satu tempat, bukan dua.
                'durasi_menit' => $i->durasi_menit,
                'durasi_teks' => $i->durasi_teks,
                'nama' => $i->nama,
                'keterangan' => $i->keterangan,
                'destinasi' => $i->destinasi,
                // Catatan master harga tempat ini — "tutup hari Senin",
                // "rombongan wajib pesan H-1". Ditulis admin berbulan-bulan
                // lalu, lalu tidak pernah terlihat saat jadwalnya disusun.
                'catatan_master' => $i->destinasi ? ($catatanDestinasi[$i->destinasi] ?? null) : null,
            ])->values()->all(),
            'ringkasan' => HitungRab::ringkas($rab),
        ];
    }
}
