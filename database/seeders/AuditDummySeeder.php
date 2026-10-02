<?php

namespace Database\Seeders;

use App\Models\Gudang;
use App\Models\Notifikasi;
use App\Models\TemuanAudit;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditDummySeeder extends Seeder
{
    /**
     * Menanam data simulasi modul Tata Kelola Audit Internal SPI & CAPA:
     * Register Temuan Ketidaksesuaian (Mayor, Moderat, Minor),
     * Tindakan Perbaikan Auditee, Evaluasi Closing CAPA, dan Notifikasi Lonceng.
     */
    public function run(): void
    {
        $auditor   = User::where('email', 'auditor@simlogrs.local')->first() ?? User::first();
        $manajemen = User::where('email', 'manajemen@simlogrs.local')->first() ?? User::first();

        $units   = Unit::all()->keyBy('kode');
        $gudangs = Gudang::all()->keyBy('kode');

        $unitIgd       = $units->get('IGD');
        $unitIcu       = $units->get('ICU');
        $unitRawat1    = $units->get('RAWAT_1');
        $unitLab       = $units->get('LAB');
        $unitFarmasi   = $units->get('FARMASI');
        $unitLogistik  = $units->get('LOGISTIK');
        $gudangSentral = $gudangs->get('GUD_SENTRAL');
        $gudangAtk     = $gudangs->get('GUD_ATK');

        $temuanItems = [
            // 1. Mayor - Closed (Uji Petik Pengadaan PO tanpa PR - Historis Juli 2026)
            [
                'kode'              => 'AUD-202607-001',
                'tanggal_audit'     => '2026-07-15',
                'objek_audit'       => 'Pengadaan Barang Logistik - Bagian Pengadaan (Ref: PO-2026-103)',
                'ringkasan'         => 'Ditemukan penerbitan Pesanan Pembelian (PO) senilai Rp 5.520.000 dengan lampiran dokumen Permintaan Pengadaan (PR) unit yang belum diverifikasi tanda tangan basah/elektronik Karu.',
                'tingkat_risiko'    => 'Mayor',
                'due_date_capa'     => '2026-08-15',
                'status_capa'       => 'Closed',
                'analisis_penyebab' => 'Staf administrasi memproses PO terburu-buru karena stok kertas HVS di gudang menipis di bawah titik pesan.',
                'tindakan_perbaikan'=> 'Diberlakukan validasi sistemik (zero-bypass PR approval) pada alur penerbitan PO dan verifikasi fisik kelengkapan telaah staf.',
                'catatan_evaluasi'  => 'Hasil uji petik tindak lanjut menunjukkan seluruh PO baru telah terikat otomatis pada PR yang sah dan disetujui.',
                'auditor_id'        => $auditor->id,
                'dievaluasi_oleh'   => $auditor->id,
                'unit_id'           => $unitLogistik?->id,
                'gudang_id'         => null,
                'tgl_buat'          => '2026-07-15 10:00:00',
            ],

            // 2. Moderat - Closed (Selisih Fisik Opname Gudang Sentral - Historis Juli 2026)
            [
                'kode'              => 'AUD-202607-002',
                'tanggal_audit'     => '2026-07-20',
                'objek_audit'       => 'Fasilitas Gudang Sentral Logistik Non-Medis (Ref: OPN-202606-001)',
                'ringkasan'         => 'Terdapat selisih fisik minus 1 botol pada batch sabun antiseptik hasil stock opname semester I tanpa Berita Acara Kerusakan saat handling.',
                'tingkat_risiko'    => 'Moderat',
                'due_date_capa'     => '2026-08-20',
                'status_capa'       => 'Closed',
                'analisis_penyebab' => 'Petugas gudang belum segera mendokumentasikan insiden barang pecah ke lembar insiden logistik.',
                'tindakan_perbaikan'=> 'Penerbitan Berita Acara Pemusnahan/Pelepasan nomor BA-LOG/2026/07-12 dan perbaikan rak pallet penahan getaran.',
                'catatan_evaluasi'  => 'Formulir berita acara kerusakan telah diarsipkan rapi dan sistem penyesuaian stok telah terotorisasi.',
                'auditor_id'        => $auditor->id,
                'dievaluasi_oleh'   => $auditor->id,
                'unit_id'           => null,
                'gudang_id'         => $gudangSentral?->id,
                'tgl_buat'          => '2026-07-20 14:30:00',
            ],

            // 3. Minor - Closed (Labeling Kadaluwarsa Batch Rak B2 - Historis Agustus 2026)
            [
                'kode'              => 'AUD-202608-001',
                'tanggal_audit'     => '2026-08-10',
                'objek_audit'       => 'Penyimpanan FEFO Rak B2 - Gudang Sentral',
                'ringkasan'         => 'Stiker penanda warna kode expired (FEFO visual tag) pada 3 karton hand sanitizer lepas akibat kelembaban ruangan.',
                'tingkat_risiko'    => 'Minor',
                'due_date_capa'     => '2026-09-10',
                'status_capa'       => 'Closed',
                'analisis_penyebab' => 'Kualitas perekat stiker label kurang tahan suhu AC gudang.',
                'tindakan_perbaikan'=> 'Penggantian stiker vinyl tahan air dan pengecekan termohigrometer harian.',
                'catatan_evaluasi'  => 'Visual tagging FEFO rak B2 rapi dan terbaca jelas.',
                'auditor_id'        => $auditor->id,
                'dievaluasi_oleh'   => $auditor->id,
                'unit_id'           => null,
                'gudang_id'         => $gudangSentral?->id,
                'tgl_buat'          => '2026-08-10 11:15:00',
            ],

            // 4. Mayor - In Review (Peminjaman Aset Melewati Batas Waktu - Akhir September 2026)
            [
                'kode'              => 'AUD-202609-001',
                'tanggal_audit'     => '2026-09-28',
                'objek_audit'       => 'Sirkulasi Aset Laboratorium (Ref: PINJ-2026-006 Suction Pump)',
                'ringkasan'         => 'Peminjaman alat medis Suction Pump dari Gudang Sentral oleh Unit Laboratorium telah melampaui tanggal kembali rencana (overdue) selama 3 hari tanpa konfirmasi perpanjangan resmi.',
                'tingkat_risiko'    => 'Mayor',
                'due_date_capa'     => '2026-10-15',
                'status_capa'       => 'In Review',
                'analisis_penyebab' => 'Unit laboratorium masih menggunakan alat untuk pengujian cadangan dan belum mengajukan permohonan perpanjangan waktu peminjaman di aplikasi SIMLOGRS.',
                'tindakan_perbaikan'=> 'Unit telah mengajukan klarifikasi tertulis dan menjadwalkan serah terima pengembalian fisik per 5 Oktober 2026.',
                'catatan_evaluasi'  => null, // Menunggu evaluasi auditor
                'auditor_id'        => $auditor->id,
                'dievaluasi_oleh'   => null,
                'unit_id'           => $unitLab?->id,
                'gudang_id'         => null,
                'tgl_buat'          => '2026-09-28 09:00:00',
            ],

            // 5. Moderat - In Progress (Penyimpanan Obat Suhu Terkontrol Farmasi - September 2026)
            [
                'kode'              => 'AUD-202609-002',
                'tanggal_audit'     => '2026-09-29',
                'objek_audit'       => 'Lemari Pendingin Obat Farmasi Rawat Jalan (Ref: AST-2026-026)',
                'ringkasan'         => 'Log pemantauan suhu manual lemari pendingin kulkas obat tidak terisi selama 2 shift akhir pekan, melanggar SOP CDOB (Cara Distribusi Obat yang Baik).',
                'tingkat_risiko'    => 'Moderat',
                'due_date_capa'     => '2026-10-20',
                'status_capa'       => 'In Progress',
                'analisis_penyebab' => null,
                'tindakan_perbaikan'=> null,
                'catatan_evaluasi'  => null,
                'auditor_id'        => $auditor->id,
                'dievaluasi_oleh'   => null,
                'unit_id'           => $unitFarmasi?->id,
                'gudang_id'         => null,
                'tgl_buat'          => '2026-09-29 13:45:00',
            ],

            // 6. Moderat - In Progress (Kepatuhan Waktu Pengiriman Vendor - Oktober 2026)
            [
                'kode'              => 'AUD-202610-001',
                'tanggal_audit'     => '2026-10-01',
                'objek_audit'       => 'Kepatuhan Kontrak Rekanan PT Surya Multi Elektronik (Ref: PO-2026-110)',
                'ringkasan'         => 'Pengiriman pesanan toner printer dan kabel power telah melewati estimasi tanggal kirim (28 September 2026) tanpa ada surat pemberitahuan kendala ekspedisi resmi.',
                'tingkat_risiko'    => 'Moderat',
                'due_date_capa'     => '2026-10-25',
                'status_capa'       => 'In Progress',
                'analisis_penyebab' => null,
                'tindakan_perbaikan'=> null,
                'catatan_evaluasi'  => null,
                'auditor_id'        => $auditor->id,
                'dievaluasi_oleh'   => null,
                'unit_id'           => $unitLogistik?->id,
                'gudang_id'         => null,
                'tgl_buat'          => '2026-10-01 10:30:00',
            ],

            // 7. Minor - In Progress (Tata Letak Rak ATK - Oktober 2026)
            [
                'kode'              => 'AUD-202610-002',
                'tanggal_audit'     => '2026-10-02',
                'objek_audit'       => 'Penataan Kardus Formulir Rak A3 - Gudang ATK',
                'ringkasan'         => 'Ketinggian tumpukan kardus formulir rekam medis melebihi batas aman 2 meter dan menutupi jalur sprinkler pemadam kebakaran.',
                'tingkat_risiko'    => 'Minor',
                'due_date_capa'     => '2026-10-18',
                'status_capa'       => 'In Progress',
                'analisis_penyebab' => null,
                'tindakan_perbaikan'=> null,
                'catatan_evaluasi'  => null,
                'auditor_id'        => $auditor->id,
                'dievaluasi_oleh'   => null,
                'unit_id'           => null,
                'gudang_id'         => $gudangAtk?->id,
                'tgl_buat'          => '2026-10-02 08:30:00',
            ],
        ];

        DB::transaction(function () use ($temuanItems, $auditor) {
            foreach ($temuanItems as $item) {
                TemuanAudit::firstOrCreate(
                    ['kode' => $item['kode']],
                    [
                        'tanggal_audit'     => $item['tanggal_audit'],
                        'objek_audit'       => $item['objek_audit'],
                        'ringkasan'         => $item['ringkasan'],
                        'tingkat_risiko'    => $item['tingkat_risiko'],
                        'due_date_capa'     => $item['due_date_capa'],
                        'status_capa'       => $item['status_capa'],
                        'analisis_penyebab' => $item['analisis_penyebab'],
                        'tindakan_perbaikan'=> $item['tindakan_perbaikan'],
                        'catatan_evaluasi'  => $item['catatan_evaluasi'],
                        'auditor_id'        => $item['auditor_id'],
                        'dievaluasi_oleh'   => $item['dievaluasi_oleh'],
                        'unit_id'           => $item['unit_id'],
                        'gudang_id'         => $item['gudang_id'],
                        'dibuat_pada'       => Carbon::parse($item['tgl_buat']),
                        'diperbarui_pada'   => Carbon::parse($item['tgl_buat']),
                    ]
                );
            }

            // Notifikasi Lonceng Internal
            $notifications = [
                [
                    'pengguna_id' => $auditor->id,
                    'judul'       => 'Peringatan Audit Kepatuhan PO',
                    'pesan'       => 'Pesanan PO-2026-110 telah melewati estimasi pengiriman vendor.',
                    'tautan'      => '/auditor/kepatuhan-pengadaan',
                    'dibaca'      => false,
                    'dibuat_pada' => Carbon::parse('2026-10-01 11:00:00'),
                ],
                [
                    'pengguna_id' => $auditor->id,
                    'judul'       => 'Laporan Tindak Lanjut CAPA Masuk',
                    'pesan'       => 'Unit Laboratorium telah mengirimkan berkas perbaikan untuk AUD-202609-001.',
                    'tautan'      => '/auditor/temuan-capa',
                    'dibaca'      => false,
                    'dibuat_pada' => Carbon::parse('2026-10-02 09:30:00'),
                ],
            ];

            foreach ($notifications as $notif) {
                Notifikasi::firstOrCreate(
                    [
                        'pengguna_id' => $notif['pengguna_id'],
                        'judul'       => $notif['judul'],
                    ],
                    $notif
                );
            }
        });
    }
}
