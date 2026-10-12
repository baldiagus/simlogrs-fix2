<?php

namespace App\Services\Anggaran;

use App\Models\AnggaranPengadaan;
use App\Models\Aset;
use App\Models\PengirimanBarangDetail;
use App\Models\SukuCadangMaintenance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnggaranRealtimeService
{
    /**
     * Menghitung total nilai rupiah pengeluaran barang logistik yang didistribusikan ke unit kerja.
     */
    public function hitungBebanDistribusiUnit(int $unitId, string|int $tahun = '2026'): float
    {
        return (float) PengirimanBarangDetail::whereHas('pengirimanBarang', function ($q) use ($unitId, $tahun) {
            $q->where('unit_id', $unitId)
              ->whereIn('status', ['DIKIRIM', 'DITERIMA'])
              ->whereYear('tanggal_kirim', $tahun);
        })
        ->join('stok_batch', 'pengiriman_barang_detail.stok_batch_id', '=', 'stok_batch.id')
        ->selectRaw('COALESCE(SUM(pengiriman_barang_detail.jumlah_dikirim * stok_batch.harga_satuan), 0) as total')
        ->value('total');
    }

    /**
     * Menghitung total nilai rupiah suku cadang yang dikonsumsi saat servis aset milik unit kerja.
     */
    public function hitungBebanSparepartUnit(int $unitId, string|int $tahun = '2026'): float
    {
        return (float) SukuCadangMaintenance::whereHas('permintaanMaintenance', function ($q) use ($unitId) {
            $q->where('unit_id', $unitId);
        })
        ->whereYear('dibuat_pada', $tahun)
        ->selectRaw('COALESCE(SUM(CASE WHEN subtotal > 0 THEN subtotal ELSE jumlah_digunakan * harga_satuan END), 0) as total')
        ->value('total');
    }

    /**
     * Menghitung total nilai rupiah seluruh distribusi barang logistik ke seluruh rumah sakit.
     */
    public function hitungTotalBebanDistribusiGlobal(string|int $tahun = '2026'): float
    {
        return (float) PengirimanBarangDetail::whereHas('pengirimanBarang', function ($q) use ($tahun) {
            $q->whereIn('status', ['DIKIRIM', 'DITERIMA'])
              ->whereYear('tanggal_kirim', $tahun);
        })
        ->join('stok_batch', 'pengiriman_barang_detail.stok_batch_id', '=', 'stok_batch.id')
        ->selectRaw('COALESCE(SUM(pengiriman_barang_detail.jumlah_dikirim * stok_batch.harga_satuan), 0) as total')
        ->value('total');
    }

    /**
     * Menghitung total nilai rupiah seluruh pemakaian suku cadang maintenance IPSRS rumah sakit.
     */
    public function hitungTotalBebanSparepartIpsrs(string|int $tahun = '2026'): float
    {
        return (float) SukuCadangMaintenance::whereYear('dibuat_pada', $tahun)
            ->selectRaw('COALESCE(SUM(CASE WHEN subtotal > 0 THEN subtotal ELSE jumlah_digunakan * harga_satuan END), 0) as total')
            ->value('total');
    }

    /**
     * Melakukan sinkronisasi otomatis kolom realisasi, sisa, dan serapan pada tabel anggaran_pengadaan.
     */
    public function syncRealisasiAnggaran(string|int $tahun = '2026'): void
    {
        // Satu transaksi: jika audit salah satu anggaran gagal, seluruh sinkronisasi dibatalkan (ADR 0002 B).
        DB::transaction(function () use ($tahun) {
            $daftarAnggaran = AnggaranPengadaan::where('tahun', (string) $tahun)->get();

            foreach ($daftarAnggaran as $anggaran) {
                if ($anggaran->tipe === 'UNIT' && $anggaran->unit_id) {
                    $bebanDistribusi = $this->hitungBebanDistribusiUnit($anggaran->unit_id, $tahun);
                    $bebanSparepart = $this->hitungBebanSparepartUnit($anggaran->unit_id, $tahun);
                    $realisasi = $bebanDistribusi + $bebanSparepart;
                } elseif ($anggaran->tipe === 'SPAREPART_IPSRS') {
                    $realisasi = $this->hitungTotalBebanSparepartIpsrs($tahun);
                } else {
                    continue;
                }

                $pagu = (float) $anggaran->pagu;
                $sisa = max(0, $pagu - $realisasi);
                $serapan = $pagu > 0 ? round(($realisasi / $pagu) * 100, 2) : 0;
                $status = $serapan > 100 ? 'Over-Budget' : ($serapan > 75 ? 'Waspada' : 'Normal');

                $anggaran->update([
                    'realisasi' => $realisasi,
                    'sisa'      => $sisa,
                    'serapan'   => $serapan,
                    'status'    => $status,
                ]);
            }
        });
    }

    /**
     * Menghasilkan ringkasan data eksekutif makro untuk kartu metrik dan dashboard.
     */
    public function getRingkasanEksekutif(string|int $tahun = '2026'): array
    {
        $this->syncRealisasiAnggaran($tahun);

        $totalPagu = (float) AnggaranPengadaan::where('tahun', (string) $tahun)->sum('pagu');
        $totalDistribusi = $this->hitungTotalBebanDistribusiGlobal($tahun);
        $totalSparepart = $this->hitungTotalBebanSparepartIpsrs($tahun);

        $totalRealisasi = $totalDistribusi + $totalSparepart;
        $totalSisa = max(0, $totalPagu - $totalRealisasi);
        $serapanPersen = $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 1) : 0;

        return [
            'total_pagu'                 => $totalPagu,
            'total_realisasi_distribusi' => $totalDistribusi,
            'total_realisasi_sparepart'  => $totalSparepart,
            'total_realisasi_global'     => $totalRealisasi,
            'total_sisa_global'          => $totalSisa,
            'serapan_global_persen'      => $serapanPersen,
        ];
    }

    /**
     * Mengambil daftar 5 aset dengan serapan biaya pemeliharaan/suku cadang tertinggi (Top 5 Maintenance Cost Assets).
     */
    public function getTopAsetBiayaPemeliharaan(string|int $tahun = '2026', int $limit = 5): Collection
    {
        return Aset::select('aset.*')
            ->join('permintaan_maintenance', 'aset.id', '=', 'permintaan_maintenance.aset_id')
            ->join('suku_cadang_maintenance', 'permintaan_maintenance.id', '=', 'suku_cadang_maintenance.permintaan_maintenance_id')
            ->whereYear('suku_cadang_maintenance.dibuat_pada', $tahun)
            ->selectRaw('COALESCE(SUM(CASE WHEN suku_cadang_maintenance.subtotal > 0 THEN suku_cadang_maintenance.subtotal ELSE suku_cadang_maintenance.jumlah_digunakan * suku_cadang_maintenance.harga_satuan END), 0) as total_biaya_sparepart')
            ->selectRaw('COUNT(DISTINCT permintaan_maintenance.id) as frekuensi_servis')
            ->groupBy('aset.id')
            ->orderByDesc('total_biaya_sparepart')
            ->take($limit)
            ->get();
    }
}
