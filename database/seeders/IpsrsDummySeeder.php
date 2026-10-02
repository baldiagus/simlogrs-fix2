<?php

namespace Database\Seeders;

use App\Models\Aset;
use App\Models\Barang;
use App\Models\JadwalMaintenance;
use App\Models\LogMaintenance;
use App\Models\PenugasanMaintenance;
use App\Models\PergerakanStok;
use App\Models\PermintaanMaintenance;
use App\Models\StokBatch;
use App\Models\SukuCadangMaintenance;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IpsrsDummySeeder extends Seeder
{
    /**
     * Menanam data simulasi pemeliharaan sarana dan alat medis:
     * Tiket Kerusakan, SPK Teknisi, Lembar Kerja, Konsumsi Sparepart FEFO,
     * Rekam Medis Aset, dan Jadwal Servis Preventif Periodik.
     */
    public function run(): void
    {
        $karuIpsrs     = User::where('email', 'karu_ipsrs@simlogrs.local')->first() ?? User::first();
        $teknisiAgus   = User::where('email', 'teknisi@simlogrs.local')->first() ?? User::first();
        $pelaporIgd    = User::where('email', 'petugasunit@simlogrs.local')->first() ?? User::first();
        $pelaporKaru   = User::where('email', 'kepalaunit@simlogrs.local')->first() ?? User::first();

        $units   = Unit::all()->keyBy('kode');
        $asets   = Aset::all()->keyBy('kode_aset');
        $barangs = Barang::all()->keyBy('kode');
        $batches = StokBatch::all();

        // 1. Data Tiket Kerusakan & SPK Servis (Mei - Oktober 2026)
        $maintenanceTickets = [
            // Tiket 1: Selesai - AC Split Daikin IGD bocor dan kurang freon
            [
                'kode'        => 'MNT-202605-001',
                'aset_kode'   => 'AST-2026-030', // AC-IPSRS-01
                'unit_kode'   => 'IGD',
                'prioritas'   => 'TINGGI',
                'status'      => 'SELESAI',
                'keluhan'     => 'AC ruangan triase IGD meneteskan air dan hembusan udara tidak dingin sejak kemarin.',
                'tgl_lapor'   => '2026-05-18 08:30:00',
                'spk' => [
                    'kode'        => 'SPK-202605-001',
                    'tgl_spk'     => '2026-05-18',
                    'target'      => '2026-05-19',
                    'status'      => 'SELESAI',
                    'instruksi'   => 'Bilas evaporator, bersihkan talang pembuangan, dan cek tekanan freon R32.',
                    'log' => [
                        'tgl_kerja' => '2026-05-18 10:00:00',
                        'mulai'     => '10:00',
                        'selesai'   => '11:45',
                        'diagnosa'  => 'Drain pembuangan tersumbat lumut dan tekanan freon turun menjadi 65 PSI.',
                        'tindakan'  => 'Pembersihan saluran drain, perbaikan insulasi, dan penambahan freon R32.',
                        'hasil'     => 'BERHASIL',
                        'catatan'   => 'Suhu ruangan kembali normal 20°C, saluran pembuangan lancar.',
                        'parts'     => [
                            ['brg' => 'BRG-2026-003', 'qty' => 1], // Freon R32
                        ],
                    ],
                ],
            ],

            // Tiket 2: Selesai - Defibrillator Mindray di IGD indikator baterai lemah
            [
                'kode'        => 'MNT-202606-001',
                'aset_kode'   => 'AST-2026-006', // DEFIB-IGD-01
                'unit_kode'   => 'IGD',
                'prioritas'   => 'CITO',
                'status'      => 'SELESAI',
                'keluhan'     => 'Alarm baterai berbunyi terus saat kabel daya dicabut, fungsi DC shock berisiko gagal.',
                'tgl_lapor'   => '2026-06-10 09:15:00',
                'spk' => [
                    'kode'        => 'SPK-202606-001',
                    'tgl_spk'     => '2026-06-10',
                    'target'      => '2026-06-11',
                    'status'      => 'SELESAI',
                    'instruksi'   => 'Lakukan penggantian unit baterai Li-ion internal dan uji pelepasan daya joule.',
                    'log' => [
                        'tgl_kerja' => '2026-06-10 13:00:00',
                        'mulai'     => '13:00',
                        'selesai'   => '14:30',
                        'diagnosa'  => 'Sel baterai lithium internal aus (tegangan drop di bawah 11.2V).',
                        'tindakan'  => 'Penggantian baterai modul baru, kalibrasi self-test energi 200 Joule.',
                        'hasil'     => 'BERHASIL',
                        'catatan'   => 'Semua pengujian lolos, self-test lulus tanpa error code.',
                        'parts'     => [
                            ['brg' => 'BRG-2026-037', 'qty' => 1], // Baterai Defibrillator
                        ],
                    ],
                ],
            ],

            // Tiket 3: Selesai - Suction Pump Portable ICU motor tersumbat
            [
                'kode'        => 'MNT-202607-001',
                'aset_kode'   => 'AST-2026-008', // SUCT-IGD-01
                'unit_kode'   => 'ICU',
                'prioritas'   => 'TINGGI',
                'status'      => 'SELESAI',
                'keluhan'     => 'Daya hisap pompa berkurang drastis, selang hisap keruh dan retak di sambungan jar.',
                'tgl_lapor'   => '2026-07-22 14:00:00',
                'spk' => [
                    'kode'        => 'SPK-202607-001',
                    'tgl_spk'     => '2026-07-22',
                    'target'      => '2026-07-23',
                    'status'      => 'SELESAI',
                    'instruksi'   => 'Ganti selang silikon medis dan pasang filter hidrofobik baru.',
                    'log' => [
                        'tgl_kerja' => '2026-07-23 09:30:00',
                        'mulai'     => '09:30',
                        'selesai'   => '10:45',
                        'diagnosa'  => 'Filter antibakteri tersumbat cairan busa dan selang silikon getas.',
                        'tindakan'  => 'Penggantian filter hidrofobik dan 2 meter selang silikon medis grade.',
                        'hasil'     => 'BERHASIL',
                        'catatan'   => 'Vakum mencapai -0.08 MPa, kelaikan operasi siap pakai.',
                        'parts'     => [
                            ['brg' => 'BRG-2026-044', 'qty' => 1], // Filter Suction
                            ['brg' => 'BRG-2026-043', 'qty' => 2], // Selang Silikon (2 meter)
                        ],
                    ],
                ],
            ],

            // Tiket 4: Selesai - Patient Monitor Mindray ICU sensor SpO2 tidak membaca nadi
            [
                'kode'        => 'MNT-202608-001',
                'aset_kode'   => 'AST-2026-014', // MON-ICU-01
                'unit_kode'   => 'ICU',
                'prioritas'   => 'CITO',
                'status'      => 'SELESAI',
                'keluhan'     => 'Grafik plethysmogram terputus-putus dan sensor finger probe SpO2 sering hilang sinyal.',
                'tgl_lapor'   => '2026-08-14 11:20:00',
                'spk' => [
                    'kode'        => 'SPK-202608-001',
                    'tgl_spk'     => '2026-08-14',
                    'target'      => '2026-08-15',
                    'status'      => 'SELESAI',
                    'instruksi'   => 'Ganti sensor probe SpO2 pin 6 dengan part original Mindray.',
                    'log' => [
                        'tgl_kerja' => '2026-08-14 13:15:00',
                        'mulai'     => '13:15',
                        'selesai'   => '14:00',
                        'diagnosa'  => 'Kabel konektor probe SpO2 terkelupas di pangkal penjepit jari.',
                        'tindakan'  => 'Penggantian sensor finger probe dewasa baru dan uji kalibrasi simulator.',
                        'hasil'     => 'BERHASIL',
                        'catatan'   => 'Grafik SpO2 stabil membaca 99% pada uji simulator.',
                        'parts'     => [
                            ['brg' => 'BRG-2026-054', 'qty' => 1], // Sensor SpO2
                        ],
                    ],
                ],
            ],

            // Tiket 5: Selesai - Bed Pasien Elektrik Rawat Inap remote macet
            [
                'kode'        => 'MNT-202608-002',
                'aset_kode'   => 'AST-2026-018', // BED-RWT-01
                'unit_kode'   => 'RAWAT_1',
                'prioritas'   => 'SEDANG',
                'status'      => 'SELESAI',
                'keluhan'     => 'Posisi kepala bed pasien tidak dapat dinaikkan saat tombol hand switch ditekan.',
                'tgl_lapor'   => '2026-08-25 15:40:00',
                'spk' => [
                    'kode'        => 'SPK-202608-002',
                    'tgl_spk'     => '2026-08-26',
                    'target'      => '2026-08-27',
                    'status'      => 'SELESAI',
                    'instruksi'   => 'Periksa sambungan kabel aktuator dan soket hand switch remote.',
                    'log' => [
                        'tgl_kerja' => '2026-08-26 10:00:00',
                        'mulai'     => '10:00',
                        'selesai'   => '11:15',
                        'diagnosa'  => 'Kabel spiral remote hand switch putus jalur pin atas.',
                        'tindakan'  => 'Penggantian modul hand switch remote Paramount Bed.',
                        'hasil'     => 'BERHASIL',
                        'catatan'   => 'Gerakan 3 crank naik turun dan backrest lancar.',
                        'parts'     => [
                            ['brg' => 'BRG-2026-049', 'qty' => 1], // Hand Switch Remote
                        ],
                    ],
                ],
            ],

            // Tiket 6: Selesai - Lampu Operasi Minor IGD mati total
            [
                'kode'        => 'MNT-202609-001',
                'aset_kode'   => 'AST-2026-009', // BRK-IGD-01 (Lampu tindakan IGD)
                'unit_kode'   => 'IGD',
                'prioritas'   => 'TINGGI',
                'status'      => 'SELESAI',
                'keluhan'     => 'Lampu tindakan tindakan bedah minor IGD mati saat switch dinyalakan.',
                'tgl_lapor'   => '2026-09-08 16:00:00',
                'spk' => [
                    'kode'        => 'SPK-202609-001',
                    'tgl_spk'     => '2026-09-09',
                    'target'      => '2026-09-10',
                    'status'      => 'SELESAI',
                    'instruksi'   => 'Cek sekring pengaman dan ganti bohlam LED tindakan jika putus.',
                    'log' => [
                        'tgl_kerja' => '2026-09-09 08:45:00',
                        'mulai'     => '08:45',
                        'selesai'   => '09:30',
                        'diagnosa'  => 'Fuse kaca putus akibat lonjakan arus dan bohlam LED aus.',
                        'tindakan'  => 'Penggantian fuse 2A dan bohlam lampu LED tindakan medis 12V 50W.',
                        'hasil'     => 'BERHASIL',
                        'catatan'   => 'Intensitas cahaya lux terang, kelaikan tindakan aman.',
                        'parts'     => [
                            ['brg' => 'BRG-2026-040', 'qty' => 1], // Lampu LED Tindakan
                            ['brg' => 'BRG-2026-058', 'qty' => 1], // Fuse Kaca 2A
                        ],
                    ],
                ],
            ],

            // Tiket 7: SPK DITUGASKAN - Infusion Pump Terumo IGD error Occlusion
            [
                'kode'        => 'MNT-202609-002',
                'aset_kode'   => 'AST-2026-011', // INF-IGD-01
                'unit_kode'   => 'IGD',
                'prioritas'   => 'TINGGI',
                'status'      => 'DITUGASKAN',
                'keluhan'     => 'Alarm occlusion sering berbunyi palsu padahal selang infus tidak terjepit.',
                'tgl_lapor'   => '2026-09-29 10:15:00',
                'spk' => [
                    'kode'        => 'SPK-202609-002',
                    'tgl_spk'     => '2026-09-29',
                    'target'      => '2026-10-03',
                    'status'      => 'DITUGASKAN',
                    'instruksi'   => 'Kalibrasi sensor tekanan peristaltik dan bersihkan kotoran pada finger door.',
                    'log'         => null, // Sedang dalam pengerjaan
                ],
            ],

            // Tiket 8: SPK DITERIMA - EKG 12 Lead IGD kabel lead 3 noise
            [
                'kode'        => 'MNT-202609-003',
                'aset_kode'   => 'AST-2026-007', // EKG-IGD-01
                'unit_kode'   => 'IGD',
                'prioritas'   => 'SEDANG',
                'status'      => 'DITUGASKAN',
                'keluhan'     => 'Hasil rekaman lead V1-V3 banyak interferensi artefak garis dasar.',
                'tgl_lapor'   => '2026-09-30 11:00:00',
                'spk' => [
                    'kode'        => 'SPK-202609-003',
                    'tgl_spk'     => '2026-10-01',
                    'target'      => '2026-10-05',
                    'status'      => 'DITERIMA',
                    'instruksi'   => 'Uji resistansi kabel lead wire pasien dan konektor ground.',
                    'log'         => null,
                ],
            ],

            // Tiket 9: DILAPORKAN - Centrifuge Laboratorium bergetar keras (Antrean Tiket Baru)
            [
                'kode'        => 'MNT-202610-003',
                'aset_kode'   => 'AST-2026-023', // CENT-LAB-01
                'unit_kode'   => 'LAB',
                'prioritas'   => 'TINGGI',
                'status'      => 'DILAPORKAN',
                'keluhan'     => 'Mesin centrifuge bergetar tidak seimbang saat rotasi di atas 3000 RPM.',
                'tgl_lapor'   => '2026-10-02 08:20:00',
                'spk'         => null, // Menunggu disposisi SPK dari Koordinator IPSRS
            ],

            // Tiket 10: DILAPORKAN - AC Split Farmasi kurang dingin (Antrean Tiket Baru)
            [
                'kode'        => 'MNT-202610-004',
                'aset_kode'   => 'AST-2026-026', // KLK-FRM-01
                'unit_kode'   => 'FARMASI',
                'prioritas'   => 'SEDANG',
                'status'      => 'DILAPORKAN',
                'keluhan'     => 'Suhu ruang penyimpanan sirup obat naik ke 26°C, perlu pengecekan kompresor.',
                'tgl_lapor'   => '2026-10-02 09:10:00',
                'spk'         => null,
            ],

            // Tiket 11: DIBATALKAN & Prioritas RENDAH (Engsel lemari berderit)
            [
                'kode'        => 'MNT-202609-004',
                'aset_kode'   => 'AST-2026-020', // KR-RWT-01
                'unit_kode'   => 'RAWAT_1',
                'prioritas'   => 'RENDAH',
                'status'      => 'DIBATALKAN',
                'keluhan'     => 'Engsel pintu meja samping berderit ringan. Dibatalkan karena sudah dilumasi mandiri oleh perawat ruangan.',
                'tgl_lapor'   => '2026-09-15 14:00:00',
                'spk'         => null,
            ],

            // Tiket 12: SPK DIBATALKAN
            [
                'kode'        => 'MNT-202608-003',
                'aset_kode'   => 'AST-2026-021', // TNS-RWT-01
                'unit_kode'   => 'RAWAT_1',
                'prioritas'   => 'SEDANG',
                'status'      => 'DITUGASKAN',
                'keluhan'     => 'Kabel manset tensimeter bocor halus.',
                'tgl_lapor'   => '2026-08-20 11:30:00',
                'spk' => [
                    'kode'        => 'SPK-202608-004',
                    'tgl_spk'     => '2026-08-21',
                    'target'      => '2026-08-22',
                    'status'      => 'DIBATALKAN',
                    'instruksi'   => 'SPK dibatalkan: Unit tensimeter diganti unit baru dari pengadaan logistik.',
                    'log'         => null,
                ],
            ],

            // Tiket 13: Lembar Kerja GAGAL (IC Rusak Parah)
            [
                'kode'        => 'MNT-202607-002',
                'aset_kode'   => 'AST-2026-004', // PRINTER-AUD-1
                'unit_kode'   => 'SPI',
                'prioritas'   => 'SEDANG',
                'status'      => 'SELESAI',
                'keluhan'     => 'Mainboard printer korsleting dan tercium bau hangus terbakar.',
                'tgl_lapor'   => '2026-07-28 09:00:00',
                'spk' => [
                    'kode'        => 'SPK-202607-003',
                    'tgl_spk'     => '2026-07-28',
                    'target'      => '2026-07-29',
                    'status'      => 'SELESAI',
                    'instruksi'   => 'Pemeriksaan rangkaian power supply dan modul logic board.',
                    'log' => [
                        'tgl_kerja' => '2026-07-29 11:00:00',
                        'mulai'     => '11:00',
                        'selesai'   => '12:30',
                        'diagnosa'  => 'IC power management terbakar parah, jalur tembaga PCB hangus total.',
                        'tindakan'  => 'Uji ukur tegangan komponen, perbaikan tidak memungkinkan secara ekonomis.',
                        'hasil'     => 'GAGAL',
                        'catatan'   => 'Perbaikan gagal. Rekomendasi teknisi: penghapusan aset / usulan penggantian printer baru.',
                        'parts'     => [],
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($maintenanceTickets, $units, $asets, $barangs, $batches, $karuIpsrs, $teknisiAgus, $pelaporIgd, $pelaporKaru) {
            foreach ($maintenanceTickets as $item) {
                $unitObj = $units->get($item['unit_kode']);
                $asetObj = $asets->get($item['aset_kode']);
                if (! $unitObj || ! $asetObj) continue;

                // 1. Simpan Permintaan Maintenance
                $tiket = PermintaanMaintenance::firstOrCreate(
                    ['kode' => $item['kode']],
                    [
                        'aset_id'           => $asetObj->id,
                        'unit_id'           => $unitObj->id,
                        'dilaporkan_oleh'   => $pelaporIgd->id,
                        'prioritas'         => $item['prioritas'],
                        'status'            => $item['status'],
                        'deskripsi_masalah' => $item['keluhan'],
                        'foto_kerusakan'    => null,
                        'dibuat_pada'       => Carbon::parse($item['tgl_lapor']),
                        'diperbarui_pada'   => Carbon::parse($item['tgl_lapor']),
                    ]
                );

                // 2. Simpan Penugasan SPK jika ada
                if (! empty($item['spk'])) {
                    $spkData = $item['spk'];

                    $spk = PenugasanMaintenance::firstOrCreate(
                        ['kode' => $spkData['kode']],
                        [
                            'permintaan_maintenance_id' => $tiket->id,
                            'teknisi_id'                => $teknisiAgus->id,
                            'ditugaskan_oleh'           => $karuIpsrs->id,
                            'tanggal_penugasan'         => $spkData['tgl_spk'],
                            'target_selesai'            => $spkData['target'],
                            'instruksi_khusus'          => $spkData['instruksi'],
                            'status'                    => $spkData['status'],
                            'dibuat_pada'               => Carbon::parse($spkData['tgl_spk'] . ' 09:00:00'),
                            'diperbarui_pada'           => Carbon::parse($spkData['tgl_spk'] . ' 09:30:00'),
                        ]
                    );

                    // 3. Simpan Lembar Kerja jika selesai
                    if (! empty($spkData['log'])) {
                        $logData = $spkData['log'];

                        $log = LogMaintenance::firstOrCreate(
                            [
                                'permintaan_maintenance_id' => $tiket->id,
                                'penugasan_maintenance_id'  => $spk->id,
                            ],
                            [
                                'dikerjakan_oleh'    => $teknisiAgus->id,
                                'tanggal_pengerjaan' => $logData['tgl_kerja'],
                                'jam_mulai'          => $logData['mulai'],
                                'jam_selesai'        => $logData['selesai'],
                                'diagnosa'           => $logData['diagnosa'],
                                'tindakan'           => $logData['tindakan'],
                                'hasil'              => $logData['hasil'],
                                'catatan'            => $logData['catatan'],
                                'foto_pengerjaan'    => null,
                                'dibuat_pada'        => Carbon::parse($logData['tgl_kerja']),
                                'diperbarui_pada'    => Carbon::parse($logData['tgl_kerja']),
                            ]
                        );

                        // 4. Catat Suku Cadang yang Digunakan
                        if (! empty($logData['parts'])) {
                            foreach ($logData['parts'] as $p) {
                                $brgObj = $barangs->get($p['brg']);
                                if (! $brgObj) continue;

                                $batch = StokBatch::where('barang_id', $brgObj->id)->where('status', 'AKTIF')->first();
                                $harga = (float) $brgObj->harga_beli_terakhir;
                                $subtotal = $harga * $p['qty'];

                                SukuCadangMaintenance::firstOrCreate(
                                    [
                                        'permintaan_maintenance_id' => $tiket->id,
                                        'log_maintenance_id'        => $log->id,
                                        'barang_id'                 => $brgObj->id,
                                    ],
                                    [
                                        'stok_batch_id'    => $batch?->id,
                                        'jumlah_digunakan' => $p['qty'],
                                        'harga_satuan'     => $harga,
                                        'subtotal'         => $subtotal,
                                        'dicatat_oleh'     => $teknisiAgus->id,
                                        'dibuat_pada'      => Carbon::parse($logData['tgl_kerja']),
                                        'diperbarui_pada'  => Carbon::parse($logData['tgl_kerja']),
                                    ]
                                );
                            }
                        }
                    }
                }
            }
        });

        // 2. Data Kalender Jadwal Pemeliharaan Preventif (10 Program Servis Rutin)
        $preventiveSchedules = [
            [
                'kode'       => 'PRV-2026-001',
                'aset_kode'  => 'AST-2026-005', // Patient Monitor IGD
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Kalibrasi Tanda Vital NIBP, SpO2 & Kelistrikan Medis',
                'interval_n' => 1,
                'interval_t' => 'BULAN',
                'frekuensi'  => 'BULANAN',
                'tgl_lalu'   => '2026-09-05',
                'tgl_next'   => '2026-10-05',
                'catatan'    => 'Pengecekan rutin akurasi sensor dan kebocoran arus kelistrikan.',
            ],
            [
                'kode'       => 'PRV-2026-002',
                'aset_kode'  => 'AST-2026-006', // Defibrillator IGD
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Pengujian Kapasitor Discharge Joule & Baterai Backup',
                'interval_n' => 2,
                'interval_t' => 'MINGGU',
                'frekuensi'  => 'MINGGUAN',
                'tgl_lalu'   => '2026-09-20',
                'tgl_next'   => '2026-10-04',
                'catatan'    => 'Uji kejut 50J, 100J, 200J pada defibrillator analyzer.',
            ],
            [
                'kode'       => 'PRV-2026-003',
                'aset_kode'  => 'AST-2026-012', // Bed ICU 01
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Pelumasan Mekanik Aktuator, Rem Roda & Pengujian Motor',
                'interval_n' => 3,
                'interval_t' => 'BULAN',
                'frekuensi'  => 'TRIWULAN',
                'tgl_lalu'   => '2026-07-15',
                'tgl_next'   => '2026-10-15',
                'catatan'    => 'Pengecekan central lock dan batas beban aman 250 kg.',
            ],
            [
                'kode'       => 'PRV-2026-004',
                'aset_kode'  => 'AST-2026-017', // USG Portable ICU
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Pembersihan Filter Udara Pendingin & Inspeksi Probe Transduser',
                'interval_n' => 1,
                'interval_t' => 'BULAN',
                'frekuensi'  => 'BULANAN',
                'tgl_lalu'   => '2026-09-12',
                'tgl_next'   => '2026-10-12',
                'catatan'    => 'Pemeriksaan integritas lensa akustik probe convex dan linear.',
            ],
            [
                'kode'       => 'PRV-2026-005',
                'aset_kode'  => 'AST-2026-024', // Mikroskop Lab
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Pembersihan Lensa Optik, Penyetelan Kondensor & Pelumasan Meja Preparat',
                'interval_n' => 1,
                'interval_t' => 'BULAN',
                'frekuensi'  => 'BULANAN',
                'tgl_lalu'   => '2026-09-18',
                'tgl_next'   => '2026-10-18',
                'catatan'    => 'Gunakan cairan pembersih lensa khusus tanpa alkohol konsentrasi tinggi.',
            ],
            [
                'kode'       => 'PRV-2026-006',
                'aset_kode'  => 'AST-2026-030', // AC Split IPSRS
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Cuci Filter AC, Pembersihan Evaporator & Cek Ampere Kompresor',
                'interval_n' => 2,
                'interval_t' => 'BULAN',
                'frekuensi'  => 'BULANAN',
                'tgl_lalu'   => '2026-08-10',
                'tgl_next'   => '2026-10-10',
                'catatan'    => 'Pengecekan getaran blower outdoor unit.',
            ],
            [
                'kode'       => 'PRV-2026-007',
                'aset_kode'  => 'AST-2026-015', // Syringe Pump ICU
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Uji Akurasi Laju Alir Infus (Flow Rate) & Kalibrasi Sensor Plunger',
                'interval_n' => 1,
                'interval_t' => 'BULAN',
                'frekuensi'  => 'BULANAN',
                'tgl_lalu'   => '2026-09-22',
                'tgl_next'   => '2026-10-22',
                'catatan'    => 'Toleransi error laju alir wajib di bawah ±2%.',
            ],
            [
                'kode'       => 'PRV-2026-008',
                'aset_kode'  => 'AST-2026-025', // Kulkas Reagen Lab
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Validasi Sensor Suhu 2-8°C, Defrosting & Pengujian Alarm Suhu',
                'interval_n' => 2,
                'interval_t' => 'MINGGU',
                'frekuensi'  => 'MINGGUAN',
                'tgl_lalu'   => '2026-09-24',
                'tgl_next'   => '2026-10-08',
                'catatan'    => 'Penting untuk menjaga stabilitas reagen darah dan vaksin.',
            ],
            [
                'kode'       => 'PRV-2026-009',
                'aset_kode'  => 'AST-2026-030', // AC-IPSRS-01
                'teknisi_id' => $teknisiAgus->id,
                'jenis'      => 'Overhaul Kompresor dan Uji Tekanan Sistem Pendingin Lama',
                'interval_n' => 6,
                'interval_t' => 'BULAN',
                'frekuensi'  => 'SEMESTER',
                'tgl_lalu'   => '2026-03-10',
                'tgl_next'   => '2026-11-10',
                'catatan'    => 'Jadwal servis sementara dinonaktifkan karena ruangan sedang dalam renovasi plafon.',
                'status'     => 'NONAKTIF',
            ],
        ];

        DB::transaction(function () use ($preventiveSchedules, $asets, $karuIpsrs) {
            foreach ($preventiveSchedules as $p) {
                $asetObj = $asets->get($p['aset_kode']);
                if (! $asetObj) continue;

                JadwalMaintenance::firstOrCreate(
                    ['kode' => $p['kode']],
                    [
                        'aset_id'           => $asetObj->id,
                        'teknisi_id'        => $p['teknisi_id'],
                        'jenis_maintenance' => $p['jenis'],
                        'interval_nilai'    => $p['interval_n'],
                        'interval_tipe'     => $p['interval_t'],
                        'frekuensi'         => $p['frekuensi'],
                        'tanggal_terakhir'  => $p['tgl_lalu'],
                        'tanggal_berikutnya' => $p['tgl_next'],
                        'catatan'           => $p['catatan'],
                        'status'            => $p['status'] ?? 'AKTIF',
                        'dibuat_oleh'       => $karuIpsrs->id,
                    ]
                );
            }
        });
    }
}
