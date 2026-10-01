<?php

namespace Database\Seeders;

use App\Models\Gudang;
use App\Models\KategoriBarang;
use App\Models\LokasiGudang;
use App\Models\MerekBarang;
use App\Models\Pemasok;
use App\Models\SatuanBarang;
use App\Models\Barang;
use App\Models\Aset;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Menanam data master kategori, merek, satuan, pemasok, dan lokasi rak/bin gudang.
     */
    public function run(): void
    {
        // 1. 5 Kategori Barang
        $kategoriList = [
            [
                'kode'      => 'KAT-ATK',
                'nama'      => 'Alat Tulis Kantor & Cetakan',
                'deskripsi' => 'Perlengkapan alat tulis kantor, kertas rekam medis, map, dan formulir administrasi RS.',
                'status'    => 'AKTIF',
            ],
            [
                'kode'      => 'KAT-RT',
                'nama'      => 'Rumah Tangga & Sanitasi',
                'deskripsi' => 'Perlengkapan kebersihan ruangan, deterjen medis, sabun cuci tangan, dan kantong sampah infeksius.',
                'status'    => 'AKTIF',
            ],
            [
                'kode'      => 'KAT-SPK',
                'nama'      => 'Suku Cadang & Sparepart IPSRS',
                'deskripsi' => 'Komponen mekanik, suku cadang alat medis, sensor, filter O2, dan aksesori penunjang fasilitas.',
                'status'    => 'AKTIF',
            ],
            [
                'kode'      => 'KAT-ELK',
                'nama'      => 'Elektronik & Perangkat Komputer',
                'deskripsi' => 'Toner printer, kabel jaringan, UPS, peripheral komputer, dan perangkat IT pendukung SIMRS.',
                'status'    => 'AKTIF',
            ],
            [
                'kode'      => 'KAT-LIN',
                'nama'      => 'Linen & Tekstil Fasilitas',
                'deskripsi' => 'Sprei bed pasien, selimut rawat inap, sarung bantal, dan tirai pembatas ruangan tindakan.',
                'status'    => 'AKTIF',
            ],
        ];

        foreach ($kategoriList as $kat) {
            KategoriBarang::firstOrCreate(['kode' => $kat['kode']], $kat);
        }

        // 2. 5 Merek Barang
        $merekList = [
            ['kode' => 'MRK-SIDU', 'nama' => 'Sinar Dunia (SiDU)', 'status' => 'AKTIF'],
            ['kode' => 'MRK-YURI', 'nama' => 'Yuri Medika', 'status' => 'AKTIF'],
            ['kode' => 'MRK-HMLT', 'nama' => 'Hamilton Medical', 'status' => 'AKTIF'],
            ['kode' => 'MRK-EPSN', 'nama' => 'Epson', 'status' => 'AKTIF'],
            ['kode' => 'MRK-PARAM', 'nama' => 'Paramount Bed', 'status' => 'AKTIF'],
        ];

        foreach ($merekList as $mrk) {
            MerekBarang::firstOrCreate(['kode' => $mrk['kode']], $mrk);
        }

        // 3. 5 Satuan Barang Standar
        $satuanList = [
            ['kode' => 'SAT-PCS', 'nama' => 'Pieces', 'simbol' => 'Pcs', 'status' => 'AKTIF'],
            ['kode' => 'SAT-BOX', 'nama' => 'Box / Kotak', 'simbol' => 'Box', 'status' => 'AKTIF'],
            ['kode' => 'SAT-RIM', 'nama' => 'Rim (500 Lembar)', 'simbol' => 'Rim', 'status' => 'AKTIF'],
            ['kode' => 'SAT-BTL', 'nama' => 'Botol Cairan', 'simbol' => 'Btl', 'status' => 'AKTIF'],
            ['kode' => 'SAT-ROL', 'nama' => 'Gulung / Roll', 'simbol' => 'Rol', 'status' => 'AKTIF'],
        ];

        foreach ($satuanList as $sat) {
            SatuanBarang::firstOrCreate(['kode' => $sat['kode']], $sat);
        }

        // 4. 5 Pemasok / Rekanan Vendor RS
        $pemasokList = [
            [
                'kode'        => 'VND-2026-001',
                'nama'        => 'PT Medika Sarana Abadi',
                'npwp'        => '01.234.567.8-012.000',
                'alamat'      => 'Jl. Jenderal Sudirman Kav. 45-46, Karet Semanggi',
                'kota'        => 'Jakarta Selatan',
                'no_telepon'  => '021-5551234',
                'email'       => 'sales@medikasarana.co.id',
                'nama_kontak' => 'Hendro Wibowo, S.T.',
                'status'      => 'AKTIF',
            ],
            [
                'kode'        => 'VND-2026-002',
                'nama'        => 'CV Graha Logistik Medis',
                'npwp'        => '02.345.678.9-023.000',
                'alamat'      => 'Jl. Gatot Subroto No. 88, Lengkong',
                'kota'        => 'Bandung',
                'no_telepon'  => '022-7772345',
                'email'       => 'kontak@grahalogistik.com',
                'nama_kontak' => 'Anita Rahmawati, S.Farm',
                'status'      => 'AKTIF',
            ],
            [
                'kode'        => 'VND-2026-003',
                'nama'        => 'PT Global Sarana Tehnik',
                'npwp'        => '03.456.789.0-034.000',
                'alamat'      => 'Jl. Pemuda No. 12, Pandansari',
                'kota'        => 'Semarang',
                'no_telepon'  => '024-8883456',
                'email'       => 'info@globalsarana.id',
                'nama_kontak' => 'Rian Firmansyah, A.Md.T.',
                'status'      => 'AKTIF',
            ],
            [
                'kode'        => 'VND-2026-004',
                'nama'        => 'PT Sumber Alam Kertasindo',
                'npwp'        => '04.567.890.1-045.000',
                'alamat'      => 'Kawasan Rungkut Industri Blok E-20',
                'kota'        => 'Surabaya',
                'no_telepon'  => '031-9994567',
                'email'       => 'order@sumberkertas.co.id',
                'nama_kontak' => 'David Santoso',
                'status'      => 'AKTIF',
            ],
            [
                'kode'        => 'VND-2026-005',
                'nama'        => 'CV Sinar Chemindo Higienis',
                'npwp'        => '05.678.901.2-056.000',
                'alamat'      => 'Jl. Kaliurang Km. 8, Sinduharjo, Ngaglik',
                'kota'        => 'Sleman (D.I. Yogyakarta)',
                'no_telepon'  => '0274-6665678',
                'email'       => 'chemindo@sinarchem.com',
                'nama_kontak' => 'Sri Wahyuni, S.Si',
                'status'      => 'AKTIF',
            ],
        ];

        foreach ($pemasokList as $pem) {
            Pemasok::firstOrCreate(['kode' => $pem['kode']], $pem);
        }

        // 5. 5 Lokasi Rak/Bin Gudang Sentral
        $gudangSentral = Gudang::where('kode', 'GUD_SENTRAL')->first() ?? Gudang::first();

        if ($gudangSentral) {
            $rakList = [
                [
                    'gudang_id' => $gudangSentral->id,
                    'kode'      => 'RAK-A1',
                    'nama'      => 'Rak A1 - Kertas HVS & Formulir Cetak',
                    'baris'     => 'A',
                    'kolom'     => '1',
                    'level'     => '1',
                    'status'    => 'AKTIF',
                ],
                [
                    'gudang_id' => $gudangSentral->id,
                    'kode'      => 'RAK-A2',
                    'nama'      => 'Rak A2 - ATK, Map & Perlengkapan Meja',
                    'baris'     => 'A',
                    'kolom'     => '2',
                    'level'     => '2',
                    'status'    => 'AKTIF',
                ],
                [
                    'gudang_id' => $gudangSentral->id,
                    'kode'      => 'RAK-B1',
                    'nama'      => 'Rak B1 - Linen Medis & Sprei Pasien',
                    'baris'     => 'B',
                    'kolom'     => '1',
                    'level'     => '1',
                    'status'    => 'AKTIF',
                ],
                [
                    'gudang_id' => $gudangSentral->id,
                    'kode'      => 'RAK-B2',
                    'nama'      => 'Rak B2 - Sabun Antiseptik & Desinfektan',
                    'baris'     => 'B',
                    'kolom'     => '2',
                    'level'     => '1',
                    'status'    => 'AKTIF',
                ],
                [
                    'gudang_id' => $gudangSentral->id,
                    'kode'      => 'BIN-C1',
                    'nama'      => 'Bin C1 - Sparepart Cepat Gerak & Sensor',
                    'baris'     => 'C',
                    'kolom'     => '1',
                    'level'     => '3',
                    'status'    => 'AKTIF',
                ],
            ];

            foreach ($rakList as $rak) {
                LokasiGudang::firstOrCreate(
                    [
                        'gudang_id' => $rak['gudang_id'],
                        'kode'      => $rak['kode'],
                    ],
                    $rak
                );
            }
        }

        // 6. Master Barang Standar
        $katAtk = KategoriBarang::where('kode', 'KAT-ATK')->value('id') ?? 1;
        $katElk = KategoriBarang::where('kode', 'KAT-ELK')->value('id') ?? 4;
        $katSpk = KategoriBarang::where('kode', 'KAT-SPK')->value('id') ?? 3;
        $satRim = SatuanBarang::where('kode', 'SAT-RIM')->value('id') ?? 3;
        $satPcs = SatuanBarang::where('kode', 'SAT-PCS')->value('id') ?? 1;
        $satBtl = SatuanBarang::where('kode', 'SAT-BTL')->value('id') ?? 4;
        $mrkSidu = MerekBarang::where('kode', 'MRK-SIDU')->value('id') ?? 1;
        $mrkEpsn = MerekBarang::where('kode', 'MRK-EPSN')->value('id') ?? 4;

        $barangList = [
            [
                'kode'                => 'BRG-2026-001',
                'nama'                => 'HVS A4',
                'tipe'                => 'HABIS_PAKAI',
                'kategori_id'         => $katAtk,
                'satuan_id'           => $satRim,
                'merek_id'            => $mrkSidu,
                'stok_minimum'        => 20,
                'stok_maksimum'       => 100,
                'titik_pesan'         => 30,
                'harga_beli_terakhir' => 60000.00,
                'harga_estimasi'      => 70000.00,
                'status'              => 'AKTIF',
            ],
            [
                'kode'                => 'BRG-2026-002',
                'nama'                => 'Printer',
                'tipe'                => 'ASET',
                'kategori_id'         => $katElk,
                'satuan_id'           => $satPcs,
                'merek_id'            => $mrkEpsn,
                'stok_minimum'        => 2,
                'stok_maksimum'       => 10,
                'titik_pesan'         => 3,
                'harga_beli_terakhir' => 2000000.00,
                'harga_estimasi'      => 3000000.00,
                'status'              => 'AKTIF',
            ],
            [
                'kode'                => 'BRG-2026-003',
                'nama'                => 'Freon AC',
                'tipe'                => 'SPAREPART',
                'kategori_id'         => $katSpk,
                'satuan_id'           => $satBtl,
                'merek_id'            => null,
                'stok_minimum'        => 5,
                'stok_maksimum'       => 20,
                'titik_pesan'         => 8,
                'harga_beli_terakhir' => 150000.00,
                'harga_estimasi'      => 180000.00,
                'status'              => 'AKTIF',
            ],
        ];

        foreach ($barangList as $b) {
            Barang::firstOrCreate(['kode' => $b['kode']], $b);
        }

        // 7. Register Aset Fisik Awal
        $printer = Barang::where('kode', 'BRG-2026-002')->first();
        $unitIgd = Unit::where('kode', 'IGD')->value('id');
        $unitDireksi = Unit::where('kode', 'DIREKSI')->value('id');
        $unitSpi = Unit::where('kode', 'SPI')->value('id');

        if ($printer) {
            $asetList = [
                [
                    'kode_aset'         => 'AST-2026-001',
                    'nomor_seri'        => 'PRINTER-IGD-1',
                    'barang_id'         => $printer->id,
                    'unit_id'           => $unitIgd,
                    'tanggal_perolehan' => '2026-01-15',
                    'nilai_perolehan'   => 2000000.00,
                    'kondisi'           => 'BAIK',
                    'status'            => 'TERSEDIA',
                ],
                [
                    'kode_aset'         => 'AST-2026-002',
                    'nomor_seri'        => 'PRINTER-IGD-2',
                    'barang_id'         => $printer->id,
                    'unit_id'           => $unitIgd,
                    'tanggal_perolehan' => '2026-02-10',
                    'nilai_perolehan'   => 2000000.00,
                    'kondisi'           => 'BAIK',
                    'status'            => 'TERSEDIA',
                ],
                [
                    'kode_aset'         => 'AST-2026-003',
                    'nomor_seri'        => 'PRINTER-MJN-1',
                    'barang_id'         => $printer->id,
                    'unit_id'           => $unitDireksi,
                    'tanggal_perolehan' => '2026-01-20',
                    'nilai_perolehan'   => 2000000.00,
                    'kondisi'           => 'BAIK',
                    'status'            => 'TERSEDIA',
                ],
                [
                    'kode_aset'         => 'AST-2026-004',
                    'nomor_seri'        => 'PRINTER-AUD-1',
                    'barang_id'         => $printer->id,
                    'unit_id'           => $unitSpi,
                    'tanggal_perolehan' => '2026-02-05',
                    'nilai_perolehan'   => 2000000.00,
                    'kondisi'           => 'BAIK',
                    'status'            => 'TERSEDIA',
                ],
            ];

            foreach ($asetList as $ast) {
                Aset::firstOrCreate(['kode_aset' => $ast['kode_aset']], $ast);
            }
        }
    }
}
