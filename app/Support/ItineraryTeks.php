<?php

namespace App\Support;

/**
 * Penerjemah itinerary antara bentuk tersimpan (JSON) dan bentuk teks yang
 * mudah diketik admin:
 *
 *   Day 1
 *   18.00 | Penjemputan Meeting Point
 *   19.00 | Perjalanan Banyuwangi
 *
 *   Day 2
 *   03.00 | Tiba di Banyuwangi
 *
 * Baris tanpa tanda "|" dianggap judul hari, baris dengan "|" dianggap agenda.
 */
class ItineraryTeks
{
    public static function keArray(?string $teks): array
    {
        $hasil = [];
        $hariSekarang = null;

        foreach (preg_split('/\R/', (string) $teks) as $baris) {
            $baris = trim($baris);

            if ($baris === '') {
                continue;
            }

            if (! str_contains($baris, '|')) {
                if ($hariSekarang !== null) {
                    $hasil[] = $hariSekarang;
                }

                $hariSekarang = ['hari' => $baris, 'agenda' => []];

                continue;
            }

            [$jam, $kegiatan] = array_pad(explode('|', $baris, 2), 2, '');

            if ($hariSekarang === null) {
                $hariSekarang = ['hari' => 'Hari 1', 'agenda' => []];
            }

            $hariSekarang['agenda'][] = [
                'jam' => trim($jam),
                'kegiatan' => trim($kegiatan),
            ];
        }

        if ($hariSekarang !== null) {
            $hasil[] = $hariSekarang;
        }

        return $hasil;
    }

    public static function keTeks(?array $itinerary): string
    {
        if (empty($itinerary)) {
            return '';
        }

        $baris = [];

        foreach ($itinerary as $hari) {
            $baris[] = $hari['hari'] ?? 'Hari';

            foreach ($hari['agenda'] ?? [] as $agenda) {
                $baris[] = ($agenda['jam'] ?? '').' | '.($agenda['kegiatan'] ?? '');
            }

            $baris[] = '';
        }

        return trim(implode("\n", $baris));
    }
}
