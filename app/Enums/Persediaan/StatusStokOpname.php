<?php

namespace App\Enums\Persediaan;

/**
 * Status sesi Stock Opname. Nilai string identik dengan kolom `stok_opname.status`.
 *
 * PROSES_COUNT (simpan hitung tanpa finalisasi via API) dipakai kode meskipun tidak tercantum di
 * komentar migrasi. DIBATALKAN hanya ada pada data lama (dulu ditulis API saat penolakan penyesuaian);
 * sejak ADR 0002 A1 penolakan selalu menulis DITOLAK. DRAFT tercantum di migrasi tetapi tidak pernah
 * ditulis kode (sesi baru langsung BERJALAN).
 */
enum StatusStokOpname: string
{
    case Draft = 'DRAFT';
    case Berjalan = 'BERJALAN';
    case ProsesCount = 'PROSES_COUNT';
    case MenungguApproval = 'MENUNGGU_APPROVAL';
    case Disetujui = 'DISETUJUI';
    case Ditolak = 'DITOLAK';
    case Dibatalkan = 'DIBATALKAN';

    /**
     * Hitung ulang dari PROSES_COUNT / MENUNGGU_APPROVAL diizinkan API (self-loop).
     * MENUNGGU_APPROVAL ditutup oleh otorisasi penyesuaian: DISETUJUI atau DITOLAK (web dan API).
     *
     * @return list<self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Berjalan => [self::ProsesCount, self::MenungguApproval, self::Disetujui],
            self::ProsesCount => [self::ProsesCount, self::MenungguApproval, self::Disetujui],
            self::MenungguApproval => [self::ProsesCount, self::MenungguApproval, self::Disetujui, self::Ditolak],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Sesi yang masih menerima input hitung fisik via API. @return list<string> */
    public static function bisaDihitung(): array
    {
        return [self::Berjalan->value, self::ProsesCount->value, self::MenungguApproval->value];
    }

    /** Sesi yang masih berjalan. @return list<string> */
    public static function aktif(): array
    {
        return [self::Draft->value, self::Berjalan->value, self::ProsesCount->value, self::MenungguApproval->value];
    }

    /** Sesi yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Disetujui->value, self::Ditolak->value, self::Dibatalkan->value];
    }
}
