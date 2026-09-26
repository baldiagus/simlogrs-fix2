<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PermintaanDistribusi;
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

        $isCrossUnitViewer = $user && (
            $user->hasRole('pengadaan') ||
            $user->can('page_PengadaanDashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_Dashboard') ||
            $user->can('page_ManajemenDashboard') ||
            $user->can('page_AuditorDashboard')
        );

        $pr = PermintaanPengadaan::whereIn('status', ['DIAJUKAN', 'DISETUJUI'])
            ->when(! $isCrossUnitViewer, function ($q) use ($user) {
                if ($user->unit_id && $user->gudang_id) {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('unit_id', $user->unit_id)
                            ->orWhere('gudang_id', $user->gudang_id);
                    });
                } elseif ($user->unit_id) {
                    $q->where('unit_id', $user->unit_id);
                } elseif ($user->gudang_id) {
                    $q->where('gudang_id', $user->gudang_id);
                } else {
                    $q->where('diajukan_oleh', $user->id);
                }
            })
            ->with(['unit:id,kode,nama', 'gudang:id,kode,nama', 'pengaju:id,nama', 'detail.barang:id,kode,nama'])
            ->latest('dibuat_pada')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'tipe' => 'PR',
                'kode' => $item->kode,
                'unit' => $item->nama_pemohon,
                'tipe_pemohon' => $item->tipe_pemohon,
                'pengaju' => $item->pengaju?->nama,
                'tanggal' => $item->tanggal_permintaan->format('Y-m-d'),
                'urgensi' => $item->urgensi,
                'status' => $item->status,
                'jumlah_item' => $item->detail->count(),
                'items' => $item->detail->map(fn ($d) => [
                    'barang' => $d->barang?->nama,
                    'kode_barang' => $d->barang?->kode,
                    'jumlah' => $d->jumlah,
                ]),
            ]);

        // 2. Permintaan Distribusi (Amprah Ruangan)
        $amprah = PermintaanDistribusi::whereIn('status', ['DIAJUKAN', 'DISETUJUI_KARU'])
            ->when($user->unit_id && ! $user->can('page_AdminLogistikDashboard'), fn ($q) => $q->where('unit_id', $user->unit_id))
            ->with(['unit:id,kode,nama', 'pengaju:id,nama', 'detail.barang:id,kode,nama'])
            ->latest('dibuat_pada')
            ->get()
            ->map(fn ($item) => [
                'id'          => $item->id,
                'tipe'        => 'AMPRAH',
                'kode'        => $item->kode,
                'unit'        => $item->unit?->nama,
                'pengaju'     => $item->pengaju?->nama,
                'tanggal'     => $item->tanggal_permintaan?->format('Y-m-d'),
                'urgensi'     => $item->urgensi,
                'status'      => $item->status,
                'jumlah_item' => $item->detail->count(),
                'items'       => $item->detail->map(fn ($d) => [
                    'barang'      => $d->barang?->nama,
                    'kode_barang' => $d->barang?->kode,
                    'jumlah'      => $d->jumlah,
                ]),
            ]);

        return $this->jsonSuccess([
            'permintaan_pengadaan'  => $pr,
            'permintaan_distribusi' => $amprah,
            'total_pr'              => $pr->count(),
            'total_amprah'          => $amprah->count(),
            'total'                 => $pr->count() + $amprah->count(),
        ], 'Daftar antrean approval berhasil diambil.');
    }

    /**
     * Approve atau reject PR.
     */
    public function approvePr(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $canApprove = $user && (
            $user->can('update_pengadaan::permintaan::pengadaan') ||
            $user->hasRole('pengadaan') ||
            $user->hasRole('super_admin')
        );

        if (! $canApprove) {
            return $this->jsonError('Anda tidak memiliki wewenang untuk menyetujui permintaan pengadaan.', 403);
        }

        $pr = PermintaanPengadaan::with('detail')->find($id);

        if (! $pr) {
            return $this->jsonError('Permintaan pengadaan tidak ditemukan.', 404);
        }

        $request->validate([
            'aksi' => 'required|in:setujui,tolak,serahkan,teruskan',
            'catatan' => 'nullable|string|max:500',
        ]);

        $aksi = $request->input('aksi');

        if ($aksi === 'serahkan' || $aksi === 'teruskan') {
            if ($pr->status !== 'DISETUJUI') {
                return $this->jsonError("Hanya PR berstatus DISETUJUI yang dapat diserahkan ke bagian pengadaan (saat ini: {$pr->status}).", 422);
            }

            $pr->update([
                'status' => 'DITERUSKAN_KE_PENGADAAN',
                'catatan' => $request->input('catatan') ?? $pr->catatan,
            ]);

            return $this->jsonSuccess([
                'kode' => $pr->kode,
                'status' => 'DITERUSKAN_KE_PENGADAAN',
            ], "PR {$pr->kode} berhasil diserahkan ke bagian pengadaan.");
        }

        if ($aksi === 'setujui') {
            if ($pr->status !== 'DIAJUKAN') {
                return $this->jsonError("PR {$pr->kode} tidak dalam status DIAJUKAN (saat ini: {$pr->status}).", 422);
            }

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

    /**
     * Approve atau reject Amprah Ruangan (Permintaan Distribusi) via mobile.
     */
    public function approveAmprah(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $amprah = PermintaanDistribusi::with('detail')->find($id);
        if (! $amprah) {
            return $this->jsonError('Permintaan distribusi tidak ditemukan.', 404);
        }

        $request->validate([
            'aksi'    => 'required|in:setujui,tolak',
            'catatan' => 'nullable|string|max:500',
        ]);

        $aksi = $request->input('aksi');

        // Meja 1: Approval Karu (status DIAJUKAN)
        if ($amprah->status === 'DIAJUKAN') {
            if (! $user->can('update_layanan_unit::approval::karu') && ! $user->can('page_AdminLogistikDashboard')) {
                return $this->jsonError('Anda tidak memiliki wewenang mengesahkan permohonan ruangan ini.', 403);
            }

            if ($user->unit_id && (int) $user->unit_id !== (int) $amprah->unit_id && ! $user->can('page_AdminLogistikDashboard')) {
                return $this->jsonError('Anda hanya berhak mengesahkan permohonan dari unit ruangan Anda sendiri.', 403);
            }

            if ($aksi === 'setujui') {
                $amprah->update([
                    'status'         => 'DISETUJUI_KARU',
                    'disetujui_oleh' => $user->id,
                    'catatan'        => $request->input('catatan') ?? $amprah->catatan,
                ]);
                $amprah->detail->each(fn ($d) => $d->update(['jumlah_disetujui' => $d->jumlah]));

                return $this->jsonSuccess([
                    'kode'   => $amprah->kode,
                    'status' => 'DISETUJUI_KARU',
                ], "Amprah {$amprah->kode} berhasil disetujui Karu.");
            }

            $amprah->update([
                'status'         => 'DITOLAK',
                'disetujui_oleh' => $user->id,
                'catatan'        => $request->input('catatan') ?? $amprah->catatan,
            ]);

            return $this->jsonSuccess([
                'kode'   => $amprah->kode,
                'status' => 'DITOLAK',
            ], "Amprah {$amprah->kode} ditolak oleh Karu.");
        }

        // Meja 2: Approval Admin Logistik (status DISETUJUI_KARU)
        if ($amprah->status === 'DISETUJUI_KARU') {
            if (! $user->can('update_gudang_stok::distribusi::approval') && ! $user->can('page_AdminLogistikDashboard')) {
                return $this->jsonError('Anda tidak memiliki wewenang otorisasi distribusi gudang logistik.', 403);
            }

            if ($aksi === 'setujui') {
                $amprah->update([
                    'status'         => 'DISETUJUI_GUDANG',
                    'disetujui_oleh' => $user->id,
                    'catatan'        => $request->input('catatan') ?? $amprah->catatan,
                ]);

                return $this->jsonSuccess([
                    'kode'   => $amprah->kode,
                    'status' => 'DISETUJUI_GUDANG',
                ], "Amprah {$amprah->kode} disetujui untuk pengeluaran barang gudang.");
            }

            $amprah->update([
                'status'         => 'DITOLAK',
                'disetujui_oleh' => $user->id,
                'catatan'        => $request->input('catatan') ?? $amprah->catatan,
            ]);

            return $this->jsonSuccess([
                'kode'   => $amprah->kode,
                'status' => 'DITOLAK',
            ], "Amprah {$amprah->kode} ditolak oleh Admin Logistik.");
        }

        return $this->jsonError("Amprah {$amprah->kode} tidak dalam status yang dapat diotorisasi (saat ini: {$amprah->status}).", 422);
    }
}
