<?php

namespace Database\Seeders;

use App\Models\AnggaranPengadaan;
use Illuminate\Database\Seeder;

class AnggaranPengadaanSeeder extends Seeder
{
    /**
     * Menanam data pagu anggaran belanja logistik resmi Tahun Anggaran 2026.
     */
    public function run(): void
    {
        $posAnggaran = [
            [
                'kode'         => 'PAGU-2026-ATK',
                'pos_anggaran' => 'Alat Tulis Kantor & Keperluan Administrasi',
                'pagu'         => 250000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 250000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-CETAK',
                'pos_anggaran' => 'Cetakan & Formulir Medis / Non-Medis',
                'pagu'         => 150000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 150000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-LINEN',
                'pos_anggaran' => 'Linen, Seragam & Tekstil Rumah Sakit',
                'pagu'         => 300000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 300000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-BERSIH',
                'pos_anggaran' => 'Bahan Pembersih & Sanitasi Lingkungan',
                'pagu'         => 200000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 200000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-IPSRS',
                'pos_anggaran' => 'Suku Cadang & Pemeliharaan Sarana IPSRS',
                'pagu'         => 500000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 500000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
        ];

        foreach ($posAnggaran as $item) {
            AnggaranPengadaan::firstOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
