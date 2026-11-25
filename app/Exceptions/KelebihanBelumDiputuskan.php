<?php

namespace App\Exceptions;

use Exception;

/**
 * Perpindahan paket menghasilkan KELEBIHAN BAYAR yang belum diputuskan
 * nasibnya.
 *
 * Dilempar dari dalam transaksi supaya seluruh perpindahan ikut dibatalkan.
 * Itu disengaja: pendaftaran yang sudah pindah sementara selisih uangnya
 * menggantung tanpa keputusan adalah keadaan yang paling mudah terlupakan —
 * tripnya jalan, pelanggan ingat pernah lebih bayar, dan kantor tidak punya
 * satu pun baris yang membenarkannya.
 *
 * Angkanya dibawa serta supaya pemanggil bisa menyebutkannya kepada admin,
 * bukan sekadar menolak.
 */
class KelebihanBelumDiputuskan extends Exception
{
    public function __construct(
        public readonly int $kelebihan,
        public readonly int $tagihanBaru,
        public readonly int $dibayar,
    ) {
        parent::__construct('Kelebihan bayar belum diputuskan.');
    }
}
