<?php

namespace App\Enums\Aset;

/**
 * Status dokumen permintaan peminjaman aset. Nilai string identik dengan kolom `permintaan_peminjaman.status`.
 */
enum StatusPeminjaman: string
{
    case Diajukan = 'DIAJUKAN';
    case Disetujui = 'DISETUJUI';
    case Dipinjam = 'DIPINJAM';
    case Selesai = 'SELESAI';
    case Ditolak = 'DITOLAK';
    case Dibatalkan = 'DIBATALKAN';

    /** @return list<self> */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Diajukan => [self::Disetujui, self::Ditolak, self::Dibatalkan],
            self::Disetujui => [self::Dipinjam],
            self::Dipinjam => [self::Selesai],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Transaksi yang masih berjalan dan mengunci aset dari pengajuan lain. @return list<string> */
    public static function aktif(): array
    {
        return [self::Diajukan->value, self::Disetujui->value, self::Dipinjam->value];
    }

    /** Transaksi yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Selesai->value, self::Ditolak->value, self::Dibatalkan->value];
    }

    /** Antrean otorisasi: menunggu persetujuan atau serah terima. @return list<string> */
    public static function antreanOtorisasi(): array
    {
        return [self::Diajukan->value, self::Disetujui->value];
    }

    /** Transaksi yang sudah disetujui sehingga aset terikat (konflik persetujuan ganda). @return list<string> */
    public static function mengikatAset(): array
    {
        return [self::Disetujui->value, self::Dipinjam->value];
    }
}
