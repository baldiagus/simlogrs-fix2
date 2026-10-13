<?php

namespace App\Enums\Distribusi;

/**
 * Status pengiriman barang (SPPB). Nilai string identik dengan kolom `pengiriman_barang.status`.
 */
enum StatusPengirimanBarang: string
{
    case Dikirim = 'DIKIRIM';
    case Diterima = 'DITERIMA';
    case Batal = 'BATAL';

    /**
     * BATAL belum memiliki alur penulisan di kode (hanya data seed), sehingga tidak ada transisi ke sana.
     *
     * @return list<self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Dikirim => [self::Diterima],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** SPPB yang masih dalam perjalanan. @return list<string> */
    public static function aktif(): array
    {
        return [self::Dikirim->value];
    }

    /** SPPB yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Diterima->value, self::Batal->value];
    }
}
