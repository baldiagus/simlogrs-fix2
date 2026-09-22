<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PermintaanDistribusi;
use App\Services\Distribusi\DistribusiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GudangApiController extends ApiController
{
    /**
     * Verifikasi barcode/QR batch & eksekusi picking FEFO via mobile scanner.
     */
    public function scanBatch(Request $request, DistribusiService $service): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('create_gudang_stok::picking::distribusi') && ! $user->can('page_AdminLogistikDashboard')) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk eksekusi picking FEFO.', 403);
        }

        $request->validate([
            'permintaan_id' => 'required|exists:permintaan_distribusi,id',
            'gudang_id'     => 'nullable|exists:gudang,id',
        ]);

        $permintaan = PermintaanDistribusi::with(['detail.barang', 'unit'])->find($request->input('permintaan_id'));

        if (! in_array($permintaan->status, ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'])) {
            return $this->jsonError("Permintaan {$permintaan->kode} belum dalam status siap picking (status: {$permintaan->status}).", 422);
        }

        try {
            $gudangId = $request->input('gudang_id') ?: $permintaan->gudang_id;
            $sppb = $service->eksekusiPickingFefo($permintaan, $user, $gudangId);

            $sppb->load(['detail.barang', 'detail.stokBatch', 'unit']);

            return $this->jsonSuccess([
                'sppb_kode'      => $sppb->kode,
                'permintaan_id'  => $permintaan->id,
                'unit_tujuan'    => $sppb->unit?->nama,
                'tanggal_kirim'  => $sppb->tanggal_kirim?->format('Y-m-d'),
                'status'         => $sppb->status,
                'alokasi_batch'  => $sppb->detail->map(fn ($d) => [
                    'barang'      => $d->barang?->nama,
                    'nomor_batch' => $d->stokBatch?->nomor_batch,
                    'expired'     => $d->stokBatch?->tanggal_kedaluwarsa?->format('Y-m-d'),
                    'qty_dikirim' => $d->jumlah_dikirim,
                ]),
            ], "Picking FEFO berhasil. SPPB {$sppb->kode} telah diterbitkan.");
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }
}
