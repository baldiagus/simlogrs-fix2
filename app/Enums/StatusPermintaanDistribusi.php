<?php

namespace App\Enums;

/**
 * Status permintaan distribusi (amprah). Nilai string identik dengan kolom `permintaan_distribusi.status`.
 */
enum StatusPermintaanDistribusi: string
{
    case Diajukan = 'DIAJUKAN';
    case DisetujuiKaru = 'DISETUJUI_KARU';
    case DisetujuiGudang = 'DISETUJUI_GUDANG';
    case SedangDikirim = 'SEDANG_DIKIRIM';
    case DikirimSebagian = 'DIKIRIM_SEBAGIAN';
    case Selesai = 'SELESAI';
    case Ditolak = 'DITOLAK';

    /**
     * Transisi yang saat ini diizinkan kode. Pembatalan oleh pemohon menulis DITOLAK.
     * Picking FEFO (service/API) juga menerima DISETUJUI_KARU, dan picking/penerimaan
     * berulang dapat menulis ulang DIKIRIM_SEBAGIAN.
     *
     * @return list<self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Diajukan => [self::DisetujuiKaru, self::Ditolak],
            self::DisetujuiKaru => [self::DisetujuiGudang, self::Ditolak, self::SedangDikirim, self::DikirimSebagian],
            self::DisetujuiGudang => [self::SedangDikirim, self::DikirimSebagian],
            self::SedangDikirim => [self::Selesai, self::DikirimSebagian],
            self::DikirimSebagian => [self::SedangDikirim, self::DikirimSebagian, self::Selesai],
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Status permintaan yang masih berjalan. @return list<string> */
    public static function aktif(): array
    {
        return [
            self::Diajukan->value,
            self::DisetujuiKaru->value,
            self::DisetujuiGudang->value,
            self::SedangDikirim->value,
            self::DikirimSebagian->value,
        ];
    }

    /** Status permintaan yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Selesai->value, self::Ditolak->value];
    }

    /** Status yang masih boleh dibatalkan pemohon. @return list<string> */
    public static function dapatDibatalkan(): array
    {
        return [self::Diajukan->value, self::DisetujuiKaru->value];
    }

    /** Status antrean picking di layar gudang. @return list<string> */
    public static function siapPicking(): array
    {
        return [self::DisetujuiGudang->value, self::DikirimSebagian->value];
    }

    /** Status yang diterima eksekusi picking FEFO (service dan API). @return list<string> */
    public static function dapatDikirim(): array
    {
        return [self::DisetujuiGudang->value, self::DikirimSebagian->value, self::DisetujuiKaru->value];
    }
}
