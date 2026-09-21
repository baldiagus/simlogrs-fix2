<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PermintaanPengadaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends ApiController
{
    /**
     * Daftar PR & Amprah yang menunggu approval user login.
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        $pr = PermintaanPengadaan::where('status', 'DIAJUKAN')
            ->when($user->unit_id, fn ($q) => $q->where('unit_id', $user->unit_id))
            ->with(['unit:id,kode,nama', 'pengaju:id,nama', 'detail.barang:id,kode,nama'])
            ->latest('dibuat_pada')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'tipe' => 'PR',
                'kode' => $item->kode,
                'unit' => $item->unit?->nama,
                'pengaju' => $item->pengaju?->nama,
                'tanggal' => $item->tanggal_permintaan->format('Y-m-d'),
                'urgensi' => $item->urgensi,
                'jumlah_item' => $item->detail->count(),
                'items' => $item->detail->map(fn ($d) => [
                    'barang' => $d->barang?->nama,
                    'kode_barang' => $d->barang?->kode,
                    'jumlah' => $d->jumlah,
                ]),
            ]);

        return $this->jsonSuccess([
            'permintaan_pengadaan' => $pr,
            'total' => $pr->count(),
        ], 'Daftar antrean approval berhasil diambil.');
    }

    /**
     * Approve atau reject PR.
     */
    public function approvePr(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('update_pengadaan::permintaan::pengadaan')) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk menyetujui permintaan pengadaan.', 403);
        }

        $pr = PermintaanPengadaan::with('detail')->find($id);

        if (! $pr) {
            return $this->jsonError('Permintaan pengadaan tidak ditemukan.', 404);
        }

        if ($pr->status !== 'DIAJUKAN') {
            return $this->jsonError("PR {$pr->kode} tidak dalam status DIAJUKAN (saat ini: {$pr->status}).", 422);
        }

        $request->validate([
            'aksi' => 'required|in:setujui,tolak',
            'catatan' => 'nullable|string|max:500',
        ]);

        $aksi = $request->input('aksi');

        if ($aksi === 'setujui') {
            $pr->update([
                'status' => 'DISETUJUI',
                'disetujui_oleh' => $user->id,
                'catatan' => $request->input('catatan') ?? $pr->catatan,
            ]);
            $pr->detail->each(fn ($d) => $d->update(['jumlah_disetujui' => $d->jumlah]));

            return $this->jsonSuccess([
                'kode' => $pr->kode,
                'status' => 'DISETUJUI',
            ], "PR {$pr->kode} berhasil disetujui.");
        }

        // Tolak
        $pr->update([
            'status' => 'DITOLAK',
            'disetujui_oleh' => $user->id,
            'catatan' => $request->input('catatan') ?? $pr->catatan,
        ]);

        return $this->jsonSuccess([
            'kode' => $pr->kode,
            'status' => 'DITOLAK',
        ], "PR {$pr->kode} ditolak.");
    }
}
