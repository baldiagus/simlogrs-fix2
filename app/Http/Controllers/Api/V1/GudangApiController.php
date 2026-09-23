<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PermintaanDistribusi;
use App\Models\StokBatch;
use App\Models\StokOpname;
use App\Models\StokOpnameDetail;
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
            'permintaan_id' => 'nullable|integer',
            'nomor_batch'   => 'nullable|string',
            'gudang_id'     => 'nullable|exists:gudang,id',
        ]);
        $barcode = trim($request->input('nomor_batch', $request->input('barcode', '')));
        $permintaanId = $request->input('permintaan_id');
        $permintaan = null;

        if ($permintaanId) {
            $permintaan = PermintaanDistribusi::with(['detail.barang', 'unit'])->find($permintaanId);
        }

        if (! $permintaan) {
            $permintaan = PermintaanDistribusi::with(['detail.barang', 'unit'])
                ->whereIn('status', ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'])
                ->latest('id')
                ->first();
        }

        // Jika ada permohonan distribusi yang siap dipicking, jalankan picking FEFO
        if ($permintaan && in_array($permintaan->status, ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'], true)) {
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

        // Jika tidak ada antrean dokumen distribusi, jalankan verifikasi batch langsung di rak
        if ($barcode !== '') {
            $batch = StokBatch::with(['barang', 'lokasiGudang'])
                ->whereRaw('LOWER(nomor_batch) = ?', [strtolower($barcode)])
                ->orWhereHas('barang', fn ($q) => $q->whereRaw('LOWER(kode) = ?', [strtolower($barcode)]))
                ->first();

            if ($batch) {
                $earliest = StokBatch::where('barang_id', $batch->barang_id)
                    ->where('status', 'AKTIF')
                    ->where('jumlah', '>', 0)
                    ->orderByRaw('tanggal_kedaluwarsa ASC NULLS LAST')
                    ->first();

                $isValid = ($earliest && $earliest->id === $batch->id);

                return $this->jsonSuccess([
                    'sppb_kode'      => 'VERIFIKASI-FEFO',
                    'permintaan_id'  => 0,
                    'unit_tujuan'    => 'Pemeriksaan Rak Gudang',
                    'tanggal_kirim'  => now()->toDateString(),
                    'status'         => $isValid ? 'VALID' : 'NON_FEFO',
                    'alokasi_batch'  => [
                        [
                            'barang'      => $batch->barang?->nama,
                            'nomor_batch' => $batch->nomor_batch,
                            'expired'     => $batch->tanggal_kedaluwarsa?->format('Y-m-d'),
                            'qty_dikirim' => $batch->jumlah,
                        ],
                    ],
                ], $isValid ? "Batch '{$batch->nomor_batch}' valid sesuai urutan FEFO." : 'Peringatan: Bukan batch terdekat.');
            }

            return $this->jsonError("Nomor batch atau barcode '{$barcode}' tidak ditemukan di gudang.", 422);
        }

        return $this->jsonError('Belum ada dokumen permohonan distribusi yang siap dipicking.', 422);
    }

    /**
     * Input perhitungan fisik blind count per barcode/nomor batch di depan rak via mobile scanner.
     * POST /v1/gudang/opname-count
     */
    public function opnameCount(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('create_gudang_stok::stok::opname') && ! $user->can('update_gudang_stok::stok::opname') && ! $user->can('page_AdminLogistikDashboard')) {
            return $this->jsonError('Anda tidak memiliki wewenang menginput hasil stock opname.', 403);
        }

        $validated = $request->validate([
            'stok_opname_id' => 'required|exists:stok_opname,id',
            'stok_batch_id'  => 'nullable|exists:stok_batch,id',
            'nomor_batch'    => 'nullable|string',
            'jumlah_fisik'   => 'required|integer|min:0',
            'catatan'        => 'nullable|string|max:500',
        ]);

        $opname = StokOpname::find($validated['stok_opname_id']);
        if ($opname->status !== 'BERJALAN') {
            return $this->jsonError("Sesi stock opname {$opname->kode} tidak sedang dalam status BERJALAN (status: {$opname->status}).", 422);
        }

        // Resolusi baris detail
        $detail = null;
        if (! empty($validated['stok_batch_id'])) {
            $detail = StokOpnameDetail::with(['barang', 'stokBatch'])
                ->where('stok_opname_id', $opname->id)
                ->where('stok_batch_id', $validated['stok_batch_id'])
                ->first();
        } elseif (! empty($validated['nomor_batch'])) {
            $batch = StokBatch::where('gudang_id', $opname->gudang_id)
                ->where('nomor_batch', trim($validated['nomor_batch']))
                ->first();

            if ($batch) {
                $detail = StokOpnameDetail::with(['barang', 'stokBatch'])
                    ->where('stok_opname_id', $opname->id)
                    ->where('stok_batch_id', $batch->id)
                    ->first();
            }
        }

        if (! $detail) {
            return $this->jsonError('Item batch tidak terdaftar pada sesi stock opname ini.', 404);
        }

        $fisik = (int) $validated['jumlah_fisik'];
        $selisih = $fisik - $detail->jumlah_sistem;

        $detail->update([
            'jumlah_fisik' => $fisik,
            'selisih'      => $selisih,
            'catatan'      => $validated['catatan'] ?? $detail->catatan,
        ]);

        return $this->jsonSuccess([
            'opname_kode'   => $opname->kode,
            'detail_id'     => $detail->id,
            'barang'        => $detail->barang?->nama,
            'nomor_batch'   => $detail->stokBatch?->nomor_batch,
            'jumlah_fisik'  => $detail->jumlah_fisik,
            'catatan'       => $detail->catatan,
        ], 'Hasil perhitungan fisik berhasil direkam.');
    }
}
