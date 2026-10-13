<?php

namespace App\Enums\Ipsrs;

/**
 * Status SPK perbaikan (`penugasan_maintenance.status`). Nilai string identik dengan kolom.
 */
enum StatusSpk: string
{
    case Ditugaskan = 'DITUGASKAN';
    case Diterima = 'DITERIMA';
    case Selesai = 'SELESAI';
    case Dibatalkan = 'DIBATALKAN';

    /** @return list<self> */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Ditugaskan, self::Diterima => [self::Diterima, self::Selesai],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** SPK yang masih dikerjakan teknisi. @return list<string> */
    public static function aktif(): array
    {
        return [self::Ditugaskan->value, self::Diterima->value];
    }

    /** SPK yang sudah final dan tidak menerima lembar kerja. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Selesai->value, self::Dibatalkan->value];
    }
}
