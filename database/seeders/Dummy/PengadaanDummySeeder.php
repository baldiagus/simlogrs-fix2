<?php

namespace Database\Seeders\Dummy;

use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\LokasiGudang;
use App\Models\Master\Pemasok;
use App\Models\Pengadaan\PenerimaanBarang;
use App\Models\Pengadaan\PenerimaanBarangDetail;
use App\Models\Persediaan\PergerakanStok;
use App\Models\Pengadaan\PermintaanPengadaan;
use App\Models\Pengadaan\PermintaanPengadaanDetail;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Pengadaan\PesananPembelianDetail;
use App\Models\Persediaan\StokBatch;
use App\Models\Master\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PengadaanDummySeeder extends Seeder
{
    /**
     * Menanam data historis siklus pengadaan (PR -> PO -> Inbound RCV -> Stok Batch Gudang)
     * yang tersebar dalam beberapa bulan ke belakang (Mei 2026 - September 2026).
     */
    public function run(): void
    {
        $adminPengadaan = User::where('email', 'adminlogistik@simlogrs.local')->first() ?? User::first();
        $petugasGudang = User::where('email', 'petugasgudang@simlogrs.local')->first() ?? User::first();
        $gudangSentral = Gudang::where('kode', 'GUD_SENTRAL')->first() ?? Gudang::first();
        $gudangIpsrs   = Gudang::where('kode', 'GUD_IPSRS')->first() ?? $gudangSentral;
        $gudangAtk     = Gudang::where('kode', 'GUD_ATK')->first() ?? $gudangSentral;

        $units = Unit::all()->keyBy('kode');
        $pemasoks = Pemasok::all()->keyBy('kode');
        $barangs = Barang::all()->keyBy('kode');
        $raks = LokasiGudang::all()->keyBy('kode');

        // Daftar paket pengadaan bulanan (Mei - September 2026)
        $procurementBatches = [
            // BULAN 1: MEI 2026
            [
                'pr_kode'     => 'PR-2026-101',
                'unit_kode'   => 'IGD',
                'tgl_pr'      => '2026-05-04',
                'urgensi'     => 'MENDESAK',
                'catatan_pr'  => 'Pengadaan APD dan desinfektan penunjang ruang isolasi & triase IGD triwulan II.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-013', 'qty' => 50, 'harga' => 28000],  // Masker 3-Ply
                    ['kode' => 'BRG-2026-015', 'qty' => 40, 'harga' => 45000],  // Sarung Tangan Non-Steril
                    ['kode' => 'BRG-2026-009', 'qty' => 30, 'harga' => 32000],  // Hand Sanitizer 500ml
                ],
                'po' => [
                    'kode'         => 'PO-2026-101',
                    'vendor'       => 'VND-2026-016', // OneMed
                    'tgl_po'       => '2026-05-06',
                    'est_kirim'    => '2026-05-12',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202605-001',
                        'tgl_rcv'   => '2026-05-11',
                        'no_sj'     => 'SJ-OM-2026/05/889',
                        'gudang'    => $gudangSentral,
                        'batches'   => [
                            ['brg' => 'BRG-2026-013', 'rak' => 'RAK-B3', 'batch' => 'BATCH-OM-2605A', 'exp' => '2028-05-01', 'qty' => 50],
                            ['brg' => 'BRG-2026-015', 'rak' => 'RAK-B4', 'batch' => 'BATCH-OM-2605B', 'exp' => '2028-05-01', 'qty' => 40],
                            ['brg' => 'BRG-2026-009', 'rak' => 'RAK-B2', 'batch' => 'BATCH-OM-2605C', 'exp' => '2027-11-01', 'qty' => 30],
                        ],
                    ],
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-102',
                'unit_kode'   => 'IPSRS',
                'tgl_pr'      => '2026-05-15',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Stok suku cadang pemeliharaan pendingin ruang dan sensor ventilator.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-003', 'qty' => 6, 'harga' => 150000],   // Freon R32
                    ['kode' => 'BRG-2026-042', 'qty' => 10, 'harga' => 65000],  // Kapasitor AC
                ],
                'po' => [
                    'kode'         => 'PO-2026-102',
                    'vendor'       => 'VND-2026-018', // Daikin
                    'tgl_po'       => '2026-05-18',
                    'est_kirim'    => '2026-05-25',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202605-002',
                        'tgl_rcv'   => '2026-05-23',
                        'no_sj'     => 'SJ-DAIKIN-V591',
                        'gudang'    => $gudangIpsrs,
                        'batches'   => [
                            ['brg' => 'BRG-2026-003', 'rak' => 'RAK-C5', 'batch' => 'BATCH-DK-2605', 'exp' => '2030-01-01', 'qty' => 6],
                            ['brg' => 'BRG-2026-042', 'rak' => 'RAK-C6', 'batch' => 'BATCH-DK-2605K', 'exp' => null, 'qty' => 10],
                        ],
                    ],
                ],
            ],

            // BULAN 2: JUNI 2026
            [
                'pr_kode'     => 'PR-2026-103',
                'unit_kode'   => 'LOGISTIK',
                'tgl_pr'      => '2026-06-02',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Pengadaan berkala kertas rekam medis dan formulir administrasi kantor.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-001', 'qty' => 60, 'harga' => 48000],  // HVS A4
                    ['kode' => 'BRG-2026-004', 'qty' => 40, 'harga' => 52000],  // HVS F4
                    ['kode' => 'BRG-2026-006', 'qty' => 20, 'harga' => 28000],  // Pulpen Joyko
                ],
                'po' => [
                    'kode'         => 'PO-2026-103',
                    'vendor'       => 'VND-2026-004', // Sumber Alam Kertasindo
                    'tgl_po'       => '2026-06-04',
                    'est_kirim'    => '2026-06-10',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202606-001',
                        'tgl_rcv'   => '2026-06-09',
                        'no_sj'     => 'SJ-SAK-0622',
                        'gudang'    => $gudangAtk,
                        'batches'   => [
                            ['brg' => 'BRG-2026-001', 'rak' => 'RAK-A1', 'batch' => 'BATCH-HVS-2606', 'exp' => null, 'qty' => 60],
                            ['brg' => 'BRG-2026-004', 'rak' => 'RAK-A1', 'batch' => 'BATCH-F4-2606',  'exp' => null, 'qty' => 40],
                            ['brg' => 'BRG-2026-006', 'rak' => 'RAK-A2', 'batch' => 'BATCH-PEN-2606', 'exp' => null, 'qty' => 20],
                        ],
                    ],
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-104',
                'unit_kode'   => 'RAWAT_1',
                'tgl_pr'      => '2026-06-16',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Penggantian linen kotor dan penambahan sprei pasien rawat inap Teratai.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-026', 'qty' => 30, 'harga' => 75000],  // Sprei Bed
                    ['kode' => 'BRG-2026-027', 'qty' => 40, 'harga' => 22000],  // Sarung Bantal
                ],
                'po' => [
                    'kode'         => 'PO-2026-104',
                    'vendor'       => 'VND-2026-012', // Nusantara Linen
                    'tgl_po'       => '2026-06-19',
                    'est_kirim'    => '2026-06-27',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202606-002',
                        'tgl_rcv'   => '2026-06-26',
                        'no_sj'     => 'SJ-NLS-26068',
                        'gudang'    => $gudangSentral,
                        'batches'   => [
                            ['brg' => 'BRG-2026-026', 'rak' => 'RAK-B1', 'batch' => 'BATCH-LIN-2606', 'exp' => null, 'qty' => 30],
                            ['brg' => 'BRG-2026-027', 'rak' => 'RAK-B1', 'batch' => 'BATCH-PIL-2606', 'exp' => null, 'qty' => 40],
                        ],
                    ],
                ],
            ],

            // BULAN 3: JULI 2026
            [
                'pr_kode'     => 'PR-2026-105',
                'unit_kode'   => 'ICU',
                'tgl_pr'      => '2026-07-03',
                'urgensi'     => 'CITO',
                'catatan_pr'  => 'Kebutuhan darurat sarung tangan steril bedah dan kasa steril ruang perawatan intensif.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-016', 'qty' => 100, 'harga' => 12500], // Sarung Tangan Steril
                    ['kode' => 'BRG-2026-017', 'qty' => 30,  'harga' => 14000], // Kasa Steril 16x16
                    ['kode' => 'BRG-2026-019', 'qty' => 25,  'harga' => 24000], // Plester Micropore 3M
                ],
                'po' => [
                    'kode'         => 'PO-2026-105',
                    'vendor'       => 'VND-2026-007', // Terumo Indonesia
                    'tgl_po'       => '2026-07-04',
                    'est_kirim'    => '2026-07-08',
                    'syarat'       => 'CASH',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202607-001',
                        'tgl_rcv'   => '2026-07-07',
                        'no_sj'     => 'SJ-TRM-26071',
                        'gudang'    => $gudangSentral,
                        'batches'   => [
                            ['brg' => 'BRG-2026-016', 'rak' => 'RAK-B4', 'batch' => 'BATCH-GLV-2607', 'exp' => '2028-12-31', 'qty' => 100],
                            ['brg' => 'BRG-2026-017', 'rak' => 'RAK-B5', 'batch' => 'BATCH-KSA-2607', 'exp' => '2029-06-30', 'qty' => 30],
                            ['brg' => 'BRG-2026-019', 'rak' => 'RAK-B5', 'batch' => 'BATCH-PLS-2607', 'exp' => '2029-01-31', 'qty' => 25],
                        ],
                    ],
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-106',
                'unit_kode'   => 'FARMASI',
                'tgl_pr'      => '2026-07-14',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Deterjen medis dan desinfektan galon penunjang higienitas ruang steril farmasi.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-010', 'qty' => 15, 'harga' => 95000],  // Sabun Medis 5L
                    ['kode' => 'BRG-2026-021', 'qty' => 10, 'harga' => 125000], // Alkohol 70% 5L
                ],
                'po' => [
                    'kode'         => 'PO-2026-106',
                    'vendor'       => 'VND-2026-005', // Sinar Chemindo
                    'tgl_po'       => '2026-07-16',
                    'est_kirim'    => '2026-07-22',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202607-002',
                        'tgl_rcv'   => '2026-07-21',
                        'no_sj'     => 'SJ-SCH-2607-44',
                        'gudang'    => $gudangSentral,
                        'batches'   => [
                            ['brg' => 'BRG-2026-010', 'rak' => 'RAK-B2', 'batch' => 'BATCH-SBN-2607', 'exp' => '2028-06-30', 'qty' => 15],
                            ['brg' => 'BRG-2026-021', 'rak' => 'RAK-B2', 'batch' => 'BATCH-ALK-2607', 'exp' => '2028-06-30', 'qty' => 10],
                        ],
                    ],
                ],
            ],

            // BULAN 4: AGUSTUS 2026
            [
                'pr_kode'     => 'PR-2026-107',
                'unit_kode'   => 'LAB',
                'tgl_pr'      => '2026-08-05',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Kebutuhan kantong sampah biohazard kuning dan wadah safety box jarum suntik.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-028', 'qty' => 25, 'harga' => 42000],  // Kantong Biohazard
                    ['kode' => 'BRG-2026-029', 'qty' => 20, 'harga' => 18500],  // Safety Box 5L
                ],
                'po' => [
                    'kode'         => 'PO-2026-107',
                    'vendor'       => 'VND-2026-016', // OneMed
                    'tgl_po'       => '2026-08-08',
                    'est_kirim'    => '2026-08-15',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202608-001',
                        'tgl_rcv'   => '2026-08-14',
                        'no_sj'     => 'SJ-OM-2608/112',
                        'gudang'    => $gudangSentral,
                        'batches'   => [
                            ['brg' => 'BRG-2026-028', 'rak' => 'PAL-D1', 'batch' => 'BATCH-BHZ-2608', 'exp' => null, 'qty' => 25],
                            ['brg' => 'BRG-2026-029', 'rak' => 'RAK-D4', 'batch' => 'BATCH-SFB-2608', 'exp' => null, 'qty' => 20],
                        ],
                    ],
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-108',
                'unit_kode'   => 'IPSRS',
                'tgl_pr'      => '2026-08-12',
                'urgensi'     => 'MENDESAK',
                'catatan_pr'  => 'Suku cadang medis darurat sensor SpO2 dan selang silikon mesin suction.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-054', 'qty' => 5,  'harga' => 750000],  // Sensor SpO2
                    ['kode' => 'BRG-2026-043', 'qty' => 20, 'harga' => 35000],   // Selang Silikon
                ],
                'po' => [
                    'kode'         => 'PO-2026-108',
                    'vendor'       => 'VND-2026-006', // Mindray Medical Indonesia
                    'tgl_po'       => '2026-08-15',
                    'est_kirim'    => '2026-08-22',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202608-002',
                        'tgl_rcv'   => '2026-08-20',
                        'no_sj'     => 'SJ-MND-082026-09',
                        'gudang'    => $gudangIpsrs,
                        'batches'   => [
                            ['brg' => 'BRG-2026-054', 'rak' => 'BIN-C1', 'batch' => 'BATCH-SPO-2608', 'exp' => null, 'qty' => 5],
                            ['brg' => 'BRG-2026-043', 'rak' => 'BIN-C4', 'batch' => 'BATCH-SLG-2608', 'exp' => null, 'qty' => 20],
                        ],
                    ],
                ],
            ],

            // BULAN 5: SEPTEMBER 2026
            [
                'pr_kode'     => 'PR-2026-109',
                'unit_kode'   => 'IGD',
                'tgl_pr'      => '2026-09-02',
                'urgensi'     => 'MENDESAK',
                'catatan_pr'  => 'Restock nasal kanul, sungkup oksigen NRM dan alkohol swab triwulan III.',
                'status_pr'   => 'SELESAI',
                'items'       => [
                    ['kode' => 'BRG-2026-022', 'qty' => 80, 'harga' => 6500],   // Nasal Kanul
                    ['kode' => 'BRG-2026-023', 'qty' => 30, 'harga' => 18000],  // Sungkup NRM
                    ['kode' => 'BRG-2026-020', 'qty' => 40, 'harga' => 15000],  // Alkohol Swab
                ],
                'po' => [
                    'kode'         => 'PO-2026-109',
                    'vendor'       => 'VND-2026-016', // OneMed
                    'tgl_po'       => '2026-09-05',
                    'est_kirim'    => '2026-09-12',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_LENGKAP',
                    'rcv' => [
                        'kode'      => 'RCV-202609-001',
                        'tgl_rcv'   => '2026-09-10',
                        'no_sj'     => 'SJ-OM-2609-771',
                        'gudang'    => $gudangSentral,
                        'batches'   => [
                            ['brg' => 'BRG-2026-022', 'rak' => 'RAK-B6', 'batch' => 'BATCH-KNL-2609', 'exp' => '2029-08-31', 'qty' => 80],
                            ['brg' => 'BRG-2026-023', 'rak' => 'RAK-B6', 'batch' => 'BATCH-NRM-2609', 'exp' => '2029-08-31', 'qty' => 30],
                            ['brg' => 'BRG-2026-020', 'rak' => 'RAK-B2', 'batch' => 'BATCH-SWB-2609', 'exp' => '2029-05-31', 'qty' => 40],
                        ],
                    ],
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-110',
                'unit_kode'   => 'LOGISTIK',
                'tgl_pr'      => '2026-09-15',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Pengadaan toner printer laserjet administrasi dan kabel power medis.',
                'status_pr'   => 'DITERUSKAN_KE_PENGADAAN',
                'items'       => [
                    ['kode' => 'BRG-2026-007', 'qty' => 8,  'harga' => 380000],  // Toner HP 85A
                    ['kode' => 'BRG-2026-045', 'qty' => 15, 'harga' => 85000],   // Kabel Power Medis
                ],
                'po' => [
                    'kode'         => 'PO-2026-110',
                    'vendor'       => 'VND-2026-013', // Surya Multi Elektronik
                    'tgl_po'       => '2026-09-18',
                    'est_kirim'    => '2026-09-28',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DIKIRIM_VENDOR',
                    'rcv'          => null, // Belum tiba di gudang (sedang dalam pengiriman ekspedisi)
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-111',
                'unit_kode'   => 'ICU',
                'tgl_pr'      => '2026-09-25',
                'urgensi'     => 'CITO',
                'catatan_pr'  => 'Permintaan darurat masker N95 3M dan steril pouches ruang ICU bedah.',
                'status_pr'   => 'DITERUSKAN_KE_PENGADAAN',
                'items'       => [
                    ['kode' => 'BRG-2026-014', 'qty' => 15, 'harga' => 285000], // N95 3M
                    ['kode' => 'BRG-2026-024', 'qty' => 4,  'harga' => 280000], // Steril Pouches
                ],
                'po' => [
                    'kode'         => 'PO-2026-111',
                    'vendor'       => 'VND-2026-017', // 3M Indonesia
                    'tgl_po'       => '2026-09-27',
                    'est_kirim'    => '2026-10-06',
                    'syarat'       => 'TEMPO_14_HARI',
                    'status'       => 'DITERBITKAN',
                    'rcv'          => null, // PO baru diterbitkan akhir September
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-112',
                'unit_kode'   => 'FARMASI',
                'tgl_pr'      => '2026-09-28',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Pengajuan kertas HVS dan buku resep untuk pelayanan poli rawat jalan.',
                'status_pr'   => 'DIAJUKAN',
                'items'       => [
                    ['kode' => 'BRG-2026-001', 'qty' => 25, 'harga' => 48000],
                ],
                'po' => null, // Masih antrean PR, belum diterbitkan PO
            ],
            [
                'pr_kode'     => 'PR-2026-113',
                'unit_kode'   => 'RAWAT_1',
                'tgl_pr'      => '2026-09-29',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Pengajuan selimut tebal pasien VIP tambahan.',
                'status_pr'   => 'DISETUJUI',
                'items'       => [
                    ['kode' => 'BRG-2026-026', 'qty' => 15, 'harga' => 75000],
                ],
                'po' => null, // Disetujui Karu, siap diteruskan ke Pengadaan
            ],
            [
                'pr_kode'     => 'PR-2026-114',
                'unit_kode'   => 'RAWAT_1',
                'tgl_pr'      => '2026-08-20',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Pengajuan televisi dan perangkat hiburan ruang tunggu.',
                'status_pr'   => 'DITOLAK',
                'items'       => [
                    ['kode' => 'BRG-2026-089', 'qty' => 2, 'harga' => 9800000],
                ],
                'po' => null,
            ],
            [
                'pr_kode'     => 'PR-2026-115',
                'unit_kode'   => 'IGD',
                'tgl_pr'      => '2026-08-10',
                'urgensi'     => 'MENDESAK',
                'catatan_pr'  => 'Pengadaan masker bedah cadangan IGD.',
                'status_pr'   => 'DITERUSKAN_KE_PENGADAAN',
                'items'       => [
                    ['kode' => 'BRG-2026-013', 'qty' => 50, 'harga' => 28000],
                ],
                'po' => [
                    'kode'         => 'PO-2026-115',
                    'vendor'       => 'VND-2026-016',
                    'tgl_po'       => '2026-08-12',
                    'est_kirim'    => '2026-08-20',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DITERIMA_SEBAGIAN',
                    'rcv' => [
                        'kode'      => 'RCV-202608-015',
                        'tgl_rcv'   => '2026-08-19',
                        'no_sj'     => 'SJ-OM-2608/PARTIAL-11',
                        'gudang'    => $gudangSentral,
                        'status'    => 'SEBAGIAN',
                        'catatan'   => 'Diterima sebagian (30 box dari total 50 box pesanan, 20 box backorder).',
                        'batches'   => [
                            ['brg' => 'BRG-2026-013', 'rak' => 'RAK-B3', 'batch' => 'BATCH-OM-PARTIAL', 'exp' => '2028-08-01', 'qty' => 30],
                        ],
                    ],
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-116',
                'unit_kode'   => 'IPSRS',
                'tgl_pr'      => '2026-07-10',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Pengadaan kapasitor kompresor tipe lama.',
                'status_pr'   => 'DITERUSKAN_KE_PENGADAAN',
                'items'       => [
                    ['kode' => 'BRG-2026-042', 'qty' => 5, 'harga' => 65000],
                ],
                'po' => [
                    'kode'         => 'PO-2026-116',
                    'vendor'       => 'VND-2026-018',
                    'tgl_po'       => '2026-07-12',
                    'est_kirim'    => '2026-07-25',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DIBATALKAN',
                    'rcv'          => null,
                ],
            ],
            [
                'pr_kode'     => 'PR-2026-117',
                'unit_kode'   => 'LOGISTIK',
                'tgl_pr'      => '2026-08-25',
                'urgensi'     => 'REGULER',
                'catatan_pr'  => 'Pengadaan cairan pembersih lantai cemara 5L.',
                'status_pr'   => 'DITERUSKAN_KE_PENGADAAN',
                'items'       => [
                    ['kode' => 'BRG-2026-012', 'qty' => 10, 'harga' => 55000],
                ],
                'po' => [
                    'kode'         => 'PO-2026-117',
                    'vendor'       => 'VND-2026-005',
                    'tgl_po'       => '2026-08-27',
                    'est_kirim'    => '2026-09-05',
                    'syarat'       => 'TEMPO_30_HARI',
                    'status'       => 'DIKIRIM_VENDOR',
                    'rcv' => [
                        'kode'      => 'RCV-202609-099',
                        'tgl_rcv'   => '2026-09-04',
                        'no_sj'     => 'SJ-SCH-REJECT-01',
                        'gudang'    => $gudangSentral,
                        'status'    => 'DITOLAK',
                        'batches'   => [
                            ['brg' => 'BRG-2026-012', 'rak' => 'PAL-D3', 'batch' => 'BATCH-REJ-2609', 'exp' => '2028-08-01', 'qty' => 0],
                        ],
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($procurementBatches, $units, $pemasoks, $barangs, $raks, $adminPengadaan, $petugasGudang, $gudangSentral) {
            foreach ($procurementBatches as $data) {
                $unitObj = $units->get($data['unit_kode']);
                if (! $unitObj) continue;

                // 1. Simpan Permintaan Pengadaan (PR)
                $pr = PermintaanPengadaan::firstOrCreate(
                    ['kode' => $data['pr_kode']],
                    [
                        'unit_id'            => $unitObj->id,
                        'gudang_id'          => null,
                        'diajukan_oleh'      => $adminPengadaan->id,
                        'tanggal_permintaan' => $data['tgl_pr'],
                        'urgensi'            => $data['urgensi'],
                        'status'             => $data['status_pr'],
                        'catatan'            => $data['catatan_pr'],
                        'disetujui_oleh'     => $adminPengadaan->id,
                        'dibuat_pada'        => Carbon::parse($data['tgl_pr'] . ' 08:30:00'),
                        'diperbarui_pada'    => Carbon::parse($data['tgl_pr'] . ' 09:15:00'),
                    ]
                );

                // 2. Simpan Detail PR
                foreach ($data['items'] as $item) {
                    $brgObj = $barangs->get($item['kode']);
                    if (! $brgObj) continue;

                    PermintaanPengadaanDetail::firstOrCreate(
                        [
                            'permintaan_pengadaan_id' => $pr->id,
                            'barang_id'                => $brgObj->id,
                        ],
                        [
                            'jumlah'           => $item['qty'],
                            'jumlah_disetujui' => $item['qty'],
                            'catatan'          => 'Kebutuhan dinas standar operasional',
                        ]
                    );
                }

                // 3. Jika ada PO
                if (! empty($data['po'])) {
                    $poData = $data['po'];
                    $vendorObj = $pemasoks->get($poData['vendor']);
                    if (! $vendorObj) continue;

                    $totalBiaya = 0;
                    foreach ($data['items'] as $it) {
                        $totalBiaya += ($it['qty'] * $it['harga']);
                    }

                    $po = PesananPembelian::firstOrCreate(
                        ['kode' => $poData['kode']],
                        [
                            'pemasok_id'              => $vendorObj->id,
                            'permintaan_pengadaan_id' => $pr->id,
                            'tanggal_po'              => $poData['tgl_po'],
                            'estimasi_tanggal_kirim'  => $poData['est_kirim'],
                            'total_biaya'             => $totalBiaya,
                            'syarat_pembayaran'       => $poData['syarat'],
                            'status'                  => $poData['status'],
                            'catatan'                 => 'Surat Pesanan Pembelian Resmi Logistik RS - Ref ' . $pr->kode,
                            'dibuat_oleh'             => $adminPengadaan->id,
                            'dibuat_pada'             => Carbon::parse($poData['tgl_po'] . ' 10:00:00'),
                            'diperbarui_pada'         => Carbon::parse($poData['tgl_po'] . ' 10:30:00'),
                        ]
                    );

                    // Detail PO
                    foreach ($data['items'] as $it) {
                        $brgObj = $barangs->get($it['kode']);
                        if (! $brgObj) continue;

                        $isReceived = in_array($poData['status'], ['DITERIMA_LENGKAP', 'DITERIMA_SEBAGIAN']);
                        $qtyDiterima = $isReceived ? $it['qty'] : 0;

                        PesananPembelianDetail::firstOrCreate(
                            [
                                'pesanan_pembelian_id' => $po->id,
                                'barang_id'            => $brgObj->id,
                            ],
                            [
                                'jumlah_dipesan'  => $it['qty'],
                                'jumlah_diterima' => $qtyDiterima,
                                'harga_satuan'    => $it['harga'],
                                'subtotal'        => $it['qty'] * $it['harga'],
                            ]
                        );

                        // Update harga beli terakhir master barang
                        $brgObj->update(['harga_beli_terakhir' => $it['harga']]);
                    }

                    // 4. Jika ada Penerimaan Inbound (RCV)
                    if (! empty($poData['rcv'])) {
                        $rcvData = $poData['rcv'];
                        $gudangObj = $rcvData['gudang'];

                        $penerimaan = PenerimaanBarang::updateOrCreate(
                            ['kode' => $rcvData['kode']],
                            [
                                'pesanan_pembelian_id' => $po->id,
                                'gudang_id'            => $gudangObj->id,
                                'nomor_surat_jalan'    => $rcvData['no_sj'],
                                'tanggal_terima'       => $rcvData['tgl_rcv'],
                                'status'               => $rcvData['status'] ?? 'LENGKAP',
                                'catatan'              => $rcvData['catatan'] ?? 'Diterima fisik lengkap dalam kondisi baik sesuai surat jalan.',
                                'diterima_oleh'        => $petugasGudang->id,
                                'dibuat_pada'          => Carbon::parse($rcvData['tgl_rcv'] . ' 11:00:00'),
                                'diperbarui_pada'      => Carbon::parse($rcvData['tgl_rcv'] . ' 11:30:00'),
                            ]
                        );

                        // Batch & Pergerakan Stok
                        foreach ($rcvData['batches'] as $bDetail) {
                            $brgObj = $barangs->get($bDetail['brg']);
                            if (! $brgObj) continue;

                            $rakObj = $raks->get($bDetail['rak']);
                            $rakId  = $rakObj ? $rakObj->id : null;

                            // Temukan harga satuan dari detail item
                            $hargaSat = $brgObj->harga_beli_terakhir;

                            PenerimaanBarangDetail::firstOrCreate(
                                [
                                    'penerimaan_barang_id' => $penerimaan->id,
                                    'barang_id'            => $brgObj->id,
                                    'nomor_batch'          => $bDetail['batch'],
                                ],
                                [
                                    'lokasi_gudang_id'    => $rakId,
                                    'tanggal_kedaluwarsa' => $bDetail['exp'],
                                    'jumlah_diterima'     => $bDetail['qty'],
                                    'jumlah_rusak'        => 0,
                                    'harga_satuan'        => $hargaSat,
                                ]
                            );

                            // Stok Batch
                            $batch = StokBatch::firstOrCreate(
                                [
                                    'barang_id'   => $brgObj->id,
                                    'gudang_id'   => $gudangObj->id,
                                    'nomor_batch' => $bDetail['batch'],
                                ],
                                [
                                    'lokasi_gudang_id'    => $rakId,
                                    'tanggal_kedaluwarsa' => $bDetail['exp'],
                                    'jumlah'              => $bDetail['qty'],
                                    'harga_satuan'        => $hargaSat,
                                    'status'              => 'AKTIF',
                                    'dibuat_pada'         => Carbon::parse($rcvData['tgl_rcv'] . ' 11:15:00'),
                                ]
                            );

                            // Pergerakan Stok Masuk
                            PergerakanStok::firstOrCreate(
                                [
                                    'barang_id'       => $brgObj->id,
                                    'stok_batch_id'   => $batch->id,
                                    'referensi_jenis' => 'PENERIMAAN_PO',
                                    'referensi_id'    => $penerimaan->id,
                                ],
                                [
                                    'gudang_id'        => $gudangObj->id,
                                    'lokasi_gudang_id' => $rakId,
                                    'jenis'            => 'MASUK',
                                    'jumlah'           => $bDetail['qty'],
                                    'saldo_sebelum'    => 0,
                                    'saldo_sesudah'    => $bDetail['qty'],
                                    'keterangan'       => "Inbound Penerimaan PO {$po->kode} - Batch {$bDetail['batch']}",
                                    'dibuat_oleh'      => $petugasGudang->id,
                                    'dibuat_pada'      => Carbon::parse($rcvData['tgl_rcv'] . ' 11:20:00'),
                                ]
                            );
                        }
                    }
                }
            }

            // Batch Khusus untuk Simulasi Monitoring FEFO (HAMPIR_KEDALUWARSA, KEDALUWARSA, HABIS)
            $brgMasker = $barangs->get('BRG-2026-013') ?? Barang::first();
            $brgAlkohol = $barangs->get('BRG-2026-020') ?? Barang::first();
            $brgKasa = $barangs->get('BRG-2026-017') ?? Barang::first();
            $rakB2 = $raks->get('RAK-B2')?->id;
            $rakB3 = $raks->get('RAK-B3')?->id;

            StokBatch::firstOrCreate(
                ['nomor_batch' => 'BATCH-CRIT-FEFO-01', 'barang_id' => $brgMasker->id],
                [
                    'gudang_id'           => $gudangSentral->id,
                    'lokasi_gudang_id'    => $rakB3,
                    'tanggal_kedaluwarsa' => Carbon::now()->addDays(35)->toDateString(),
                    'jumlah'              => 15,
                    'harga_satuan'        => $brgMasker->harga_beli_terakhir,
                    'status'              => 'HAMPIR_KEDALUWARSA',
                    'dibuat_pada'         => Carbon::now()->subMonths(6),
                ]
            );

            StokBatch::firstOrCreate(
                ['nomor_batch' => 'BATCH-EXP-FEFO-02', 'barang_id' => $brgAlkohol->id],
                [
                    'gudang_id'           => $gudangSentral->id,
                    'lokasi_gudang_id'    => $rakB2,
                    'tanggal_kedaluwarsa' => Carbon::now()->subDays(20)->toDateString(),
                    'jumlah'              => 5,
                    'harga_satuan'        => $brgAlkohol->harga_beli_terakhir,
                    'status'              => 'KEDALUWARSA',
                    'dibuat_pada'         => Carbon::now()->subMonths(12),
                ]
            );

            StokBatch::firstOrCreate(
                ['nomor_batch' => 'BATCH-DEPLETED-03', 'barang_id' => $brgKasa->id],
                [
                    'gudang_id'           => $gudangSentral->id,
                    'lokasi_gudang_id'    => $rakB3,
                    'tanggal_kedaluwarsa' => Carbon::now()->addYear()->toDateString(),
                    'jumlah'              => 0,
                    'harga_satuan'        => $brgKasa->harga_beli_terakhir,
                    'status'              => 'HABIS',
                    'dibuat_pada'         => Carbon::now()->subMonths(8),
                ]
            );
        });
    }
}
