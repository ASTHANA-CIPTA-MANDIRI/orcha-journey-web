<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Menuntun sekali persetujuan Google, lalu mencetak refresh token-nya.
 *
 * Ada karena langkah ini yang paling sering menggagalkan orang. Halaman
 * dokumentasi Google menerangkan alur OAuth untuk aplikasi web yang punya
 * alamat balik; yang kita butuhkan cuma satu token, sekali seumur hidup
 * pemasangan, untuk sebuah perintah cron. Tanpa tuntunan ini langkahnya
 * dikerjakan sambil menebak, dan tebakan yang meleset menghasilkan token yang
 * tampak jalan lalu mati tujuh hari kemudian.
 *
 * TIGA HAL YANG PALING SERING TERLEWAT, dan ketiganya diam:
 *
 *   - access_type=offline. Tanpa ini Google hanya memberi access token yang
 *     berlaku sejam, dan tidak ada refresh token sama sekali.
 *   - prompt=consent. Tanpa ini, persetujuan KEDUA untuk akun yang sama tidak
 *     mengembalikan refresh token lagi — dan yang mengulang penyiapan karena
 *     tokennya hilang justru tidak mendapatkannya.
 *   - Akun Gmail Anda harus terdaftar sebagai Test user di OAuth consent
 *     screen. Aplikasi yang statusnya "Testing" mencabut refresh token
 *     penggunanya setelah tujuh hari, kecuali penggunanya test user.
 */
class DriveIzin extends Command
{
    protected $signature = 'orcha:drive-izin';

    protected $description = 'Menuntun persetujuan Google Drive dan mencetak refresh token';

    /**
     * Hanya berkas yang dibuat aplikasi ini sendiri.
     *
     * drive.file, bukan drive. Cakupan penuh memberi kita akses baca-tulis ke
     * SELURUH isi Drive pemilik akun — foto keluarga, dokumen pribadi,
     * semuanya — demi mengunggah satu berkas cadangan per hari. Kalau kunci
     * ini suatu saat bocor, bedanya kedua cakupan itu adalah bedanya
     * kehilangan cadangan dan kehilangan segalanya.
     */
    private const CAKUPAN = 'https://www.googleapis.com/auth/drive.file';

    /** Alur "aplikasi terpasang": kodenya ditempel manual, tanpa alamat balik. */
    private const TEMPEL = 'urn:ietf:wg:oauth:2.0:oob';

    public function handle(): int
    {
        $id = config('orcha.drive.client_id') ?: $this->ask('Client ID dari Google Cloud Console');
        $rahasia = config('orcha.drive.client_secret') ?: $this->secret('Client Secret');

        if (blank($id) || blank($rahasia)) {
            $this->error('Client ID dan Secret wajib diisi.');

            return self::FAILURE;
        }

        $tautan = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $id,
            'redirect_uri' => self::TEMPEL,
            'response_type' => 'code',
            'scope' => self::CAKUPAN,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        $this->newLine();
        $this->line('1. Buka tautan ini di peramban, masuk dengan akun Google pemilik Drive:');
        $this->newLine();
        $this->line('   '.$tautan);
        $this->newLine();
        $this->line('2. Setujui, lalu salin kode yang ditampilkan Google.');
        $this->newLine();

        $kode = $this->ask('3. Tempel kodenya di sini');

        if (blank($kode)) {
            $this->error('Kodenya kosong.');

            return self::FAILURE;
        }

        $jawab = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => trim($kode),
            'client_id' => $id,
            'client_secret' => $rahasia,
            'redirect_uri' => self::TEMPEL,
            'grant_type' => 'authorization_code',
        ]);

        if (! $jawab->successful()) {
            $this->error('Google menolak kodenya: '.$jawab->body());

            return self::FAILURE;
        }

        $refresh = $jawab->json('refresh_token');

        if (blank($refresh)) {
            $this->error('Google tidak mengirim refresh token.');
            $this->warn('Biasanya karena akun ini sudah pernah menyetujui aplikasinya.');
            $this->warn('Cabut dulu di myaccount.google.com/permissions, lalu ulangi.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Berhasil. Tambahkan ke .env di server:');
        $this->newLine();
        $this->line('ORCHA_DRIVE_CLIENT_ID='.$id);
        $this->line('ORCHA_DRIVE_CLIENT_SECRET='.$rahasia);
        $this->line('ORCHA_DRIVE_REFRESH_TOKEN='.$refresh);
        $this->line('ORCHA_DRIVE_FOLDER=   # id folder tujuan, dari alamat peramban');
        $this->newLine();
        $this->line('Lalu coba: php artisan orcha:cadangan');

        return self::SUCCESS;
    }
}
