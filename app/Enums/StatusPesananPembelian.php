<?php

namespace App\Enums;

/**
 * Status Pesanan Pembelian (PO). Nilai string identik dengan kolom `pesanan_pembelian.status`.
 */
enum StatusPesananPembelian: string
{
    case Diterbitkan = 'DITERBITKAN';
    case DikirimVendor = 'DIKIRIM_VENDOR';
    case DiterimaSebagian = 'DITERIMA_SEBAGIAN';
    case DiterimaLengkap = 'DITERIMA_LENGKAP';
    case Dibatalkan = 'DIBATALKAN';

    /**
     * DITERIMA_SEBAGIAN boleh berulang (penerimaan parsial berikutnya).
     * Tidak ada kode yang saat ini menulis DIBATALKAN.
     *
     * @return list<self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Diterbitkan => [self::DikirimVendor],
            self::DikirimVendor => [self::DiterimaSebagian, self::DiterimaLengkap],
            self::DiterimaSebagian => [self::DiterimaSebagian, self::DiterimaLengkap],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** PO yang barangnya sudah dikirim vendor dan dapat diterima gudang. @return list<string> */
    public static function bisaDiterima(): array
    {
        return [self::DikirimVendor->value, self::DiterimaSebagian->value];
    }

    /** PO yang masih berjalan (tracking penerimaan). @return list<string> */
    public static function aktif(): array
    {
        return [self::Diterbitkan->value, self::DikirimVendor->value, self::DiterimaSebagian->value];
    }

    /** PO yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::DiterimaLengkap->value, self::Dibatalkan->value];
    }

    /** Seluruh PO yang tidak dibatalkan (dihitung sebagai belanja). @return list<string> */
    public static function tidakDibatalkan(): array
    {
        return [self::Diterbitkan->value, self::DikirimVendor->value, self::DiterimaSebagian->value, self::DiterimaLengkap->value];
    }
}
