<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AnggaranPengadaan;
use App\Models\PengirimanBarang;
use App\Models\SukuCadangMaintenance;
use App\Models\Unit;
use App\Services\Anggaran\AnggaranRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnggaranApiController extends ApiController
{
    /**
     * Data screening pagu & realisasi pengeluaran logistik ruangan dan suku cadang IPSRS.
     * GET /api/v1/anggaran/realisasi
     */
    public function realisasi(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowed = $user->hasAnyRole(['super_admin', 'admin_logistik'])
            || $user->can('view_any_pengadaan::realisasi::anggaran')
            || $user->can('view_pengadaan::realisasi::anggaran');

        if (! $allowed) {
            return $this->jsonError('Akses ditolak: Anda tidak memiliki wewenang melihat Pagu & Realisasi Anggaran.', 403);
        }

        $tahun = $request->query('tahun', '2026');

        /** @var AnggaranRealtimeService $service */
        // getRingkasanEksekutif() sudah melakukan syncRealisasiAnggaran(); tidak perlu dipanggil ganda.
        $service = app(AnggaranRealtimeService::class);
        $ringkasan = $service->getRingkasanEksekutif($tahun);

        $unitAnggarans = $this->unitAnggarans($service, $tahun);
        $sparepartAnggaran = $this->sparepartAnggaran($tahun, $ringkasan);

        $topAset = $service->getTopAsetBiayaPemeliharaan($tahun, 5)->map(function ($item) {
            return [
                'id'                    => $item->id,
                'kode_aset'             => $item->kode_aset ?? $item->kode ?? '-',
                'nama'                  => $item->nama,
                'lokasi_ruangan'        => $item->unit?->nama ?? 'Sentral',
                'frekuensi_servis'      => (int) $item->frekuensi_servis,
                'total_biaya_sparepart' => (float) ($item->total_biaya_sparepart ?? 0),
            ];
        });

        $riwayatSparepart = $this->riwayatSparepart($tahun);

        return $this->jsonSuccess([
            'tahun'              => (string) $tahun,
            'ringkasan'          => $ringkasan,
            'unit_anggarans'     => $unitAnggarans,
            'sparepart_anggaran' => $sparepartAnggaran,
            'top_aset'           => $topAset,
            'riwayat_sparepart'  => $riwayatSparepart,
        ], 'Data screening pagu & realisasi berhasil dimuat.');
    }

    /**
     * Rincian drilldown pengeluaran satu unit kerja (distribusi & suku cadang).
     * GET /api/v1/anggaran/unit/{unitId}
     */
    public function unitDetail(Request $request, int $unitId): JsonResponse
    {
        $user = $request->user();
        $allowed = $user->hasAnyRole(['super_admin', 'admin_logistik'])
            || $user->can('view_any_pengadaan::realisasi::anggaran')
            || $user->can('view_pengadaan::realisasi::anggaran');

        if (! $allowed) {
            return $this->jsonError('Akses ditolak: Anda tidak memiliki wewenang melihat Pagu & Realisasi Anggaran.', 403);
        }

        $unit = Unit::find($unitId);
        if (! $unit) {
            return $this->jsonError('Unit kerja tidak ditemukan.', 404);
        }

        $tahun = $request->query('tahun', '2026');

        /** @var AnggaranRealtimeService $service */
        $service = app(AnggaranRealtimeService::class);
        $bebanDistribusi = $service->hitungBebanDistribusiUnit($unitId, $tahun);
        $bebanSparepart = $service->hitungBebanSparepartUnit($unitId, $tahun);

        $riwayatSppb = $this->riwayatSppbUnit($unitId, $tahun);
        $riwayatServis = $this->riwayatServisUnit($unitId, $tahun);

        return $this->jsonSuccess([
            'unit' => [
                'id'   => $unit->id,
                'kode' => $unit->kode,
                'nama' => $unit->nama,
            ],
            'tahun'             => (string) $tahun,
            'beban_distribusi'  => (float) $bebanDistribusi,
            'beban_sparepart'   => (float) $bebanSparepart,
            'total_pengeluaran' => (float) ($bebanDistribusi + $bebanSparepart),
            'riwayat_sppb'      => $riwayatSppb,
            'riwayat_servis'    => $riwayatServis,
        ], 'Rincian drilldown unit berhasil dimuat.');
    }

    private function riwayatSppbUnit(int $unitId, $tahun)
    {
        return PengirimanBarang::with(['gudang', 'detail.barang', 'detail.stokBatch'])
            ->where('unit_id', $unitId)
            ->whereIn('status', ['DIKIRIM', 'DITERIMA'])
            ->whereYear('tanggal_kirim', $tahun)
            ->latest('tanggal_kirim')
            ->take(10)
            ->get()
            ->map(function ($sppb) {
                $totalNilai = $sppb->detail->sum(function ($d) {
                    $harga = $d->stokBatch?->harga_satuan ?? 0;
                    return $d->jumlah_dikirim * $harga;
                });

                return [
                    'id'            => $sppb->id,
                    'kode'          => $sppb->kode,
                    'tanggal_kirim' => $sppb->tanggal_kirim?->format('Y-m-d') ?? '-',
                    'gudang_nama'   => $sppb->gudang?->nama ?? 'Gudang Sentral',
                    'status'        => $sppb->status,
                    'total_item'    => $sppb->detail->count(),
                    'total_nilai'   => (float) $totalNilai,
                ];
            });
    }

    private function riwayatServisUnit(int $unitId, $tahun)
    {
        return SukuCadangMaintenance::with(['barang', 'permintaanMaintenance.aset'])
            ->whereHas('permintaanMaintenance', fn ($q) => $q->where('unit_id', $unitId))
            ->whereYear('dibuat_pada', $tahun)
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function ($sp) {
                return [
                    'id'             => $sp->id,
                    'spk_kode'       => $sp->permintaanMaintenance?->kode ?? '-',
                    'aset_nama'      => $sp->permintaanMaintenance?->aset?->nama ?? '-',
                    'sparepart_nama' => $sp->barang?->nama ?? '-',
                    'jumlah'         => (int) $sp->jumlah_digunakan,
                    'subtotal'       => (float) ($sp->subtotal ?? ($sp->jumlah_digunakan * $sp->harga_satuan)),
                    'tanggal'        => $sp->dibuat_pada?->format('Y-m-d') ?? '-',
                ];
            });
    }

    private function unitAnggarans(AnggaranRealtimeService $service, $tahun)
    {
        return Unit::orderBy('nama')
            ->get()
            ->map(function ($unit) use ($service, $tahun) {
                $distribusi = $service->hitungBebanDistribusiUnit($unit->id, $tahun);
                $sparepart = $service->hitungBebanSparepartUnit($unit->id, $tahun);
                $total = $distribusi + $sparepart;

                return [
                    'id'               => $unit->id,
                    'kode'             => $unit->kode,
                    'pos_anggaran'     => $unit->nama,
                    'unit_id'          => $unit->id,
                    'unit_nama'        => $unit->nama,
                    'unit_kode'        => $unit->kode,
                    'pagu'             => 0.0,
                    'realisasi'        => (float) $total,
                    'sisa'             => 0.0,
                    'serapan'          => 0.0,
                    'status'           => 'Normal',
                    'beban_distribusi' => (float) $distribusi,
                    'beban_sparepart'  => (float) $sparepart,
                ];
            });
    }

    private function sparepartAnggaran($tahun, array $ringkasan): array
    {
        $sparepartAnggaranModel = AnggaranPengadaan::where('tahun', (string) $tahun)
            ->where('tipe', 'SPAREPART_IPSRS')
            ->first();

        return [
            'id'           => $sparepartAnggaranModel?->id ?? 0,
            'kode'         => $sparepartAnggaranModel?->kode ?? 'SPAREPART-IPSRS',
            'pos_anggaran' => $sparepartAnggaranModel?->pos_anggaran ?? 'Screening Pemeliharaan Sarana & Sparepart IPSRS',
            'pagu'         => (float) ($sparepartAnggaranModel?->pagu ?? 0),
            'realisasi'    => (float) ($sparepartAnggaranModel?->realisasi ?? $ringkasan['total_realisasi_sparepart']),
            'sisa'         => (float) ($sparepartAnggaranModel?->sisa ?? 0),
            'serapan'      => (float) ($sparepartAnggaranModel?->serapan ?? 0),
            'status'       => $sparepartAnggaranModel?->status ?? 'Normal',
        ];
    }

    private function riwayatSparepart($tahun)
    {
        return SukuCadangMaintenance::with(['barang', 'stokBatch', 'permintaanMaintenance.aset', 'permintaanMaintenance.unit'])
            ->whereYear('dibuat_pada', $tahun)
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function ($sp) {
                return [
                    'id'             => $sp->id,
                    'spk_kode'       => $sp->permintaanMaintenance?->kode ?? '-',
                    'aset_nama'      => $sp->permintaanMaintenance?->aset?->nama ?? '-',
                    'sparepart_nama' => $sp->barang?->nama ?? '-',
                    'nomor_batch'    => $sp->stokBatch?->nomor_batch ?? '-',
                    'jumlah'         => (int) $sp->jumlah_digunakan,
                    'harga_satuan'   => (float) $sp->harga_satuan,
                    'subtotal'       => (float) ($sp->subtotal ?? ($sp->jumlah_digunakan * $sp->harga_satuan)),
                    'tanggal'        => $sp->dibuat_pada?->format('Y-m-d H:i') ?? '-',
                ];
            });
    }
}
