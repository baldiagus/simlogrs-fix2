<?php

namespace App\Enums;

/**
 * Status dokumen pengalihan (aset dan barang). Nilai string identik dengan kolom `status`.
 */
enum StatusPengalihan: string
{
    case Diajukan = 'DIAJUKAN';
    case Disetujui = 'DISETUJUI';
    case Selesai = 'SELESAI';
    case Ditolak = 'DITOLAK';
    case Dibatalkan = 'DIBATALKAN';

    /** @return list<self> */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Diajukan => [self::Disetujui, self::Ditolak, self::Dibatalkan],
            self::Disetujui => [self::Selesai, self::Ditolak],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Status dokumen yang masih berjalan. @return list<string> */
    public static function aktif(): array
    {
        return [self::Diajukan->value, self::Disetujui->value];
    }

    /** Status dokumen yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Selesai->value, self::Ditolak->value, self::Dibatalkan->value];
    }
}
