<?php

namespace Database\Seeders\Master;

use App\Models\Master\Gudang;
use Illuminate\Database\Seeder;

class GudangSeeder extends Seeder
{
    /**
     * Menanam data gudang logistik resmi rumah sakit.
     */
    public function run(): void
    {
        $gudangs = [
            ['kode' => 'GUD_SENTRAL', 'nama' => 'Gudang Sentral Logistik Non-Medis', 'jenis' => 'SENTRAL', 'penanggung_jawab' => 'Hendra Kusuma, S.T.', 'lokasi_gedung' => 'Gedung Logistik Lantai 1'],
            ['kode' => 'GUD_IPSRS', 'nama' => 'Gudang Suku Cadang & Sparepart IPSRS', 'jenis' => 'DEPO', 'penanggung_jawab' => 'Sujono', 'lokasi_gedung' => 'Gedung Penunjang / IPSRS'],
            ['kode' => 'GUD_ATK', 'nama' => 'Gudang ATK & Cetakan Rumah Sakit', 'jenis' => 'SENTRAL', 'penanggung_jawab' => 'Nurul Aini', 'lokasi_gedung' => 'Gedung Administrasi Lantai 1'],
            ['kode' => 'GUD_B3', 'nama' => 'Gudang Bahan Berbahaya & Beracun (B3)', 'jenis' => 'SENTRAL', 'penanggung_jawab' => 'Dedy Kusuma', 'lokasi_gedung' => 'Area Khusus B3', 'status' => 'AKTIF'],
            ['kode' => 'GUD_TRANSIT_LAMA', 'nama' => 'Gudang Transit Depo Lama (Nonaktif)', 'jenis' => 'TRANSIT', 'penanggung_jawab' => '-', 'lokasi_gedung' => 'Gedung Lama', 'status' => 'NONAKTIF'],
        ];

        foreach ($gudangs as $g) {
            Gudang::firstOrCreate(['kode' => $g['kode']], $g);
        }
    }
}
