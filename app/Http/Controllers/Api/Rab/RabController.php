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
use App\Support\Rab\BerkasRab;
use App\Support\Rab\HitungRab;
use App\Support\RincianBiaya;
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
                fn ($s) => $s->where('kode', 'like', "%{$cari}%")
                    ->orWhere('nama_pelanggan', 'like', "%{$cari}%")
                    ->orWhere('judul', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $st) => $q->where('status', $st))
            ->latest('id')
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
            ['kosakata' => MasterHargaController::kosakataRab()],
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

        $vendor = Vendor::aktif()->orderBy('nama')
            ->get(['id', 'nama', 'kontak', 'jenis'])
            ->map(fn (Vendor $v) => [
                'id' => $v->id, 'nama' => $v->nama, 'kontak' => $v->kontak, 'jenis' => $v->jenis,
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

    public function update(Rab $rab, Request $request): JsonResponse
    {
        $rab->update($this->periksaKepala($request, $rab));

        $this->catat($request, 'ubah rab', ['kode' => $rab->kode]);

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'RAB diperbarui.']);
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

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'Itinerary disimpan.']);
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

    public function hapusBiaya(Rab $rab, RabBiaya $biaya): JsonResponse
    {
        abort_unless((int) $biaya->rab_id === (int) $rab->id, 404);
        $biaya->delete();

        return response()->json(['data' => $this->bentuk($rab->fresh()), 'pesan' => 'Biaya dihapus.']);
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

        $punyaTiket = $calon->map(fn ($m) => $kunci($m->destinasi))->filter()->flip();
        $tanpaTiket = $destinasi->reject(fn ($d) => $punyaTiket->has($kunci($d)))->values();

        return response()->json([
            'data' => $this->bentuk($rab->fresh()),
            'pesan' => $ditambah > 0
                ? "{$ditambah} biaya ditarik dari itinerary."
                : 'Tidak ada biaya baru — semuanya sudah ada di RAB.',
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
