<?php

namespace Database\Seeders;

use App\Models\Aset;
use App\Models\Gudang;
use App\Models\PengembalianPeminjaman;
use App\Models\PengembalianPeminjamanDetail;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PeminjamanAsetDummySeeder extends Seeder
{
    /**
     * Menanam data simulasi sirkulasi peminjaman dan pengembalian aset inventaris
     * dengan tanggal rencana kembali beberapa minggu ke depan (Oktober - November 2026)
     * mencakup status: DIPINJAM (aktif), DIPINJAM (overdue), DISETUJUI, DIAJUKAN, dan SELESAI.
     */
    public function run(): void
    {
        $adminLogistik = User::where('email', 'adminlogistik@simlogrs.local')->first() ?? User::first();
        $karuIgd       = User::where('email', 'kepalaunit@simlogrs.local')->first() ?? User::first();
        $stafIgd       = User::where('email', 'petugasunit@simlogrs.local')->first() ?? User::first();
        $manajemen     = User::where('email', 'manajemen@simlogrs.local')->first() ?? User::first();

        $units   = Unit::all()->keyBy('kode');
        $gudangs = Gudang::all()->keyBy('kode');
        $gudangSentral = $gudangs->get('GUD_SENTRAL') ?? Gudang::first();

        $unitIgd    = $units->get('IGD');
        $unitIcu    = $units->get('ICU');
        $unitRawat1 = $units->get('RAWAT_1');
        $unitLab    = $units->get('LAB');
        $unitFarm   = $units->get('FARMASI');
        $unitLog    = $units->get('LOGISTIK');

        // Daftar paket transaksi peminjaman aset (Oktober - November 2026)
        $pinjamList = [
            // 1. DIPINJAM - Aktif (Rencana kembali 1 minggu ke depan: 09 Oktober 2026)
            // Kasus: ICU meminjam Infusion Pump dari pool Gudang Sentral untuk lonjakan pasien
            [
                'kode'            => 'PINJ-2026-001',
                'tipe_penyedia'   => 'GUDANG',
                'unit_penyedia'   => null,
                'gudang_penyedia' => $gudangSentral->id,
                'unit_peminjam'   => $unitIcu->id,
                'diajukan_oleh'   => $adminLogistik->id,
                'tgl_pinjam'      => '2026-10-01',
                'tgl_kembali'     => '2026-10-09', // 1 minggu ke depan
                'keperluan'       => 'Peminjaman darurat Infusion Pump cadangan untuk lonjakan pasien kritis di ruang ICU.',
                'status'          => 'DIPINJAM',
                'disetujui_oleh'  => $adminLogistik->id,
                'catatan'         => 'Disetujui dan diserahterimakan lengkap dengan kabel power & sensor tetes.',
                'aset_kode'       => 'AST-2026-033', // POOL-INF-01
                'kembali'         => null,
            ],

            // 2. DIPINJAM - Aktif (Rencana kembali 2 minggu ke depan: 16 Oktober 2026)
            // Kasus: Rawat Inap Teratai meminjam Syringe Pump presisi dari pool Gudang Sentral
            [
                'kode'            => 'PINJ-2026-002',
                'tipe_penyedia'   => 'GUDANG',
                'unit_penyedia'   => null,
                'gudang_penyedia' => $gudangSentral->id,
                'unit_peminjam'   => $unitRawat1->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-10-02',
                'tgl_kembali'     => '2026-10-16', // 2 minggu ke depan
                'keperluan'       => 'Pemberian obat titrasi kontinu pada pasien post-operasi bedah mayor di rawat inap.',
                'status'          => 'DIPINJAM',
                'disetujui_oleh'  => $adminLogistik->id,
                'catatan'         => 'Alat dalam kondisi prima terkalibrasi.',
                'aset_kode'       => 'AST-2026-034', // POOL-SYR-01
                'kembali'         => null,
            ],

            // 3. DIPINJAM - Aktif (Rencana kembali 3 minggu ke depan: 23 Oktober 2026)
            // Kasus: IGD meminjam Bed Pasien 3-Crank dari pool Gudang Sentral
            [
                'kode'            => 'PINJ-2026-003',
                'tipe_penyedia'   => 'GUDANG',
                'unit_penyedia'   => null,
                'gudang_penyedia' => $gudangSentral->id,
                'unit_peminjam'   => $unitIgd->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-09-30',
                'tgl_kembali'     => '2026-10-23', // 3 minggu ke depan
                'keperluan'       => 'Tambahan tempat tidur observasi triase IGD selama renovasi ruang tindakan.',
                'status'          => 'DIPINJAM',
                'disetujui_oleh'  => $adminLogistik->id,
                'catatan'         => 'Diserahterimakan bersama matras antialergi.',
                'aset_kode'       => 'AST-2026-031', // POOL-BED-01
                'kembali'         => null,
            ],

            // 4. DIPINJAM - Aktif Antar-Ruangan (Rencana kembali 4 minggu ke depan: 30 Oktober 2026)
            // Kasus: Rawat Inap meminjam Kursi Roda Standar milik IGD
            [
                'kode'            => 'PINJ-2026-004',
                'tipe_penyedia'   => 'RUANGAN',
                'unit_penyedia'   => $unitIgd->id,
                'gudang_penyedia' => null,
                'unit_peminjam'   => $unitRawat1->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-10-02',
                'tgl_kembali'     => '2026-10-30', // 4 minggu ke depan
                'keperluan'       => 'Mobilisasi pasien pasca tindakan menuju ruang fisioterapi dan radiologi.',
                'status'          => 'DIPINJAM',
                'disetujui_oleh'  => $karuIgd->id,
                'catatan'         => 'Peminjaman peer-to-peer antar ruangan disetujui Karu IGD.',
                'aset_kode'       => 'AST-2026-010', // KR-IGD-01
                'kembali'         => null,
            ],

            // 5. DIPINJAM - Aktif Antar-Ruangan (Rencana kembali November 2026: 10 November 2026)
            // Kasus: IGD meminjam USG Portable dari ICU untuk pemeriksaan FAST trauma tumpul abdomen
            [
                'kode'            => 'PINJ-2026-005',
                'tipe_penyedia'   => 'RUANGAN',
                'unit_penyedia'   => $unitIcu->id,
                'gudang_penyedia' => null,
                'unit_peminjam'   => $unitIgd->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-09-28',
                'tgl_kembali'     => '2026-11-10', // 5-6 minggu ke depan
                'keperluan'       => 'Pemeriksaan USG cito darurat pasien kecelakaan lalu lintas di ruang resusitasi IGD.',
                'status'          => 'DIPINJAM',
                'disetujui_oleh'  => $manajemen->id,
                'catatan'         => 'Disetujui manajemen rumah sakit untuk dukungan trauma center.',
                'aset_kode'       => 'AST-2026-017', // USG-ICU-01
                'kembali'         => null,
            ],

            // 6. DIPINJAM - OVERDUE (Tanggal kembali telah lewat: 25 September 2026)
            // Kasus: Simulasi keterlambatan pengembalian untuk memicu flag is_overdue & audit SPI
            [
                'kode'            => 'PINJ-2026-006',
                'tipe_penyedia'   => 'GUDANG',
                'unit_penyedia'   => null,
                'gudang_penyedia' => $gudangSentral->id,
                'unit_peminjam'   => $unitLab->id,
                'diajukan_oleh'   => $adminLogistik->id,
                'tgl_pinjam'      => '2026-09-10',
                'tgl_kembali'     => '2026-09-25', // Lewat batas waktu (Overdue)
                'keperluan'       => 'Peminjaman Suction Pump cadangan saat kalibrasi tahunan instrumen lab.',
                'status'          => 'DIPINJAM',
                'disetujui_oleh'  => $adminLogistik->id,
                'catatan'         => 'Peringatan: Melebihi estimasi tanggal kembali, perlu klarifikasi unit peminjam.',
                'aset_kode'       => 'AST-2026-036', // POOL-SUCT-01
                'kembali'         => null,
            ],

            // 7. DISETUJUI - Menunggu Serah Terima Fisik (Awal Oktober 2026)
            // Kasus: Farmasi meminjam Printer Barcode dari Gudang Sentral, siap diserahterimakan
            [
                'kode'            => 'PINJ-2026-007',
                'tipe_penyedia'   => 'GUDANG',
                'unit_penyedia'   => null,
                'gudang_penyedia' => $gudangSentral->id,
                'unit_peminjam'   => $unitFarm->id,
                'diajukan_oleh'   => $adminLogistik->id,
                'tgl_pinjam'      => '2026-10-02',
                'tgl_kembali'     => '2026-10-20',
                'keperluan'       => 'Cetak barcode etiket obat dan pelabelan resep kronis rawat jalan.',
                'status'          => 'DISETUJUI',
                'disetujui_oleh'  => $adminLogistik->id,
                'catatan'         => 'Permohonan disetujui, menunggu pengambilan fisik & scan QR oleh petugas farmasi.',
                'aset_kode'       => 'AST-2026-035', // POOL-MON-01 (disetujui, belum serah terima)
                'kembali'         => null,
            ],

            // 8. DISETUJUI - Antar Ruangan (Awal Oktober 2026)
            // Kasus: Lab meminjam EKG 12 Lead milik IGD untuk medical check-up (MCU) karyawan
            [
                'kode'            => 'PINJ-2026-008',
                'tipe_penyedia'   => 'RUANGAN',
                'unit_penyedia'   => $unitIgd->id,
                'gudang_penyedia' => null,
                'unit_peminjam'   => $unitLab->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-10-02',
                'tgl_kembali'     => '2026-10-18',
                'keperluan'       => 'Pemeriksaan treadmill dan rekam jantung medical check up tahunan korporasi.',
                'status'          => 'DISETUJUI',
                'disetujui_oleh'  => $karuIgd->id,
                'catatan'         => 'Disetujui Karu IGD, siap serah terima.',
                'aset_kode'       => 'AST-2026-007', // EKG-IGD-01 (disetujui, belum mutasi serah terima)
                'kembali'         => null,
            ],

            // 9. DIAJUKAN - Antrean Baru Menunggu Otorisasi (02 Oktober 2026)
            // Kasus: IGD mengajukan pinjaman Lampu Tindakan LED dari Gudang Sentral
            [
                'kode'            => 'PINJ-2026-009',
                'tipe_penyedia'   => 'GUDANG',
                'unit_penyedia'   => null,
                'gudang_penyedia' => $gudangSentral->id,
                'unit_peminjam'   => $unitIgd->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-10-02',
                'tgl_kembali'     => '2026-10-15',
                'keperluan'       => 'Penerangan tambahan di ruang hecting dan penjahitan luka IGD shift malam.',
                'status'          => 'DIAJUKAN',
                'disetujui_oleh'  => null,
                'catatan'         => 'Menunggu persetujuan Admin Logistik.',
                'aset_kode'       => 'AST-2026-040', // POOL-LAMP-01 (masih berstatus TERSEDIA di gudang)
                'kembali'         => null,
            ],

            // 10. DIAJUKAN - Antrean Baru Antar-Ruangan (02 Oktober 2026)
            // Kasus: ICU meminjam Nebulizer Medis dari Rawat Inap Teratai
            [
                'kode'            => 'PINJ-2026-010',
                'tipe_penyedia'   => 'RUANGAN',
                'unit_penyedia'   => $unitRawat1->id,
                'gudang_penyedia' => null,
                'unit_peminjam'   => $unitIcu->id,
                'diajukan_oleh'   => $adminLogistik->id,
                'tgl_pinjam'      => '2026-10-02',
                'tgl_kembali'     => '2026-10-12',
                'keperluan'       => 'Terapi inhalasi bronkodilator untuk pasien asma akut berat di ICU.',
                'status'          => 'DIAJUKAN',
                'disetujui_oleh'  => null,
                'catatan'         => 'Menunggu otorisasi Kepala Ruangan Rawat Inap.',
                'aset_kode'       => 'AST-2026-022', // NEB-RWT-01
                'kembali'         => null,
            ],

            // 11. SELESAI - Peminjaman Selesai & Telah Dikembalikan (Bulan September 2026)
            // Kasus: Farmasi meminjam Printer Epson IGD untuk print stok opname dan sudah tuntas dikembalikan
            [
                'kode'            => 'PINJ-2026-011',
                'tipe_penyedia'   => 'RUANGAN',
                'unit_penyedia'   => $unitIgd->id,
                'gudang_penyedia' => null,
                'unit_peminjam'   => $unitFarm->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-09-01',
                'tgl_kembali'     => '2026-09-15',
                'keperluan'       => 'Pencetakan formulir kartu stok dan label barcode opname fisik farmasi.',
                'status'          => 'SELESAI',
                'disetujui_oleh'  => $karuIgd->id,
                'catatan'         => 'Peminjaman tuntas, fisik telah dikembalikan dan diverifikasi.',
                'aset_kode'       => 'AST-2026-002', // PRINTER-IGD-2
                'kembali'         => [
                    'kode'        => 'RTN-202609-001',
                    'tgl_kembali' => '2026-09-15',
                    'kondisi'     => 'BAIK',
                    'catatan'     => 'Fisik printer kembali lengkap, kabel USB dan kabel power utuh, hasil print bersih.',
                ],
            ],

            // 12. SELESAI - Peminjaman Selesai dari Gudang Sentral (Bulan September 2026)
            // Kasus: IGD meminjam Kursi Roda dari Gudang Sentral dan sudah dikembalikan
            [
                'kode'            => 'PINJ-2026-012',
                'tipe_penyedia'   => 'GUDANG',
                'unit_penyedia'   => null,
                'gudang_penyedia' => $gudangSentral->id,
                'unit_peminjam'   => $unitIgd->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-09-05',
                'tgl_kembali'     => '2026-09-20',
                'keperluan'       => 'Dukungan transfer evakuasi pasien saat simulasi akreditasi KARS.',
                'status'          => 'SELESAI',
                'disetujui_oleh'  => $adminLogistik->id,
                'catatan'         => 'Peminjaman selesai sesuai jadwal.',
                'aset_kode'       => 'AST-2026-037', // POOL-KR-01
                'kembali'         => [
                    'kode'        => 'RTN-202609-002',
                    'tgl_kembali' => '2026-09-19',
                    'kondisi'     => 'BAIK',
                    'catatan'     => 'Roda, rem dan pijakan kaki berfungsi normal.',
                ],
            ],

            // 13. DITOLAK - Permintaan Ditolak Penyedia
            [
                'kode'            => 'PINJ-2026-013',
                'tipe_penyedia'   => 'RUANGAN',
                'unit_penyedia'   => $unitIgd->id,
                'gudang_penyedia' => null,
                'unit_peminjam'   => $unitRawat1->id,
                'diajukan_oleh'   => $stafIgd->id,
                'tgl_pinjam'      => '2026-09-24',
                'tgl_kembali'     => '2026-10-05',
                'keperluan'       => 'Peminjaman Brankar IGD untuk cadangan ruang rawat inap.',
                'status'          => 'DITOLAK',
                'disetujui_oleh'  => $karuIgd->id,
                'catatan'         => 'Ditolak: Brankar IGD disiagakan penuh untuk antisipasi lonjakan rujukan IGD.',
                'aset_kode'       => 'AST-2026-009',
                'kembali'         => null,
            ],

            // 14. DIBATALKAN - Permintaan Dibatalkan oleh Pemohon
            [
                'kode'            => 'PINJ-2026-014',
                'tipe_penyedia'   => 'GUDANG',
                'unit_penyedia'   => null,
                'gudang_penyedia' => $gudangSentral->id,
                'unit_peminjam'   => $unitLab->id,
                'diajukan_oleh'   => $adminLogistik->id,
                'tgl_pinjam'      => '2026-09-27',
                'tgl_kembali'     => '2026-10-10',
                'keperluan'       => 'Peminjaman Lampu Tindakan LED untuk pemeriksaan spesimen lapangan.',
                'status'          => 'DIBATALKAN',
                'disetujui_oleh'  => null,
                'catatan'         => 'Dibatalkan oleh pemohon: Kegiatan sampling lapangan ditunda.',
                'aset_kode'       => 'AST-2026-040',
                'kembali'         => null,
            ],
        ];

        DB::transaction(function () use ($pinjamList, $adminLogistik) {
            foreach ($pinjamList as $item) {
                $aset = Aset::where('kode_aset', $item['aset_kode'])->first();
                if (! $aset) continue;

                // 1. Simpan Header Permintaan Peminjaman
                $pinjam = PermintaanPeminjaman::firstOrCreate(
                    ['kode' => $item['kode']],
                    [
                        'tipe_penyedia'           => $item['tipe_penyedia'],
                        'unit_penyedia_id'        => $item['unit_penyedia'],
                        'gudang_penyedia_id'      => $item['gudang_penyedia'],
                        'unit_id'                 => $item['unit_peminjam'],
                        'diajukan_oleh'           => $item['diajukan_oleh'],
                        'tanggal_pinjam'          => $item['tgl_pinjam'],
                        'tanggal_kembali_rencana'  => $item['tgl_kembali'],
                        'keperluan'               => $item['keperluan'],
                        'status'                  => $item['status'],
                        'disetujui_oleh'          => $item['disetujui_oleh'],
                        'catatan'                 => $item['catatan'],
                        'dibuat_pada'             => Carbon::parse($item['tgl_pinjam'] . ' 08:30:00'),
                        'diperbarui_pada'         => Carbon::parse($item['tgl_pinjam'] . ' 09:00:00'),
                    ]
                );

                // 2. Simpan Detail Permintaan Peminjaman
                $detail = PermintaanPeminjamanDetail::firstOrCreate(
                    [
                        'permintaan_peminjaman_id' => $pinjam->id,
                        'aset_id'                  => $aset->id,
                    ],
                    [
                        'kondisi_saat_pinjam' => 'BAIK',
                        'catatan'             => 'Alat inventaris dalam kondisi laik pakai.',
                    ]
                );

                // 3. Mutasi status kepemilikan aset sesuai siklus peminjaman
                if ($item['status'] === 'DIPINJAM') {
                    // Chain of custody: kepemilikan operasional dialihkan ke unit peminjam
                    // Memenuhi constraint chk_aset_lokasi_xor (unit_id NOT NULL, gudang_id NULL)
                    $aset->update([
                        'status'           => 'DIPINJAM',
                        'unit_id'          => $item['unit_peminjam'],
                        'gudang_id'        => null,
                        'lokasi_gudang_id' => null,
                    ]);
                }

                // 4. Jika transaksi sudah selesai dikembalikan (Pengembalian)
                if (! empty($item['kembali']) && $item['status'] === 'SELESAI') {
                    $kmb = $item['kembali'];

                    $pengembalian = PengembalianPeminjaman::firstOrCreate(
                        ['kode' => $kmb['kode']],
                        [
                            'permintaan_peminjaman_id' => $pinjam->id,
                            'tanggal_kembali_aktual'   => $kmb['tgl_kembali'],
                            'diperiksa_oleh'           => $adminLogistik->id,
                            'catatan'                  => $kmb['catatan'],
                            'dibuat_pada'              => Carbon::parse($kmb['tgl_kembali'] . ' 14:00:00'),
                        ]
                    );

                    PengembalianPeminjamanDetail::firstOrCreate(
                        [
                            'pengembalian_peminjaman_id'      => $pengembalian->id,
                            'permintaan_peminjaman_detail_id' => $detail->id,
                            'aset_id'                         => $aset->id,
                        ],
                        [
                            'kondisi_saat_kembali' => $kmb['kondisi'],
                            'catatan'              => $kmb['catatan'],
                        ]
                    );

                    // Kembalikan lokasi aset ke penyedia semula
                    if ($item['tipe_penyedia'] === 'GUDANG') {
                        $aset->update([
                            'status'           => 'TERSEDIA',
                            'unit_id'          => null,
                            'gudang_id'        => $item['gudang_penyedia'],
                            'lokasi_gudang_id' => null,
                            'kondisi'          => $kmb['kondisi'],
                        ]);
                    } else {
                        $aset->update([
                            'status'           => 'DIGUNAKAN',
                            'unit_id'          => $item['unit_penyedia'],
                            'gudang_id'        => null,
                            'lokasi_gudang_id' => null,
                            'kondisi'          => $kmb['kondisi'],
                        ]);
                    }
                }
            }
        });
    }
}
