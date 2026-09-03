<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Mengunggah berkas ke Google Drive tanpa satu pun paket tambahan.
 *
 * Dua jebakan yang membuat jalur biasa tidak bisa dipakai di sini:
 *
 * 1. SERVICE ACCOUNT TIDAK PUNYA KUOTA PENYIMPANAN SENDIRI. Cara yang paling
 *    sering ditulis orang — buat service account, bagikan foldernya —
 *    menghasilkan galat "storage quota exceeded" pada unggahan pertama, kecuali
 *    tujuannya Shared Drive milik Google Workspace. Untuk akun Gmail biasa
 *    jalurnya harus OAuth atas nama pemilik akunnya sendiri, dan berkasnya
 *    dihitung terhadap 15 GB miliknya. Itu yang dipakai di sini.
 *
 * 2. Pustaka resmi google/apiclient menyeret puluhan paket dan menuntut
 *    composer install di hosting. Yang dibutuhkan sebenarnya cuma dua
 *    permintaan HTTP: tukar refresh token jadi access token, lalu unggah.
 *    Keduanya ada di bawah, dan tidak ada yang perlu dipasang.
 *
 * Refresh token diambil SEKALI lewat persetujuan di peramban, lalu disimpan di
 * .env. Ia tidak kedaluwarsa sendiri — yang mencabutnya cuma pemilik akun.
 */
class GoogleDrive
{
    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const UNGGAH = 'https://www.googleapis.com/upload/drive/v3/files';

    private const BERKAS = 'https://www.googleapis.com/drive/v3/files';

    public static function siap(): bool
    {
        return filled(config('orcha.drive.client_id'))
            && filled(config('orcha.drive.client_secret'))
            && filled(config('orcha.drive.refresh_token'));
    }

    /**
     * @return string id berkas di Drive
     */
    public static function unggah(string $jalur, ?string $nama = null): string
    {
        if (! is_file($jalur)) {
            throw new \RuntimeException('Berkas tidak ditemukan: '.$jalur);
        }

        $token = self::token();
        $nama ??= basename($jalur);

        $keterangan = array_filter([
            'name' => $nama,
            'parents' => array_filter([config('orcha.drive.folder_id')]) ?: null,
        ]);

        /*
         | Unggahan bersambung (resumable), bukan sekali kirim.
         |
         | Sekali kirim menuntut seluruh berkas berada di memori, dan cadangan
         | basis data adalah justru berkas yang paling mungkin tidak muat.
         | Bersambung juga bertahan pada sambungan hosting yang putus di
         | tengah — dan cadangan yang gagal separuh jalan tanpa ada yang tahu
         | lebih buruk daripada tidak ada cadangan sama sekali, karena ia
         | terlihat ada.
         */
        $mulai = Http::withToken($token)
            ->withHeaders(['X-Upload-Content-Type' => 'application/gzip'])
            ->post(self::UNGGAH.'?uploadType=resumable', $keterangan);

        if (! $mulai->successful()) {
            throw new \RuntimeException('Google Drive menolak permulaan unggahan: '.$mulai->body());
        }

        $tujuan = $mulai->header('Location');

        if (blank($tujuan)) {
            throw new \RuntimeException('Google Drive tidak memberi alamat unggahan.');
        }

        $aliran = fopen($jalur, 'rb');

        try {
            $hasil = Http::withToken($token)
                ->withBody($aliran, 'application/gzip')
                ->withHeaders(['Content-Length' => (string) filesize($jalur)])
                ->timeout(600)
                ->put($tujuan);
        } finally {
            if (is_resource($aliran)) {
                fclose($aliran);
            }
        }

        if (! $hasil->successful()) {
            throw new \RuntimeException('Unggahan ke Google Drive gagal: '.$hasil->body());
        }

        return (string) $hasil->json('id');
    }

    /**
     * Membuang cadangan lama di Drive, menyisakan sekian yang terbaru.
     *
     * @return array<int, string> nama berkas yang dibuang
     */
    public static function rapikan(int $sisakan): array
    {
        $token = self::token();
        $folder = config('orcha.drive.folder_id');

        $saring = "name contains 'orcha-' and trashed = false"
            .($folder ? " and '".$folder."' in parents" : '');

        $daftar = Http::withToken($token)->get(self::BERKAS, [
            'q' => $saring,
            'orderBy' => 'name',
            'fields' => 'files(id,name)',
            'pageSize' => 1000,
        ]);

        if (! $daftar->successful()) {
            throw new \RuntimeException('Tidak bisa membaca daftar cadangan di Drive: '.$daftar->body());
        }

        $berkas = collect($daftar->json('files', []));
        $buang = $berkas->slice(0, max(0, $berkas->count() - $sisakan));

        foreach ($buang as $satu) {
            Http::withToken($token)->delete(self::BERKAS.'/'.$satu['id']);
        }

        return $buang->pluck('name')->values()->all();
    }

    /**
     * Menukar refresh token jadi access token yang berlaku sejam.
     *
     * Tidak disimpan di cache. Perintah cadangan berjalan sekali sehari dan
     * hidup beberapa menit; menyimpan token akses di cache berarti kredensial
     * Drive tergeletak di penyimpanan bersama sepanjang hari demi menghemat
     * satu permintaan HTTP setiap dua puluh empat jam.
     */
    private static function token(): string
    {
        $jawab = Http::asForm()->post(self::TOKEN, [
            'client_id' => config('orcha.drive.client_id'),
            'client_secret' => config('orcha.drive.client_secret'),
            'refresh_token' => config('orcha.drive.refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if (! $jawab->successful()) {
            throw new \RuntimeException('Google menolak refresh token: '.$jawab->body());
        }

        return (string) $jawab->json('access_token');
    }
}
