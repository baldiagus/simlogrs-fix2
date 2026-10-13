<?php

namespace Database\Seeders\Dummy;

use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\Persediaan\PenyesuaianStokDetail;
use App\Models\Persediaan\PergerakanStok;
use App\Models\Persediaan\StokBatch;
use App\Models\Persediaan\StokOpname;
use App\Models\Persediaan\StokOpnameDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersediaanDummySeeder extends Seeder
{
    /**
     * Menanam data simulasi modul Persediaan & Opname Fisik Gudang:
     * Sesi Stock Opname (Blind Count, Selisih Cocok/Kurang/Lebih),
     * Pengajuan Otorisasi Penyesuaian Saldo Batch, dan Mutasi Penyesuaian.
     */
    public function run(): void
    {
        $kepalaGudang  = User::where('email', 'kepalagudang@simlogrs.local')->first() ?? User::first();
        $petugasGudang = User::where('email', 'petugasgudang@simlogrs.local')->first() ?? User::first();
        $adminLogistik = User::where('email', 'adminlogistik@simlogrs.local')->first() ?? User::first();

        $gudangSentral = Gudang::where('kode', 'GUD_SENTRAL')->first() ?? Gudang::first();
        $gudangAtk     = Gudang::where('kode', 'GUD_ATK')->first() ?? $gudangSentral;
        $gudangIpsrs   = Gudang::where('kode', 'GUD_IPSRS')->first() ?? $gudangSentral;
        $gudangB3      = Gudang::where('kode', 'GUD_B3')->first() ?? $gudangSentral;

        // Ambil stok batch yang sudah ada
        $batches = StokBatch::with('barang')->get();
        if ($batches->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($gudangSentral, $gudangAtk, $gudangIpsrs, $gudangB3, $batches, $kepalaGudang, $petugasGudang, $adminLogistik) {
            // Sesi 1: Stock Opname Triwulan II Gudang Sentral (DISETUJUI - Historis Juni 2026)
            $opname1 = StokOpname::firstOrCreate(
                ['kode' => 'OPN-202606-001'],
                [
                    'gudang_id'      => $gudangSentral->id,
                    'tanggal_opname' => '2026-06-30',
                    'status'         => 'DISETUJUI',
                    'catatan'        => 'Stock opname fisik penutupan semester I logistik medis dan bahan habis pakai.',
                    'dibuat_oleh'    => $petugasGudang->id,
                    'disetujui_oleh' => $kepalaGudang->id,
                    'dibuat_pada'    => Carbon::parse('2026-06-30 09:00:00'),
                    'diperbarui_pada'=> Carbon::parse('2026-06-30 17:00:00'),
                ]
            );

            // Detail Opname 1
            $sentralBatches = $batches->where('gudang_id', $gudangSentral->id)->take(6);
            $idx = 0;
            $selisihBatches = [];

            foreach ($sentralBatches as $b) {
                $idx++;
                $sysQty = $b->jumlah;
                // Buat 1 batch selisih kurang (rusak/bocor saat handling)
                if ($idx === 2 && $sysQty >= 2) {
                    $physQty = $sysQty - 1;
                    $catatan = 'Selisih kurang 1 botol pecah di sudut rak saat pemindahan.';
                    $selisihBatches[] = ['batch' => $b, 'diff' => 1, 'type' => 'KURANG', 'alasan' => $catatan];
                } else {
                    $physQty = $sysQty;
                    $catatan = 'Fisik barang di rak cocok sempurna dengan kartu stok sistem.';
                }

                StokOpnameDetail::firstOrCreate(
                    [
                        'stok_opname_id' => $opname1->id,
                        'barang_id'      => $b->barang_id,
                        'stok_batch_id'  => $b->id,
                    ],
                    [
                        'jumlah_sistem'  => $sysQty,
                        'jumlah_fisik'   => $physQty,
                        'selisih'        => $physQty - $sysQty,
                        'catatan'        => $catatan,
                        'dibuat_pada'    => Carbon::parse('2026-06-30 14:00:00'),
                    ]
                );
            }

            // Jika ada selisih, buat dokumen Penyesuaian Stok (DISETUJUI)
            if (! empty($selisihBatches)) {
                $adj1 = PenyesuaianStok::firstOrCreate(
                    ['kode' => 'ADJ-202607-001'],
                    [
                        'stok_opname_id'     => $opname1->id,
                        'gudang_id'          => $gudangSentral->id,
                        'tanggal_penyesuaian'=> '2026-07-01',
                        'alasan'             => 'Penyesuaian saldo fisik hasil Stock Opname Triwulan II (Ref: ' . $opname1->kode . ')',
                        'status'             => 'DISETUJUI',
                        'diajukan_oleh'      => $petugasGudang->id,
                        'disetujui_oleh'     => $kepalaGudang->id,
                        'dibuat_pada'        => Carbon::parse('2026-07-01 09:30:00'),
                        'diperbarui_pada'    => Carbon::parse('2026-07-01 10:15:00'),
                    ]
                );

                foreach ($selisihBatches as $sb) {
                    $bObj = $sb['batch'];
                    $harga = (float) $bObj->harga_satuan;

                    PenyesuaianStokDetail::firstOrCreate(
                        [
                            'penyesuaian_stok_id' => $adj1->id,
                            'barang_id'           => $bObj->barang_id,
                            'stok_batch_id'       => $bObj->id,
                        ],
                        [
                            'jumlah_penyesuaian' => $sb['diff'],
                            'jenis_penyesuaian'  => $sb['type'],
                            'harga_satuan'       => $harga,
                            'subtotal_nilai'     => $harga * $sb['diff'],
                            'catatan'            => $sb['alasan'],
                            'dibuat_pada'        => Carbon::parse('2026-07-01 09:45:00'),
                        ]
                    );

                    // Catat mutasi di pergerakan_stok
                    PergerakanStok::firstOrCreate(
                        [
                            'barang_id'       => $bObj->barang_id,
                            'stok_batch_id'   => $bObj->id,
                            'referensi_jenis' => 'STOCK_OPNAME',
                            'referensi_id'    => $adj1->id,
                        ],
                        [
                            'gudang_id'        => $gudangSentral->id,
                            'lokasi_gudang_id' => $bObj->lokasi_gudang_id,
                            'jenis'            => 'PENYESUAIAN',
                            'jumlah'           => $sb['diff'],
                            'saldo_sebelum'    => $bObj->jumlah + $sb['diff'],
                            'saldo_sesudah'    => $bObj->jumlah,
                            'keterangan'       => 'Otorisasi Penyesuaian Opname ' . $adj1->kode . ' - ' . $sb['alasan'],
                            'dibuat_oleh'      => $kepalaGudang->id,
                            'dibuat_pada'      => Carbon::parse('2026-07-01 10:20:00'),
                        ]
                    );
                }
            }

            // Sesi 2: Stock Opname Gudang ATK & Cetakan (DISETUJUI - Historis Juli 2026)
            $opname2 = StokOpname::firstOrCreate(
                ['kode' => 'OPN-202607-001'],
                [
                    'gudang_id'      => $gudangAtk->id,
                    'tanggal_opname' => '2026-07-31',
                    'status'         => 'DISETUJUI',
                    'catatan'        => 'Uji petik fisik kertas HVS, formulir cetakan rekam medis, dan alat tulis.',
                    'dibuat_oleh'    => $petugasGudang->id,
                    'disetujui_oleh' => $kepalaGudang->id,
                    'dibuat_pada'    => Carbon::parse('2026-07-31 10:00:00'),
                    'diperbarui_pada'=> Carbon::parse('2026-07-31 16:30:00'),
                ]
            );

            $atkBatches = $batches->where('gudang_id', $gudangAtk->id);
            foreach ($atkBatches as $b) {
                StokOpnameDetail::firstOrCreate(
                    [
                        'stok_opname_id' => $opname2->id,
                        'barang_id'      => $b->barang_id,
                        'stok_batch_id'  => $b->id,
                    ],
                    [
                        'jumlah_sistem'  => $b->jumlah,
                        'jumlah_fisik'   => $b->jumlah,
                        'selisih'        => 0,
                        'catatan'        => 'Kuantitas fisik rak A1-A2 cocok 100%.',
                        'dibuat_pada'    => Carbon::parse('2026-07-31 15:00:00'),
                    ]
                );
            }

            // Sesi 3: Stock Opname Gudang IPSRS (MENUNGGU_APPROVAL - Akhir September 2026)
            $opname3 = StokOpname::firstOrCreate(
                ['kode' => 'OPN-202609-001'],
                [
                    'gudang_id'      => $gudangIpsrs->id,
                    'tanggal_opname' => '2026-09-29',
                    'status'         => 'MENUNGGU_APPROVAL',
                    'catatan'        => 'Perhitungan fisik triwulan III suku cadang pendingin ruangan dan sparepart biomedis.',
                    'dibuat_oleh'    => $petugasGudang->id,
                    'disetujui_oleh' => null,
                    'dibuat_pada'    => Carbon::parse('2026-09-29 11:00:00'),
                    'diperbarui_pada'=> Carbon::parse('2026-09-29 16:00:00'),
                ]
            );

            $ipsrsBatches = $batches->where('gudang_id', $gudangIpsrs->id);
            $adjPendingBatches = [];
            $bIdx = 0;

            foreach ($ipsrsBatches as $b) {
                $bIdx++;
                $sysQty = $b->jumlah;

                if ($bIdx === 1 && $sysQty >= 1) {
                    $physQty = $sysQty + 1; // Selisih lebih (ada bonus vendor belum tercatat)
                    $catatan = 'Ditemukan 1 botol freon cadangan belum masuk register penerimaan.';
                    $adjPendingBatches[] = ['batch' => $b, 'diff' => 1, 'type' => 'TAMBAH', 'alasan' => $catatan];
                } else {
                    $physQty = $sysQty;
                    $catatan = 'Stok bin C1-C6 sesuai sistem.';
                }

                StokOpnameDetail::firstOrCreate(
                    [
                        'stok_opname_id' => $opname3->id,
                        'barang_id'      => $b->barang_id,
                        'stok_batch_id'  => $b->id,
                    ],
                    [
                        'jumlah_sistem'  => $sysQty,
                        'jumlah_fisik'   => $physQty,
                        'selisih'        => $physQty - $sysQty,
                        'catatan'        => $catatan,
                        'dibuat_pada'    => Carbon::parse('2026-09-29 15:30:00'),
                    ]
                );
            }

            // Buat pengajuan penyesuaian yang MENUNGGU Otorisasi (untuk antrean approval)
            if (! empty($adjPendingBatches)) {
                $adjPending = PenyesuaianStok::firstOrCreate(
                    ['kode' => 'ADJ-202610-001'],
                    [
                        'stok_opname_id'     => $opname3->id,
                        'gudang_id'          => $gudangIpsrs->id,
                        'tanggal_penyesuaian'=> '2026-10-01',
                        'alasan'             => 'Koreksi saldo fisik hasil Stock Opname IPSRS (Ref: ' . $opname3->kode . ')',
                        'status'             => 'MENUNGGU',
                        'diajukan_oleh'      => $petugasGudang->id,
                        'disetujui_oleh'     => null,
                        'dibuat_pada'        => Carbon::parse('2026-10-01 08:30:00'),
                    ]
                );

                foreach ($adjPendingBatches as $pb) {
                    $bObj = $pb['batch'];
                    $harga = (float) $bObj->harga_satuan;

                    PenyesuaianStokDetail::firstOrCreate(
                        [
                            'penyesuaian_stok_id' => $adjPending->id,
                            'barang_id'           => $bObj->barang_id,
                            'stok_batch_id'       => $bObj->id,
                        ],
                        [
                            'jumlah_penyesuaian' => $pb['diff'],
                            'jenis_penyesuaian'  => $pb['type'],
                            'harga_satuan'       => $harga,
                            'subtotal_nilai'     => $harga * $pb['diff'],
                            'catatan'            => $pb['alasan'],
                            'dibuat_pada'        => Carbon::parse('2026-10-01 08:45:00'),
                        ]
                    );
                }
            }

            // Sesi 4: Stock Opname Gudang Sentral (BERJALAN - Sesi Aktif Awal Oktober 2026)
            $opname4 = StokOpname::firstOrCreate(
                ['kode' => 'OPN-202610-001'],
                [
                    'gudang_id'      => $gudangSentral->id,
                    'tanggal_opname' => '2026-10-02',
                    'status'         => 'BERJALAN',
                    'catatan'        => 'Opname fisik berjalan triwulan IV untuk bahan habis pakai rawat luka dan linen.',
                    'dibuat_oleh'    => $petugasGudang->id,
                    'disetujui_oleh' => null,
                    'dibuat_pada'    => Carbon::parse('2026-10-02 08:00:00'),
                ]
            );

            // Masukkan batch aktif untuk sesi berjalan
            $activeBatches = $batches->where('gudang_id', $gudangSentral->id)->skip(6)->take(4);
            foreach ($activeBatches as $b) {
                StokOpnameDetail::firstOrCreate(
                    [
                        'stok_opname_id' => $opname4->id,
                        'barang_id'      => $b->barang_id,
                        'stok_batch_id'  => $b->id,
                    ],
                    [
                        'jumlah_sistem'  => $b->jumlah,
                        'jumlah_fisik'   => 0, // Belum selesai dihitung
                        'selisih'        => 0 - $b->jumlah,
                        'catatan'        => 'Menunggu input hitung fisik blind count tim pelaksana.',
                        'dibuat_pada'    => Carbon::parse('2026-10-02 08:15:00'),
                    ]
                );
            }

            // Sesi 5: Stock Opname DRAFT (Rencana Opname Gudang B3)
            $opname5 = StokOpname::firstOrCreate(
                ['kode' => 'OPN-202610-002'],
                [
                    'gudang_id'      => $gudangB3->id,
                    'tanggal_opname' => '2026-10-05',
                    'status'         => 'DRAFT',
                    'catatan'        => 'Draft rencana jadwal perhitungan fisik bahan kimia B3 dan klorin IPAL.',
                    'dibuat_oleh'    => $petugasGudang->id,
                    'disetujui_oleh' => null,
                    'dibuat_pada'    => Carbon::parse('2026-10-02 09:30:00'),
                ]
            );

            // Sesi 6: Stock Opname DITOLAK
            $opname6 = StokOpname::firstOrCreate(
                ['kode' => 'OPN-202608-002'],
                [
                    'gudang_id'      => $gudangSentral->id,
                    'tanggal_opname' => '2026-08-28',
                    'status'         => 'DITOLAK',
                    'catatan'        => 'Ditolak: Lembar hitung fisik belum ditandatangani saksi tim independen.',
                    'dibuat_oleh'    => $petugasGudang->id,
                    'disetujui_oleh' => $kepalaGudang->id,
                    'dibuat_pada'    => Carbon::parse('2026-08-28 10:00:00'),
                    'diperbarui_pada'=> Carbon::parse('2026-08-28 16:30:00'),
                ]
            );

            // Penyesuaian Stok DITOLAK
            $adjRejected = PenyesuaianStok::firstOrCreate(
                ['kode' => 'ADJ-202608-002'],
                [
                    'stok_opname_id'     => $opname6->id,
                    'gudang_id'          => $gudangSentral->id,
                    'tanggal_penyesuaian'=> '2026-08-29',
                    'alasan'             => 'Koreksi manual selisih tanpa verifikasi fisik ulang.',
                    'status'             => 'DITOLAK',
                    'diajukan_oleh'      => $petugasGudang->id,
                    'disetujui_oleh'     => $kepalaGudang->id,
                    'dibuat_pada'        => Carbon::parse('2026-08-29 09:00:00'),
                    'diperbarui_pada'    => Carbon::parse('2026-08-29 11:00:00'),
                ]
            );

            $firstBatch = $batches->first();
            if ($firstBatch) {
                PenyesuaianStokDetail::firstOrCreate(
                    [
                        'penyesuaian_stok_id' => $adjRejected->id,
                        'barang_id'           => $firstBatch->barang_id,
                        'stok_batch_id'       => $firstBatch->id,
                    ],
                    [
                        'jumlah_penyesuaian' => 2,
                        'jenis_penyesuaian'  => 'KURANG',
                        'harga_satuan'       => $firstBatch->harga_satuan,
                        'subtotal_nilai'     => $firstBatch->harga_satuan * 2,
                        'catatan'            => 'Ditolak: wajib investigasi lapangan oleh SPI terlebih dahulu.',
                        'dibuat_pada'        => Carbon::parse('2026-08-29 09:15:00'),
                    ]
                );
            }
        });
    }
}
