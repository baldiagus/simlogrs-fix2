<?php

namespace App\Enums;

/**
 * Status Permintaan Pengadaan (PR). Nilai string identik dengan kolom `permintaan_pengadaan.status`.
 *
 * DISETUJUI tidak lagi ditulis oleh kode mana pun, tetapi masih diterima sebagai status asal
 * (data lama) oleh aksi teruskan dan penerbitan PO via API.
 */
enum StatusPermintaanPengadaan: string
{
    case Diajukan = 'DIAJUKAN';
    case Disetujui = 'DISETUJUI';
    case DiteruskanKePengadaan = 'DITERUSKAN_KE_PENGADAAN';
    case Diproses = 'DIPROSES';
    case Selesai = 'SELESAI';
    case Ditolak = 'DITOLAK';

    /**
     * Transisi yang dijalankan kode saat ini. Self-loop dicantumkan bila kode memang
     * mengizinkannya (teruskan ulang, penerbitan PO tambahan dari PR yang sudah DIPROSES).
     * Penolakan hanya dari status non-final sebelum diproses: DIAJUKAN, DISETUJUI, DITERUSKAN_KE_PENGADAAN (ADR 0002 A7).
     *
     * @return list<self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Diajukan => [self::DiteruskanKePengadaan, self::Ditolak],
            self::Disetujui => [self::DiteruskanKePengadaan, self::Diproses, self::Ditolak],
            self::DiteruskanKePengadaan => [self::DiteruskanKePengadaan, self::Diproses, self::Ditolak],
            self::Diproses => [self::Diproses, self::Selesai],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Status PR yang boleh diterbitkan PO lewat API. @return list<string> */
    public static function siapPo(): array
    {
        return [self::DiteruskanKePengadaan->value, self::Diproses->value, self::Disetujui->value];
    }

    /** Status PR yang masih berjalan. @return list<string> */
    public static function aktif(): array
    {
        return [self::Diajukan->value, self::Disetujui->value, self::DiteruskanKePengadaan->value, self::Diproses->value];
    }

    /** Status PR yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Selesai->value, self::Ditolak->value];
    }
}
