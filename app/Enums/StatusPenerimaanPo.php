<?php

namespace App\Enums;

/**
 * Status dokumen Penerimaan Barang dari PO. Nilai string identik dengan kolom `penerimaan_barang.status`.
 *
 * Dokumen penerimaan bersifat final sejak dibuat; statusnya tidak pernah diubah setelahnya.
 */
enum StatusPenerimaanPo: string
{
    case Lengkap = 'LENGKAP';
    case Sebagian = 'SEBAGIAN';
    case Ditolak = 'DITOLAK';

    /** @return list<self> */
    public function transisiSah(): array
    {
        return [];
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Penerimaan yang memasukkan stok ke gudang. @return list<string> */
    public static function diterima(): array
    {
        return [self::Lengkap->value, self::Sebagian->value];
    }
}
