<?php

namespace Database\Seeders\Dummy;

use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Distribusi\PenerimaanDistribusi;
use App\Models\Distribusi\PenerimaanDistribusiDetail;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PengirimanBarangDetail;
use App\Models\Persediaan\PergerakanStok;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Distribusi\PermintaanDistribusiDetail;
use App\Models\Persediaan\StokBatch;
use App\Models\Distribusi\StokRuangan;
use App\Models\Master\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistribusiDummySeeder extends Seeder
{
    /**
     * Menanam data historis siklus amprah distribusi ruangan
     * (Permintaan Amprah -> SPPB FEFO -> Penerimaan Ruangan RCV -> Saldo Lemari Ruangan)
     * yang tersebar dalam beberapa bulan ke belakang (Mei 2026 - September 2026).
     */
    public function run(): void
    {
        $adminGudang = User::where('email', 'kepalagudang@simlogrs.local')->first() ?? User::first();
        $petugasGudang = User::where('email', 'petugasgudang@simlogrs.local')->first() ?? User::first();
        $stafIgd = User::where('email', 'petugasunit@simlogrs.local')->first() ?? User::first();
        $karuIgd = User::where('email', 'kepalaunit@simlogrs.local')->first() ?? User::first();

        $gudangSentral = Gudang::where('kode', 'GUD_SENTRAL')->first() ?? Gudang::first();
        $gudangAtk     = Gudang::where('kode', 'GUD_ATK')->first() ?? $gudangSentral;
        $gudangIpsrs   = Gudang::where('kode', 'GUD_IPSRS')->first() ?? $gudangSentral;

        $units = Unit::all()->keyBy('kode');
        $barangs = Barang::all()->keyBy('kode');

        // Daftar paket amprah distribusi bulanan (Mei - September 2026)
        $amprahBatches = [
            // BULAN MEI 2026
            [
                'kode'        => 'REQ-202605-001',
                'unit'        => 'IGD',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-05-14',
                'urgensi'     => 'MENDESAK',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-013', 'qty' => 15], // Masker 3-Ply
                    ['brg' => 'BRG-2026-015', 'qty' => 10], // Sarung Tangan Non-Steril
                    ['brg' => 'BRG-2026-009', 'qty' => 8],  // Hand Sanitizer
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260515-0001',
                    'tgl'     => '2026-05-15',
                    'rcv'     => 'RCV-202605-011',
                    'tgl_rcv' => '2026-05-16',
                ],
            ],
            [
                'kode'        => 'REQ-202605-002',
                'unit'        => 'RAWAT_1',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-05-20',
                'urgensi'     => 'REGULER',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-013', 'qty' => 10],
                    ['brg' => 'BRG-2026-015', 'qty' => 10],
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260521-0002',
                    'tgl'     => '2026-05-21',
                    'rcv'     => 'RCV-202605-012',
                    'tgl_rcv' => '2026-05-22',
                ],
            ],

            // BULAN JUNI 2026
            [
                'kode'        => 'REQ-202606-001',
                'unit'        => 'IGD',
                'gudang'      => $gudangAtk,
                'tgl_req'     => '2026-06-11',
                'urgensi'     => 'REGULER',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-001', 'qty' => 10], // Kertas A4
                    ['brg' => 'BRG-2026-006', 'qty' => 5],  // Pulpen Joyko
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260612-0001',
                    'tgl'     => '2026-06-12',
                    'rcv'     => 'RCV-202606-011',
                    'tgl_rcv' => '2026-06-12',
                ],
            ],
            [
                'kode'        => 'REQ-202606-002',
                'unit'        => 'RAWAT_1',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-06-28',
                'urgensi'     => 'REGULER',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-026', 'qty' => 12], // Sprei Bed Pasien
                    ['brg' => 'BRG-2026-027', 'qty' => 15], // Sarung Bantal
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260629-0002',
                    'tgl'     => '2026-06-29',
                    'rcv'     => 'RCV-202606-012',
                    'tgl_rcv' => '2026-06-30',
                ],
            ],

            // BULAN JULI 2026
            [
                'kode'        => 'REQ-202607-001',
                'unit'        => 'ICU',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-07-08',
                'urgensi'     => 'CITO',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-016', 'qty' => 30], // Sarung Tangan Steril
                    ['brg' => 'BRG-2026-017', 'qty' => 10], // Kasa Steril 16x16
                    ['brg' => 'BRG-2026-019', 'qty' => 8],  // Plester Micropore
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260709-0001',
                    'tgl'     => '2026-07-09',
                    'rcv'     => 'RCV-202607-011',
                    'tgl_rcv' => '2026-07-09',
                ],
            ],
            [
                'kode'        => 'REQ-202607-002',
                'unit'        => 'FARMASI',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-07-23',
                'urgensi'     => 'REGULER',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-010', 'qty' => 4], // Sabun Cuci Tangan 5L
                    ['brg' => 'BRG-2026-021', 'qty' => 3], // Alkohol 70% 5L
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260724-0002',
                    'tgl'     => '2026-07-24',
                    'rcv'     => 'RCV-202607-012',
                    'tgl_rcv' => '2026-07-25',
                ],
            ],

            // BULAN AGUSTUS 2026
            [
                'kode'        => 'REQ-202608-001',
                'unit'        => 'LAB',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-08-16',
                'urgensi'     => 'REGULER',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-028', 'qty' => 8], // Kantong Biohazard
                    ['brg' => 'BRG-2026-029', 'qty' => 6], // Safety Box 5L
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260817-0001',
                    'tgl'     => '2026-08-17',
                    'rcv'     => 'RCV-202608-011',
                    'tgl_rcv' => '2026-08-18',
                ],
            ],
            [
                'kode'        => 'REQ-202608-002',
                'unit'        => 'IGD',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-08-22',
                'urgensi'     => 'MENDESAK',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-015', 'qty' => 12],
                    ['brg' => 'BRG-2026-017', 'qty' => 8],
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260823-0002',
                    'tgl'     => '2026-08-23',
                    'rcv'     => 'RCV-202608-012',
                    'tgl_rcv' => '2026-08-24',
                ],
            ],

            // BULAN SEPTEMBER 2026
            [
                'kode'        => 'REQ-202609-001',
                'unit'        => 'IGD',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-09-12',
                'urgensi'     => 'MENDESAK',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-022', 'qty' => 25], // Nasal Kanul
                    ['brg' => 'BRG-2026-023', 'qty' => 10], // Sungkup NRM
                    ['brg' => 'BRG-2026-020', 'qty' => 15], // Alkohol Swab
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260913-0001',
                    'tgl'     => '2026-09-13',
                    'rcv'     => 'RCV-202609-011',
                    'tgl_rcv' => '2026-09-14',
                ],
            ],
            [
                'kode'        => 'REQ-202609-002',
                'unit'        => 'ICU',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-09-20',
                'urgensi'     => 'CITO',
                'status'      => 'SEDANG_DIKIRIM',
                'items'       => [
                    ['brg' => 'BRG-2026-016', 'qty' => 20],
                    ['brg' => 'BRG-2026-022', 'qty' => 15],
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260921-0002',
                    'tgl'     => '2026-09-21',
                    'rcv'     => null, // Masih di jalan / belum konfirmasi terima
                ],
            ],
            [
                'kode'        => 'REQ-202609-003',
                'unit'        => 'LAB',
                'gudang'      => $gudangAtk,
                'tgl_req'     => '2026-09-26',
                'urgensi'     => 'REGULER',
                'status'      => 'DISETUJUI_GUDANG', // Siap Picking FEFO
                'items'       => [
                    ['brg' => 'BRG-2026-001', 'qty' => 8],
                    ['brg' => 'BRG-2026-006', 'qty' => 4],
                ],
                'sppb' => null,
            ],
            [
                'kode'        => 'REQ-202609-004',
                'unit'        => 'RAWAT_1',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-09-29',
                'urgensi'     => 'REGULER',
                'status'      => 'DISETUJUI_KARU', // Menunggu persetujuan Gudang
                'items'       => [
                    ['brg' => 'BRG-2026-026', 'qty' => 6],
                    ['brg' => 'BRG-2026-027', 'qty' => 8],
                ],
                'sppb' => null,
            ],
            [
                'kode'        => 'REQ-202609-005',
                'unit'        => 'FARMASI',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-09-30',
                'urgensi'     => 'REGULER',
                'status'      => 'DIAJUKAN', // Baru diajukan
                'items'       => [
                    ['brg' => 'BRG-2026-015', 'qty' => 15],
                ],
                'sppb' => null,
            ],
            [
                'kode'        => 'REQ-202609-006',
                'unit'        => 'LAB',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-09-22',
                'urgensi'     => 'REGULER',
                'status'      => 'DITOLAK',
                'catatan'     => 'Permintaan amprah ditolak Karu: Stok lemari ruangan masih mencukupi.',
                'items'       => [
                    ['brg' => 'BRG-2026-028', 'qty' => 10],
                ],
                'sppb' => null,
            ],
            [
                'kode'        => 'REQ-202609-007',
                'unit'        => 'FARMASI',
                'gudang'      => $gudangSentral,
                'tgl_req'     => '2026-09-18',
                'urgensi'     => 'REGULER',
                'status'      => 'SELESAI',
                'items'       => [
                    ['brg' => 'BRG-2026-021', 'qty' => 2],
                ],
                'sppb' => [
                    'kode'    => 'SPPB-20260919-0003',
                    'tgl'     => '2026-09-19',
                    'status'  => 'BATAL',
                    'catatan' => 'Dibatalkan karena salah alokasi barang gudang oleh staf admin.',
                    'rcv'     => null,
                ],
            ],
        ];

        DB::transaction(function () use ($amprahBatches, $units, $barangs, $adminGudang, $petugasGudang, $stafIgd, $karuIgd) {
            foreach ($amprahBatches as $data) {
                $unitObj = $units->get($data['unit']);
                if (! $unitObj) continue;

                $gudangObj = $data['gudang'];

                // 1. Permintaan Distribusi (Amprah)
                $permintaan = PermintaanDistribusi::firstOrCreate(
                    ['kode' => $data['kode']],
                    [
                        'unit_id'            => $unitObj->id,
                        'gudang_id'          => $gudangObj->id,
                        'tanggal_permintaan' => $data['tgl_req'],
                        'urgensi'            => $data['urgensi'],
                        'status'             => $data['status'],
                        'catatan'            => 'Amprah rutin pelayanan operasional ruangan ' . $unitObj->nama,
                        'diajukan_oleh'      => $stafIgd->id,
                        'disetujui_oleh'     => $karuIgd->id,
                        'dibuat_pada'        => Carbon::parse($data['tgl_req'] . ' 08:00:00'),
                        'diperbarui_pada'    => Carbon::parse($data['tgl_req'] . ' 09:00:00'),
                    ]
                );

                // Detail Permintaan
                foreach ($data['items'] as $item) {
                    $brgObj = $barangs->get($item['brg']);
                    if (! $brgObj) continue;

                    PermintaanDistribusiDetail::firstOrCreate(
                        [
                            'permintaan_distribusi_id' => $permintaan->id,
                            'barang_id'                => $brgObj->id,
                        ],
                        [
                            'jumlah'           => $item['qty'],
                            'jumlah_disetujui' => $item['qty'],
                            'catatan'          => 'Disetujui sesuai kuota plafon ruangan',
                        ]
                    );
                }

                // 2. Jika ada SPPB
                if (! empty($data['sppb'])) {
                    $sppbData = $data['sppb'];
                    $sppbStatus = ! empty($sppbData['rcv']) ? 'DITERIMA' : ($sppbData['status'] ?? 'DIKIRIM');

                    $sppb = PengirimanBarang::firstOrCreate(
                        ['kode' => $sppbData['kode']],
                        [
                            'permintaan_distribusi_id' => $permintaan->id,
                            'gudang_id'                => $gudangObj->id,
                            'unit_id'                  => $unitObj->id,
                            'tanggal_kirim'            => $sppbData['tgl'],
                            'status'                   => $sppbStatus,
                            'catatan'                  => $sppbData['catatan'] ?? ('SPPB Pengeluaran Logistik Gudang - Ref ' . $permintaan->kode),
                            'dikirim_oleh'             => $petugasGudang->id,
                            'dibuat_pada'              => Carbon::parse($sppbData['tgl'] . ' 10:00:00'),
                        ]
                    );

                    // Detail SPPB FEFO
                    foreach ($data['items'] as $item) {
                        $brgObj = $barangs->get($item['brg']);
                        if (! $brgObj) continue;

                        $qtyNeeded = $item['qty'];

                        // Cari batch aktif dari stok_batch
                        $batches = StokBatch::where('barang_id', $brgObj->id)
                            ->where('gudang_id', $gudangObj->id)
                            ->where('jumlah', '>', 0)
                            ->orderByRaw('tanggal_kedaluwarsa ASC NULLS LAST')
                            ->get();

                        $allocatedBatch = $batches->first();

                        if (! $allocatedBatch) {
                            // Jika belum ada batch, buat batch dummy agar SPPB valid
                            $allocatedBatch = StokBatch::firstOrCreate(
                                [
                                    'barang_id'   => $brgObj->id,
                                    'gudang_id'   => $gudangObj->id,
                                    'nomor_batch' => 'INIT-BATCH-' . $brgObj->kode,
                                ],
                                [
                                    'lokasi_gudang_id'    => null,
                                    'tanggal_kedaluwarsa' => '2028-12-31',
                                    'jumlah'              => 100,
                                    'harga_satuan'        => $brgObj->harga_beli_terakhir,
                                    'status'              => 'AKTIF',
                                ]
                            );
                        }

                        $allocatedQty = min($allocatedBatch->jumlah, $qtyNeeded);

                        PengirimanBarangDetail::firstOrCreate(
                            [
                                'pengiriman_barang_id' => $sppb->id,
                                'barang_id'            => $brgObj->id,
                                'stok_batch_id'        => $allocatedBatch->id,
                            ],
                            [
                                'jumlah_dikirim' => $allocatedQty > 0 ? $allocatedQty : $qtyNeeded,
                            ]
                        );

                        // Kurangi stok batch jika mencukupi
                        if ($allocatedBatch->jumlah >= $allocatedQty) {
                            $saldoSebelum = $allocatedBatch->jumlah;
                            $allocatedBatch->decrement('jumlah', $allocatedQty);
                            $saldoSesudah = $allocatedBatch->jumlah;

                            // Mutasi Keluar
                            PergerakanStok::firstOrCreate(
                                [
                                    'barang_id'       => $brgObj->id,
                                    'stok_batch_id'   => $allocatedBatch->id,
                                    'referensi_jenis' => 'PENGIRIMAN_DISTRIBUSI',
                                    'referensi_id'    => $sppb->id,
                                ],
                                [
                                    'gudang_id'        => $gudangObj->id,
                                    'lokasi_gudang_id' => $allocatedBatch->lokasi_gudang_id,
                                    'jenis'            => 'KELUAR',
                                    'jumlah'           => $allocatedQty,
                                    'saldo_sebelum'    => $saldoSebelum,
                                    'saldo_sesudah'    => $saldoSesudah,
                                    'keterangan'       => "Distribusi SPPB {$sppb->kode} ke unit {$unitObj->nama}",
                                    'dibuat_oleh'      => $petugasGudang->id,
                                    'dibuat_pada'      => Carbon::parse($sppbData['tgl'] . ' 10:15:00'),
                                ]
                            );
                        }
                    }

                    // 3. Jika sudah diterima ruangan (RCV Ruangan)
                    if (! empty($sppbData['rcv'])) {
                        $penerimaanDist = PenerimaanDistribusi::firstOrCreate(
                            ['kode' => $sppbData['rcv']],
                            [
                                'pengiriman_barang_id' => $sppb->id,
                                'unit_id'              => $unitObj->id,
                                'tanggal_terima'       => $sppbData['tgl_rcv'],
                                'status'               => 'SELESAI',
                                'catatan'              => 'Diterima fisik lengkap oleh perawat/staf jaga ruangan.',
                                'diterima_oleh'        => $stafIgd->id,
                                'dibuat_pada'          => Carbon::parse($sppbData['tgl_rcv'] . ' 14:00:00'),
                            ]
                        );

                        foreach ($data['items'] as $item) {
                            $brgObj = $barangs->get($item['brg']);
                            if (! $brgObj) continue;

                            PenerimaanDistribusiDetail::firstOrCreate(
                                [
                                    'penerimaan_distribusi_id' => $penerimaanDist->id,
                                    'barang_id'                => $brgObj->id,
                                ],
                                [
                                    'jumlah_diterima' => $item['qty'],
                                ]
                            );

                            // Masukkan ke stok lemari ruangan (stok_ruangan)
                            $stokRuangan = StokRuangan::firstOrCreate(
                                [
                                    'unit_id'   => $unitObj->id,
                                    'barang_id' => $brgObj->id,
                                ],
                                [
                                    'jumlah'        => 0,
                                    'stok_minimum'  => 5,
                                    'stok_maksimum' => 50,
                                ]
                            );

                            $stokRuangan->increment('jumlah', $item['qty']);
                        }
                    }
                }
            }

            // Stok Ruangan HABIS (Kuantitas = 0) untuk simulasi filter kondisi HABIS
            $unitIgdObj = $units->get('IGD');
            $unitRawatObj = $units->get('RAWAT_1');
            $brgN95 = $barangs->get('BRG-2026-014');
            $brgElastis = $barangs->get('BRG-2026-018');
            $brgPlester = $barangs->get('BRG-2026-019');

            if ($unitIgdObj && $brgN95) {
                StokRuangan::firstOrCreate(
                    ['unit_id' => $unitIgdObj->id, 'barang_id' => $brgN95->id],
                    ['jumlah' => 0, 'stok_minimum' => 10, 'stok_maksimum' => 50]
                );
            }

            if ($unitIgdObj && $brgElastis) {
                StokRuangan::firstOrCreate(
                    ['unit_id' => $unitIgdObj->id, 'barang_id' => $brgElastis->id],
                    ['jumlah' => 0, 'stok_minimum' => 5, 'stok_maksimum' => 30]
                );
            }

            if ($unitRawatObj && $brgPlester) {
                StokRuangan::firstOrCreate(
                    ['unit_id' => $unitRawatObj->id, 'barang_id' => $brgPlester->id],
                    ['jumlah' => 0, 'stok_minimum' => 5, 'stok_maksimum' => 40]
                );
            }
        });
    }
}
