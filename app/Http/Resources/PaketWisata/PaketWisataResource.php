<?php

namespace App\Http\Resources\PaketWisata;

use App\Models\PaketWisata\TravelPackage;
use App\Support\PaketWisata\ItineraryTeks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TravelPackage $resource
 */
class PaketWisataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'nama' => $this->name,
            'kategori' => $this->category,
            'kategori_label' => $this->category_label,
            'status' => $this->status,
            'status_tayang' => $this->status_tayang,
            'status_tayang_label' => $this->status_tayang_label,
            'sedang_tayang' => $this->sedang_tayang,
            'tayang_mulai' => $this->tayang_mulai?->format('Y-m-d\TH:i'),
            'tayang_sampai' => $this->tayang_sampai?->format('Y-m-d\TH:i'),
            'berakhir_otomatis' => (bool) $this->berakhir_otomatis,
            'durasi' => $this->duration,
            'jadwal_label' => $this->jadwal_label,
            'tanggal_berangkat' => $this->tanggal_berangkat?->toDateString(),
            'tanggal_pulang' => $this->tanggal_pulang?->toDateString(),
            'batas_pelunasan' => $this->batas_pelunasan?->toDateString(),
            'sudah_lewat' => $this->sudah_lewat,
            'titik_jemput' => $this->titik_jemput,
            'minimal_peserta' => $this->minimal_peserta,

            // Kuota beserta hitungannya: lemon perlu ketiganya untuk
            // memperlihatkan "12 dari 20 kursi terisi" tanpa menghitung
            // sendiri — dua tempat yang menghitung sendiri-sendiri akan
            // berbeda angkanya suatu saat.
            /*
             | Tanggal-tanggal keberangkatan paket ini.
             |
             | Dimuat lewat whenLoaded: daftar paket tidak membutuhkannya dan
             | memuatnya di sana berarti satu kueri per baris. Yang memakainya
             | borang paket di lemon dan halaman publiknya.
             */
            'keberangkatan' => $this->whenLoaded('keberangkatan', fn () => $this->keberangkatan
                ->map(fn ($k) => [
                    'id' => $k->id,
                    'tanggal_berangkat' => $k->tanggal_berangkat?->toDateString(),
                    'tanggal_pulang' => $k->tanggal_pulang?->toDateString(),
                    'rentang_tanggal' => $k->rentang_tanggal,
                    // Angka MENTAHNYA ikut, supaya borang tahu mana yang
                    // memang diisi dan mana yang ikut paket — keduanya tidak
                    // bisa dibedakan lagi kalau cuma nilai akhirnya yang
                    // dikirim.
                    'kuota' => $k->kuota,
                    'price' => $k->price,
                    'original_price' => $k->original_price,
                    'harga_modal' => $k->harga_modal,
                    'kuota_dipakai' => $k->kuota_dipakai,
                    'harga_dipakai' => $k->harga_dipakai,
                    'kursi_terpakai' => $k->kursi_terpakai,
                    'sisa_kursi' => $k->sisa_kursi,
                    'penuh' => $k->penuh,
                    'aktif' => $k->aktif,
                    'catatan' => $k->catatan,
                    // Batas pelunasan TANGGAL INI, bukan tanggal paket —
                    // lihat alasannya di Keberangkatan::getBatasPelunasan.
                    'batas_pelunasan' => $k->batas_pelunasan?->toDateString(),
                ])->values()->all()),

            'kuota' => $this->kuota,
            'kursi_terpakai' => $this->kursi_terpakai,
            'sisa_kursi' => $this->sisa_kursi,
            'harga' => $this->price,
            'harga_asli' => $this->original_price,

            // Modal dan marginnya hanya lewat jalur ini — jalur yang dijaga
            // kunci antar server. Halaman publik tidak pernah menyentuhnya.
            'harga_modal' => $this->harga_modal,
            'modal_terisi' => $this->modal_terisi,
            'margin_per_orang' => $this->margin_per_orang,
            'margin_per_orang_teks' => $this->margin_per_orang_teks,
            'margin_persen' => $this->margin_persen,
            'diskon_persen' => $this->discount_percentage,
            'catatan_promo' => $this->catatan_promo,
            'pilihan_terbaik' => (bool) $this->is_best_choice,

            // Ikut promo rombongan? Tingkatnya seragam untuk seluruh
            // perusahaan, tetapi tidak setiap trip ikut — sebagian sudah tipis
            // marginnya, sebagian sedang musim ramai dan tidak perlu didorong.
            'promo_rombongan' => (bool) $this->promo_rombongan,
            'destinasi' => $this->destination_list ?? [],
            'fasilitas' => $this->fasilitas ?? [],

            // Lawan dari fasilitas. Dikirim sebagai senarai kosong bila belum
            // diisi, supaya yang memakainya tidak perlu membedakan null.
            'tidak_termasuk' => $this->tidak_termasuk ?? [],
            'itinerary' => $this->itinerary ?? [],

            // Bentuk teks siap sunting, supaya dashboard lemon tidak perlu
            // menulis ulang aturan formatnya sendiri.
            'itinerary_teks' => ItineraryTeks::keTeks($this->itinerary),
            'sampul' => $this->sampul,
            'foto_galeri' => $this->foto_galeri ?? [],
            'jumlah_pendaftar' => $this->whenCounted('pendaftaran'),

            /*
             | Ringkasan tanggal untuk DAFTAR paket.
             |
             | Daftar tidak memuat relasi keberangkatan-nya (satu kueri per
             | baris), jadi tanpa ini layar daftar tidak punya cara menyebut
             | bahwa satu paket dijual untuk tiga tanggal — ia hanya melihat
             | tanggal lama milik paket, dan admin membacanya sebagai satu
             | tanggal saja.
             */
            'jumlah_keberangkatan' => $this->whenCounted('keberangkatan'),
            'tautan_publik' => url('/paket/'.$this->uuid),
        ];
    }
}
