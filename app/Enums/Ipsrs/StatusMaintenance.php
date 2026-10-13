<?php

namespace App\Enums\Ipsrs;

/**
 * Status tiket permintaan maintenance (`permintaan_maintenance.status`). Nilai string identik dengan kolom.
 */
enum StatusMaintenance: string
{
    case Dilaporkan = 'DILAPORKAN';
    case Ditugaskan = 'DITUGASKAN';
    case SedangDikerjakan = 'SEDANG_DIKERJAKAN';
    case MenungguSparepart = 'MENUNGGU_SPAREPART';
    case UjiFungsi = 'UJI_FUNGSI';
    case Selesai = 'SELESAI';
    case Dibatalkan = 'DIBATALKAN';

    /** Nilai lama yang masih diterima sebagai antrean penugasan, tetapi tidak pernah ditulis lagi oleh kode. */
    case MenungguSpk = 'MENUNGGU_SPK';

    /** @return list<self> */
    public function transisiSah(): array
    {
        // Lembar kerja teknisi menentukan status tiket dari hasil pengerjaan, termasuk pengisian ulang.
        $hasilPengerjaan = [self::SedangDikerjakan, self::MenungguSparepart, self::UjiFungsi, self::Selesai];

        return match ($this) {
            self::Dilaporkan => [self::Ditugaskan, self::Dibatalkan],
            self::MenungguSpk => [self::Ditugaskan],
            self::Ditugaskan, self::SedangDikerjakan, self::MenungguSparepart, self::UjiFungsi => $hasilPengerjaan,
            default => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** Tiket yang boleh diterbitkan SPK. @return list<string> */
    public static function menungguPenugasan(): array
    {
        return [self::Dilaporkan->value, self::MenungguSpk->value];
    }

    /** Tiket yang masih dalam penanganan. @return list<string> */
    public static function aktif(): array
    {
        return [
            self::Dilaporkan->value,
            self::Ditugaskan->value,
            self::SedangDikerjakan->value,
            self::MenungguSparepart->value,
            self::UjiFungsi->value,
        ];
    }

    /** Tiket yang sudah final. @return list<string> */
    public static function riwayat(): array
    {
        return [self::Selesai->value, self::Dibatalkan->value];
    }
}
