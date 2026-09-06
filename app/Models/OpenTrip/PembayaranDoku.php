<?php

namespace App\Models\OpenTrip;

use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\PemilikPesanan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu percobaan pembayaran lewat gerbang DOKU.
 *
 * Barisnya lahir saat pelanggan menekan "Bayar Sekarang", bukan saat uangnya
 * masuk. Karena itu keberadaannya TIDAK berarti apa-apa soal tagihan —
 * lihat alasannya di migrasi 2026_09_04_120000. Yang berarti hanyalah baris
 * yang berstatus `berhasil`, dan itu pun sudah punya pasangannya sendiri di
 * tbl_konfirmasi_pembayaran.
 */
class PembayaranDoku extends Model
{
    protected $table = 'tbl_pembayaran_doku';

    protected $fillable = [
        'invoice',
        'kode',
        'jenis',
        'nominal_pokok',
        'kode_unik',
        'nominal',
        'status',
        'token_id',
        'url',
        'channel',
        'dibayar_pada',
        'kedaluwarsa_pada',
        'notifikasi',
        'konfirmasi_id',
    ];

    protected $casts = [
        'nominal_pokok' => 'integer',
        'kode_unik' => 'integer',
        'nominal' => 'integer',
        'dibayar_pada' => 'datetime',
        'kedaluwarsa_pada' => 'datetime',
        'notifikasi' => 'array',
    ];

    /**
     * Nomor tagihan untuk DOKU.
     *
     * Bentuknya KODE-JN-XXXX, misal OT-1508-A7K3-DP-9F2C.
     *
     * Kode pesanannya ikut supaya nomor yang muncul di mutasi DOKU dan di
     * surel pelanggan bisa langsung dibaca manusia — admin yang menerima
     * keluhan tidak perlu membuka sistem untuk tahu ini pendaftaran siapa.
     *
     * Empat karakter acak di belakang membuat tiap percobaan punya nomornya
     * sendiri. Tanpa itu, pelanggan yang halaman bayarnya kedaluwarsa lalu
     * mencoba lagi akan mengirim nomor tagihan yang sama persis, dan DOKU
     * menolaknya sebagai duplikat — persis pada saat orang itu sedang
     * berusaha membayar.
     *
     * Panjangnya dijaga di bawah 30 karakter: batas invoice_number DOKU
     * sebenarnya 64, tetapi channel kartu kredit memangkasnya ke 30, dan
     * batas terkecil yang berlaku itulah batas yang harus dipatuhi.
     */
    public static function nomorInvoice(string $kode, string $jenis): string
    {
        $singkatan = [
            'dp' => 'DP',
            'pelunasan' => 'PL',
            'sewa' => 'SW',
            'lainnya' => 'LN',
        ][$jenis] ?? 'LN';

        // Kode pesanan yang panjangnya tak wajar dipangkas, bukan dibiarkan
        // membuat nomor tagihan yang ditolak DOKU di ujung.
        $kode = Str::limit(strtoupper($kode), 22, '');

        return $kode.'-'.$singkatan.'-'.strtoupper(Str::random(4));
    }

    /**
     * Kode unik untuk satu percobaan bayar. DIACAK, 500–1.500.
     *
     * Dulu ia diturunkan dari kode pesanan (crc32) supaya selalu menghasilkan
     * angka yang sama. Kestabilan itu memang dibutuhkan waktu pelanggan
     * mengetik sendiri nominalnya di aplikasi bank: ia membuka halaman
     * pembayaran berkali-kali, dan angka yang berubah tiap muat membuatnya
     * mentransfer jumlah yang tidak kita tunggu.
     *
     * Alasan itu habis begitu pembayaran lewat gerbang. Nominalnya dikunci ke
     * dalam tagihan DOKU pada saat halaman bayar dibuat — pelanggan tidak
     * mengetik apa pun, dan gerbang menagih persis angka yang kita minta.
     * Halaman yang dibuka dua kali menghasilkan dua tagihan, masing-masing
     * dengan angkanya sendiri, dan keduanya sah.
     *
     * Yang didapat dari mengacaknya: dua percobaan atas pesanan yang sama
     * tidak lagi bernominal kembar. Di mutasi rekening, dua baris beda angka
     * bisa dibedakan; dua baris seangka hanya bisa ditebak.
     *
     * Batas bawahnya 500, bukan 1. Tambahan Rp 7 tenggelam di antara
     * pembulatan dan biaya administrasi; Rp 913 tidak mungkin disalahartikan
     * sebagai apa pun selain penanda.
     */
    public static function kodeUnikAcak(): int
    {
        // random_int, bukan rand(): yang kedua bisa ditebak dari keluaran
        // sebelumnya, dan angka ini ikut menyusun nominal tagihan.
        return random_int(500, 1500);
    }

    /** Pesanan yang dibayar, apa pun jenisnya. */
    public function pesanan(): PendaftaranOpenTrip|PenyewaanKendaraan|null
    {
        return PemilikPesanan::tanpaPeriksa($this->kode);
    }

    public function konfirmasi()
    {
        return $this->belongsTo(KonfirmasiPembayaran::class, 'konfirmasi_id');
    }

    public function getNominalFormattedAttribute(): string
    {
        return 'Rp '.number_format($this->nominal, 0, ',', '.');
    }

    public function scopeMenunggu($query)
    {
        return $query->where('status', 'menunggu');
    }

    /**
     * Tagihan yang waktunya sudah lewat, meski statusnya masih "menunggu".
     *
     * Dihitung dari jam, BUKAN dari status. Statusnya baru berubah kalau DOKU
     * mengirimkan notifikasi kedaluwarsa — dan notifikasi itu punya sakelarnya
     * sendiri di dashboard (Pengaturan Kadaluwarsa → Aktifkan Notifikasi
     * Kedaluwarsa) yang bisa mati tanpa ada yang menyadarinya.
     *
     * Menggantungkan kebenaran layar pada sakelar di sistem orang lain berarti
     * halaman hasil akan menyatakan "menunggu pembayaran Anda" untuk tagihan
     * yang sudah mati, lengkap dengan tombol menuju halaman DOKU yang tidak
     * lagi menerima apa-apa. Jamnya sendiri selalu benar, dan sudah kita
     * simpan waktu tagihannya dibuat.
     */
    public function getSudahKedaluwarsaAttribute(): bool
    {
        if ($this->status !== 'menunggu') {
            return false;
        }

        return $this->kedaluwarsa_pada !== null
            && $this->kedaluwarsa_pada->isPast();
    }

    /**
     * Masih pantas ditanyakan ke DOKU?
     *
     * BUKAN kebalikan dari sudah_kedaluwarsa, dan bedanya justru pokoknya.
     *
     * Jam kedaluwarsa itu MILIK KAMI. Yang membayar pada menit terakhir
     * menyelesaikan transaksinya beberapa detik sesudah jam kami menyatakan
     * mati — dan berhenti bertanya tepat pada detik itu berarti uangnya sudah
     * berpindah sementara layar kami menyatakan batas waktu habis. Bahayanya
     * kecil saat batasnya 24 jam; sejak dipendekkan jadi setengah jam, menit
     * terakhir itu jadi tempat yang ramai.
     *
     * DOKU sendiri tetap memproses pembayaran yang telanjur masuk dan tetap
     * mengirim notifikasinya — TerimaNotifikasiDoku memang tidak menolak
     * keberhasilan hanya karena jamnya lewat. Yang dijaga di sini cuma jalur
     * cadangannya, supaya ia tidak menyerah lebih dulu daripada jalur utama.
     */
    public function getMasihPantasDiperiksaAttribute(): bool
    {
        if ($this->status !== 'menunggu') {
            return false;
        }

        if ($this->kedaluwarsa_pada === null) {
            return true;
        }

        $tenggang = (int) config('doku.tenggang_periksa_menit', 15);

        return $this->kedaluwarsa_pada->copy()->addMinutes($tenggang)->isFuture();
    }
}
