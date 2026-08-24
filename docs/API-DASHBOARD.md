# API Dashboard Orcha → Admin Phoenix

Tujuannya satu: **admin cukup sekali login**. Admin masuk lewat Phoenix ("lemon by acm"),
menekan tombol **Ganti ke Orcha** di sidebar, lalu dashboard dan menu berganti jadi milik
Orcha — tanpa login kedua, tanpa pindah aplikasi.

Basis data Orcha **tetap di aplikasi Orcha**. Phoenix tidak menyentuh tabelnya sama
sekali; Phoenix hanya meminta data lewat API ini dan menggambar tampilannya.

```
Admin ──login──▶ Phoenix (tampilan)  ──HTTP + kunci rahasia──▶  Orcha (API + basis data)
```

## Kenapa API, bukan sambungan basis data kedua

Menyambungkan Phoenix langsung ke MySQL Orcha memang lebih singkat, tapi keduanya jadi
terikat: satu kali ubah struktur tabel di Orcha, Phoenix ikut rusak diam-diam. Lewat API,
bentuk datanya jadi perjanjian yang jelas dan ada ujinya. Kalau nanti Orcha pindah server,
yang berubah cuma satu baris alamat di `.env` Phoenix.

---

## Penyiapan

### 1. Buat kunci di sisi Orcha

```bash
php artisan orcha:kunci-api --tulis
```

Perintah itu mengisi `ORCHA_API_KEY` di `.env` Orcha dan menampilkan nilainya.

```env
# .env Orcha
ORCHA_API_KEY=orcha_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
ORCHA_API_IP=203.0.113.10          # IP server Phoenix; kosongkan bila belum tahu
```

### 2. Pasang nilai yang sama di Phoenix

```env
# .env Phoenix
ORCHA_API_URL=https://orchajourney.com/api/v1
ORCHA_API_KEY=orcha_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

> Kunci ini rahasia bersama antar server. **Jangan pernah** menaruhnya di Blade, JavaScript,
> atau apa pun yang sampai ke browser. Panggilan API dilakukan dari server Phoenix.

### 3. Uji sambungan

```bash
curl -H "X-Orcha-Key: $ORCHA_API_KEY" https://orchajourney.com/api/v1/ping
```

---

## Cara memanggil

Setiap permintaan membawa dua header:

| Header | Wajib | Isi |
| --- | --- | --- |
| `X-Orcha-Key` | ya | kunci rahasia bersama (boleh juga `Authorization: Bearer <kunci>`) |
| `X-Orcha-Admin` | tidak | email admin Phoenix yang sedang bertindak — dicatat di log Orcha |

`X-Orcha-Admin` bukan penentu hak akses, hanya jejak audit. **Penentu hak akses tetap
Phoenix**: siapa pun yang memegang kunci dianggap sudah berwenang oleh Orcha.

Batas laju: 120 permintaan per menit per IP.

### Balasan

Selalu berbentuk sama:

```json
{
  "data": [ ... ],
  "meta": { "halaman": 1, "per_halaman": 25, "total": 37, "halaman_terakhir": 2 }
}
```

| Kode | Arti |
| --- | --- |
| 200 | berhasil |
| 401 | kunci salah atau tidak dikirim |
| 403 | IP di luar daftar yang diizinkan |
| 404 | data tidak ditemukan |
| 422 | isian tidak sah (`errors` berisi rinciannya, berbahasa Indonesia) |
| 429 | melebihi batas laju |
| 503 | `ORCHA_API_KEY` belum diisi di sisi Orcha |

---

## Daftar jalur

Awalan: `/api/v1`

### Keterangan sistem

| Cara | Jalur | Guna |
| --- | --- | --- |
| GET | `/ping` | uji sambungan sebelum tombol "Ganti ke Orcha" dipakai |
| GET | `/menu` | susunan menu Orcha untuk digambar di sidebar Phoenix |
| GET | `/rujukan` | daftar pilihan: status, kategori, satuan sewa, aturan pembayaran |

`menu` mengirim `jalur` yang **relatif** (`pendaftaran`, `penyewaan`, …). Phoenix bebas
memberi awalan sendiri, misalnya `/admin/orcha/pendaftaran`.

### Dashboard

| Cara | Jalur |
| --- | --- |
| GET | `/dashboard` |

Isinya: `kartu` (ringkasan berangka), `paket_per_kategori`, `kendaraan_per_jenis`,
`pendaftaran_terbaru`, `penyewaan_terbaru`, dan `perlu_ditindak`.

### Pendaftaran open trip

| Cara | Jalur | Keterangan |
| --- | --- | --- |
| GET | `/pendaftaran` | saringan: `cari`, `status`, `per_halaman`, `page` |
| GET | `/pendaftaran/{id}` | |
| GET | `/pendaftaran/{id}/riwayat-kesehatan` | **data sensitif**, lihat catatan di bawah |
| PATCH | `/pendaftaran/{id}/status` | badan: `{"status":"dp_masuk"}` |

### Sewa kendaraan yang masuk

| Cara | Jalur | Keterangan |
| --- | --- | --- |
| GET | `/penyewaan` | saringan: `cari`, `status` |
| GET | `/penyewaan/{id}` | |
| PATCH | `/penyewaan/{id}/status` | badan: `{"status":"dikonfirmasi"}` |

### Pembatalan

| Cara | Jalur | Keterangan |
| --- | --- | --- |
| GET | `/pembatalan` | saringan: `cari`, `status` |
| GET | `/pembatalan/{id}` | ikut membawa data pendaftarannya |
| PATCH | `/pembatalan/{id}/status` | badan: `{"status":"dana_dikirim","catatan_admin":"..."}` |

### Pesan kontak

| Cara | Jalur | Keterangan |
| --- | --- | --- |
| GET | `/pesan` | saringan: `cari`, `keperluan`, `belum_dibaca=1` |
| GET | `/pesan/{id}` | |
| PATCH | `/pesan/{id}/dibaca` | tanpa badan |

### Etalase (baca saja)

| Cara | Jalur | Keterangan |
| --- | --- | --- |
| GET | `/paket-wisata` | saringan: `cari`, `kategori` |
| GET | `/paket-wisata/{id}` | |
| GET | `/kendaraan` | saringan: `cari`, `jenis`; membawa tarif bertingkat |
| GET | `/kendaraan/{id}` | |
| GET | `/destinasi` | saringan: `wilayah` |
| GET | `/testimoni` | |
| GET | `/partner` | |

### Etalase (menulis)

| Cara | Jalur | Keterangan |
| --- | --- | --- |
| POST | `/paket-wisata` | tambah paket |
| POST | `/paket-wisata/{id}` | ubah paket |
| DELETE | `/paket-wisata/{id}` | ditolak 422 bila paket sudah punya pendaftar |
| POST | `/kendaraan` · `/kendaraan/{id}` · DELETE | ditolak 422 bila unit pernah disewa |
| POST | `/destinasi` · `/destinasi/{id}` · DELETE | |
| POST | `/testimoni` · `/testimoni/{id}` · DELETE | |
| POST | `/partner` · `/partner/{id}` · DELETE | |

Gambar dikirim **multipart** dengan nama medan `gambar`, pada permintaan yang sama.
Berkasnya disimpan di server Orcha (`/storage/...`), bukan di Phoenix — supaya website
Orcha tetap bisa menampilkannya tanpa bergantung aplikasi tetangga. Bila `gambar` tidak
diikutkan saat mengubah, gambar lama tetap dipakai.

Pembaruan memakai **POST**, bukan PUT, karena PHP tidak menguraikan multipart pada
permintaan PUT.

Isian yang ditolak dibalas 422 dengan `errors` per kolom, berbahasa Indonesia — Phoenix
cukup menampilkan pesan pertamanya.

### Keuntungan paket

| Cara | Jalur | Keterangan |
| --- | --- | --- |
| GET | `/keuntungan` | rekap utuh; saringan: `dari`, `sampai`, `kategori`, `paket_id`, `dasar` |
| GET | `/keuntungan/rincian` | baris per pendaftaran, berhalaman; tambahan `hanya_lunas=1` |

Paket menyimpan **`harga_modal`** — biaya internal **per orang**, satuan yang sama dengan
harga jual. Marginnya selisih keduanya: paket bermodal 1.400.000 yang dijual 1.430.000
menghasilkan 30.000 per peserta. Medan itu ikut pada `POST /paket-wisata` dan balasan
`/paket-wisata`, beserta `margin_per_orang` dan `margin_persen` yang sudah terhitung.

Tiga aturan yang menentukan angkanya, dan semuanya disengaja:

1. **Hanya pendaftaran lunas** yang dihitung sebagai keuntungan. Pesanan ber-DP masih bisa
   batal dan potongannya mengikuti tangga pembatalan, jadi untungnya belum tentu ada —
   angkanya tetap dilaporkan, tapi di kolom `potensi_*` yang terpisah. Pesanan batal tidak
   dihitung di mana pun.
2. **Modal kosong bukan nol.** Paket yang modalnya belum diisi masuk hitungan `belum_lengkap`,
   omzetnya tetap terhitung, keuntungannya tidak dikarang. Nilai uang yang tidak diketahui
   dikirim sebagai `null` dengan `_teks` berbunyi "Belum dihitung".
3. **Harga jual dan modal dibekukan** di tiap pendaftaran saat kodenya dibuat. Modal paket
   berubah sepanjang tahun mengikuti harga hotel dan sewa bus; tanpa jejak itu, keuntungan
   bulan lalu ikut berubah setiap kali admin merevisi modal hari ini. Pendaftaran yang masuk
   sebelum medan ini ada meminjam angka paketnya.

`dasar` menentukan tanggal mana yang dipakai menyaring dan mengelompokkan bulan:
`daftar` (bawaan, tanggal pendaftaran masuk) atau `berangkat`. Untuk trip yang dijual jauh
hari, keduanya bisa terpaut berbulan-bulan.

Modal **tidak pernah keluar lewat halaman publik** — kolomnya `$hidden` di model, dan ada
uji yang memastikan angkanya tidak muncul di halaman paket yang dibuka pengunjung.

## Login Orcha sudah dimatikan

Karena seluruh pengelolaan pindah ke lemon, halaman `/login`, `/register`, dan
`/admin/*` di aplikasi Orcha **dimatikan** (404). Rute Fortify pun dihentikan lewat
`Fortify::ignoreRoutes()`. Admin tidak perlu mengingat akun kedua.

Berkas komponennya sengaja tidak dihapus. Bila lemon bermasalah dan Orcha perlu diurus
sendiri untuk sementara:

```env
ORCHA_ADMIN_BAWAAN=true
```

lalu `php artisan optimize:clear`. Ada uji (`AdminBawaanMatiTest`) yang menjaga supaya
halaman itu tidak hidup lagi tanpa disengaja.

---

## Riwayat kesehatan peserta

Data ini sengaja **tidak ikut** di daftar pendaftaran. Daftar hanya membawa
`jumlah_riwayat_kesehatan`; isinya baru keluar bila diminta lewat
`/pendaftaran/{id}/riwayat-kesehatan`, dan setiap pembukaannya dicatat di log Orcha
beserta email admin yang membukanya.

Di sisi Phoenix, jalur ini sebaiknya dijaga permission tersendiri — jangan disamakan
dengan permission melihat pendaftaran biasa.

---

## Sisi Phoenix: yang perlu dibuat

Berikut rancangan yang cocok dengan pola Phoenix yang ada sekarang.

### 1. Config

```php
// config/orcha.php di Phoenix
return [
    'url' => rtrim(env('ORCHA_API_URL', ''), '/'),
    'kunci' => env('ORCHA_API_KEY'),
];
```

### 2. Satu kelas pemanggil

```php
// app/Services/OrchaClient.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class OrchaClient
{
    private function permintaan()
    {
        return Http::withHeaders([
            'X-Orcha-Key' => config('orcha.kunci'),
            'X-Orcha-Admin' => auth()->user()?->email ?? '-',
            'Accept' => 'application/json',
        ])->timeout(10)->baseUrl(config('orcha.url'));
    }

    public function dashboard(): array
    {
        return $this->permintaan()->get('/dashboard')->throw()->json('data');
    }

    public function pendaftaran(array $saringan = []): array
    {
        return $this->permintaan()->get('/pendaftaran', $saringan)->throw()->json();
    }

    public function ubahStatusPendaftaran(int $id, string $status): array
    {
        return $this->permintaan()->patch("/pendaftaran/{$id}/status", ['status' => $status])
            ->throw()->json('data');
    }

    public function hidup(): bool
    {
        return rescue(fn () => $this->permintaan()->get('/ping')->successful(), false);
    }
}
```

`->throw()` penting: kalau Orcha sedang mati, biar jelas gagalnya, jangan tampil
dashboard kosong yang menyesatkan.

### 3. Permission dan tombol di sidebar

Tambahkan satu permission lewat seeder Phoenix, misalnya:

| name | display_name | group |
| --- | --- | --- |
| `akses_orcha` | Akses Dashboard Orcha | Orcha Journey |

Lalu di `resources/views/livewire/layout/sidebar.blade.php`, ikuti pola yang sudah dipakai
di sana:

```blade
@if (auth()->user()->hasPermission('akses_orcha'))
    <li class="sidebar-item">
        <a wire:navigate href="{{ route('admin.orcha.dashboard') }}">
            <span>Ganti ke Orcha</span>
        </a>
    </li>
@endif
```

Admin tanpa permission itu tidak melihat tombolnya sama sekali.

### 4. Rute dan penjagaan

```php
Route::middleware(['auth', 'permission:akses_orcha'])
    ->prefix('admin/orcha')
    ->name('admin.orcha.')
    ->group(function () {
        Volt::route('/dashboard', 'pages.orcha.dashboard')->name('dashboard');
        Volt::route('/pendaftaran', 'pages.orcha.pendaftaran')->name('pendaftaran');
        // dst, mengikuti daftar dari GET /menu
    });
```

Tombol yang disembunyikan bukan pengaman — penjagaan sebenarnya ada di middleware
`permission:akses_orcha` ini.

### 5. Menandai "sedang mode Orcha"

Supaya sidebar berganti isi setelah admin menekan tombolnya, cara paling sederhana adalah
membaca awalan rutenya:

```blade
@php($modeOrcha = request()->routeIs('admin.orcha.*'))
```

Kalau `$modeOrcha` benar, gambar menu dari `GET /menu` (boleh disimpan cache beberapa
menit) dan tampilkan tombol **Kembali ke Phoenix**. Tidak perlu menyimpan status di sesi —
memakai alamat rute berarti admin bisa membuka dua tab sekaligus tanpa saling mengganggu.

---

## Catatan keamanan

- Kunci hanya hidup di `.env` kedua server. Jangan masuk ke git, jangan ke browser.
- Aktifkan `ORCHA_API_IP` begitu IP server Phoenix pasti, supaya kunci yang bocor pun
  tidak bisa dipakai dari tempat lain.
- API ini **harus** lewat HTTPS. Tanpa itu, kuncinya terbaca di jalan.
- Ganti kunci berkala: jalankan `php artisan orcha:kunci-api --tulis` di Orcha, lalu
  perbarui `.env` Phoenix. Ada jeda singkat saat keduanya belum sama.
- Semua perubahan data lewat API tercatat di log Orcha beserta email admin pemanggilnya.
