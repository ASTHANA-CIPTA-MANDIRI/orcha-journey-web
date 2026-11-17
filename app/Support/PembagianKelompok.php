<?php

namespace App\Support;

/**
 * Membagi peserta satu rombongan ke kamar dan ke kursi kendaraan.
 *
 * Pembagian kamar TIDAK PERNAH mencampur laki-laki dengan perempuan. Itu
 * bukan pilihan yang ditawarkan ke admin, melainkan aturan yang ditegakkan di
 * sini: pembagian otomatis yang kadang-kadang mencampur hanya akan dipercaya
 * sebagian, dan admin yang harus memeriksa ulang seluruh hasilnya lebih baik
 * membagi sendiri dari awal.
 *
 * Peserta yang jenis kelaminnya BELUM DIISI tidak ditebak — tidak dari nama,
 * tidak dari apa pun. Nama Indonesia sering tidak menunjukkan jenis kelamin
 * ("Sri", "Nur", "Adi" dipakai keduanya), dan tebakan yang salah di sini
 * berarti satu orang tidur di kamar yang salah. Mereka dikembalikan sebagai
 * sisa dengan sebabnya disebut, supaya yang kurang bisa dilengkapi lalu dibagi
 * ulang — bukan dibagi diam-diam lalu ketahuan di hotel.
 *
 * Pencampuran tetap MUNGKIN dilakukan admin dengan tangan (pasangan suami-istri
 * memang satu kamar), dan itu tidak dilarang — hanya ditandai lewat periksa().
 */
class PembagianKelompok
{
    public const LAKI = 'Laki-laki';

    public const PEREMPUAN = 'Perempuan';

    /**
     * Membagi kamar menurut jenis kelamin.
     *
     * @param  array<int, array<string, mixed>>  $peserta  tiap baris: kunci, nama, jenis_kelamin, pendaftaran_id
     * @param  array<int, array<string, mixed>>  $kamar  tiap baris: nama, kapasitas
     * @return array{isi: array<string, string>, sisa: array<int, array<string, string>>, catatan: array<int, string>}
     */
    public static function kamar(array $peserta, array $kamar): array
    {
        $tersedia = collect($kamar)
            ->map(fn ($satu) => [
                'nama' => trim((string) ($satu['nama'] ?? '')),
                'kapasitas' => max(1, (int) ($satu['kapasitas'] ?? 1)),
            ])
            ->filter(fn ($satu) => $satu['nama'] !== '')
            ->values()
            ->all();

        $isi = [];
        $sisa = [];
        $catatan = [];

        $belumJelas = collect($peserta)
            ->filter(fn ($satu) => ! in_array($satu['jenis_kelamin'] ?? '', [self::LAKI, self::PEREMPUAN], true))
            ->values();

        foreach ($belumJelas as $satu) {
            $sisa[] = [
                'kunci' => (string) $satu['kunci'],
                'nama' => (string) ($satu['nama'] ?? ''),
                'sebab' => 'Jenis kelaminnya belum diisi — tidak ditebak.',
            ];
        }

        if ($belumJelas->isNotEmpty()) {
            $catatan[] = $belumJelas->count().' peserta belum diisi jenis kelaminnya, '
                .'jadi belum bisa dimasukkan ke kamar.';
        }

        if ($tersedia === []) {
            $catatan[] = 'Belum ada kamar yang didaftarkan untuk rombongan ini.';

            return ['isi' => $isi, 'sisa' => $sisa, 'catatan' => $catatan];
        }

        /*
         | Kamar dibagikan per jenis kelamin, satu kelompok habis dulu baru
         | kelompok berikutnya. Kamar yang sudah dipakai satu kelompok tidak
         | ditawarkan lagi ke kelompok lain, walau masih ada sisa tempatnya —
         | sisa tempat di kamar perempuan bukan tempat yang bisa dipakai
         | laki-laki, dan memperlakukannya begitu adalah justru kesalahan yang
         | mau dihindari.
         */
        $nomorKamar = 0;

        foreach ([self::PEREMPUAN, self::LAKI] as $kelamin) {
            // Diurutkan menurut pendaftarannya supaya satu keluarga atau satu
            // pemesanan tidak terpecah ke kamar yang berbeda tanpa sebab.
            $kelompok = collect($peserta)
                ->filter(fn ($satu) => ($satu['jenis_kelamin'] ?? '') === $kelamin)
                ->sortBy([
                    fn ($a, $b) => ((int) ($a['pendaftaran_id'] ?? 0)) <=> ((int) ($b['pendaftaran_id'] ?? 0)),
                    fn ($a, $b) => ((int) ($a['urutan'] ?? 0)) <=> ((int) ($b['urutan'] ?? 0)),
                ])
                ->values();

            if ($kelompok->isEmpty()) {
                continue;
            }

            $antre = $kelompok->all();

            while ($antre !== []) {
                if (! isset($tersedia[$nomorKamar])) {
                    foreach ($antre as $satu) {
                        $sisa[] = [
                            'kunci' => (string) $satu['kunci'],
                            'nama' => (string) ($satu['nama'] ?? ''),
                            'sebab' => 'Kamarnya sudah penuh semua.',
                        ];
                    }

                    $catatan[] = 'Kamar kurang untuk '.count($antre).' peserta '
                        .mb_strtolower($kelamin).'. Tambah kamar lalu bagi ulang.';

                    $antre = [];

                    break;
                }

                $ruang = $tersedia[$nomorKamar];
                $masuk = array_splice($antre, 0, $ruang['kapasitas']);

                foreach ($masuk as $satu) {
                    $isi[(string) $satu['kunci']] = $ruang['nama'];
                }

                $nomorKamar++;
            }
        }

        return ['isi' => $isi, 'sisa' => $sisa, 'catatan' => $catatan];
    }

    /**
     * Membagi kursi kendaraan.
     *
     * Satu pemesanan diisi berurutan supaya orang yang mendaftar bersama duduk
     * berdekatan. Itu bukan kenyamanan tambahan — rombongan yang terpisah-pisah
     * menghasilkan tukar-menukar kursi di dalam bus, dan daftar yang sudah
     * dicetak panitia langsung tidak berlaku lagi.
     *
     * Jenis kelamin TIDAK dipakai di sini: duduk bersebelahan beberapa jam
     * berbeda urusannya dengan tidur satu kamar, dan memisahkannya hanya akan
     * mencerai-ceraikan pasangan dan keluarga.
     *
     * @param  array<int, array<string, mixed>>  $peserta
     * @param  array<int, array<string, mixed>>  $petak  hasil TataKursi::semua()
     * @return array{isi: array<string, array{bus: string, kursi: string}>, sisa: array<int, array<string, string>>, catatan: array<int, string>}
     */
    public static function kursi(array $peserta, array $petak): array
    {
        $isi = [];
        $sisa = [];
        $catatan = [];

        if ($petak === []) {
            $catatan[] = 'Belum ada kendaraan yang didaftarkan untuk rombongan ini.';

            return ['isi' => $isi, 'sisa' => $sisa, 'catatan' => $catatan];
        }

        $antre = collect($peserta)
            ->sortBy([
                fn ($a, $b) => ((int) ($a['pendaftaran_id'] ?? 0)) <=> ((int) ($b['pendaftaran_id'] ?? 0)),
                fn ($a, $b) => ((int) ($a['urutan'] ?? 0)) <=> ((int) ($b['urutan'] ?? 0)),
            ])
            ->values()
            ->all();

        $tempat = [];

        foreach ($petak as $kendaraan) {
            foreach ($kendaraan['kursi'] as $nomor) {
                $tempat[] = ['bus' => $kendaraan['nama'], 'kursi' => $nomor];
            }
        }

        foreach ($antre as $urutan => $satu) {
            if (! isset($tempat[$urutan])) {
                $sisa[] = [
                    'kunci' => (string) $satu['kunci'],
                    'nama' => (string) ($satu['nama'] ?? ''),
                    'sebab' => 'Kursinya sudah penuh semua.',
                ];

                continue;
            }

            $isi[(string) $satu['kunci']] = $tempat[$urutan];
        }

        if ($sisa !== []) {
            $catatan[] = 'Kursi kurang untuk '.count($sisa).' peserta. '
                .'Tambah kendaraan atau perbesar jumlah kursinya lalu bagi ulang.';
        }

        return ['isi' => $isi, 'sisa' => $sisa, 'catatan' => $catatan];
    }

    /**
     * Memeriksa pembagian yang SUDAH ada — termasuk yang dibuat tangan.
     *
     * Dipisahkan dari pembagian otomatis dengan sengaja. Yang otomatis tidak
     * pernah mencampur; yang manual boleh, karena pasangan suami-istri memang
     * satu kamar. Yang dibutuhkan admin bukan larangan, melainkan supaya
     * campuran yang TIDAK disengaja tidak lolos tanpa terlihat.
     *
     * @param  array<int, array<string, mixed>>  $peserta  sudah berisi kamar & kursi
     * @return array<int, array{jenis: string, pesan: string}>
     */
    public static function periksa(array $peserta): array
    {
        $peringatan = [];

        $perKamar = collect($peserta)
            ->filter(fn ($satu) => trim((string) ($satu['kamar'] ?? '')) !== '')
            ->groupBy(fn ($satu) => trim((string) $satu['kamar']));

        foreach ($perKamar as $nama => $penghuni) {
            $kelamin = $penghuni
                ->pluck('jenis_kelamin')
                ->map(fn ($satu) => trim((string) $satu))
                ->filter()
                ->unique()
                ->values();

            if ($kelamin->count() > 1) {
                $peringatan[] = [
                    'jenis' => 'kamar_campur',
                    'pesan' => 'Kamar '.$nama.' berisi '.$kelamin->implode(' dan ')
                        .'. Kalau ini pasangan, biarkan; kalau tidak, pindahkan salah satunya.',
                ];
            }

            $belum = $penghuni->filter(
                fn ($satu) => ! in_array(trim((string) ($satu['jenis_kelamin'] ?? '')), [self::LAKI, self::PEREMPUAN], true)
            );

            if ($belum->isNotEmpty()) {
                $peringatan[] = [
                    'jenis' => 'kamar_tanpa_kelamin',
                    'pesan' => 'Kamar '.$nama.' berisi '.$belum->count()
                        .' peserta yang jenis kelaminnya belum diisi.',
                ];
            }
        }

        /*
         | Kursi kembar diperiksa terpisah dan selalu jadi peringatan.
         |
         | Tidak ada keadaan sah di mana dua orang menempati satu kursi, jadi
         | ini bukan hal yang perlu ditimbang admin — ini salah ketik yang
         | baru terasa akibatnya setelah bus berjalan.
         */
        $perKursi = collect($peserta)
            ->filter(fn ($satu) => trim((string) ($satu['kursi'] ?? '')) !== '')
            ->groupBy(fn ($satu) => trim((string) ($satu['bus'] ?? '')).'|'.trim((string) $satu['kursi']));

        foreach ($perKursi as $kunci => $penghuni) {
            if ($penghuni->count() < 2) {
                continue;
            }

            [$bus, $nomor] = array_pad(explode('|', (string) $kunci, 2), 2, '');

            $peringatan[] = [
                'jenis' => 'kursi_kembar',
                'pesan' => trim(($bus !== '' ? $bus.' ' : '').'kursi '.$nomor)
                    .' dipakai '.$penghuni->count().' orang: '
                    .$penghuni->pluck('nama')->implode(', ').'.',
            ];
        }

        return $peringatan;
    }
}
