<?php

namespace App\Support\Generator;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CodeGenerator
{
    /**
     * Menerbitkan nomor kode dokumen transaksi resmi secara terpusat.
     * Format: {PREFIX}-{YYYY}-{XXX} atau {PREFIX}-{YYYYMM}-{XXX}
     */
    public static function generate(
        string $prefix,
        string $modelClass,
        string $column = 'kode',
        string $dateFormat = 'Y',
        int $pad = 3
    ): string {
        $now = Carbon::now();
        $datePart = $now->format($dateFormat);
        $searchPrefix = "{$prefix}-{$datePart}-";

        // Cari nomor urut terakhir pada periode terkait
        $lastRecord = $modelClass::query()
            ->where($column, 'like', "{$searchPrefix}%")
            ->orderBy($column, 'desc')
            ->value($column);

        $nextNumber = 1;

        if ($lastRecord) {
            $parts = explode('-', $lastRecord);
            $lastSeq = end($parts);
            if (is_numeric($lastSeq)) {
                $nextNumber = (int) $lastSeq + 1;
            }
        }

        $paddedNumber = str_pad((string) $nextNumber, $pad, '0', STR_PAD_LEFT);

        return "{$searchPrefix}{$paddedNumber}";
    }

    /**
     * Khusus SPPB Gudang: SPPB-YYYYMMDD-XXXX (4 digit urut per hari)
     */
    public static function generateSppb(
        string $modelClass = 'App\Models\PengirimanBarang',
        string $column = 'kode'
    ): string {
        return static::generate('SPPB', $modelClass, $column, 'Ymd', 4);
    }

    /**
     * Khusus Permintaan Pengadaan: PR-YYYY-XXX
     */
    public static function generatePr(string $modelClass = 'App\Models\PermintaanPengadaan'): string
    {
        return static::generate('PR', $modelClass, 'kode', 'Y', 3);
    }

    /**
     * Khusus Pesanan Pembelian: PO-YYYY-XXX
     */
    public static function generatePo(string $modelClass = 'App\Models\PesananPembelian'): string
    {
        return static::generate('PO', $modelClass, 'kode', 'Y', 3);
    }

    /**
     * Khusus Penerimaan Distribusi Ruangan: RCV-YYYYMM-XXX
     */
    public static function generateRcv(string $modelClass = 'App\Models\PenerimaanDistribusi'): string
    {
        return static::generate('RCV', $modelClass, 'kode', 'Ym', 3);
    }

    /**
     * Khusus Peminjaman Aset: PINJ-YYYY-XXX
     */
    public static function generatePinj(string $modelClass = 'App\Models\PermintaanPeminjaman'): string
    {
        return static::generate('PINJ', $modelClass, 'kode', 'Y', 3);
    }

    /**
     * Khusus Maintenance IPSRS: MNT-YYYYMM-XXX
     */
    public static function generateMnt(string $modelClass = 'App\Models\PermintaanMaintenance'): string
    {
        return static::generate('MNT', $modelClass, 'kode', 'Ym', 3);
    }

    /**
     * Khusus Temuan Audit SPI: AUD-YYYYMM-XXX
     */
    public static function generateAud(string $modelClass = 'App\Models\TemuanAudit'): string
    {
        return static::generate('AUD', $modelClass, 'kode', 'Ym', 3);
    }
}
