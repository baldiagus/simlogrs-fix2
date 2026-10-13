<?php

namespace App\Enums\Aset;

/**
 * Status operasional aset. Nilai string identik dengan kolom `aset.status`.
 *
 * transisiSah() memetakan perpindahan yang dijalankan oleh proses bisnis (peminjaman,
 * pengalihan, maintenance IPSRS, ubah status di ruangan). Master Aset (admin) dapat
 * menimpa status secara manual ke salah satu nilai dapatDiaturManual().
 */
enum StatusAset: string
{
    case Tersedia = 'TERSEDIA';
    case Digunakan = 'DIGUNAKAN';
    case Dipinjam = 'DIPINJAM';
    case DalamMaintenance = 'DALAM_MAINTENANCE';
    case DalamPengalihan = 'DALAM_PENGALIHAN';
    case Dihapuskan = 'DIHAPUSKAN';

    /** @return list<self> */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Tersedia => [self::Digunakan, self::Dipinjam, self::DalamMaintenance, self::DalamPengalihan],
            self::Digunakan => [self::Tersedia, self::DalamMaintenance, self::DalamPengalihan],
            self::Dipinjam => [self::Tersedia, self::DalamMaintenance],
            self::DalamMaintenance => [self::Tersedia, self::Digunakan],
            self::DalamPengalihan => [self::Tersedia],
            self::Dihapuskan => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Aset operasional yang boleh diajukan pengalihan. @return list<string> */
    public static function dapatDialihkan(): array
    {
        return [self::Tersedia->value, self::Digunakan->value];
    }

    /** Pilihan status yang boleh ditetapkan admin di Master Aset. @return list<string> */
    public static function dapatDiaturManual(): array
    {
        return [
            self::Tersedia->value,
            self::Digunakan->value,
            self::Dipinjam->value,
            self::DalamMaintenance->value,
            self::Dihapuskan->value,
        ];
    }
}
