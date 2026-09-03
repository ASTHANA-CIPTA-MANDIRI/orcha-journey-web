<?php

namespace App\Http\Controllers\Api\OpenTrip;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OpenTrip\PendaftaranResource;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Umum\TautanPendek;
use App\Support\BerkasKwitansi;
use App\Support\PerkiraanPotongan;
use App\Support\RincianBiaya;
use App\Support\SuratPenggantian;
use App\Support\TagihanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            // Saringan per paket: manifes tour leader dibentuk dari daftar yang
            // sedang tampil, dan satu keberangkatan hampir selalu berarti satu
            // paket. Tanpa saringan ini admin harus mengetik nama paketnya di
            // kotak cari dan berharap tidak ada paket lain yang namanya mirip.
            ->when($request->integer('paket_id'), fn ($q, $id) => $q->where('travel_package_id', $id))

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

            // Urutan ini menyusul yang di atas, jadi ia jadi pemutus seri —
            // bukan penentu utama saat saringan tagihan menyala.
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
                    'kalimat' => 'Uang muka Anda sebesar <strong>'.$tagihan['sudah_teks'].'</strong> '
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
        ], [], [
            'travel_package_id' => 'paket',
            'peserta.*.nama' => 'nama peserta',
            'pendamping_gratis' => 'pendamping gratis',
        ]);

        $paket = TravelPackage::find($data['travel_package_id']);

        $peserta = collect($data['peserta'] ?? [])
            ->map(fn ($baris) => [
                'nama' => trim($baris['nama']),
                'titik_jemput' => trim($baris['titik_jemput'] ?? '') ?: null,
                'bus' => trim($baris['bus'] ?? '') ?: null,
                'kamar' => trim($baris['kamar'] ?? '') ?: null,
            ])
            ->filter(fn ($baris) => $baris['nama'] !== '')
            ->values()
            ->all();

        $pendaftaran = PendaftaranOpenTrip::create([
            'travel_package_id' => $paket->id,
            'nama_paket' => $paket->name,
            'nama' => $data['nama'],
            'whatsapp' => $data['whatsapp'],
            'email' => $data['email'] ?? null,
            'jumlah_peserta' => $data['jumlah_peserta'],
            'pendamping_gratis' => $data['pendamping_gratis'] ?? 0,
            'daftar_peserta' => $peserta,
            'tanggal_berangkat' => $paket->tanggal_berangkat,
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
}
