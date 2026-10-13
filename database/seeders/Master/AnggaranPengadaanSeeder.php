<?php

namespace Database\Seeders\Master;

use App\Models\Master\AnggaranPengadaan;
use App\Models\Master\Unit;
use Illuminate\Database\Seeder;

class AnggaranPengadaanSeeder extends Seeder
{
    /**
     * Menanam data pagu anggaran operasional per unit ruangan dan pos suku cadang IPSRS TA 2026.
     */
    public function run(): void
    {
        AnggaranPengadaan::truncate();

        $units = Unit::all()->keyBy('kode');

        $dataAnggaran = [
            // 1. Pagu Operasional Logistik Per Unit Ruangan (Cost Center)
            [
                'kode'         => 'PAGU-2026-IGD',
                'pos_anggaran' => 'Pagu Operasional Logistik - Instalasi Gawat Darurat (IGD)',
                'unit_id'      => $units->get('IGD')?->id,
                'tipe'         => 'UNIT',
                'pagu'         => 350000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 350000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-ICU',
                'pos_anggaran' => 'Pagu Operasional Logistik - Intensive Care Unit (ICU)',
                'unit_id'      => $units->get('ICU')?->id,
                'tipe'         => 'UNIT',
                'pagu'         => 400000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 400000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-RAWAT1',
                'pos_anggaran' => 'Pagu Operasional Logistik - Rawat Inap Teratai',
                'unit_id'      => $units->get('RAWAT_1')?->id,
                'tipe'         => 'UNIT',
                'pagu'         => 500000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 500000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-LAB',
                'pos_anggaran' => 'Pagu Operasional Logistik - Laboratorium Patologi Klinik',
                'unit_id'      => $units->get('LAB')?->id,
                'tipe'         => 'UNIT',
                'pagu'         => 250000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 250000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-FARMASI',
                'pos_anggaran' => 'Pagu Operasional Logistik - Instalasi Farmasi Sentral',
                'unit_id'      => $units->get('FARMASI')?->id,
                'tipe'         => 'UNIT',
                'pagu'         => 200000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 200000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
            [
                'kode'         => 'PAGU-2026-LOGISTIK',
                'pos_anggaran' => 'Pagu Logistik Rumah Tangga & Administrasi Kantor',
                'unit_id'      => $units->get('LOGISTIK')?->id,
                'tipe'         => 'UNIT',
                'pagu'         => 150000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 150000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],

            // 2. Pos Khusus: Pagu Suku Cadang & Pemeliharaan Sarana IPSRS
            [
                'kode'         => 'PAGU-2026-IPSRS-SPAREPART',
                'pos_anggaran' => 'Plafon Anggaran Suku Cadang & Pemeliharaan Sarana IPSRS',
                'unit_id'      => $units->get('IPSRS')?->id,
                'tipe'         => 'SPAREPART_IPSRS',
                'pagu'         => 650000000.00,
                'realisasi'    => 0.00,
                'sisa'         => 650000000.00,
                'serapan'      => 0.00,
                'tahun'        => '2026',
                'status'       => 'Normal',
                'po_terkait'   => [],
            ],
        ];

        foreach ($dataAnggaran as $item) {
            AnggaranPengadaan::create($item);
        }
    }
}
