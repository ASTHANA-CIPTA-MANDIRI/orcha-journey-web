<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rentang tanggal yang punya tarifnya sendiri: Nataru, Lebaran, long weekend.
 *
 * Bedanya dengan HariLibur: hari libur hanya berkata "perlakukan seperti akhir
 * pekan" dan memakai kolom harga_akhir_pekan. Musim punya ANGKANYA SENDIRI
 * per baris master, di tbl_master_harga_musim.
 *
 * Satu tanggal boleh jadi keduanya — 25 Desember adalah tanggal merah DAN di
 * dalam Nataru. Musim menang, karena ia yang lebih khusus; urutannya diputus
 * di MasterHarga::hargaUntuk(), satu tempat saja.
 */
class MusimHarga extends Model
{
    protected $table = 'tbl_musim_harga';

    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_sampai', 'prioritas', 'aktif', 'oleh'];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_sampai' => 'date',
        'prioritas' => 'integer',
        'aktif' => 'boolean',
    ];

    /**
     * Harga-harga master yang naik di musim ini.
     *
     * Dipakai menjawab "Nataru menyentuh berapa harga" — pertanyaan yang
     * selalu ada sebelum musimnya diumumkan, dan tanpa angkanya layar Musim
     * terbaca seperti pekerjaan administratif tanpa akibat, lalu tidak diisi.
     */
    public function harga(): HasMany
    {
        return $this->hasMany(HargaMusim::class, 'musim_id');
    }

    /**
     * Tanggal disimpan tanpa jam.
     *
     * Alasannya sama dengan HariLibur: MySQL memangkas jamnya sendiri karena
     * kolomnya DATE, jadi tidak ada yang terlihat salah — tetapi SQLite
     * menyimpannya utuh, dan perbandingan rentang "2026-12-20 00:00:00" <=
     * "2026-12-20" gagal. Tanggal pertama musim jadi tidak masuk musimnya
     * sendiri, hanya di basis data yang dipakai pengujian.
     */
    protected function setTanggalMulaiAttribute($nilai): void
    {
        $this->attributes['tanggal_mulai'] = $nilai === null ? null : Carbon::parse($nilai)->toDateString();
    }

    protected function setTanggalSampaiAttribute($nilai): void
    {
        $this->attributes['tanggal_sampai'] = $nilai === null ? null : Carbon::parse($nilai)->toDateString();
    }

    /**
     * Seluruh musim yang masih berlaku, terurut dari yang paling menang.
     *
     * Dibaca SEKALI per RAB lalu ditanya berkali-kali — alasan yang sama
     * dengan HariLibur::peta(). Satu RAB bisa punya 30 baris biaya, dan
     * membaca tabel musim 30 kali untuk jawaban yang sama adalah 29 kueri
     * yang tidak perlu, di jalur yang ikut dipakai kedua PDF.
     *
     * @return Collection<int, self>
     */
    public static function berlaku(): Collection
    {
        /*
         | Diurutkan di PHP, bukan di kueri.
         |
         | Panjang rentang butuh selisih dua tanggal, dan fungsinya berbeda
         | tiap basis data: julianday() di SQLite, DATEDIFF() di MySQL. Yang
         | ditulis dengan salah satunya akan hijau di pengujian dan meledak di
         | server — atau lebih buruk, diam-diam mengurutkan terbalik.
         |
         | Barisnya segelintir per tahun, jadi mengurutkannya di PHP tidak
         | menukar apa pun.
         */
        return self::query()->where('aktif', true)->get()
            ->sortBy([
                // Yang paling berprioritas DULU: cuti bersama di dalam
                // rentang Nataru harus bisa menang tanpa bergantung urutan
                // pengisian.
                fn (self $m) => -$m->prioritas,
                // Seri di prioritas diputus oleh rentang yang lebih SEMPIT.
                // Musim sehari di dalam musim dua pekan jelas lebih khusus,
                // dan menjatuhkannya ke urutan id berarti jawabannya berubah
                // begitu barisnya diisi ulang dengan urutan berbeda.
                fn (self $m) => $m->jumlah_hari,
                fn (self $m) => $m->id,
            ])
            ->values();
    }

    /** Apakah tanggal ini ada di dalam rentang musim ini. */
    public function memuat(?Carbon $tanggal): bool
    {
        if (! $tanggal) {
            return false;
        }

        $hari = $tanggal->toDateString();

        return $hari >= $this->tanggal_mulai->toDateString()
            && $hari <= $this->tanggal_sampai->toDateString();
    }

    /**
     * Musim yang memuat tanggal ini, atau null.
     *
     * @param  Collection<int, self>|null  $musim  dari self::berlaku()
     */
    public static function pada(?Carbon $tanggal, ?Collection $musim = null): ?self
    {
        if (! $tanggal) {
            return null;
        }

        $musim ??= self::berlaku();

        return $musim->first(fn (self $m) => $m->memuat($tanggal));
    }

    public function getJumlahHariAttribute(): int
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_sampai) + 1;
    }

    /** "20 Des 2026 – 5 Jan 2027", atau satu tanggal bila musimnya sehari. */
    public function getRentangTeksAttribute(): string
    {
        $mulai = $this->tanggal_mulai->locale('id');
        $sampai = $this->tanggal_sampai->locale('id');

        if ($mulai->isSameDay($sampai)) {
            return $mulai->translatedFormat('j M Y');
        }

        return $mulai->translatedFormat('j M Y').' – '.$sampai->translatedFormat('j M Y');
    }

    public function getLewatAttribute(): bool
    {
        return $this->tanggal_sampai->isBefore(now()->startOfDay());
    }

    public function getBerjalanAttribute(): bool
    {
        return $this->memuat(now()->startOfDay());
    }
}
