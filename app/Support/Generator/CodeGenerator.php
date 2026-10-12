<?php

namespace App\Support\Generator;

use Illuminate\Support\Carbon;

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

    protected static array $presets = [
        'sppb' => ['SPPB', 'App\Models\PengirimanBarang', 'kode', 'Ymd', 4],
        'pr'   => ['PR', 'App\Models\PermintaanPengadaan', 'kode', 'Y', 3],
        'po'   => ['PO', 'App\Models\PesananPembelian', 'kode', 'Y', 3],
        'rcv'  => ['RCV', 'App\Models\PenerimaanDistribusi', 'kode', 'Ym', 3],
        'pinj' => ['PINJ', 'App\Models\PermintaanPeminjaman', 'kode', 'Y', 3],
        'mnt'  => ['MNT', 'App\Models\PermintaanMaintenance', 'kode', 'Ym', 3],
        'spk'  => ['SPK', 'App\Models\PenugasanMaintenance', 'kode', 'Ym', 3],
        'prv'  => ['PRV', 'App\Models\JadwalMaintenance', 'kode', 'Ym', 3],
        'opn'  => ['OPN', 'App\Models\StokOpname', 'kode', 'Ym', 3],
        'adj'  => ['ADJ', 'App\Models\PenyesuaianStok', 'kode', 'Ym', 3],
        'aud'  => ['AUD', 'App\Models\TemuanAudit', 'kode', 'Ym', 3],
    ];

    public static function __callStatic(string $name, array $args)
    {
        if (str_starts_with($name, 'generate')) {
            $key = strtolower(substr($name, 8));
            if (isset(static::$presets[$key])) {
                [$prefix, $model, $col, $date, $pad] = static::$presets[$key];
                return static::generate($prefix, $args[0] ?? $model, $args[1] ?? $col, $date, $pad);
            }
        }
        throw new \BadMethodCallException("Method {$name} tidak ditemukan.");
    }
}
