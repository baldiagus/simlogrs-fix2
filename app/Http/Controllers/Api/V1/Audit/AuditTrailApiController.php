<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesForensicAccess;
use App\Models\Audit\LogAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Audit trail sistem (forensik).
 */
class AuditTrailApiController extends ApiController
{
    use AuthorizesForensicAccess;

    /**
     * Digital Forensic Audit Trail.
     * GET /api/v1/audit/trail
     */
    public function auditTrail(Request $request): JsonResponse
    {
        if ($auth = $this->authorizeForensicAccess($request)) {
            return $auth;
        }
        $aksi = $request->query('aksi');
        $entitas = $request->query('entitas');
        $search = $request->query('search');

        $query = LogAudit::with(['pengguna:id,nama,nip', 'peran:id,name'])
            ->when($aksi, fn ($q) => $q->where('aksi', $aksi))
            ->when($entitas, fn ($q) => $q->where('referensi_jenis', $entitas))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('referensi_jenis', 'like', "%{$search}%")
                        ->orWhere('aksi', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('pengguna', fn ($u) => $u->where('nama', 'like', "%{$search}%"));
                });
            })
            ->latest('id');

        $paginated = $query->paginate(20);

        $items = $paginated->getCollection()->map(function ($log) {
            return [
                'id'              => $log->id,
                'pengguna_nama'   => $log->pengguna?->nama ?? 'Sistem / Anonim',
                'pengguna_nip'    => $log->pengguna?->nip ?? '-',
                'peran'           => $log->peran?->name ?? '-',
                'aksi'            => $log->aksi,
                'referensi_jenis' => class_basename($log->referensi_jenis),
                'referensi_id'    => (int) $log->referensi_id,
                'ip_address'      => $log->ip_address ?? '-',
                'device'          => $log->device ?? '-',
                'waktu'           => $log->dibuat_pada?->format('d/m/Y H:i:s') ?? '-',
                'timestamp'       => $log->dibuat_pada?->toIso8601String() ?? now()->toIso8601String(),
                'has_data_sebelum'=> ! empty($log->data_sebelum),
                'has_data_sesudah'=> ! empty($log->data_sesudah),
            ];
        });

        return $this->jsonSuccess([
            'items'    => $items,
            'paginasi' => [
                'total'        => $paginated->total(),
                'per_halaman'  => $paginated->perPage(),
                'halaman_ini'  => $paginated->currentPage(),
                'total_halaman'=> $paginated->lastPage(),
            ],
        ], 'Daftar riwayat audit trail berhasil dimuat.');
    }

    /**
     * Detail forensik nilai data sebelum dan sesudah perubahan.
     * GET /api/v1/audit/trail/{id}
     */
    public function auditTrailDetail(Request $request, int $id): JsonResponse
    {
        if ($auth = $this->authorizeForensicAccess($request)) {
            return $auth;
        }
        $log = LogAudit::with(['pengguna:id,nama,nip', 'peran:id,name'])->find($id);

        if (! $log) {
            return $this->jsonError('Log audit tidak ditemukan.', 404);
        }

        return $this->jsonSuccess([
            'id'              => $log->id,
            'pengguna_nama'   => $log->pengguna?->nama ?? 'Sistem / Anonim',
            'pengguna_nip'    => $log->pengguna?->nip ?? '-',
            'peran'           => $log->peran?->name ?? '-',
            'aksi'            => $log->aksi,
            'referensi_jenis' => class_basename($log->referensi_jenis),
            'referensi_id'    => (int) $log->referensi_id,
            'ip_address'      => $log->ip_address ?? '-',
            'device'          => $log->device ?? '-',
            'waktu'           => $log->dibuat_pada?->format('d/m/Y H:i:s') ?? '-',
            'data_sebelum'    => $log->data_sebelum,
            'data_sesudah'    => $log->data_sesudah,
        ], 'Detail forensik log audit berhasil dimuat.');
    }
}
