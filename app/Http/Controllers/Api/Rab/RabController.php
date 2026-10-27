<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Etalase\KatalogDestinasi;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabItinerary;
use App\Models\Rab\Vendor;
use App\Models\Rab\VersiPenawaran;
use App\Support\Rab\BerkasRab;
use App\Support\Rab\HitungRab;
use App\Support\RincianBiaya;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
    public function katalog(Request $request): JsonResponse
    {
        $provinsi = $request->string('provinsi')->toString() ?: null;
        $katalog = KatalogDestinasi::gabungan();

        // Sekali untuk seluruh katalog, bukan satu kueri per destinasi.
        $bertiket = MasterHarga::where('aktif', true)->whereNotNull('destinasi')
            ->pluck('destinasi')->flip();

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
                ->where('otomatis', true)->get());

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
            $destinasi = $biaya->master?->destinasi;

            if (! $destinasi || $masihDikunjungi->has($kunci($destinasi))) {
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
                    Rule::requiredIf(in_array($request->input('satuan'), ['unit_hari', 'kamar_malam'], true))],
                'jumlah' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            $biaya->update(['harga_satuan' => $biaya->master->harga]);
        } else {
            $data = $request->validate([
                'nama' => ['sometimes', 'string', 'max:150'],
                'harga_satuan' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
                'jumlah' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'kapasitas' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            ]);
            $biaya->update($data);
        }

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'Biaya diperbarui.']);
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

        return response()->json(['data' => $daftar]);
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
        $destinasi = $biaya->master?->destinasi;
        $masihDiItinerary = $destinasi && $rab->itinerary()
            ->whereRaw('LOWER(TRIM(destinasi)) = ?', [mb_strtolower(trim($destinasi))])
            ->exists();

        $biaya->delete();

        // Diminta sekalian: kunjungannya ikut dibuang dari itinerary.
        if ($masihDiItinerary && $request->boolean('sekalian_itinerary')) {
            $rab->itinerary()
                ->whereRaw('LOWER(TRIM(destinasi)) = ?', [mb_strtolower(trim($destinasi))])
                ->delete();

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

        $calon = MasterHarga::query()->where('aktif', true)
            ->where(fn ($q) => $q->whereIn(DB::raw('LOWER(TRIM(destinasi))'), $dicari)
                ->orWhere(fn ($o) => $o->where('otomatis', true)
                    ->where(fn ($p) => $p->whereNull('provinsi')->orWhere('provinsi', $rab->provinsi))))
            ->get();

        $ditambah = $this->tambahkanDariMaster($rab, $calon);
        $dibuang = $this->buangTiketYatim($rab->fresh());

        $punyaTiket = $calon->map(fn ($m) => $kunci($m->destinasi))->filter()->flip();
        $tanpaTiket = $destinasi->reject(fn ($d) => $punyaTiket->has($kunci($d)))->values();

        return response()->json([
            'data' => $this->bentuk($rab->fresh()),
            'pesan' => match (true) {
                $ditambah > 0 && $dibuang !== [] => "{$ditambah} biaya ditarik, ".count($dibuang).' tiket yatim dibuang.',
                $ditambah > 0 => "{$ditambah} biaya ditarik dari itinerary.",
                $dibuang !== [] => count($dibuang).' tiket dibuang karena tempatnya tidak lagi dikunjungi.',
                default => 'Tidak ada biaya baru — semuanya sudah ada di RAB.',
            },
            'tiket_dibuang' => $dibuang,
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
     * Menjadikan RAB yang disetujui sebuah pendaftaran, dengan angka modalnya
     * terisi sendiri.
     *
     * Pemecahan harga_modal / biaya_tetap datang dari HitungRab, dirakit
     * supaya modal pendaftaran SAMA PERSIS dengan RAB — laporan keuntungan
     * yang berbeda beberapa rupiah dari penawaran yang disetujui pelanggan
     * adalah selisih yang tidak bisa dijelaskan siapa pun kelak.
     */
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
     * @param  Collection<int, MasterHarga>  $master
     * @return int jumlah baris yang ditambahkan
     */
    private function tambahkanDariMaster(Rab $rab, $master, bool $bolehGanda = false): int
    {
        $sudah = $rab->biaya()->whereNotNull('master_harga_id')->pluck('master_harga_id')->all();
        $urutan = (int) $rab->biaya()->max('urutan');
        $n = 0;

        foreach ($master as $m) {
            if (! $bolehGanda && in_array($m->id, $sudah, true)) {
                continue;
            }

            RabBiaya::create([
                'rab_id' => $rab->id,
                'master_harga_id' => $m->id,
                // Dari siapa biaya ini dibeli ikut tersalin, jadi kontaknya
                // ada di layar yang sedang dipakai — bukan di menu lain.
                'vendor_id' => $m->vendor_id,
                'kategori' => $m->kategori,
                'nama' => $m->nama,
                'satuan' => $m->satuan,
                'harga_satuan' => $m->harga,
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

        $data = $request->validate([
            'judul' => [$wajib, 'string', 'min:3', 'max:150'],
            'nama_pelanggan' => [$wajib, 'string', 'min:2', 'max:120'],
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
            'pembulatan' => ['sometimes', 'integer', Rule::in([1, 100, 500, 1000, 5000, 10000])],
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

        if (($data['margin_jenis'] ?? $rab?->margin_jenis) === 'persen'
            && (int) ($data['margin_nilai'] ?? $rab?->margin_nilai ?? 0) > 500) {
            throw ValidationException::withMessages([
                'margin_nilai' => 'Margin persen di atas 500% hampir pasti salah ketik — mungkin maksudnya nominal?',
            ]);
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
            'jumlah_hari' => $rab->jumlah_hari,
            'jumlah_malam' => $rab->jumlah_malam,
            'jumlah_peserta' => $rab->jumlah_peserta,
            'margin_jenis' => $rab->margin_jenis,
            'margin_nilai' => $rab->margin_nilai,
            'pembulatan' => $rab->pembulatan,
            'status' => $rab->status,
            'status_label' => $rab->status_label,
            'jenis' => $rab->jenis,
            'jenis_label' => $rab->jenis === 'open' ? 'Open Trip' : 'Private Trip',
            'peserta_minimum' => $rab->peserta_minimum,
            'harga_dasar' => $rab->harga_dasar,
            'berlaku_sampai' => $rab->berlaku_sampai?->toDateString(),
            'kedaluwarsa' => $rab->berlaku_sampai?->isPast() && in_array($rab->status, ['draf', 'dikirim'], true),
            'kode_pendaftaran' => $rab->kode_pendaftaran,
            'dibuat_oleh' => $rab->dibuat_oleh,
            'dibuat_pada' => $rab->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function bentuk(Rab $rab): array
    {
        $rab->loadMissing('itinerary', 'biaya.master');

        return $this->kepala($rab) + [
            // Supaya layar bisa menautkan langsung ke pendaftarannya.
            'pendaftaran_id' => $rab->kode_pendaftaran
                ? PendaftaranOpenTrip::where('kode', $rab->kode_pendaftaran)->value('id')
                : null,
            'catatan' => $rab->catatan,
            'catatan_penawaran' => $rab->catatan_penawaran,
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
            ])->values()->all(),
            'ringkasan' => HitungRab::ringkas($rab),
        ];
    }
}
