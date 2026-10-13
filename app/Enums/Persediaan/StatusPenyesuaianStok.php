<?php

namespace App\Enums\Persediaan;

/**
 * Status dokumen Penyesuaian Stok hasil opname. Nilai string identik dengan kolom `penyesuaian_stok.status`.
 */
enum StatusPenyesuaianStok: string
{
    case Menunggu = 'MENUNGGU';
    case Disetujui = 'DISETUJUI';
    case Ditolak = 'DITOLAK';

    /** @return list<self> */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Menunggu => [self::Disetujui, self::Ditolak],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Dokumen yang menunggu otorisasi. @return list<string> */
    public static function aktif(): array
    {
        return [self::Menunggu->value];
    }

    /** Dokumen yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Disetujui->value, self::Ditolak->value];
    }
}
