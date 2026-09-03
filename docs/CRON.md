# Cron Orcha Journey

Penjadwal Laravel **tidak dipakai di sini**. Hosting mematikan `proc_open`,
sehingga `schedule:run` gagal tiap menit tanpa menjalankan apa pun — dan
kegagalannya diam. Semua perintah berkala dipanggil cron **satu per satu**.

Jalur aplikasinya di server hPanel ada di bawah `~/domains/<domain>/app`, bukan
`public_html`. Panjang satu baris cron di hPanel dibatasi 255 karakter.

```cron
# Melepas kursi yang ditahan pemesanan yang tidak pernah membayar (72 jam).
# Sekaligus mengabari yang menunggu di daftar tunggu bahwa kursinya terbuka.
0 * * * * cd ~/domains/orchajourney.com/app && php artisan orcha:lepas-kursi >> storage/logs/cron.log 2>&1

# Pengingat pelunasan dan briefing keberangkatan.
# Pukul sembilan pagi: jam saat orang bisa benar-benar ke bank dan berkemas.
0 9 * * * cd ~/domains/orchajourney.com/app && php artisan orcha:pengingat >> storage/logs/cron.log 2>&1

# Mengajak peserta yang pulang dua hari lalu menulis testimoni.
# Suratnya sekaligus membawakan kode rujukan miliknya.
30 9 * * * cd ~/domains/orchajourney.com/app && php artisan orcha:ajak-testimoni >> storage/logs/cron.log 2>&1

# Menyelaraskan status pesanan dengan pembayaran yang benar-benar diterima.
15 * * * * cd ~/domains/orchajourney.com/app && php artisan orcha:selaraskan-status >> storage/logs/cron.log 2>&1

# Membuang riwayat kesehatan yang sudah lewat masa simpannya (90 hari).
0 2 * * * cd ~/domains/orchajourney.com/app && php artisan orcha:bersihkan-kesehatan >> storage/logs/cron.log 2>&1

# Cadangan basis data, lalu diunggah ke Google Drive.
# Setengah tiga pagi: jam paling sepi, dan menyalin basis data membebani
# server sebentar. Lihat config/orcha.php kunci "drive" untuk penyiapannya.
30 2 * * * cd ~/domains/orchajourney.com/app && php artisan orcha:cadangan >> storage/logs/cron.log 2>&1

# Berkas unggahan yang tidak lagi ditunjuk baris mana pun.
# Mingguan, dan JALANKAN DULU TANPA --hapus untuk melihat daftarnya.
0 3 * * 0 cd ~/domains/orchajourney.com/app && php artisan orcha:berkas-yatim --hapus >> storage/logs/cron.log 2>&1
```

## Yang perlu diperhatikan

**Pengingat dikirim sekali.** `orcha:pengingat` menandai tiap pendaftaran yang
sudah dikirimi di basis data. Menjalankannya manual untuk memeriksa akan
benar-benar mengirim surat — pakai `--percobaan` untuk melihat siapa saja tanpa
mengirim apa pun.

**Cadangan gagal harus berisik.** `orcha:cadangan` keluar dengan kode bukan-nol
saat penyalinan atau unggahannya gagal, supaya cron mengirimkan surat
kegagalan. Cadangan yang diam-diam berhenti dibuat baru ketahuan pada hari ia
dibutuhkan — dan pada hari itu tidak ada lagi yang bisa dikerjakan.

**Memulihkan cadangan.** Berkasnya `.sql` biasa yang dikompresi gzip. Buka
phpMyAdmin, pilih basis datanya, tab Impor, unggah berkasnya. Sengaja bukan
bentuk khusus yang menuntut alat kami sendiri untuk membacanya: pada hari kita
benar-benar membutuhkannya, alat itu mungkin ikut hilang.
