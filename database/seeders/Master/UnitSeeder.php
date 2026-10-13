<?php

namespace Database\Seeders\Master;

use App\Models\Master\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Menanam data unit kerja resmi rumah sakit.
     */
    public function run(): void
    {
        $units = [
            ['kode' => 'IGD', 'nama' => 'Instalasi Gawat Darurat', 'jenis' => 'PELAYANAN_MEDIS', 'penanggung_jawab' => 'dr. Pratama, Sp.EM', 'lokasi_gedung' => 'Gedung A Lantai 1'],
            ['kode' => 'IPSRS', 'nama' => 'Instalasi Pemeliharaan Sarana Rumah Sakit', 'jenis' => 'OPERASIONAL', 'penanggung_jawab' => 'Ir. Gunawan, S.T.', 'lokasi_gedung' => 'Gedung Penunjang'],
            ['kode' => 'ICU', 'nama' => 'Intensive Care Unit', 'jenis' => 'PELAYANAN_MEDIS', 'penanggung_jawab' => 'dr. Handayani, Sp.An-KIC', 'lokasi_gedung' => 'Gedung A Lantai 2'],
            ['kode' => 'RAWAT_1', 'nama' => 'Instalasi Rawat Inap Teratai', 'jenis' => 'PELAYANAN_MEDIS', 'penanggung_jawab' => 'Ns. Ratna, S.Kep', 'lokasi_gedung' => 'Gedung B Lantai 2'],
            ['kode' => 'FARMASI', 'nama' => 'Instalasi Farmasi Sentral', 'jenis' => 'PENUNJANG_MEDIS', 'penanggung_jawab' => 'Apt. Wulandari, S.Farm', 'lokasi_gedung' => 'Gedung A Lantai 1'],
            ['kode' => 'LAB', 'nama' => 'Laboratorium Patologi Klinik', 'jenis' => 'PENUNJANG_MEDIS', 'penanggung_jawab' => 'dr. Siregar, Sp.PK', 'lokasi_gedung' => 'Gedung A Lantai 2'],
            ['kode' => 'LOGISTIK', 'nama' => 'Bagian Logistik & Pengadaan', 'jenis' => 'ADMINISTRASI', 'penanggung_jawab' => 'Drs. Hendrawan, M.M.', 'lokasi_gedung' => 'Gedung Administrasi Lantai 2'],
            ['kode' => 'DIREKSI', 'nama' => 'Direktorat & Manajemen Eksekutif', 'jenis' => 'ADMINISTRASI', 'penanggung_jawab' => 'Drs. H. Mulyono, M.Kes', 'lokasi_gedung' => 'Gedung Administrasi Lantai 3'],
            ['kode' => 'SPI', 'nama' => 'Satuan Pengawas Internal (SPI)', 'jenis' => 'ADMINISTRASI', 'penanggung_jawab' => 'Rina Kartika, S.E., Ak., CA', 'lokasi_gedung' => 'Gedung Administrasi Lantai 2', 'status' => 'AKTIF'],
            ['kode' => 'ISOLASI_COVID', 'nama' => 'Unit Pelayanan Isolasi Khusus Pandemi (Nonaktif)', 'jenis' => 'PELAYANAN_MEDIS', 'penanggung_jawab' => '-', 'lokasi_gedung' => 'Gedung Karantina Lama', 'status' => 'NONAKTIF'],
        ];

        foreach ($units as $u) {
            Unit::firstOrCreate(['kode' => $u['kode']], $u);
        }
    }
}
