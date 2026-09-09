<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pembungkus DOKU Checkout (non-SNAP).
 *
 * Dua pekerjaan, dan keduanya soal tanda tangan:
 *
 *   1. Meminta halaman pembayaran ke DOKU — kita yang menandatangani.
 *   2. Memeriksa notifikasi yang dikirim DOKU — DOKU yang menandatangani,
 *      kita yang memverifikasi.
 *
 * Keduanya memakai formula yang sama persis: lima baris komponen, HMAC-SHA256
 * dengan Secret Key, hasilnya base64 berawalan "HMACSHA256=". Yang berbeda
 * hanya Request-Target-nya — jalur endpoint DOKU saat kita memanggil, jalur
 * URL notifikasi kita saat DOKU memanggil.
 *
 * Kelas ini sengaja tidak menyentuh basis data sama sekali. Yang mencatat
 * pembayaran adalah pemanggilnya; di sini hanya percakapan dengan DOKU, supaya
 * bisa diuji tanpa satu baris pun tabel.
 */
class DokuCheckout
{
    /**
     * Siap dipakai atau tidak.
     *
     * Sakelar DOKU_AKTIF saja tidak cukup: server yang sakelarnya menyala
     * tetapi kredensialnya belum ditempel akan gagal di tengah, sesudah
     * pelanggan menekan tombol bayar. Lebih baik ketahuan sebelum tombolnya
     * ditampilkan.
     */
    public function aktif(): bool
    {
        return (bool) config('doku.aktif')
            && filled(config('doku.client_id'))
            && filled(config('doku.secret_key'));
    }

    /**
     * Meminta halaman pembayaran ke DOKU.
     *
     * @param  string  $invoice  Nomor tagihan kita sendiri, maksimal 30 karakter
     *                           (bukan 64 — kartu kredit memangkasnya ke 30, dan
     *                           batas terkecil yang berlaku itulah batas kita)
     * @param  int  $nominal  Rupiah bulat, sudah termasuk kode uniknya
     * @param  array  $rincian  Baris tagihan: [['nama' => string, 'harga' => int], ...].
     *                          Kosong berarti satu baris sebesar seluruh nominal.
     * @param  array  $pelanggan  ['nama' => ..., 'email' => ..., 'telepon' => ...]
     * @param  array  $kembali  ['selesai' => url, 'batal' => url]
     * @return array{url: string, token_id: string, kedaluwarsa: ?string}
     *
     * @throws \RuntimeException bila DOKU menolak atau tidak terjangkau
     */
    public function buatHalamanBayar(
        string $invoice,
        int $nominal,
        string $judul,
        array $rincian = [],
        array $pelanggan = [],
        array $kembali = [],
    ): array {
        $badan = [
            'order' => array_filter([
                'amount' => $nominal,
                'invoice_number' => $invoice,
                'currency' => 'IDR',
                'language' => 'ID',
                'callback_url' => $kembali['selesai'] ?? null,
                'callback_url_cancel' => $kembali['batal'] ?? null,
                /*
                 | Sesudah membayar, pelanggan dikembalikan sendiri ke situs.
                 |
                 | Tanpa ini ia berhenti di halaman DOKU dan harus menekan
                 | tombol yang tidak selalu ia lihat — lalu bertanya lewat
                 | WhatsApp apakah pembayarannya masuk, padahal notifikasinya
                 | sudah tiba di server kita beberapa detik sebelumnya.
                 */
                'auto_redirect' => true,
                'line_items' => self::barisTagihan($judul, $nominal, $rincian),
            ], fn ($nilai) => $nilai !== null),

            'payment' => array_filter([
                'payment_due_date' => (int) config('doku.batas_bayar_menit'),
                'type' => 'SALE',
                // Kosong berarti tidak dikirim sama sekali: DOKU menampilkan
                // semua channel yang aktif untuk merchant ini.
                'payment_method_types' => config('doku.metode') ?: null,
            ], fn ($nilai) => $nilai !== null),
        ];

        /*
         | Data pelanggan dikirim seadanya, dan hanya yang memang ada.
         |
         | Nama dan nomornya membuat pelanggan mengenali halaman DOKU sebagai
         | miliknya sendiri, dan sebagian channel memang mewajibkannya. Tetapi
         | mengirim kunci berisi null justru ditolak DOKU sebagai kolom kosong,
         | jadi yang tidak ada lebih baik tidak disebut.
         */
        $pelanggan = array_filter([
            'name' => isset($pelanggan['nama']) ? self::bersihkan((string) $pelanggan['nama']) : null,
            'email' => $pelanggan['email'] ?? null,
            'phone' => $pelanggan['telepon'] ?? null,
        ], fn ($nilai) => filled($nilai));

        if ($pelanggan !== []) {
            $badan['customer'] = $pelanggan;
        }

        $jalur = (string) config('doku.jalur.bayar');
        $json = json_encode($badan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestId = (string) Str::uuid();
        $waktu = gmdate('Y-m-d\TH:i:s\Z');

        try {
            $jawaban = Http::withHeaders([
                'Client-Id' => (string) config('doku.client_id'),
                'Request-Id' => $requestId,
                'Request-Timestamp' => $waktu,
                'Signature' => $this->tandaTangan($jalur, $requestId, $waktu, $json),
                'Content-Type' => 'application/json',
            ])
                ->withBody($json, 'application/json')
                ->timeout((int) config('doku.batas_tunggu'))
                ->post($this->pangkalan().$jalur);
        } catch (ConnectionException $e) {
            // Isi badan TIDAK ikut dicatat: di dalamnya ada nama, email, dan
            // nomor telepon pelanggan.
            Log::error('DOKU tidak terjangkau', ['invoice' => $invoice, 'sebab' => $e->getMessage()]);

            throw new \RuntimeException('Gerbang pembayaran sedang tidak bisa dihubungi.');
        }

        if ($jawaban->failed()) {
            Log::error('DOKU menolak permintaan halaman bayar', [
                'invoice' => $invoice,
                'status' => $jawaban->status(),
                'pesan' => $jawaban->json('error_messages') ?? $jawaban->json('message'),
            ]);

            throw new \RuntimeException('Gerbang pembayaran menolak permintaan ini.');
        }

        $url = $jawaban->json('response.payment.url');

        if (blank($url)) {
            Log::error('DOKU menjawab tanpa payment.url', ['invoice' => $invoice]);

            throw new \RuntimeException('Gerbang pembayaran tidak mengirimkan alamat pembayaran.');
        }

        return [
            'url' => (string) $url,
            'token_id' => (string) $jawaban->json('response.payment.token_id'),
            'kedaluwarsa' => $jawaban->json('response.payment.expired_date'),
        ];
    }

    /**
     * Memeriksa bahwa sebuah notifikasi benar-benar datang dari DOKU.
     *
     * Ini SATU-SATUNYA hal yang memisahkan pembayaran sungguhan dari siapa pun
     * yang menebak alamat notifikasi kita lalu mengirimkan JSON buatan sendiri
     * berisi "status": "SUCCESS". Tanpa pemeriksaan ini, melunasi trip cukup
     * dengan satu perintah curl.
     *
     * Request-Target-nya adalah jalur URL notifikasi KITA, bukan jalur DOKU —
     * karena kali ini DOKU yang menjadi pemanggil.
     */
    public function notifikasiSah(Request $request): bool
    {
        $dikirim = (string) $request->header('Signature');
        $clientId = (string) $request->header('Client-Id');
        $requestId = (string) $request->header('Request-Id');
        $waktu = (string) $request->header('Request-Timestamp');

        if (blank($dikirim) || blank($clientId) || blank($requestId) || blank($waktu)) {
            return false;
        }

        /*
         | Client-Id yang tidak dikenal ditolak sebelum menghitung apa pun.
         |
         | Tanda tangannya toh tidak akan cocok, tetapi menolaknya di sini
         | membuat catatan log menyebut sebab yang benar — dan satu akun DOKU
         | yang keliru dipasang di server tidak berakhir sebagai misteri
         | "tanda tangan salah" yang dikejar berjam-jam.
         */
        if (! hash_equals((string) config('doku.client_id'), $clientId)) {
            return false;
        }

        /*
         | Notifikasi yang terlalu tua ditolak, meski tanda tangannya sah.
         |
         | Tanda tangan membuktikan notifikasi itu PERNAH dibuat DOKU; ia tidak
         | membuktikan notifikasi itu baru datang. Satu notifikasi sah yang
         | direkam — dari log perantara, dari server yang pernah bocor, dari
         | mana pun — bisa dikirim ulang bertahun-tahun kemudian dan tetap
         | lolos pemeriksaan.
         |
         | Akibatnya terbatas karena pemrosesannya idempoten: memutar ulang
         | keberhasilan tidak mencatat pembayaran dua kali. Tetapi "terbatas"
         | bukan "tidak ada", dan yang menutupnya cuma satu perbandingan waktu.
         |
         | Jendelanya longgar dengan sengaja. Jam server kita dan jam DOKU
         | tidak pernah persis sama, dan notifikasi yang gagal diulang DOKU
         | beberapa menit kemudian harus tetap diterima — menolak pembayaran
         | sungguhan demi menutup pemutaran ulang adalah pertukaran yang salah
         | arah.
         */
        if (! self::waktuMasukAkal($waktu)) {
            return false;
        }

        $benar = $this->tandaTangan(
            $request->getPathInfo(),
            $requestId,
            $waktu,
            $request->getContent(),
        );

        return hash_equals($benar, $dikirim);
    }

    /**
     * Tanda tangan HMAC-SHA256 menurut spesifikasi non-SNAP DOKU.
     *
     * Lima komponen, satu per baris, dipisah \n, TANPA baris kosong di akhir.
     * Urutannya tidak boleh ditukar dan namanya tidak boleh diubah kapitalnya:
     * yang dihitung adalah string mentahnya, jadi satu spasi berlebih sudah
     * cukup untuk menghasilkan tanda tangan yang selalu ditolak.
     */
    /**
     * Apakah cap waktunya masih dalam jendela yang wajar?
     *
     * Menerima dua arah: notifikasi bisa terbaca "dari masa depan" bila jam
     * DOKU sedikit di depan jam kita, dan itu bukan tanda kejahatan apa pun.
     *
     * Cap waktu yang tidak bisa dibaca ditolak. Ia bagian dari bahan tanda
     * tangan, jadi bentuk yang aneh berarti sesuatu yang tidak kita kenali —
     * dan menebak-nebak maksudnya bukan pekerjaan pemeriksa keamanan.
     */
    private static function waktuMasukAkal(string $waktu): bool
    {
        try {
            $cap = Carbon::parse($waktu);
        } catch (\Throwable) {
            return false;
        }

        $menit = (int) config('doku.jendela_notifikasi_menit', 30);

        return abs(now()->diffInMinutes($cap)) <= $menit;
    }

    public function tandaTangan(string $requestTarget, string $requestId, string $waktu, ?string $badan = null): string
    {
        $komponen = [
            'Client-Id:'.config('doku.client_id'),
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$waktu,
            'Request-Target:'.$requestTarget,
        ];

        /*
         | Digest hanya ada bila permintaannya berbadan.
         |
         | Permintaan GET — Check Status API — tidak punya badan, dan
         | spesifikasi DOKU meminta komponennya berhenti di Request-Target.
         | Menambahkan "Digest:" berisi hash dari string kosong menghasilkan
         | tanda tangan yang selalu ditolak, dan penolakannya tidak menyebut
         | sebabnya.
         */
        if ($badan !== null) {
            // Digest = base64 dari SHA-256 badan permintaan, dalam bentuk biner.
            $komponen[] = 'Digest:'.base64_encode(hash('sha256', $badan, true));
        }

        $komponen = implode("\n", $komponen);

        return 'HMACSHA256='.base64_encode(
            hash_hmac('sha256', $komponen, (string) config('doku.secret_key'), true)
        );
    }

    /**
     * Menanyakan status sebuah tagihan langsung ke DOKU.
     *
     * Jalur cadangan untuk notifikasi yang tidak sampai — dan itu bukan
     * kemungkinan yang jauh. Notifikasi dikirim SEKALI ke alamat yang kita
     * daftarkan; kalau saat itu server sedang di-deploy, jaringannya putus,
     * atau alamatnya sudah basi, kabarnya hilang dan tidak ada yang tahu.
     * Yang terlihat kemudian: pelanggan sudah membayar, layar kami masih
     * menyatakan menunggu, dan tidak ada satu pun cara memperbaikinya selain
     * mengetik langsung ke basis data.
     *
     * Bedanya arah. Notifikasi menunggu DOKU menghubungi kita; ini kita yang
     * bertanya, jadi tidak ada yang bisa hilang di jalan.
     *
     * Jawabannya berbentuk sama dengan notifikasi, sehingga bisa langsung
     * diserahkan ke TerimaNotifikasiDoku tanpa penerjemahan.
     *
     * @return array<string, mixed>|null null bila DOKU tidak menjawab
     */
    public function cekStatus(string $invoice): ?array
    {
        $jalur = '/orders/v1/status/'.rawurlencode($invoice);
        $requestId = (string) Str::uuid();
        $waktu = gmdate('Y-m-d\TH:i:s\Z');

        try {
            $jawaban = Http::withHeaders([
                'Client-Id' => (string) config('doku.client_id'),
                'Request-Id' => $requestId,
                'Request-Timestamp' => $waktu,
                // Tanpa badan, jadi tanpa Digest.
                'Signature' => $this->tandaTangan($jalur, $requestId, $waktu),
            ])
                ->timeout((int) config('doku.batas_tunggu'))
                ->get($this->pangkalan().$jalur);
        } catch (ConnectionException $e) {
            Log::warning('Cek status DOKU tidak terjangkau', [
                'invoice' => $invoice,
                'sebab' => $e->getMessage(),
            ]);

            return null;
        }

        if ($jawaban->failed()) {
            /*
             | Dicatat sebagai peringatan, bukan galat.
             |
             | Tagihan yang baru saja dibuat belum tentu dikenal Check Status
             | API — DOKU sendiri meminta menunggu sekitar semenit sesudah
             | pembayaran. Menaikkannya jadi galat berarti mengisi log dengan
             | baris yang tidak salah apa-apa.
             */
            Log::warning('Cek status DOKU ditolak', [
                'invoice' => $invoice,
                'status' => $jawaban->status(),
            ]);

            return null;
        }

        return (array) $jawaban->json();
    }

    /**
     * Menyusun baris tagihan yang dikirim ke DOKU.
     *
     * DOKU menampilkan baris-baris ini apa adanya di halaman pembayaran DAN di
     * surel tagihan yang ia kirim sendiri ke pelanggan. Itu satu-satunya
     * tempat kita bisa menerangkan angkanya di luar situs sendiri — begitu
     * pelanggan menutup tab kita, surel DOKU-lah yang ia buka lagi.
     *
     * Karena itu kode unik dikirim sebagai BARISNYA SENDIRI, bukan dilebur ke
     * dalam harga. Satu baris "Uang Muka (DP) 859.257" memaksa pembacanya
     * menerka dari mana 257-nya datang; dua baris menjawabnya tanpa perlu
     * dijelaskan siapa pun.
     *
     * @param  array  $rincian  [['nama' => string, 'harga' => int], ...]
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException bila rinciannya tidak berjumlah nominal
     */
    private static function barisTagihan(string $judul, int $nominal, array $rincian): array
    {
        if ($rincian === []) {
            $rincian = [['nama' => $judul, 'harga' => $nominal]];
        }

        /*
         | Jumlah rinciannya WAJIB sama dengan nominal yang ditagihkan.
         |
         | Yang dijaga bukan DOKU — ia menagih order.amount, apa pun isi
         | rinciannya. Yang dijaga pelanggan: tagihan yang barisnya berjumlah
         | Rp 858.000 tetapi menagih Rp 859.257 adalah tagihan yang tidak bisa
         | dipertanggungjawabkan, dan kekeliruan seperti itu lahir dari satu
         | penyuntingan kecil yang lupa menyesuaikan sisi lainnya.
         */
        $jumlah = array_sum(array_map(fn (array $b) => (int) $b['harga'], $rincian));

        if ($jumlah !== $nominal) {
            throw new \RuntimeException("Rincian tagihan berjumlah {$jumlah}, bukan {$nominal}.");
        }

        return array_map(fn (array $b) => [
            'name' => Str::limit(self::bersihkan((string) $b['nama']), 60, ''),
            'quantity' => 1,
            'price' => (int) $b['harga'],
            'category' => 'travel',
        ], $rincian);
    }

    /**
     * Membuang karakter yang ditolak DOKU dari teks bebas.
     *
     * DOKU hanya menerima a-z A-Z 0-9 . - / + , = _ : ' @ % ( ) dan spasi pada
     * kolom teksnya, dan menolak SELURUH permintaan dengan HTTP 400 bila ada
     * satu saja karakter di luar itu.
     *
     * Yang tersandung bukan kasus aneh-aneh. Nama paket seperti "Bromo & Ijen"
     * memuat "&"; kalimat yang kita susun sendiri memakai tanda pisah "—"; dan
     * nama pelanggan bisa memuat apa saja yang sempat ia ketik. Ketiganya
     * menghasilkan kegagalan yang sama, dan kegagalannya muncul di tempat
     * paling mahal: tepat setelah pelanggan menekan tombol bayar.
     *
     * Yang dibuang diganti spasi, bukan dihapus rapat. "Bromo&Ijen" yang
     * menjadi "BromoIjen" lebih sulit dibaca daripada "Bromo Ijen", dan teks
     * ini muncul di halaman pembayaran serta di surel tagihan pelanggan.
     */
    private static function bersihkan(string $teks): string
    {
        $bersih = preg_replace("#[^a-zA-Z0-9 .\-/+,=_:'@%()]#u", ' ', $teks);

        return trim(preg_replace('/\s+/', ' ', (string) $bersih));
    }

    /**
     * Pangkalan endpoint sesuai lingkungan.
     *
     * Lingkungan yang tidak dikenali MELEDAK, tidak diam-diam jatuh ke sandbox.
     *
     * Jatuh ke sandbox terasa seperti pilihan aman, dan justru sebaliknya:
     * DOKU_LINGKUNGAN=production (bukan "produksi") adalah salah ketik yang
     * sangat mudah dilakukan saat memasang .env di server, dan akibatnya situs
     * produksi menagih pelanggan sungguhan lewat gerbang yang tidak memindahkan
     * uang sepeser pun. Yang menyadarinya bukan kita, melainkan pelanggan yang
     * kursinya tidak pernah terkunci.
     *
     * Meledak di sini berarti tombol "Bayar Sekarang" gagal dengan pesan jujur
     * pada percobaan pertama, bukan pada rekonsiliasi bulan depan.
     */
    private function pangkalan(): string
    {
        $daftar = (array) config('doku.endpoint');
        $lingkungan = (string) config('doku.lingkungan');

        if (! isset($daftar[$lingkungan])) {
            throw new \RuntimeException(
                'DOKU_LINGKUNGAN tidak dikenali: "'.$lingkungan.'". '
                .'Yang diterima: '.implode(', ', array_keys($daftar)).'.'
            );
        }

        return $daftar[$lingkungan];
    }
}
