<?php

namespace App\Enums;

/**
 * Status tindak lanjut CAPA temuan audit (`temuan_audit.status_capa`). Nilai string identik dengan kolom.
 */
enum StatusCapa: string
{
    case InProgress = 'In Progress';
    case InReview = 'In Review';
    case Closed = 'Closed';

    /** @return list<self> */
    public function transisiSah(): array
    {
        return match ($this) {
            self::InProgress => [self::InReview],
            self::InReview => [self::Closed, self::InProgress],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Pilihan hasil evaluasi auditor atas tindak lanjut. @return list<string> */
    public static function hasilEvaluasi(): array
    {
        return [self::Closed->value, self::InProgress->value];
    }

    /** Temuan yang belum ditutup. @return list<string> */
    public static function aktif(): array
    {
        return [self::InProgress->value, self::InReview->value];
    }

    /** Temuan yang sudah ditutup resmi. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Closed->value];
    }
}
