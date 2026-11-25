<?php

namespace App\Http\Controllers\Api\OpenTrip;

use App\Exceptions\KelebihanBelumDiputuskan;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OpenTrip\PendaftaranResource;
use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Pelanggan\SaldoPelanggan;
use App\Models\Rab\Rab;
use App\Models\Umum\TautanPendek;
use App\Support\BerkasKwitansi;
use App\Support\NomorTelepon;
use App\Support\PerkiraanPotongan;
use App\Support\Rab\HitungRab;
use App\Support\RencanaAngsuran;
use App\Support\RincianBiaya;
use App\Support\SuratPenggantian;
use App\Support\TagihanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PendaftaranController extends ApiController
{
    /**
     * Urutan daftar yang boleh diminta: kunci => [kolom, arah].
     *
     * Daftar tertutup, bukan nama kolom yang dioper apa adanya dari
     * permintaan — kolom bebas berarti siapa pun yang bisa memanggil API ini
     * bisa mengurut menurut kolom yang tidak dimaksudkan terbaca, dan galat
     * SQL-nya membocorkan nama kolom yang ada.
     */
    private const URUT = [
        'terbaru' => ['id', 'desc'],
        'terlama' => ['id', 'asc'],
        'berangkat_dekat' => ['tanggal_berangkat', 'asc'],
        'berangkat_jauh' => ['tanggal_berangkat', 'desc'],
        'nama' => ['nama', 'asc'],
        'nama_z' => ['nama', 'desc'],
        'peserta_banyak' => ['jumlah_peserta', 'desc'],
        'peserta_sedikit' => ['jumlah_peserta', 'asc'],
        'uang_masuk_kecil' => ['pembayaran_diterima', 'asc'],
        'uang_masuk_besar' => ['pembayaran_diterima', 'desc'],
    ];

    /**
     * Urutan sisa tagihan, yang tidak bisa dinyatakan sebagai satu kolom.
     *
     * Sisa = total_tagihan − uang yang sudah DITERIMA. Kolom pertama adalah
     * salinan omzet yang dihitung PHP lalu dituliskan oleh kait saved pada
     * modelnya; yang kedua dijumlahkan di sini lewat subkueri. Aturannya tetap
     * hidup di satu tempat — yang ada di SQL cuma pengurangannya.
     *
     * Hanya yang berstatus diterima yang dijumlahkan, sama seperti
     * TagihanPesanan: bukti yang belum dicek belum uang, dan menghitungnya
     * membuat orang yang baru mengunggah gambar tampak sudah melunasi.
     */
    private const URUT_SISA = [
        'sisa_besar' => 'desc',
        'sisa_kecil' => 'asc',
    ];

    public function index(Request $request): JsonResponse
    {
        $daftar = PendaftaranOpenTrip::query()
            ->withCount('riwayatKesehatan')

            /*
             | Bahan penanda angsuran, dimuat sekali untuk seluruh halaman.
             |
             | Dua kueri tambahan untuk dua puluh baris, bukan dua puluh.
             | Penandanya perlu tahu berapa termin yang sudah tertutup, dan itu
             | dijawab dengan membandingkan jumlah kumulatif termin dengan uang
             | yang benar-benar diterima — keduanya diambil di sini supaya
             | PenandaAngsuran tidak perlu menyentuh basis data sama sekali.
             |
             | Hanya yang berstatus diterima yang dijumlahkan, sama seperti
             | TagihanPesanan: bukti yang belum dicek belum uang, dan termin
             | yang ditandai lunas oleh unggahan yang belum diperiksa membuat
             | admin berhenti menagih orang yang belum membayar.
             */
            ->with(['angsuranAktif.termin', 'keberangkatan'])
            ->withSum([
                'konfirmasiPembayaran as pembayaran_diterima' => fn ($q) => $q->where('status', 'diterima'),
            ], 'nominal')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('nama_paket', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            // Saringan per paket: manifes tour leader dibentuk dari daftar yang
            // sedang tampil, dan satu keberangkatan hampir selalu berarti satu
            // paket. Tanpa saringan ini admin harus mengetik nama paketnya di
            // kotak cari dan berharap tidak ada paket lain yang namanya mirip.
            ->when($request->integer('paket_id'), fn ($q, $id) => $q->where('travel_package_id', $id))

            /*
             | Saringan per ROMBONGAN, satu tingkat lebih halus daripada paket.
             |
             | Sejak satu paket boleh dijual untuk beberapa tanggal, "satu
             | keberangkatan = satu paket" tidak lagi benar: manifes yang
             | dibentuk dari saringan paket akan memuat tiga rombongan
             | sekaligus, dan kelebihannya baru ketahuan di parkiran.
             */
            ->when($request->integer('keberangkatan_id'), fn ($q, $id) => $q->where('keberangkatan_id', $id))

            /*
             | Saringan "perlu ditagih": belum lunas, dan tanggalnya sudah dekat.
             |
             | Urutannya ikut berubah — yang paling mepet di atas, bukan yang
             | paling baru mendaftar. Daftar tagihan yang diurutkan menurut
             | waktu pendaftaran menaruh yang berangkat besok di halaman tiga.
             */
            ->when($request->boolean('perlu_ditagih'), fn ($q) => $q
                ->perluDitagih()
                ->orderBy('tanggal_berangkat'))

            /*
             | Urutan yang dipilih admin.
             |
             | Menyusul saringan tagihan di atas dengan sengaja: saat tab
             | "Perlu ditelepon" menyala, yang paling mepet tetap di atas apa
             | pun yang dipilih di pemilih urutan — daftar pekerjaan yang
             | urutannya bisa diacak admin berhenti jadi daftar pekerjaan.
             |
             | Sisa tagihan TIDAK ada di daftar ini. Totalnya dihitung PHP
             | (lihat TagihanPesanan) karena ikut memperhitungkan campuran tipe
             | kamar dan potongan rujukan; menulis ulang hitungan itu sebagai
             | SQL hanya supaya bisa diurut berarti dua hitungan yang cepat
             | atau lambat berbeda jawabannya — dan yang berbeda di sini adalah
             | angka uang.
             */
            /*
             | Sisa tagihan: total_tagihan − uang diterima.
             |
             | Pendaftaran yang total_tagihan-nya masih null (data lama sebelum
             | kolomnya ada, atau yang belum disentuh perintah
             | pendaftaran:hitung-tagihan) didorong ke BELAKANG, bukan dibaca
             | sebagai nol — "belum pernah dihitung" dan "tidak punya tagihan"
             | adalah dua keadaan yang berbeda, dan yang pertama menumpuk di
             | puncak daftar akan terbaca seperti rombongan yang sudah lunas
             | semua.
             */
            ->when(
                array_key_exists($request->string('urut')->toString(), self::URUT_SISA),
                function ($q) use ($request) {
                    $arah = self::URUT_SISA[$request->string('urut')->toString()];

                    return $q
                        ->orderByRaw('total_tagihan IS NULL')
                        ->orderByRaw(
                            '(COALESCE(total_tagihan, 0) - ('
                            .'select COALESCE(SUM(nominal), 0) from tbl_konfirmasi_pembayaran '
                            .'where tbl_konfirmasi_pembayaran.kode = tbl_pendaftaran_open_trip.kode '
                            .'and tbl_konfirmasi_pembayaran.status = ?'
                            .')) '.($arah === 'desc' ? 'desc' : 'asc'),
                            ['diterima'],
                        );
                },
            )

            ->when(
                array_key_exists($request->string('urut')->toString(), self::URUT),
                function ($q) use ($request) {
                    [$kolom, $arah] = self::URUT[$request->string('urut')->toString()];

                    // NULL didorong ke belakang: pendaftaran lama tanpa tanggal
                    // berangkat yang menumpuk di puncak "paling dekat" membuat
                    // daftarnya terbaca seperti salah urut.
                    return $q->orderByRaw("{$kolom} IS NULL")->orderBy($kolom, $arah);
                },
            )

            // Urutan ini menyusul yang di atas, jadi ia jadi pemutus seri —
            // bukan penentu utama saat saringan tagihan menyala.
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta(
            $daftar,
            fn () => PendaftaranResource::collection($daftar->getCollection())->resolve(),
            array_filter([
                'jumlah_tab' => $this->jumlahTab($request),
                'rombongan' => $this->ringkasanRombongan($request),
            ], fn ($nilai) => $nilai !== null),
        );
    }

    /**
     * Ringkasan satu rombongan: kursi terjual, uang masuk, sisa tagihan.
     *
     * Hanya ikut saat daftar DISARING per rombongan — di luar itu angkanya
     * tidak menjawab apa pun, karena menjumlahkan tiga keberangkatan yang
     * berbeda tanggal dan berbeda harga.
     *
     * Pertanyaan yang dijawabnya ditanyakan setiap kali rombongan diputuskan
     * jadi berangkat atau tidak: "sudah berapa kursi, dan uangnya sudah
     * masuk berapa". Sebelum ini jawabannya dirakit admin sendiri dari
     * daftar, satu baris demi satu baris.
     *
     * Yang BATAL tidak ikut dihitung — kursinya sudah dilepas, dan uangnya
     * (kalau ada) masuk ke urusan pengembalian, bukan ke pemasukan rombongan.
     *
     * @return array<string, mixed>|null
     */
    private function ringkasanRombongan(Request $request): ?array
    {
        $id = $request->integer('keberangkatan_id');

        if (! $id) {
            return null;
        }

        $keberangkatan = Keberangkatan::find($id);

        if (! $keberangkatan) {
            return null;
        }

        $baris = PendaftaranOpenTrip::where('keberangkatan_id', $id)
            ->where('status', '!=', 'batal')
            ->get(['id', 'kode', 'jumlah_peserta', 'total_tagihan']);

        $masuk = (int) KonfirmasiPembayaran::whereIn('kode', $baris->pluck('kode'))
            ->where('status', 'diterima')
            ->sum('nominal');

        $tagihan = (int) $baris->sum('total_tagihan');
        $kuota = $keberangkatan->kuota_dipakai;
        $peserta = (int) $baris->sum('jumlah_peserta');

        return [
            'rentang_tanggal' => $keberangkatan->rentang_tanggal,
            'pendaftaran' => $baris->count(),
            'peserta' => $peserta,
            'kuota' => $kuota,
            // null berarti "paket ini memang tanpa kuota", bukan nol kursi.
            'sisa_kursi' => $kuota ? max(0, $kuota - $peserta) : null,
            'tagihan' => $tagihan,
            'masuk' => $masuk,
            'kurang' => max(0, $tagihan - $masuk),
        ];
    }

    /**
     * Berapa pendaftaran di tiap tab — dihitung dari SELURUH data, bukan
     * dari halaman yang sedang tampil.
     *
     * Angka di atas tab itulah yang membuatnya berguna. Tanpa angkanya,
     * "Perlu ditagih" adalah tombol yang harus ditekan dulu untuk tahu
     * apakah ada pekerjaan di baliknya — dan tombol semacam itu berhenti
     * ditekan setelah dua kali kosong, termasuk pada hari ia berisi.
     *
     * Saringan paket & pencarian IKUT diperhitungkan, tetapi saringan
     * status dan tagihan TIDAK: angka di tab harus menjawab "kalau saya
     * pindah ke sana, ada berapa", bukan memantulkan pilihan yang sedang
     * aktif. Tab yang angkanya ikut menyusut begitu ditekan tidak menjawab
     * apa pun.
     *
     * @return array<string, int>
     */
    private function jumlahTab(Request $request): array
    {
        $dasar = fn () => PendaftaranOpenTrip::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('nama_paket', 'like', "%{$cari}%")
            ))
            ->when($request->integer('paket_id'), fn ($q, $id) => $q->where('travel_package_id', $id));

        $perStatus = $dasar()->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')->pluck('jumlah', 'status');

        /*
         | Kuncinya diambil dari config, bukan ditulis tangan.
         |
         | Status yang ditambahkan di config lalu terlupa di sini akan
         | menghasilkan tab tanpa angka — dan tab yang angkanya selalu nol
         | terbaca sebagai "tidak ada pekerjaan di sana", persis kebalikan
         | dari yang sebenarnya.
         */
        $hasil = ['semua' => (int) $perStatus->sum()];

        foreach (array_keys(config('orcha.status_pendaftaran', [])) as $status) {
            $hasil[$status] = (int) ($perStatus[$status] ?? 0);
        }

        $hasil['perlu_ditagih'] = (int) $dasar()->perluDitagih()->count();

        return $hasil;
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
        $pendaftaran->loadCount('riwayatKesehatan')
            ->load(['angsuranAktif.termin', 'keberangkatan'])
            ->loadSum([
                'konfirmasiPembayaran as pembayaran_diterima' => fn ($q) => $q->where('status', 'diterima'),
            ], 'nominal');

        $data = (new PendaftaranResource($pendaftaran))->resolve();

        $data['tagihan'] = TagihanPesanan::untuk($pendaftaran);

        /*
         | Tautan berkas dibuat DI SINI, bukan di resource.
         |
         | Resource yang sama dipakai halaman daftar, dan membuat tautan di sana
         | berarti satu tulisan basis data per baris per pembukaan halaman —
         | dua puluh baris jadi dua puluh tulisan untuk tautan yang tidak
         | seorang pun minta.
         */
        $data['kwitansi_tautan'] = route('tautan.pendek', [
            'kode' => TautanPendek::untuk($pendaftaran->id, 'kwitansi')->kode,
        ]);

        /*
         | Tautan formulir konfirmasi pembayaran, kodenya sudah terbawa.
         |
         | Cukup kodenya: formulir itu sendiri yang mencari tagihannya, memilih
         | jenis pembayaran yang disarankan, lalu mengisikan nominalnya. Yang
         | membuka tinggal melampirkan bukti transfer.
         |
         | Alamatnya sengaja dibiarkan terbaca — bukan dipendekkan jadi /t/xxx
         | seperti berkas. Yang diminta di sini bukan mengunduh melainkan
         | mengunggah bukti transfer, dan orang yang diminta menyerahkan bukti
         | pembayaran pantas melihat ke mana ia dibawa sebelum mengetuk.
         */
        $data['konfirmasi_pembayaran_tautan'] = route('konfirmasi-pembayaran', [
            'kode' => $pendaftaran->kode,
        ]);

        /*
         | Tautan pribadi tiap peserta yang belum mengisi riwayat kesehatan.
         |
         | Kode dan namanya sudah terbawa, sehingga yang membukanya tinggal
         | mengisi kondisinya sendiri — pola yang sudah dipakai halaman publik
         | untuk ketua rombongan, dengan alasan yang sama: rombongan besar bisa
         | jalan sendiri tanpa ada yang menyalin data kesehatan orang lain.
         |
         | Dibuat di sini, bukan di lemon: rutenya milik Orcha, dan menyusunnya
         | dari seberang berarti menebak bentuk alamat yang sewaktu-waktu
         | berubah tanpa ada yang memberi tahu.
         */
        $data['peserta_belum_isi_tautan'] = collect($pendaftaran->peserta_belum_isi)
            ->mapWithKeys(fn ($nama) => [$nama => route('riwayat-kesehatan', [
                'kode' => $pendaftaran->kode,
                'peserta' => $nama,
            ])])
            ->all();

        /*
         | Jenis kelamin yang SUDAH diketahui dari riwayat kesehatan.
         |
         | Bukan sumber kebenarannya — yang dipakai membagi kamar adalah nilai
         | yang tersimpan di baris pesertanya. Ini hanya bahan untuk MENGISI
         | AWAL layar peserta, supaya rombongan yang pesertanya sudah mengisi
         | borang kesehatan tidak perlu diketik ulang satu per satu.
         |
         | Dikirim hanya di show(), tidak di daftar: ia butuh satu kueri
         | tambahan, dan daftar dua puluh baris tidak boleh membayar dua puluh
         | kueri untuk keterangan yang tidak tampil di sana.
         */
        $data['kelamin_kesehatan'] = $pendaftaran->riwayatKesehatan()
            ->get(['nama_peserta', 'jenis_kelamin'])
            ->filter(fn ($satu) => filled($satu->jenis_kelamin))
            ->mapWithKeys(fn ($satu) => [
                mb_strtolower(trim((string) $satu->nama_peserta)) => $satu->jenis_kelamin,
            ])
            ->all();

        // Null bila belum pernah ada penggantian: suratnya memang tidak bisa
        // diterbitkan, dan menawarkan tautan yang pasti berujung galat lebih
        // buruk daripada tidak menawarkan apa pun.
        $data['surat_penggantian_kosong_tautan'] = filled($pendaftaran->riwayat_penggantian)
            ? route('tautan.pendek', [
                'kode' => TautanPendek::untuk($pendaftaran->id, 'surat-penggantian')->kode,
            ])
            : null;

        $data['pembayaran'] = KonfirmasiPembayaran::where('kode', $pendaftaran->kode)
            ->latest('id')
            ->get()
            ->map(fn ($bayar) => [
                'id' => $bayar->id,
                'jenis' => $bayar->jenis,
                'jenis_label' => $bayar->jenis_label,

                // Kanal dan pecahannya ikut dikirim: layar pesanan perlu tahu
                // baris mana yang tidak punya bukti untuk ditampilkan, dan
                // memang tidak seharusnya punya.
                'kanal' => $bayar->kanal ?? 'transfer',
                'rincian' => $bayar->rincian_gerbang,
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

        /*
         | Keputusan pengembaliannya ikut dikirim, bukan hanya nomor rekeningnya.
         |
         | Sebelumnya lemon hanya menerima 'rekening' dan menampilkannya apa
         | adanya sebagai "Rekening pengembalian: ...". Pada pengajuan yang
         | potongannya sebesar seluruh pembayaran — kembali Rp 0 — kalimat itu
         | terbaca sebagai perintah mentransfer ke sana, padahal tidak ada yang
         | perlu dikirim. Admin yang awam mengerjakannya.
         |
         | Yang menentukan bukan ada tidaknya rekening, melainkan ANGKANYA.
         */
        $perkiraan = $pembatalan
            ? PerkiraanPotongan::untuk($pembatalan->pesanan(), $pembatalan->potongan_ditetapkan)
            : null;

        $data['pembatalan'] = $pembatalan ? [
            'id' => $pembatalan->id,
            'nama_pemohon' => $pembatalan->nama_pemohon,
            'alasan_label' => $pembatalan->alasan_label,
            'penjelasan' => $pembatalan->penjelasan,
            'jumlah_dibatalkan' => $pembatalan->jumlah_dibatalkan,
            'rekening' => $pembatalan->bank.' · '.$pembatalan->nomor_rekening
                .' a.n. '.$pembatalan->atas_nama_rekening,
            'status' => $pembatalan->status,
            'status_label' => $pembatalan->status_label,
            'perkiraan' => $perkiraan,
            'dibuat_pada' => $pembatalan->created_at?->toIso8601String(),
        ] : null;

        return response()->json(['data' => $data]);
    }

    /**
     * Angka untuk penanda di bilah samping dan lonceng lemon.
     *
     * Dipecah per keadaan, bukan satu angka gabungan — tiap keadaan menuntut
     * perbuatan yang berbeda, dan admin perlu tahu mana yang didahulukan:
     *
     *   baru        — belum disentuh siapa pun; pemesannya menunggu dijawab.
     *   dihubungi   — sudah dihubungi, tetapi belum satu rupiah pun masuk.
     *   telat_lunas — sudah DP, belum lunas, DAN tenggat pelunasannya sudah
     *                 lewat. Ini yang paling mahal dibiarkan: kursinya tertahan
     *                 atas nama orang yang belum tentu berangkat, dan makin
     *                 dekat hari-H makin sulit dijual ulang.
     *
     * Yang lunas dan yang batal tidak dihitung — keduanya sudah selesai.
     */
    public function perhatian(): JsonResponse
    {
        $hitung = PendaftaranOpenTrip::selectRaw('status, count(*) as jumlah')
            ->whereIn('status', ['baru', 'dihubungi'])
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        /*
         | Tenggatnya dihitung dari tanggal berangkat, bukan disimpan sebagai
         | kolom, jadi penyaringannya diselesaikan di PHP. Yang ditarik hanya
         | yang belum lunas dan belum batal — jumlahnya kecil.
         */
        $batas = (int) config('orcha.pembayaran.pelunasan_hari_sebelum', 0);

        $telat = $batas > 0
            ? PendaftaranOpenTrip::where('status', 'dp_masuk')
                ->whereNotNull('tanggal_berangkat')
                ->get()
                ->filter(fn (PendaftaranOpenTrip $p) => $p->tanggal_berangkat->copy()->subDays($batas)->isPast())
                ->count()
            : 0;

        return response()->json(['data' => [
            'baru' => (int) $hitung->get('baru', 0),
            'dihubungi' => (int) $hitung->get('dihubungi', 0),
            'telat_lunas' => $telat,
        ]]);
    }

    /**
     * Riwayat kesehatan sengaja dipisah ke jalur sendiri. Data ini sensitif,
     * jadi Phoenix harus memintanya secara khusus, bukan ikut terbawa daftar.
     */
    public function riwayatKesehatan(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $this->catat($request, 'membuka riwayat kesehatan', ['kode' => $pendaftaran->kode]);

        /*
         | Nama yang masih tercantum di daftar peserta.
         |
         | Riwayat milik peserta yang sudah diganti TIDAK dihapus — ia arsip,
         | dan menghapus data kesehatan orang hanya karena namanya dicoret dari
         | satu daftar bukan keputusan yang pantas diambil diam-diam. Yang
         | dilakukan cukup menandainya, supaya tim lapangan tidak mencari orang
         | yang tidak ikut.
         */
        $masihIkut = collect($pendaftaran->peserta)
            ->pluck('nama')
            ->map(fn ($nama) => mb_strtolower(trim((string) $nama)))
            ->all();

        return response()->json([
            'data' => $pendaftaran->riwayatKesehatan->map(fn ($riwayat) => [
                'id' => $riwayat->id,
                'nama_peserta' => $riwayat->nama_peserta,
                'peserta_aktif' => $masihIkut === []
                    || in_array(mb_strtolower(trim((string) $riwayat->nama_peserta)), $masihIkut, true),
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

        // Cap dan angka besarnya mengikuti uang yang benar-benar masuk.
        // Sebelumnya keduanya dipatok "Belum Dibayar", sehingga kwitansi yang
        // diunduh admin tetap menyatakan belum dibayar padahal pembayarannya
        // sudah diterima seminggu sebelumnya — dan itu berkas yang dipegang
        // pelanggan.
        $tagihan = TagihanPesanan::untuk($pendaftaran);

        /*
         | Angka besar, cap, kalimat keadaan, dan perlu-tidaknya petunjuk
         | transfer — keempatnya ditentukan satu keadaan yang sama.
         |
         | Sebelumnya berkas ini selalu mencetak tenggat DP, sisa pelunasan, dan
         | cara pembayaran, apa pun keadaan pesanannya. Pelanggan yang sudah
         | melunasi menerima berkas yang tetap menagih; pelanggan yang
         | pesanannya sudah dibatalkan menerima berkas yang meminta ia
         | menyelesaikan sisa pembayaran. Berkas yang menagih uang yang tidak
         | perlu dibayar bukan sekadar salah tulis — ia membuat orang mentransfer.
         */
        $batal = $pendaftaran->status === 'batal';
        $sudahMasuk = (int) ($tagihan['sudah'] ?? 0);
        $lunas = (bool) ($tagihan['lunas'] ?? false);

        $tenggatPelunasan = $pendaftaran->tanggal_berangkat
            ? $pendaftaran->tanggal_berangkat->copy()
                ->subDays((int) config('orcha.pembayaran.pelunasan_hari_sebelum'))
                ->locale('id')->translatedFormat('j F Y')
            : null;

        // null untuk pesanan biasa, jadi tidak ada yang berubah bagi mayoritas.
        $angsuran = RencanaAngsuran::ringkasUntukPelanggan($pendaftaran->kode);

        [$jumlah, $jumlahLabel, $cap, $keadaan, $caraBayar] = match (true) {
            // Dibatalkan: tidak ada lagi yang perlu ditransfer, apa pun sisanya.
            $batal => [
                $sudahMasuk > 0 ? $tagihan['sudah_teks'] : null,
                $sudahMasuk > 0 ? 'Sudah dibayar sebelum dibatalkan' : null,
                'Dibatalkan',
                [
                    'nada' => 'awas',
                    'kalimat' => $sudahMasuk > 0
                        ? '<strong>Pendaftaran ini dibatalkan.</strong> Pembayaran yang sudah kami terima '
                            .'sebesar <strong>'.$tagihan['sudah_teks'].'</strong> diproses menurut kebijakan '
                            .'pengembalian — besar potongannya mengikuti kapan pembatalan diajukan. '
                            .'Tidak ada lagi yang perlu Anda transfer.'
                        : '<strong>Pendaftaran ini dibatalkan.</strong> Belum ada pembayaran yang kami terima, '
                            .'jadi tidak ada yang perlu dikembalikan maupun ditransfer.',
                ],
                false,
            ],

            $lunas => [
                $tagihan['total_teks'], 'Sudah dibayar penuh', 'Lunas',
                [
                    'nada' => 'aman',
                    'kalimat' => 'Pembayaran Anda <strong>sudah lunas</strong>. Seluruh biaya sebesar '
                        .'<strong>'.$tagihan['total_teks'].'</strong> telah kami terima — tidak ada sisa '
                        .'yang perlu dibayar lagi. Simpan berkas ini sampai perjalanan selesai.',
                ],
                false,
            ],

            // Sudah membayar sebagian: yang ditanya berikutnya selalu sama —
            // DP saya sudah masuk belum, dan sisanya kapan.
            $tagihan !== [] && $sudahMasuk > 0 => [
                $tagihan['sisa_teks'], 'Sisa yang harus dibayar', 'Dibayar Sebagian',
                [
                    'nada' => 'awas',
                    /*
                     | Pesanan berangsur punya tenggatnya sendiri.
                     |
                     | Berkas ini dulu selalu menagih seluruh sisanya pada H-5,
                     | termasuk kepada pelanggan yang layar pembayarannya justru
                     | menampilkan tiga termin dengan tanggalnya masing-masing.
                     | Dua janji yang bertentangan, dari sistem yang sama, kepada
                     | orang yang sedang kesulitan keuangan — dan yang mana yang
                     | ia percaya tidak bisa ditebak.
                     |
                     | Angkanya datang dari RencanaAngsuran, sumber yang sama
                     | dengan surat tanda terima. Menghitungnya ulang di sini
                     | berarti dua jawaban untuk satu jadwal.
                     */
                    'kalimat' => $angsuran
                        ? 'Pembayaran Anda <strong>sudah kami terima</strong> — <strong>'
                            .$angsuran['lunas'].' dari '.$angsuran['jumlah'].' termin</strong> lunas. '
                            .$angsuran['label'].' berikutnya <strong>'.$angsuran['kurang_teks']
                            .'</strong>, jatuh tempo <strong>'.$angsuran['jatuh_tempo_teks'].'</strong>. '
                            .($angsuran['telat']
                                ? 'Termin ini sudah lewat jatuh temponya; hubungi kami bila perlu '
                                    .'penyesuaian jadwal. '
                                : '')
                            .'Sisa seluruhnya <strong>'.$tagihan['sisa_teks'].'</strong>.'
                        : 'Uang muka Anda sebesar <strong>'.$tagihan['sudah_teks'].'</strong> '
                            .'<strong>sudah kami terima</strong>. Sisa yang perlu dilunasi '
                            .'<strong>'.$tagihan['sisa_teks'].'</strong>'
                            .($tenggatPelunasan
                                ? ', paling lambat <strong>'.$tenggatPelunasan.'</strong> (H-'
                                    .config('orcha.pembayaran.pelunasan_hari_sebelum').' sebelum berangkat).'
                                : '.'),
                ],
                true,
            ],

            default => [
                $tagihan !== [] ? $tagihan['dp_teks'] : ($biaya ? $biaya['dp_teks'] : null),
                $tagihan !== [] || $biaya
                    ? 'Dibayar sekarang · DP '.($tagihan['dp_persen'] ?? $biaya['dp_persen']).'%'
                    : null,
                'Belum Dibayar',
                $biaya || $tagihan !== [] ? [
                    'nada' => 'netral',
                    'kalimat' => '<strong>Belum ada pembayaran yang kami terima</strong> untuk pendaftaran ini. '
                        .'Uang muka sebesar <strong>'.($tagihan['dp_teks'] ?? $biaya['dp_teks']).'</strong> '
                        .'ditunggu paling lambat '.config('orcha.pembayaran.dp_batas_jam').' jam sejak '
                        .'pendaftaran, dan sisanya'
                        .($tenggatPelunasan ? ' paling lambat '.$tenggatPelunasan : ' sebelum keberangkatan').'.',
                ] : [],
                true,
            ],
        };

        // Tenggat DP dan sisa pelunasan hanya dicetak selama uangnya memang
        // masih ditunggu.
        if ($biaya !== [] && ! $caraBayar) {
            $biaya['tempo'] = false;
        }

        $isi = BerkasKwitansi::buat(
            'Rincian Biaya Pendaftaran',
            $pendaftaran->kode,
            $rincian,
            $pendaftaran->catatan,
            $jumlah,
            $jumlahLabel,
            $cap,
            biaya: $biaya,
            keadaan: $keadaan,
            caraBayar: $caraBayar,
        );

        abort_if($isi === null, 503, 'Kwitansi gagal dibuat.');

        $this->catat($request, 'unduh kwitansi pendaftaran', ['kode' => $pendaftaran->kode]);

        return response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                .BerkasKwitansi::namaBerkas('rincian-biaya', $pendaftaran->kode).'"',
        ]);
    }

    /**
     * Mendaftarkan rombongan dari sisi admin.
     *
     * Private trip dan study tour tidak pernah mendaftar lewat website, dan
     * itu bukan kekurangan melainkan bentuk jualannya: harganya dirundingkan,
     * jumlah pesertanya berubah-ubah sampai menit terakhir, dan seluruh
     * percakapannya terjadi di WhatsApp. Memaksanya lewat formulir publik
     * berarti meminta panitia sekolah mengisi ulang sesuatu yang sudah
     * disepakati lewat telepon.
     *
     * Tetapi begitu disepakati, rombongannya HARUS masuk sistem — kalau tidak,
     * ia tidak punya kode pemesanan, tidak bisa mengisi riwayat kesehatan,
     * tidak masuk manifes tour leader, dan tidak terhitung di laporan
     * keuntungan. Jalur inilah yang memasukkannya.
     *
     * HARGA BOLEH DIISI TANGAN. Paket private trip dan study tour sering
     * belum berharga di sistem karena memang dihitung per rombongan; tanpa
     * jalan memasukkannya, pendaftarannya masuk dengan tagihan nol dan
     * seluruh laporan keuntungan ikut salah tanpa ada yang menyadarinya.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'travel_package_id' => ['required', 'integer', 'exists:tbl_travel_package,id'],
            /*
             | Tanggal keberangkatan yang dipilih pendaftar.
             |
             | Nullable supaya pemanggil lama — dan paket yang belum punya
             | tanggal sama sekali — tetap bekerja seperti dulu. Yang dikirim
             | diperiksa kepemilikannya di bawah: id milik paket LAIN akan
             | memindahkan kuota ke rombongan yang salah.
             */
            'keberangkatan_id' => ['nullable', 'integer', 'exists:tbl_paket_keberangkatan,id'],
            'nama' => ['required', 'string', 'min:3', 'max:120'],
            'whatsapp' => ['required', 'string', 'min:8', 'max:32'],
            'email' => ['nullable', 'email', 'max:150'],
            'jumlah_peserta' => ['required', 'integer', 'min:1', 'max:200'],
            /*
             | Guru pendamping yang ikut berangkat tanpa dibayar.
             |
             | Tidak boleh melebihi jumlah peserta — rombongan yang seluruhnya
             | gratis berarti tidak ada yang membayar apa pun, dan itu bukan
             | pendaftaran melainkan salah ketik.
             */
            'pendamping_gratis' => ['nullable', 'integer', 'min:0', 'lt:jumlah_peserta'],
            'peserta' => ['nullable', 'array', 'max:200'],
            'peserta.*.nama' => ['required', 'string', 'max:120'],
            'peserta.*.titik_jemput' => ['nullable', 'string', 'max:191'],
            'peserta.*.bus' => ['nullable', 'string', 'max:60'],
            'peserta.*.kamar' => ['nullable', 'string', 'max:60'],
            'peserta.*.kursi' => ['nullable', 'string', 'max:10'],
            // Ejaannya SAMA PERSIS dengan borang riwayat kesehatan. Dua ejaan
            // untuk hal yang sama membuat pembagian kamar otomatis membaca
            // separuh rombongan sebagai belum diisi.
            'peserta.*.jenis_kelamin' => ['nullable', 'in:Laki-laki,Perempuan'],
            'titik_jemput' => ['nullable', 'string', 'max:191'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            /*
             | Harga per orang, bukan harga rombongan.
             |
             | Seluruh sistem menghitung tagihan sebagai satuan x peserta —
             | laporan keuntungan, kwitansi, dan rincian biaya semuanya. Menerima
             | harga rombongan di sini berarti satu tempat memakai satuan yang
             | berbeda dari semua yang lain, dan selisihnya baru ketahuan saat
             | ada yang membandingkan dua laporan.
             */
            'harga_jual' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'harga_modal' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            /*
             | Biaya tetap, satu-satunya angka di sini yang PER ROMBONGAN.
             |
             | Pengecualian yang disengaja terhadap aturan satu paragraf di
             | atas, dan justru aturan itu yang memaksanya ada: carter bus,
             | guide, dan sopir tidak bertambah mahal karena penumpangnya
             | bertambah satu, jadi memaksanya jadi angka per orang menuntut
             | admin membagi sendiri tiap kali. Selama ia ingat hasilnya benar;
             | yang terjadi sebenarnya adalah ia memakai ulang angka rombongan
             | sebelumnya.
             */
            'biaya_tetap' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [], [
            'travel_package_id' => 'paket',
            'keberangkatan_id' => 'tanggal keberangkatan',
            'peserta.*.nama' => 'nama peserta',
            'pendamping_gratis' => 'pendamping gratis',
            'biaya_tetap' => 'biaya tetap rombongan',
        ]);

        $paket = TravelPackage::find($data['travel_package_id']);

        /*
         | Keberangkatan yang dipilih, atau yang terdekat bila tidak disebut.
         |
         | Jatuh ke yang terdekat — bukan ke yang pertama di basis data —
         | supaya pemanggil lama yang belum mengirim pilihannya tetap masuk ke
         | rombongan yang masuk akal, bukan ke tanggal yang sudah lewat.
         */
        $keberangkatan = null;

        if (! empty($data['keberangkatan_id'])) {
            $keberangkatan = Keberangkatan::find($data['keberangkatan_id']);

            if (! $keberangkatan || $keberangkatan->travel_package_id !== $paket->id) {
                return response()->json([
                    'pesan' => 'Tanggal keberangkatan itu bukan milik paket ini.',
                ], 422);
            }

            if (! $keberangkatan->aktif) {
                return response()->json([
                    'pesan' => 'Tanggal '.$keberangkatan->rentang_tanggal.' sudah ditutup. Pilih tanggal lain.',
                ], 422);
            }
        } else {
            $keberangkatan = $paket->keberangkatanTersedia()->first();
        }

        /*
         | Kuota diperiksa PER TANGGAL.
         |
         | Satu paket dengan tiga tanggal yang kuotanya dihitung bersama akan
         | menolak pendaftar pada tanggal yang masih kosong hanya karena
         | tanggal lain sudah penuh — dan penolakan itu tidak punya penjelasan
         | yang bisa dibaca siapa pun.
         */
        if ($keberangkatan) {
            $sisa = $keberangkatan->sisa_kursi;

            if ($sisa !== null && $sisa < (int) $data['jumlah_peserta']) {
                return response()->json([
                    'pesan' => $sisa <= 0
                        ? 'Tanggal '.$keberangkatan->rentang_tanggal.' sudah penuh. Pilih tanggal lain.'
                        : 'Tanggal '.$keberangkatan->rentang_tanggal.' tinggal '.$sisa.' kursi.',
                ], 422);
            }
        }

        $peserta = collect($data['peserta'] ?? [])
            ->map(fn ($baris) => [
                'nama' => trim($baris['nama']),
                'titik_jemput' => trim($baris['titik_jemput'] ?? '') ?: null,
                'bus' => trim($baris['bus'] ?? '') ?: null,
                'kamar' => trim($baris['kamar'] ?? '') ?: null,
                'kursi' => trim((string) ($baris['kursi'] ?? '')) ?: null,
                'jenis_kelamin' => trim((string) ($baris['jenis_kelamin'] ?? '')) ?: null,
            ])
            ->filter(fn ($baris) => $baris['nama'] !== '')
            ->values()
            ->all();

        $pendaftaran = PendaftaranOpenTrip::create([
            'travel_package_id' => $paket->id,
            'keberangkatan_id' => $keberangkatan?->id,
            'nama_paket' => $paket->name,
            'nama' => $data['nama'],
            'whatsapp' => $data['whatsapp'],
            'email' => $data['email'] ?? null,
            'jumlah_peserta' => $data['jumlah_peserta'],
            'pendamping_gratis' => $data['pendamping_gratis'] ?? 0,
            'daftar_peserta' => $peserta,
            // Tanggal keberangkatannya, bukan tanggal paket: paket kini boleh
            // punya beberapa, dan yang dipakai surat & kwitansi adalah yang
            // benar-benar dipilih pendaftar.
            'tanggal_berangkat' => $keberangkatan?->tanggal_berangkat ?? $paket->tanggal_berangkat,
            'titik_jemput' => $data['titik_jemput'] ?? null,
            'catatan' => $data['catatan'] ?? null,

            /*
             | Harga yang dikirim admin MENANG atas harga paket.
             |
             | Model membekukan harga paket sendiri saat membuat baris, tetapi
             | hanya bila kolomnya masih kosong (??=). Mengisinya di sini
             | membuat harga rundingan yang dipakai, bukan harga daftar — dan
             | untuk private trip harga daftarnya memang sering tidak ada.
             */
            'harga_jual' => $data['harga_jual'] ?? null,
            'harga_modal' => $data['harga_modal'] ?? null,
            // Nol, bukan null: rombongan tanpa biaya carter memang tidak
            // punya biaya tetap, dan itu keadaan yang diketahui — bukan
            // keadaan yang belum diisi.
            'biaya_tetap' => (int) ($data['biaya_tetap'] ?? 0),

            /*
             | Statusnya 'baru', bukan langsung 'dp_masuk'.
             |
             | Memasukkan rombongan bukan berarti uangnya sudah diterima, dan
             | status yang memajukan dirinya sendiri membuat laporan keuangan
             | menyebut uang yang belum ada. Admin memajukannya lewat tombol
             | status seperti pendaftaran lain — dan sampai itu terjadi,
             | formulir riwayat kesehatannya memang belum bisa diisi.
             */
            'status' => 'baru',
        ]);

        $this->catat($request, 'daftarkan rombongan dari admin', [
            'kode' => $pendaftaran->kode,
            'paket' => $paket->name,
            'kategori' => $paket->category,
            'jumlah_peserta' => $pendaftaran->jumlah_peserta,
            'pendamping_gratis' => $pendaftaran->pendamping_gratis,
            'nama_terdata' => count($peserta),
        ]);

        // Tautan riwayat kesehatannya sudah dibawa PendaftaranResource sendiri.
        $data = (new PendaftaranResource($pendaftaran->fresh()->loadCount('riwayatKesehatan')))->resolve();

        return response()->json(['data' => $data], 201);
    }

    /**
     * Melengkapi daftar nama peserta dari sisi admin.
     *
     * Pendaftaran lama — yang masuk sebelum website meminta nama tiap peserta —
     * tidak punya daftar itu, dan rombongan tanpa nama tidak bisa masuk manifes
     * panggil-nama. Sebelumnya satu-satunya jalan adalah meminta pemesan
     * mengisi ulang lewat website; sekarang admin bisa memasukkannya sendiri
     * dari daftar yang biasanya sudah ada di WhatsApp atau berkas panitia.
     *
     * jumlah_peserta SENGAJA tidak ikut berubah. Angka itulah yang mengalikan
     * harga paket jadi tagihan; menyesuaikannya diam-diam karena admin
     * kelebihan satu baris tempelan berarti mengubah jumlah yang harus dibayar
     * pelanggan tanpa ada yang memutuskannya. Selisihnya dilaporkan lewat
     * pesan balasan, keputusannya tetap di tangan manusia.
     */
    public function ubahPeserta(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'peserta' => 'present|array|max:200',
            'peserta.*.nama' => 'required|string|max:120',
            'peserta.*.titik_jemput' => 'nullable|string|max:191',
            /*
             | Bus dan kamar dibagikan lewat layar yang sama dengan nama.
             |
             | Pembagiannya dikerjakan beberapa hari sebelum berangkat, jauh
             | setelah namanya masuk — tetapi memisahkannya jadi layar sendiri
             | berarti daftar nama yang sama harus dibuka dua kali, dan yang
             | kedua hampir tidak pernah dibuka.
             */
            'peserta.*.bus' => 'nullable|string|max:60',
            'peserta.*.kamar' => 'nullable|string|max:60',
            // Nomor kursi, terpisah dari nama busnya — lihat alasannya di
            // PendaftaranOpenTrip::getPesertaAttribute().
            'peserta.*.kursi' => 'nullable|string|max:10',
            'peserta.*.jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            // Nama yang digantikan baris ini. Diisi HANYA saat admin memang
            // mengganti orang — pembetulan salah ketik tidak mengisinya.
            'peserta.*.gantikan' => 'nullable|string|max:120',
            // Titik jemput yang dipakai orang yang digantikan. Ikut dikirim
            // supaya perpindahan titiknya tercatat, bukan hanya namanya.
            'peserta.*.gantikan_titik' => 'nullable|string|max:191',
        ], [], [
            'peserta.*.nama' => 'nama peserta',
            'peserta.*.titik_jemput' => 'titik jemput',
        ]);

        $bersih = collect($data['peserta'])
            ->map(fn ($baris) => [
                'nama' => trim($baris['nama']),
                'titik_jemput' => trim($baris['titik_jemput'] ?? '') ?: null,
                'bus' => trim($baris['bus'] ?? '') ?: null,
                'kamar' => trim($baris['kamar'] ?? '') ?: null,
                'kursi' => trim((string) ($baris['kursi'] ?? '')) ?: null,
                'jenis_kelamin' => trim((string) ($baris['jenis_kelamin'] ?? '')) ?: null,
            ])
            ->filter(fn ($baris) => $baris['nama'] !== '')
            ->values()
            ->all();

        /*
         | Penggantian dicatat dari NIAT yang dinyatakan admin, bukan ditebak
         | dari selisih daftar.
         |
         | Menebaknya dari selisih tidak bisa membedakan dua hal yang berbeda
         | jauh: mengganti orang, dan membetulkan salah ketik. Keduanya
         | berbentuk sama — satu nama keluar, satu nama masuk — sehingga
         | membetulkan "Suparjimen" jadi "Suparjiman" ikut tercatat sebagai
         | penggantian peserta, lengkap dengan suratnya. Sekarang yang mencatat
         | adalah tombol "Ganti" yang ditekan admin.
         */
        $penggantian = collect($data['peserta'])
            ->filter(fn ($baris) => filled($baris['gantikan'] ?? null)
                && mb_strtolower(trim($baris['gantikan'])) !== mb_strtolower(trim($baris['nama'])))
            ->map(function ($baris) use ($request) {
                $catatan = [
                    'dari' => trim($baris['gantikan']),
                    'ke' => trim($baris['nama']),
                    'pada' => now()->toIso8601String(),
                    'oleh' => $request->attributes->get('admin_pemanggil'),
                ];

                /*
                 | Titik jemputnya ikut dicatat walau tidak berpindah.
                 |
                 | Arsip yang menyebut titiknya hanya saat berubah menyisakan
                 | pertanyaan yang tidak bisa dijawab lagi setahun kemudian:
                 | baris tanpa titik itu berarti titiknya memang tetap, atau
                 | titiknya tidak sempat dicatat? Dicatat selalu, sehingga
                 | diamnya arsip tidak perlu ditafsirkan.
                 */
                $titikLama = trim((string) ($baris['gantikan_titik'] ?? ''));
                $titikBaru = trim((string) ($baris['titik_jemput'] ?? ''));

                if ($titikLama !== '' || $titikBaru !== '') {
                    $catatan['dari_titik'] = $titikLama ?: null;
                    $catatan['ke_titik'] = $titikBaru ?: null;
                }

                return $catatan;
            })
            ->values()
            ->all();

        $pendaftaran->update([
            'daftar_peserta' => $bersih ?: null,
            'riwayat_penggantian' => $penggantian !== []
                ? array_merge($pendaftaran->riwayat_penggantian ?? [], $penggantian)
                : $pendaftaran->riwayat_penggantian,
        ]);

        $this->catat($request, 'ubah daftar peserta pendaftaran', [
            'penggantian' => collect($penggantian)
                ->map(fn ($satu) => ($satu['dari'] ?? '—').' → '.($satu['ke'] ?? '—'))
                ->all(),
            'kode' => $pendaftaran->kode,
            'jumlah_nama' => count($bersih),
            'jumlah_peserta' => $pendaftaran->jumlah_peserta,
        ]);

        return response()->json([
            'data' => (new PendaftaranResource($pendaftaran->fresh()->loadCount('riwayatKesehatan')))->resolve(),
            'pesan' => count($bersih) === (int) $pendaftaran->jumlah_peserta
                ? 'Daftar peserta tersimpan.'
                : 'Daftar peserta tersimpan — '.count($bersih).' nama untuk '
                    .$pendaftaran->jumlah_peserta.' peserta yang tercatat.',
        ]);
    }

    /**
     * Surat pernyataan penggantian peserta, berbentuk DOCX.
     *
     * Dibuat di sini, bukan digambar ulang oleh lemon: surat ini berdampingan
     * dengan kwitansi di tangan pemesan, dan dua aplikasi yang menggambar
     * dokumen resmi yang sama cepat atau lambat menghasilkan dua rupa yang
     * berbeda.
     */
    /**
     * Surat pernyataan penggantian peserta — SATU untuk seluruh pendaftaran.
     *
     * Sebelumnya satu surat per penggantian, dan pemesan yang mengganti dua
     * orang menandatangani dua surat bermaterai untuk pemesanan yang sama.
     * Padahal pihak yang menyatakan sama, pendaftaran yang dirujuk sama, dan
     * kebijakan yang mendasarinya sama — yang berbeda cuma barisnya. Sekarang
     * seluruh riwayat disusun jadi satu tabel bernomor di dalam satu surat.
     *
     * Isinya dibaca dari riwayat pendaftaran itu sendiri, bukan dari parameter
     * yang dikirim pemanggil: yang tercetak di surat bermaterai harus persis
     * yang tercatat sistem, tidak boleh bisa disetir lewat URL.
     */
    public function suratPenggantian(PendaftaranOpenTrip $pendaftaran, Request $request)
    {
        $riwayat = $pendaftaran->riwayat_penggantian ?? [];

        abort_if($riwayat === [], 422,
            'Pendaftaran ini belum punya penggantian peserta yang bisa disuratkan.');

        $this->catat($request, 'cetak surat penggantian peserta', [
            'kode' => $pendaftaran->kode,
            'jumlah_penggantian' => count($riwayat),
        ]);

        return response()->streamDownload(
            fn () => print SuratPenggantian::buat($pendaftaran, $riwayat),
            SuratPenggantian::namaBerkas($pendaftaran->kode),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Menyimpan surat pernyataan yang sudah ditandatangani.
     *
     * Diterima apa adanya — PDF hasil pindaian maupun foto dari ponsel. Memaksa
     * satu bentuk saja berarti menolak cara paling lazim berkasnya sampai ke
     * admin: pemesan memotretnya lalu mengirim lewat WhatsApp.
     *
     * Tidak diubah jadi WebP seperti gambar etalase. Ini bukti bertanda tangan;
     * memampatkannya ulang berarti menurunkan mutu satu-satunya hal yang
     * membuatnya berguna — coretan tinta di atas kertas.
     */
    public function unggahSuratPenggantian(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $request->validate([
            'surat' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:8192',
        ], [], ['surat' => 'berkas surat']);

        $this->hapusSurat($pendaftaran->surat_penggantian);

        $jalur = $request->file('surat')->store('surat-penggantian', 'public');

        $pendaftaran->update([
            'surat_penggantian' => '/storage/'.$jalur,
            'surat_penggantian_pada' => now(),
        ]);

        $this->catat($request, 'unggah surat penggantian bertanda tangan', [
            'kode' => $pendaftaran->kode,
        ]);

        return response()->json([
            'pesan' => 'Surat pernyataan bertanda tangan tersimpan.',
            'data' => new PendaftaranResource($pendaftaran->fresh()),
        ]);
    }

    /** Mencabut surat yang salah unggah. Berkasnya ikut dihapus, bukan ditinggal yatim. */
    public function hapusSuratPenggantian(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $this->hapusSurat($pendaftaran->surat_penggantian);

        $pendaftaran->update([
            'surat_penggantian' => null,
            'surat_penggantian_pada' => null,
        ]);

        $this->catat($request, 'hapus surat penggantian bertanda tangan', [
            'kode' => $pendaftaran->kode,
        ]);

        return response()->json([
            'pesan' => 'Surat pernyataan dihapus.',
            'data' => new PendaftaranResource($pendaftaran->fresh()),
        ]);
    }

    private function hapusSurat(?string $jalur): void
    {
        if (blank($jalur) || ! str_starts_with($jalur, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(str_replace('/storage/', '', $jalur));
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

    /**
     * Membetulkan data pendaftaran yang sudah masuk — termasuk MEMINDAHKANNYA
     * ke rombongan lain.
     *
     * Sebelum ini tidak ada jalur sama sekali: salah ketik nomor WhatsApp,
     * jumlah peserta yang bertambah, atau pelanggan yang minta pindah dari
     * tanggal 4 ke tanggal 11 semuanya hanya bisa diselesaikan dengan
     * membatalkan lalu mendaftar ulang. Cara itu membuang kode pemesanan,
     * riwayat pembayaran, angsuran, dan riwayat kesehatan yang sudah diisi
     * peserta — empat hal yang tidak bisa dibuat ulang.
     *
     * Semua medan bersifat `sometimes`: lemon mengirim yang diubah saja, dan
     * borang yang hanya memperbaiki nomor WhatsApp tidak boleh ikut
     * mengosongkan catatan.
     */
    /**
     * Memindahkan pendaftaran ke PAKET LAIN — open trip jadi private trip,
     * atau sebaliknya.
     *
     * Jalur tersendiri, bukan ditempelkan ke update(). update() sengaja
     * menolak pindah paket dengan kalimatnya sendiri ("pemesanannya memang
     * berbeda harga dan isi"), dan penolakan itu tetap benar untuk jalur yang
     * dipakai sehari-hari: mengubah paket diam-diam lewat borang data pemesan
     * akan menggeser tagihan tanpa ada yang memutuskannya.
     *
     * Yang membuat ini TIDAK boleh dikerjakan dengan membatalkan lalu
     * mendaftar ulang: seluruh riwayat uang — bukti pembayaran, angsuran,
     * kwitansi — menempel pada KODE pendaftaran, bukan pada nomor barisnya.
     * Pendaftaran baru dapat kode baru, jadi uang yang sudah masuk tertinggal
     * di kode lama dan jejaknya terbelah dua. Di sini kodenya dipertahankan,
     * sehingga tidak ada satu rupiah pun yang perlu dipindahkan.
     *
     * Harga jual DIBEKUKAN saat pendaftaran dibuat (lihat booted() pada
     * modelnya) supaya revisi harga paket tidak menggeser laporan bulan lalu.
     * Pembekuan itu harus dilepas di sini — kalau tidak, pindah ke paket yang
     * harganya berbeda tidak akan mengubah tagihannya sama sekali.
     */
    /**
     * RAB yang boleh dipakai memindahkan pendaftaran ini.
     *
     * Disaring di sini, bukan di lemon: daftar RAB berisi nama dan nomor
     * seluruh pelanggan, dan layar pendaftaran satu rombongan tidak punya
     * urusan dengan rombongan lain. Yang dikirim hanya milik nomor yang sama.
     *
     * Yang sudah terpakai pendaftaran LAIN tetap ikut, ditandai — supaya
     * "RAB saya kok tidak ada di daftar" terjawab di layarnya sendiri, bukan
     * lewat tebakan.
     */
    public function rabCocok(PendaftaranOpenTrip $pendaftaran): JsonResponse
    {
        $nomor = NomorTelepon::angka($pendaftaran->whatsapp);

        if (! $nomor) {
            return response()->json(['data' => []]);
        }

        $daftar = Rab::query()
            ->where('status', 'disetujui')
            ->orderByDesc('id')
            /*
             | Barisnya diambil UTUH, tanpa daftar kolom.
             |
             | Memilih kolom di sini pernah memulangkan harga yang salah tanpa
             | satu pun galat: HitungRab membaca harga_nego, margin_jenis,
             | margin_nilai, pembulatan, dan harga_dasar — kolom yang tidak
             | ikut terambil terbaca null, dan RAB nego Rp 395.000 tampil
             | Rp 406.250 dengan untung Rp 0. Angka itulah yang dipakai admin
             | memutuskan, jadi salahnya berakhir di tagihan pelanggan.
             */
            ->get()
            ->filter(fn (Rab $r) => NomorTelepon::angka($r->whatsapp) === $nomor)
            ->map(function (Rab $r) use ($pendaftaran) {
                $angka = HitungRab::ringkas($r);

                return [
                    'id' => $r->id,
                    'kode' => $r->kode,
                    'judul' => $r->judul,
                    'jenis' => $r->jenis,
                    'tanggal_mulai' => $r->tanggal_mulai?->toDateString(),
                    'tanggal_teks' => $r->tanggal_mulai?->locale('id')->translatedFormat('j M Y'),
                    'jumlah_peserta' => (int) $r->jumlah_peserta,
                    'harga_jual' => (int) $angka['untuk_pendaftaran']['harga_jual'],
                    'harga_modal' => (int) $angka['untuk_pendaftaran']['harga_modal'],
                    /*
                     | Modal TOTAL ikut, dan layar memakai angka inilah.
                     |
                     | Modal per orang sering nol dengan benar: Hiace dan
                     | homestay dibayar per rombongan, bukan per kepala, jadi
                     | seluruhnya masuk biaya tetap. "Modal Rp 0/orang" di
                     | layar akan terbaca sebagai trip tanpa biaya.
                     */
                    'biaya_tetap' => (int) $angka['untuk_pendaftaran']['biaya_tetap'],
                    'modal_total' => (int) $angka['modal_total'],
                    'untung' => (int) $angka['untung'],
                    'punya_paket' => (bool) $r->travel_package_id,
                    // Terpakai pendaftaran lain — ditampilkan, tetapi tidak
                    // bisa dipilih.
                    'terpakai' => $r->kode_pendaftaran && $r->kode_pendaftaran !== $pendaftaran->kode
                        ? $r->kode_pendaftaran
                        : null,
                    'peserta_cocok' => (int) $r->jumlah_peserta === (int) $pendaftaran->jumlah_peserta,
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => $daftar]);
    }

    public function pindahPaket(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            /*
             | RAB sebagai SUMBER ANGKA, bukan sekadar jalan pintas mengetik.
             |
             | Tanpa ini modal pendaftaran diambil dari paket katalog,
             | sementara modal yang sebenarnya sudah dihitung baris per baris
             | di RAB. Keduanya hampir tidak pernah sama, dan yang terbaca
             | laporan keuntungan adalah yang salah: trip yang RAB-nya rugi
             | Rp 45.000 bisa terbaca untung sembilan ratus ribu tanpa ada
             | seorang pun salah mengetik.
             |
             | Paket dan tanggalnya menyusul dari RAB, jadi keduanya tidak
             | lagi wajib dikirim saat rab_id ada.
             */
            'rab_id' => ['sometimes', 'nullable', 'integer', 'exists:tbl_rab,id'],
            'travel_package_id' => ['required_without:rab_id', 'integer', 'exists:tbl_travel_package,id'],
            'keberangkatan_id' => ['required_without:rab_id', 'integer', 'exists:tbl_paket_keberangkatan,id'],
            'harga_jual' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'paksa' => ['sometimes', 'boolean'],
            // Hanya dibutuhkan bila tagihan barunya LEBIH KECIL daripada uang
            // yang sudah diterima. Diminta saat itu juga, bukan ditanyakan
            // belakangan: selisih yang menggantung tanpa keputusan adalah
            // uang yang pelanggannya ingat dan kantornya lupa.
            'kelebihan' => ['sometimes', 'nullable', 'in:refund,saldo'],
            'alasan' => ['sometimes', 'nullable', 'string', 'max:191'],
        ], [], [
            'travel_package_id' => 'paket tujuan',
            'keberangkatan_id' => 'tanggal keberangkatan',
            'harga_jual' => 'harga jual',
        ]);

        if ((string) $pendaftaran->status === 'batal') {
            return response()->json([
                'pesan' => 'Pendaftaran ini sudah dibatalkan. Yang batal tidak dipindahkan, tetapi didaftarkan ulang.',
            ], 422);
        }

        $rab = null;
        $angkaRab = null;

        if (! empty($data['rab_id'])) {
            $rab = Rab::find($data['rab_id']);

            if ((string) $rab->status !== 'disetujui') {
                return response()->json([
                    'pesan' => 'RAB '.$rab->kode.' belum disetujui (status sekarang "'.$rab->status
                        .'"). Tagihan pelanggan hanya boleh dihitung ulang dari angka yang sudah disepakati.',
                ], 422);
            }

            if ($rab->kode_pendaftaran && $rab->kode_pendaftaran !== $pendaftaran->kode) {
                return response()->json([
                    'pesan' => 'RAB '.$rab->kode.' sudah dipakai pendaftaran '.$rab->kode_pendaftaran
                        .'. Satu RAB hanya untuk satu rombongan.',
                ], 422);
            }

            /*
             | Pemiliknya dicocokkan lewat nomor yang sudah dibakukan.
             |
             | "+62 882-0010-32472" dan "0882-0010-32472" adalah orang yang
             | sama; penolakan karena bedanya tanda baca akan terbaca sebagai
             | sistem yang rusak, bukan sebagai pengaman.
             */
            $waRab = NomorTelepon::angka($rab->whatsapp);
            $waDaftar = NomorTelepon::angka($pendaftaran->whatsapp);

            if ($waRab && $waDaftar && $waRab !== $waDaftar) {
                return response()->json([
                    'pesan' => 'RAB ini atas nama '.$rab->nama_pelanggan.' ('.$rab->whatsapp
                        .'), sedangkan pendaftaran '.$pendaftaran->kode.' atas nama '.$pendaftaran->nama
                        .' ('.$pendaftaran->whatsapp.'). Pastikan RAB-nya milik rombongan yang sama.',
                ], 422);
            }

            /*
             | Jumlah orang TIDAK diambil diam-diam dari RAB.
             |
             | Harga per orang di RAB dihitung untuk jumlah kepala tertentu.
             | Memakainya pada rombongan yang jumlahnya lain mengubah total
             | tagihan tanpa ada yang memutuskannya — dan mana yang benar,
             | RAB atau daftar peserta, tidak bisa ditebak dari sini.
             */
            if ((int) $rab->jumlah_peserta !== (int) $pendaftaran->jumlah_peserta) {
                return response()->json([
                    'pesan' => 'RAB ini disusun untuk '.$rab->jumlah_peserta.' orang, sedangkan pendaftarannya '
                        .$pendaftaran->jumlah_peserta.' orang. Samakan dulu jumlahnya — harga per orang di RAB '
                        .'dihitung dari jumlah itu, jadi menebaknya di sini berarti mengubah tagihan tanpa sengaja.',
                ], 422);
            }

            $paket = $rab->travel_package_id ? TravelPackage::find($rab->travel_package_id) : null;

            if (! $paket && ! empty($data['travel_package_id'])) {
                $paket = TravelPackage::find($data['travel_package_id']);
            }

            if (! $paket) {
                return response()->json([
                    'pesan' => 'RAB '.$rab->kode.' belum tertaut ke paket wisata mana pun. Buka RAB-nya lalu '
                        .'jadikan paket dulu, atau pilih paket tujuannya sendiri di bawah.',
                    'butuh_paket' => true,
                ], 422);
            }

            $angkaRab = HitungRab::ringkas($rab);

            if ($angkaRab['modal_total'] <= 0) {
                return response()->json([
                    'pesan' => 'RAB '.$rab->kode.' belum berisi biaya apa pun, jadi tidak ada angka yang bisa dipakai.',
                ], 422);
            }

            /*
             | Tanggal keberangkatannya ikut RAB. Bila paket itu belum punya
             | tanggal tersebut, barisnya DIBUAT — tetapi di dalam transaksi
             | di bawah, bukan di sini: perpindahan masih bisa dibatalkan
             | (kelebihan bayar yang belum diputuskan), dan tanggal sisa yang
             | tertinggal di paket terbit akan muncul sebagai pilihan
             | berangkat di halaman publik.
             */
            $tanggalRab = $rab->tanggal_mulai?->toDateString();
            $tujuan = $tanggalRab
                ? Keberangkatan::where('travel_package_id', $paket->id)
                    ->whereDate('tanggal_berangkat', $tanggalRab)->first()
                : null;

            if (! $tanggalRab) {
                return response()->json([
                    'pesan' => 'RAB '.$rab->kode.' belum punya tanggal mulai.',
                ], 422);
            }
        } else {
            $paket = TravelPackage::findOrFail($data['travel_package_id']);
            $tujuan = Keberangkatan::find($data['keberangkatan_id']);

            if (! $tujuan || (int) $tujuan->travel_package_id !== (int) $paket->id) {
                return response()->json([
                    'pesan' => 'Tanggal itu bukan milik paket '.$paket->name.'.',
                ], 422);
            }

            if ((int) $paket->id === (int) $pendaftaran->travel_package_id
                && (int) $tujuan->id === (int) $pendaftaran->keberangkatan_id) {
                return response()->json([
                    'pesan' => 'Paket dan tanggalnya sama dengan yang sekarang — tidak ada yang dipindahkan.',
                ], 422);
            }
        }

        $paksa = (bool) ($data['paksa'] ?? false);

        /*
         | Kuota diperiksa, dan boleh ditembus DENGAN SENGAJA — aturan yang
         | sama persis dengan pindah tanggal di update(). Rombongan penuh yang
         | kelebihan satu kursi memang kadang diputuskan; yang tidak boleh
         | adalah kelebihannya terjadi tanpa ada yang memilihnya.
         */
        // Tanggal yang belum ada (baru akan dibuat dari RAB) jelas masih
        // kosong — tidak ada kuota yang bisa ditembus di sana.
        $sisa = $tujuan?->sisa_kursi;
        if (! $paksa && $tujuan && $sisa !== null && $sisa < (int) $pendaftaran->jumlah_peserta) {
            return response()->json([
                'pesan' => 'Tanggal '.$tujuan->rentang_tanggal.' tinggal '.max(0, $sisa)
                    .' kursi, sedangkan rombongan ini '.$pendaftaran->jumlah_peserta.' orang.',
                'butuh_paksa' => true,
            ], 422);
        }

        $sebelum = [
            'paket' => $pendaftaran->nama_paket,
            'tanggal' => $pendaftaran->tanggal_berangkat?->toDateString(),
            'tagihan' => (int) $pendaftaran->total_tagihan,
        ];

        /*
         | Dikerjakan di dalam transaksi, lalu DIBATALKAN bila ternyata
         | kelebihan bayar dan admin belum memutuskan apa jadinya.
         |
         | Alternatifnya menghitung sendiri tagihan barunya sebelum menyimpan
         | — dan itu berarti menyalin rumus omzet ke tempat kedua. Dua rumus
         | sejajar untuk angka uang pasti berbeda jawabannya suatu saat, dan
         | yang pertama ketahuan biasanya keuntungan yang tidak pernah cocok.
         | Lebih aman membiarkan model menghitungnya sendiri lalu membaca
         | hasilnya.
         */
        try {
            $hasil = DB::transaction(function () use ($pendaftaran, $paket, $tujuan, $data, $request, $sebelum, $rab, $angkaRab) {
                /*
                 | Tanggal dari RAB yang belum ada di paket dibuatkan sekarang,
                 | lengkap dengan angkanya, supaya tanggal itu tidak jadi lubang
                 | yang harganya harus ditebak lain kali.
                 |
                 | Kuotanya dipatok sejumlah rombongan ini: private trip yang
                 | tanggalnya terbuka di paket terbit bisa didaftari orang lain,
                 | dan rombongan yang disewa satu keluarga tiba-tiba kedatangan
                 | orang asing adalah kesalahan yang tidak bisa ditarik.
                 */
                if (! $tujuan) {
                    $mulai = $rab->tanggal_mulai;
                    $hari = max(1, (int) $rab->jumlah_hari);

                    $tujuan = Keberangkatan::create([
                        'travel_package_id' => $paket->id,
                        'tanggal_berangkat' => $mulai,
                        'tanggal_pulang' => $hari > 1 ? $mulai->copy()->addDays($hari - 1) : null,
                        'kuota' => (int) $rab->jumlah_peserta,
                        'price' => (int) $angkaRab['untuk_pendaftaran']['harga_jual'],
                        'harga_modal' => (int) $angkaRab['untuk_pendaftaran']['harga_modal'],
                        'aktif' => true,
                        'catatan' => 'Dibuat dari '.$rab->kode.' saat '.$pendaftaran->kode.' dipindahkan.',
                    ]);
                }

                $pendaftaran->travel_package_id = $paket->id;
                $pendaftaran->nama_paket = $paket->name;
                $pendaftaran->keberangkatan_id = $tujuan->id;
                $pendaftaran->tanggal_berangkat = $tujuan->tanggal_berangkat;

                if ($angkaRab) {
                    /*
                     | Angkanya dari RAB, termasuk MODAL dan biaya tetap — itu
                     | seluruh alasan jalur ini ada. Dirakit HitungRab, sama
                     | persis dengan yang dipakai RabController::jadikanPendaftaran,
                     | supaya laporan keuntungan tidak pernah berbeda beberapa
                     | rupiah dari penawaran yang disetujui pelanggan.
                     |
                     | Promo katalog TIDAK ditumpuk di atasnya: harga RAB sudah
                     | harga yang disepakati, dan potongan kedua di atas harga
                     | nego adalah diskon yang tidak pernah dijanjikan siapa pun.
                     */
                    $pendaftaran->harga_jual = $data['harga_jual'] ?? (int) $angkaRab['untuk_pendaftaran']['harga_jual'];
                    $pendaftaran->harga_modal = (int) $angkaRab['untuk_pendaftaran']['harga_modal'];
                    $pendaftaran->biaya_tetap = (int) $angkaRab['untuk_pendaftaran']['biaya_tetap'];
                    $pendaftaran->potongan_promo = 0;
                } else {
                    // Pembekuan harga dilepas: diisi ulang dari paket tujuan, kecuali
                    // admin menyebut angkanya sendiri (rombongan yang sudah telanjur
                    // disepakati di harga lain).
                    $pendaftaran->harga_jual = $data['harga_jual'] ?? (int) ($tujuan->price ?? $paket->price);
                    $pendaftaran->harga_modal = $tujuan->harga_modal ?? $paket->harga_modal;
                    $pendaftaran->potongan_promo = (int) (
                        RincianBiaya::untuk($paket, (int) $pendaftaran->jumlah_peserta)['promo_potongan'] ?? 0
                    );
                }

                $pendaftaran->save();
                $pendaftaran->refresh();

                $tagihanBaru = (int) $pendaftaran->total_tagihan;
                $dibayar = (int) KonfirmasiPembayaran::where('kode', $pendaftaran->kode)
                    ->where('status', 'diterima')
                    ->sum('nominal');

                $kelebihan = max(0, $dibayar - $tagihanBaru);

                if ($kelebihan > 0 && empty($data['kelebihan'])) {
                    // Belum diputuskan: seluruh perpindahan dibatalkan, dan
                    // angkanya disebutkan supaya admin memilih dengan tahu.
                    throw new KelebihanBelumDiputuskan($kelebihan, $tagihanBaru, $dibayar);
                }

                $saldo = null;
                if ($kelebihan > 0 && ($data['kelebihan'] ?? null) === 'saldo') {
                    $saldo = SaldoPelanggan::create([
                        'whatsapp' => $pendaftaran->whatsapp,
                        'nama' => $pendaftaran->nama,
                        'nominal' => $kelebihan,
                        'asal_kode' => $pendaftaran->kode,
                        'alasan' => 'Kelebihan bayar setelah pindah dari '.$sebelum['paket'].' ke '.$paket->name,
                        'dicatat_oleh' => $request->attributes->get('admin_pemanggil'),
                    ]);
                }

                // Ditandai setelah perpindahan benar-benar berdiri: RAB yang
                // tertaut ke pendaftaran yang batal tersimpan akan menolak
                // dipakai lagi, padahal belum pernah terpakai.
                $rab?->update(['kode_pendaftaran' => $pendaftaran->kode]);

                return [
                    'tagihan_baru' => $tagihanBaru,
                    'dibayar' => $dibayar,
                    'kelebihan' => $kelebihan,
                    'perlakuan_kelebihan' => $kelebihan > 0 ? ($data['kelebihan'] ?? null) : null,
                    'saldo_id' => $saldo?->id,
                    /*
                     | Tanggalnya dipulangkan, tidak dibaca ulang dari $tujuan di
                     | luar: pada jalur RAB barisnya baru lahir DI DALAM closure
                     | ini, jadi $tujuan di luar masih null dan jejak auditnya
                     | akan jatuh justru saat perpindahannya berhasil.
                     */
                    'tanggal_baru' => $tujuan->tanggal_berangkat?->toDateString(),
                    'keberangkatan_id' => $tujuan->id,
                    'rab' => $rab?->kode,
                ];
            });
        } catch (KelebihanBelumDiputuskan $e) {
            return response()->json([
                'pesan' => 'Tagihan barunya Rp '.number_format($e->tagihanBaru, 0, ',', '.')
                    .', sedangkan yang sudah dibayar Rp '.number_format($e->dibayar, 0, ',', '.')
                    .'. Kelebihan Rp '.number_format($e->kelebihan, 0, ',', '.')
                    .' mau dikembalikan atau disimpan sebagai saldo untuk trip berikutnya?',
                'butuh_keputusan_kelebihan' => true,
                'kelebihan' => $e->kelebihan,
                'tagihan_baru' => $e->tagihanBaru,
                'dibayar' => $e->dibayar,
            ], 422);
        }

        $this->catat($request, 'pindah paket pendaftaran', [
            'kode' => $pendaftaran->kode,
            'dari' => $sebelum['paket'].' ('.($sebelum['tanggal'] ?? '-').')',
            'ke' => $paket->name.' ('.($hasil['tanggal_baru'] ?? '-').')',
            'rab' => $hasil['rab'] ?? '-',
            'tagihan_lama' => $sebelum['tagihan'],
            'tagihan_baru' => $hasil['tagihan_baru'],
            'kelebihan' => $hasil['kelebihan'],
            'perlakuan' => $hasil['perlakuan_kelebihan'] ?? '-',
        ]);

        return response()->json([
            'data' => (new PendaftaranResource($pendaftaran->fresh()))->resolve(),
            'ringkasan' => $hasil + ['sebelum' => $sebelum],
            'pesan' => 'Pendaftaran dipindahkan ke '.$paket->name
                .($hasil['rab'] ? ', angkanya mengikuti '.$hasil['rab'].'.' : '.'),
        ]);
    }

    public function update(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => 'sometimes|required|string|max:120',
            'whatsapp' => 'sometimes|required|string|max:30',
            'email' => 'sometimes|nullable|email|max:191',
            'jumlah_peserta' => 'sometimes|required|integer|min:1|max:200',
            'pendamping_gratis' => 'sometimes|nullable|integer|min:0|max:200',
            'titik_jemput' => 'sometimes|nullable|string|max:191',
            'catatan' => 'sometimes|nullable|string|max:2000',
            'catatan_internal' => 'sometimes|nullable|string|max:2000',
            'keberangkatan_id' => 'sometimes|nullable|integer|exists:tbl_paket_keberangkatan,id',
            // Menembus penolakan kuota. Lihat alasannya di bawah.
            'paksa' => 'sometimes|boolean',
        ], [], [
            'jumlah_peserta' => 'jumlah peserta',
            'pendamping_gratis' => 'pendamping gratis',
            'titik_jemput' => 'titik jemput',
            'catatan_internal' => 'catatan internal',
            'keberangkatan_id' => 'rombongan',
        ]);

        $paksa = (bool) ($data['paksa'] ?? false);
        unset($data['paksa']);

        $perubahan = [];
        $pindah = null;

        /*
         | Perpindahan rombongan diperiksa lebih dulu, dan bisa MENOLAK seluruh
         | permintaan.
         |
         | Diperiksa sebelum apa pun disimpan supaya borang yang sekaligus
         | membetulkan nama dan memindahkan rombongan tidak separuh tersimpan:
         | nama berubah, rombongannya tidak, dan tidak ada yang memberi tahu
         | bahwa yang kedua gagal.
         */
        if (array_key_exists('keberangkatan_id', $data)
            && (int) $data['keberangkatan_id'] !== (int) $pendaftaran->keberangkatan_id) {

            if ($data['keberangkatan_id'] === null) {
                /*
                 | Mengosongkan rombongan TIDAK diizinkan.
                 |
                 | Pendaftaran tanpa rombongan adalah bentuk peninggalan dari
                 | masa sebelum tabel keberangkatan ada; batas pelunasannya
                 | dihitung lewat jalur mundur yang berbeda. Membuat yang baru
                 | berarti menambah data yang hanya ditoleransi, bukan
                 | didukung — dan tidak ada alasan sah untuk memerlukannya.
                 */
                return response()->json([
                    'pesan' => 'Rombongan tidak boleh dikosongkan. Pilih salah satu tanggal keberangkatan.',
                ], 422);
            }

            $tujuan = Keberangkatan::find($data['keberangkatan_id']);

            if (! $tujuan || $tujuan->travel_package_id !== (int) $pendaftaran->travel_package_id) {
                return response()->json([
                    'pesan' => 'Tanggal itu bukan milik paket '.$pendaftaran->nama_paket
                        .'. Pindah paket tidak bisa lewat sini — pemesanannya memang berbeda harga dan isi.',
                ], 422);
            }

            /*
             | Kuota diperiksa, tetapi bisa ditembus dengan sengaja.
             |
             | Menolak mati-matian salah: rombongan penuh yang kelebihan satu
             | kursi adalah keputusan bisnis yang memang kadang diambil (bus
             | masih ada bangku tambahan, atau satu orang lain baru saja
             | membatalkan tetapi belum tercatat). Yang tidak boleh adalah
             | kelebihannya terjadi tanpa ada yang MEMILIHNYA — karena itu
             | tolakan pertama memulangkan 422 dengan angkanya disebut, dan
             | pengirim yang tetap ingin melanjutkan mengirim paksa=1.
             */
            $sisa = $tujuan->sisa_kursi;

            if (! $paksa && $sisa !== null && $sisa < (int) $pendaftaran->jumlah_peserta) {
                return response()->json([
                    'pesan' => 'Tanggal '.$tujuan->rentang_tanggal.' tinggal '
                        .max(0, $sisa).' kursi, sedangkan rombongan ini '
                        .$pendaftaran->jumlah_peserta.' orang.',
                    'butuh_paksa' => true,
                ], 422);
            }

            $pindah = [
                'dari' => $pendaftaran->keberangkatan?->rentang_tanggal
                    ?? ($pendaftaran->tanggal_berangkat?->toDateString() ?: 'tanpa rombongan'),
                'ke' => $tujuan->rentang_tanggal,
            ];

            /*
             | tanggal_berangkat ikut digeser.
             |
             | Kolom itu masih dibaca belasan tempat — pengingat pelunasan,
             | saringan "perlu ditelepon", manifes. Rombongan yang berpindah
             | tanpa menggeser kolomnya menghasilkan pendaftaran yang ditagih
             | menurut tanggal lama, dan tidak ada galat yang menjelaskannya.
             */
            $data['tanggal_berangkat'] = $tujuan->tanggal_berangkat;
        }

        /*
         | Perubahan yang LAYAK DICATAT dikumpulkan sebelum disimpan.
         |
         | Jumlah peserta terutama: angka itulah yang mengalikan harga paket
         | jadi tagihan. Perubahannya menggeser uang, jadi jejaknya harus
         | menyebut angka lama dan angka baru — "ubah pendaftaran" saja tidak
         | bisa dipakai menjawab kenapa tagihannya berbeda dari kwitansi
         | pertama.
         */
        $pantau = [
            'nama' => 'nama pemesan',
            'whatsapp' => 'WhatsApp',
            'email' => 'email',
            'jumlah_peserta' => 'jumlah peserta',
            'pendamping_gratis' => 'pendamping gratis',
            'titik_jemput' => 'titik jemput',
        ];

        foreach ($pantau as $medan => $sebutan) {
            if (! array_key_exists($medan, $data)) {
                continue;
            }

            $lama = (string) ($pendaftaran->{$medan} ?? '');
            $baru = (string) ($data[$medan] ?? '');

            if ($lama !== $baru) {
                $perubahan[] = $sebutan.': '.($lama === '' ? '(kosong)' : $lama)
                    .' → '.($baru === '' ? '(kosong)' : $baru);
            }
        }

        $pendaftaran->update($data);

        $this->catat($request, 'ubah data pendaftaran', array_filter([
            'kode' => $pendaftaran->kode,
            'perubahan' => $perubahan,
            'pindah_rombongan' => $pindah ? $pindah['dari'].' → '.$pindah['ke'] : null,
            'melebihi_kuota' => $paksa && $pindah ? 'ya' : null,
        ]));

        /*
         | Peringatan, bukan penolakan: jumlah peserta yang lebih kecil
         | daripada nama yang sudah terdaftar.
         |
         | Namanya tidak ikut dibuang — yang kelebihan mungkin justru yang
         | benar, dan membuang nama orang karena satu angka diketik salah tidak
         | bisa dibatalkan. Yang dikembalikan cuma kalimat supaya admin
         | merapikannya sendiri di layar peserta.
         */
        $pesan = 'Data pendaftaran tersimpan.';

        if ($pindah) {
            $pesan = 'Dipindahkan ke rombongan '.$pindah['ke'].'.';
        }

        $jumlahNama = count($pendaftaran->peserta);

        if ($jumlahNama > (int) $pendaftaran->jumlah_peserta) {
            $pesan .= ' Catatan: ada '.$jumlahNama.' nama peserta terdaftar untuk '
                .$pendaftaran->jumlah_peserta.' peserta. Rapikan di daftar peserta.';
        }

        return response()->json([
            'data' => (new PendaftaranResource(
                $pendaftaran->fresh()->load('keberangkatan')->loadCount('riwayatKesehatan')
            ))->resolve(),
            'pesan' => $pesan,
        ]);
    }

    /**
     * Menghapus pendaftaran — untuk kiriman ganda dan salah masuk, BUKAN untuk
     * pembatalan.
     *
     * Dua hal itu sengaja dibedakan. Pembatalan adalah peristiwa yang harus
     * tetap terbaca: uangnya pernah masuk, refundnya pernah dihitung, kursinya
     * dilepas pada tanggal tertentu. Menghapus barisnya membuat semua itu
     * lenyap dari laporan, dan laporan yang kehilangan baris tidak memberi
     * tanda apa pun — angkanya cuma berbeda.
     *
     * Karena itu pendaftaran yang UANGNYA SUDAH MASUK tidak bisa dihapus sama
     * sekali, sekeras apa pun yang meminta. Yang bisa dihapus hanya yang belum
     * pernah menyentuh uang: borang yang terkirim dua kali, atau yang
     * dimasukkan admin ke pelanggan yang salah.
     */
    public function destroy(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $uangMasuk = (int) KonfirmasiPembayaran::where('kode', $pendaftaran->kode)
            ->where('status', 'diterima')
            ->sum('nominal');

        if ($uangMasuk > 0) {
            return response()->json([
                'pesan' => 'Pendaftaran ini sudah menerima uang Rp '
                    .number_format($uangMasuk, 0, ',', '.')
                    .', jadi tidak bisa dihapus. Pakai pembatalan supaya refundnya terhitung '
                    .'dan jejaknya tetap terbaca di laporan.',
            ], 422);
        }

        /*
         | Bukti yang masih MENUNGGU dicek juga menahan penghapusan.
         |
         | Nominalnya belum jadi uang, tetapi buktinya ada — gambar transfer
         | yang dikirim seseorang. Menghapus pendaftarannya membuang bukti itu
         | bersama pengirimnya, dan yang mengirim tetap merasa sudah membayar.
         */
        $buktiMenunggu = KonfirmasiPembayaran::where('kode', $pendaftaran->kode)
            ->where('status', 'menunggu')
            ->count();

        if ($buktiMenunggu > 0) {
            return response()->json([
                'pesan' => 'Masih ada '.$buktiMenunggu.' bukti pembayaran yang belum dicek. '
                    .'Periksa atau tolak dulu buktinya, baru pendaftarannya bisa dihapus.',
            ], 422);
        }

        if (Pembatalan::where('kode_pendaftaran', $pendaftaran->kode)->exists()) {
            return response()->json([
                'pesan' => 'Pendaftaran ini punya pengajuan pembatalan. '
                    .'Selesaikan pembatalannya — jejaknya harus tetap terbaca.',
            ], 422);
        }

        $kode = $pendaftaran->kode;
        $nama = $pendaftaran->nama;

        /*
         | Riwayat kesehatan ikut dihapus, dan itu disebut di jawabannya.
         |
         | Kaitannya lewat kode pendaftaran, bukan kunci asing — jadi tanpa
         | baris ini ia tertinggal sebagai data kesehatan yatim yang tidak bisa
         | dibuka dari mana pun, tidak bisa dihapus dari mana pun, dan tetap
         | terhitung di laporan jumlah pengisi.
         */
        $jumlahKesehatan = $pendaftaran->riwayatKesehatan()->count();
        $pendaftaran->riwayatKesehatan()->delete();

        /*
         | Rencana angsuran ikut dihapus, dengan alasan yang sama.
         |
         | Ia dikaitkan lewat kode pendaftaran juga, jadi yang tertinggal
         | muncul di daftar angsuran sebagai rencana milik pesanan yang tidak
         | ada — baris yang tidak bisa dibuka dan tidak bisa ditutup.
         |
         | Hanya sampai di sini karena penjaga di atas sudah memastikan belum
         | ada uang yang masuk: rencana angsuran tanpa satu pun pembayaran
         | tidak menyimpan apa pun yang perlu diingat.
         */
        Angsuran::where('kode', $pendaftaran->kode)->delete();

        $pendaftaran->delete();

        $this->catat($request, 'hapus pendaftaran', [
            'kode' => $kode,
            'nama' => $nama,
            'riwayat_kesehatan_terhapus' => $jumlahKesehatan,
        ]);

        return response()->json([
            'pesan' => 'Pendaftaran '.$kode.' dihapus.'
                .($jumlahKesehatan > 0
                    ? ' '.$jumlahKesehatan.' data riwayat kesehatan ikut terhapus.'
                    : ''),
        ]);
    }

    /**
     * Menggabungkan dua pendaftaran yang sebenarnya satu rombongan.
     *
     * Terjadi setiap kali pemesan mengisi borang dua kali — sekali untuk
     * dirinya, sekali untuk sisa keluarganya — atau saat admin terlanjur
     * mendaftarkan rombongan yang sama dari WhatsApp. Sebelum ini keduanya
     * tetap berdiri sendiri: dua kode pemesanan, dua kwitansi, dua kursi yang
     * terhitung di kuota, dan dua tagihan yang harus ditagih terpisah ke orang
     * yang sama.
     *
     * Menghapus salah satunya BUKAN jalan keluarnya begitu uangnya sudah
     * masuk — dan di situlah masalahnya, karena biasanya memang sudah.
     *
     * Yang dipindahkan: pembayaran, riwayat kesehatan, nama peserta, jumlah
     * peserta, dan pendamping gratis. Yang TIDAK dipindahkan: kode pemesanan.
     * Kwitansi yang sudah dipegang pemesan menyebut kode asalnya, dan kode itu
     * harus tetap bisa ditemukan — karena itu penggabungannya dicatat di
     * catatan internal tujuan beserta kode yang hilang.
     */
    public function gabungkan(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'ke' => 'required|integer|exists:tbl_pendaftaran_open_trip,id',
        ], [], ['ke' => 'pendaftaran tujuan']);

        $tujuan = PendaftaranOpenTrip::find($data['ke']);

        if (! $tujuan || $tujuan->id === $pendaftaran->id) {
            return response()->json([
                'pesan' => 'Pilih pendaftaran lain sebagai tujuannya.',
            ], 422);
        }

        if ((int) $tujuan->travel_package_id !== (int) $pendaftaran->travel_package_id) {
            return response()->json([
                'pesan' => 'Keduanya bukan paket yang sama. Harga dan isi perjalanannya berbeda, '
                    .'jadi tagihannya tidak bisa dijadikan satu.',
            ], 422);
        }

        if ((int) $tujuan->keberangkatan_id !== (int) $pendaftaran->keberangkatan_id) {
            return response()->json([
                'pesan' => 'Keduanya bukan rombongan yang sama. Pindahkan dulu salah satunya '
                    .'ke tanggal yang sama, baru digabungkan.',
            ], 422);
        }

        /*
         | Rencana angsuran pada SUMBER menghalangi penggabungan.
         |
         | Termin yang sudah disepakati dihitung dari total tagihan yang
         | lama; menempelkannya ke tagihan gabungan menghasilkan jadwal yang
         | tidak pernah disetujui siapa pun, dan pelanggan menerima tagihan
         | dengan angka yang tidak ia kenali. Dibatalkan dulu, lalu disusun
         | ulang setelah digabung.
         */
        /*
         | Rencana angsuran pada SUMBER dibatalkan, bukan menolak penggabungan.
         |
         | Menolak itu benar secara aturan — termin lama dihitung dari tagihan
         | lama — tetapi membuat admin buntu: ia harus membatalkan rencana
         | lewat layar lain, kembali ke sini, lalu menyusun ulang. Tiga langkah
         | di tiga tempat untuk satu pekerjaan, dan yang berhenti di tengahnya
         | meninggalkan dua pendaftaran yang tetap terpisah.
         |
         | Sekarang pembatalannya dikerjakan di sini dan DISEBUT di jawabannya,
         | supaya admin tahu jadwalnya harus disusun ulang — bukan mengira
         | terminnya ikut berpindah.
         */
        $angsuranDibatalkan = Angsuran::where('kode', $pendaftaran->kode)
            ->whereNull('dibatalkan_pada')
            ->exists();

        if (Pembatalan::where('kode_pendaftaran', $pendaftaran->kode)->exists()) {
            return response()->json([
                'pesan' => 'Pendaftaran ini punya pengajuan pembatalan. Selesaikan dulu '
                    .'pembatalannya — jejaknya harus tetap terbaca.',
            ], 422);
        }

        $kodeAsal = $pendaftaran->kode;
        $uangPindah = 0;

        // Rencana angsuran TUJUAN juga ikut gugur bila ada: tagihannya baru
        // saja bertambah, jadi terminnya tidak lagi menutup totalnya.
        $angsuranTujuanGugur = Angsuran::where('kode', $tujuan->kode)
            ->whereNull('dibatalkan_pada')
            ->exists();

        DB::transaction(function () use ($pendaftaran, $tujuan, $kodeAsal, &$uangPindah) {
            $uangPindah = (int) KonfirmasiPembayaran::where('kode', $kodeAsal)->sum('nominal');

            // Pembayaran dan riwayat kesehatan dikaitkan lewat KODE, bukan
            // kunci asing — jadi memindahkannya berarti menulis ulang kodenya.
            KonfirmasiPembayaran::where('kode', $kodeAsal)->update(['kode' => $tujuan->kode]);

            $pendaftaran->riwayatKesehatan()->update(['kode_pendaftaran' => $tujuan->kode]);

            /*
             | Nama peserta digabung, yang kembar dibuang.
             |
             | Pemesan yang mengisi dua kali hampir selalu menuliskan dirinya
             | sendiri di kedua borang. Tanpa pembuangan ini, rombongan
             | gabungan berisi satu orang dua kali — dan jumlah pesertanya,
             | yang dijumlahkan di bawah, sudah terlanjur menghitungnya dua
             | kali juga.
             */
            $gabungan = collect($tujuan->peserta)
                ->concat($pendaftaran->peserta)
                ->unique(fn ($satu) => mb_strtolower(trim((string) $satu['nama'])))
                ->values()
                ->all();

            $catatan = trim((string) $tujuan->catatan_internal)."\n"
                .'Digabung dari pendaftaran '.$kodeAsal.' ('.$pendaftaran->nama.') pada '
                .now()->locale('id')->translatedFormat('d F Y, H:i').' WIB'
                .($uangPindah > 0 ? ', beserta pembayaran Rp '.number_format($uangPindah, 0, ',', '.') : '')
                .'.';

            $tujuan->update([
                'daftar_peserta' => $gabungan ?: null,
                /*
                 | Jumlah peserta dijumlahkan, lalu DIBATASI jumlah nama yang
                 | tersisa sesudah yang kembar dibuang.
                 |
                 | Angka inilah yang mengalikan harga jadi tagihan. Menjumlah
                 | begitu saja menagih pemesan untuk dirinya sendiri dua kali.
                 */
                'jumlah_peserta' => min(
                    (int) $tujuan->jumlah_peserta + (int) $pendaftaran->jumlah_peserta,
                    max(count($gabungan), (int) $tujuan->jumlah_peserta),
                ),
                'pendamping_gratis' => (int) $tujuan->pendamping_gratis + (int) $pendaftaran->pendamping_gratis,
                'catatan_internal' => trim($catatan),
            ]);

            Angsuran::where('kode', $kodeAsal)->delete();

            /*
             | Rencana angsuran tujuan DIBATALKAN, bukan dihapus.
             |
             | Ia pernah berlaku dan termin-terminnya mungkin sudah dibayar
             | sebagian; menghapusnya membuang jejak kesepakatan yang pernah
             | ada. Dibatalkan, jadi tetap terbaca sebagai rencana yang pernah
             | disetujui lalu gugur karena tagihannya berubah.
             */
            Angsuran::where('kode', $tujuan->kode)
                ->whereNull('dibatalkan_pada')
                ->update(['dibatalkan_pada' => now()]);

            $pendaftaran->delete();
        });

        $this->catat($request, 'gabungkan pendaftaran', [
            'kode' => $tujuan->kode,
            'dari' => $kodeAsal,
            'ke' => $tujuan->kode,
            'pembayaran_dipindahkan' => $uangPindah,
        ]);

        return response()->json([
            'data' => (new PendaftaranResource(
                $tujuan->fresh()->load('keberangkatan')->loadCount('riwayatKesehatan')
            ))->resolve(),
            'pesan' => 'Pendaftaran '.$kodeAsal.' digabungkan ke '.$tujuan->kode.'.'
                .($uangPindah > 0
                    ? ' Pembayaran Rp '.number_format($uangPindah, 0, ',', '.').' ikut dipindahkan.'
                    : '')
                .($angsuranDibatalkan || $angsuranTujuanGugur
                    ? ' Rencana angsurannya dibatalkan karena tagihannya berubah — susun ulang terminnya.'
                    : ''),
        ]);
    }

    /**
     * Mengubah keadaan beberapa pendaftaran sekaligus.
     *
     * Mengabari dua puluh pendaftar lalu menandai semuanya "sudah dihubungi"
     * sebelumnya berarti membuka dua puluh halaman. Yang dikerjakan di sini
     * cuma penandaan itu — bukan pembatalan massal.
     *
     * "batal" SENGAJA tidak boleh lewat sini. Membatalkan melepas kursi dan
     * mengabari daftar tunggu; dua puluh pembatalan sekaligus yang dipicu satu
     * salah klik tidak bisa dibatalkan, dan tidak ada layar yang bisa
     * menjelaskan apa yang baru saja terjadi.
     */
    public function statusMassal(Request $request): JsonResponse
    {
        $dibolehkan = collect(array_keys(config('orcha.status_pendaftaran', [])))
            ->reject(fn ($satu) => $satu === 'batal')
            ->values();

        $data = $request->validate([
            'id' => 'required|array|min:1|max:200',
            'id.*' => 'integer',
            'status' => 'required|in:'.$dibolehkan->implode(','),
        ], [
            'status.in' => 'Keadaan itu tidak bisa diberikan ke banyak pendaftaran sekaligus. '
                .'Pembatalan melepas kursi dan mengabari daftar tunggu, jadi harus satu per satu.',
        ], ['id' => 'daftar pendaftaran']);

        $baris = PendaftaranOpenTrip::whereIn('id', $data['id'])->get();

        // Yang sudah batal TIDAK ikut diubah: menghidupkannya kembali lewat
        // tindakan massal berarti kursinya diambil lagi tanpa ada yang
        // memeriksa apakah kuotanya masih ada.
        $bisa = $baris->reject(fn ($satu) => $satu->status === 'batal');

        foreach ($bisa as $satu) {
            $satu->update(['status' => $data['status']]);
        }

        $this->catat($request, 'ubah keadaan beberapa pendaftaran', [
            'ke' => $data['status'],
            'jumlah' => $bisa->count(),
            'kode' => $bisa->pluck('kode')->implode(', '),
        ]);

        $dilewati = $baris->count() - $bisa->count();

        return response()->json([
            'pesan' => $bisa->count().' pendaftaran diperbarui.'
                .($dilewati > 0 ? ' '.$dilewati.' yang sudah batal dilewati.' : ''),
        ]);
    }
}
