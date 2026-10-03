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
            $user->hasRole(['pengadaan', 'manajemen', 'auditor', 'super_admin', 'admin_logistik']) ||
            $user->can('page_PengadaanDashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_Dashboard') ||
            $user->can('page_ManajemenDashboard') ||
            $user->can('page_AuditorDashboard')
        );

        $isPengadaanOnly = $user && $user->hasRole('pengadaan') && ! $user->hasRole(['super_admin', 'admin_logistik']);
        $isAdminOrAudit = $user && ($user->hasRole(['super_admin', 'admin_logistik', 'manajemen', 'auditor']) || $user->can('page_AdminLogistikDashboard'));

        $prStatuses = [PermintaanPengadaan::STATUS_DIAJUKAN];
        if ($isPengadaanOnly) {
            $prStatuses = [PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN];
        } elseif ($isAdminOrAudit) {
            $prStatuses = [PermintaanPengadaan::STATUS_DIAJUKAN, PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN];
        }

        $pr = PermintaanPengadaan::whereIn('status', $prStatuses)
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
            ->with(['unit:id,kode,nama', 'gudang:id,kode,nama', 'pengaju:id,nama', 'detail.barang:id,kode,nama,satuan_id,harga_beli_terakhir,harga_estimasi', 'detail.barang.satuan:id,nama,simbol'])
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
                    'id'                  => $d->id,
                    'barang_id'           => $d->barang_id,
                    'barang'              => $d->barang?->nama,
                    'kode_barang'         => $d->barang?->kode,
                    'satuan'              => $d->barang?->satuan?->simbol ?? $d->barang?->satuan?->nama ?? 'Pcs',
                    'jumlah'              => $d->jumlah,
                    'harga_beli_terakhir' => (float) ($d->barang?->harga_beli_terakhir ?? 0),
                    'harga_estimasi'      => (float) ($d->barang?->harga_estimasi ?? 0),
                ]),
            ]);

        // 2. Permintaan Distribusi (Amprah Ruangan)
        $isKaruOnly = $user && ($user->hasRole('kepala_unit') || $user->unit_id) && ! $user->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang']);
        $isGudangRole = $user && ($user->hasRole(['kepala_gudang', 'petugas_gudang']) || $user->gudang_id) && ! $user->hasRole(['super_admin', 'admin_logistik']);
        $isAdminLogistik = $user && $user->hasRole('admin_logistik') && ! $user->hasRole('super_admin');
        $isSuperAdmin = $user && $user->hasRole('super_admin');
        $isAuditOrManajemen = $user && $user->hasRole(['auditor', 'manajemen']);

        if ($isKaruOnly) {
            // Kepala Ruangan (Meja 1): HANYA memproses DIAJUKAN dari unit ruangannya sendiri
            $amprahStatuses = ['DIAJUKAN'];
        } elseif ($isGudangRole || $isAdminLogistik) {
            // Kepala Gudang & Admin Logistik (Meja 2): HANYA memproses amprah yang SUDAH disetujui Karu
            $amprahStatuses = ['DISETUJUI_KARU'];
        } elseif ($isSuperAdmin || $isAuditOrManajemen) {
            // Super Admin & Audit/Manajemen: Dapat memantau seluruh antrean Meja 1 & Meja 2
            $amprahStatuses = ['DIAJUKAN', 'DISETUJUI_KARU'];
        } else {
            $amprahStatuses = [];
        }

        $amprah = PermintaanDistribusi::whereIn('status', $amprahStatuses)
            ->when($isKaruOnly && $user->unit_id, fn ($q) => $q->where('unit_id', $user->unit_id))
            ->when($isGudangRole && $user->gudang_id, fn ($q) => $q->where('gudang_id', $user->gudang_id))
            ->with(['unit:id,kode,nama', 'pengaju:id,nama', 'detail.barang:id,kode,nama,satuan_id', 'detail.barang.satuan:id,nama,simbol'])
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
                    'id'          => $d->id,
                    'barang_id'   => $d->barang_id,
                    'barang'      => $d->barang?->nama,
                    'kode_barang' => $d->barang?->kode,
                    'satuan'      => $d->barang?->satuan?->simbol ?? $d->barang?->satuan?->nama ?? 'Pcs',
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

        // Validasi Kepemilikan Unit / Fasilitas (Anti Cross-Unit IDOR/BOLA)
        if (! $user->hasRole(['super_admin', 'admin_logistik'])) {
            if ($user->hasRole('kepala_unit')) {
                if ((int) $user->unit_id !== (int) $pr->unit_id) {
                    return $this->jsonError('Anda hanya berhak mengesahkan atau menolak permintaan pengadaan dari unit ruangan Anda sendiri.', 403);
                }
            }

            if ($user->hasRole('kepala_gudang')) {
                if ((int) $user->gudang_id !== (int) $pr->gudang_id) {
                    return $this->jsonError('Anda hanya berhak mengesahkan atau menolak permintaan pengadaan dari fasilitas gudang Anda sendiri.', 403);
                }
            }
        }

        if ($aksi === 'serahkan' || $aksi === 'teruskan') {
            if ($pr->status !== 'DISETUJUI' && $pr->status !== PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN) {
                return $this->jsonError("Hanya PR berstatus DISETUJUI / DITERUSKAN yang dapat diserahkan ke bagian pengadaan (saat ini: {$pr->status}).", 422);
            }

            $pr->update([
                'status' => PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN,
                'catatan' => $request->input('catatan') ?? $pr->catatan,
            ]);

            return $this->jsonSuccess([
                'kode' => $pr->kode,
                'status' => PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN,
            ], "PR {$pr->kode} berhasil diserahkan ke bagian pengadaan.");
        }

        if ($aksi === 'setujui') {
            if ($user->hasRole('pengadaan') && ! $user->hasRole(['super_admin', 'admin_logistik', 'kepala_unit', 'kepala_gudang'])) {
                return $this->jsonError('Bagian Pengadaan tidak memiliki wewenang untuk menyetujui pengajuan PR ruangan. Otorisasi persetujuan tersebut adalah wewenang Kepala Ruangan / Kepala Gudang.', 403);
            }

            if ($pr->status !== PermintaanPengadaan::STATUS_DIAJUKAN) {
                return $this->jsonError("PR {$pr->kode} tidak dalam status DIAJUKAN (saat ini: {$pr->status}).", 422);
            }

            $pr->update([
                'status' => PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN,
                'disetujui_oleh' => $user->id,
                'catatan' => $request->input('catatan') ?? $pr->catatan,
            ]);
            $pr->detail->each(fn ($d) => $d->update(['jumlah_disetujui' => $d->jumlah]));

            return $this->jsonSuccess([
                'kode' => $pr->kode,
                'status' => PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN,
            ], "PR {$pr->kode} berhasil disetujui dan diteruskan ke bagian pengadaan.");
        }

        // Tolak
        if (! $user->hasRole(['pengadaan', 'super_admin', 'admin_logistik']) && $pr->status !== PermintaanPengadaan::STATUS_DIAJUKAN) {
            return $this->jsonError("Hanya PR berstatus DIAJUKAN yang dapat ditolak pada tingkat ruangan/unit (saat ini: {$pr->status}).", 422);
        }

        $catatanTolak = $request->input('catatan') ?? $pr->catatan;
        if ($user->hasRole('pengadaan') && ! $user->hasRole(['super_admin', 'admin_logistik'])) {
            $catatanTolak = '[Ditolak Pengadaan]: ' . ($request->input('catatan') ?? 'Tidak memenuhi kriteria belanja/katalog');
        }

        $pr->update([
            'status' => PermintaanPengadaan::STATUS_DITOLAK,
            'disetujui_oleh' => $user->id,
            'catatan' => $catatanTolak,
        ]);

        return $this->jsonSuccess([
            'kode' => $pr->kode,
            'status' => PermintaanPengadaan::STATUS_DITOLAK,
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
            if ($user->hasRole(['kepala_gudang', 'petugas_gudang']) && ! $user->hasRole('super_admin')) {
                return $this->jsonError('Kepala Gudang tidak berwenang mengesahkan pengajuan Meja 1. Permohonan harus disetujui oleh Kepala Ruangan pemohon terlebih dahulu.', 403);
            }

            if (! $user->can('update_layanan_unit::approval::karu') && ! $user->hasRole(['super_admin', 'admin_logistik'])) {
                return $this->jsonError('Anda tidak memiliki wewenang mengesahkan permohonan ruangan ini.', 403);
            }

            if ($user->unit_id && (int) $user->unit_id !== (int) $amprah->unit_id && ! $user->hasRole(['super_admin', 'admin_logistik'])) {
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
            if ($user->hasRole('kepala_unit') && ! $user->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang'])) {
                return $this->jsonError('Otorisasi pengeluaran barang gudang (Meja 2) adalah wewenang Kepala Gudang / Admin Logistik.', 403);
            }

            if (! $user->can('update_gudang_stok::distribusi::approval') && ! $user->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang'])) {
                return $this->jsonError('Anda tidak memiliki wewenang otorisasi distribusi gudang logistik.', 403);
            }

            if ($user->gudang_id && (int) $user->gudang_id !== (int) $amprah->gudang_id && ! $user->hasRole(['super_admin', 'admin_logistik'])) {
                return $this->jsonError('Anda hanya berhak mengesahkan permohonan untuk fasilitas gudang Anda sendiri.', 403);
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
