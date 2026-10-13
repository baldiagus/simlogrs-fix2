<?php

namespace App\Http\Controllers\Api\V1\Notifikasi;

use App\Http\Controllers\Api\ApiController;
use App\Models\Master\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbox notifikasi pengguna aktif.
 */
class NotifikasiApiController extends ApiController
{
    /**
     * Daftar notifikasi milik pengguna, terbaru dahulu (20 per halaman).
     * GET /api/v1/notifikasi?page=1&hanya_belum_dibaca=0|1
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $paginated = Notifikasi::where('pengguna_id', $userId)
            ->when($request->boolean('hanya_belum_dibaca'), fn ($q) => $q->belumDibaca())
            ->orderByDesc('dibuat_pada')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->jsonSuccess([
            'items'    => $paginated->getCollection()->map(fn (Notifikasi $n) => $this->format($n))->values(),
            'paginasi' => [
                'total'         => $paginated->total(),
                'per_halaman'   => $paginated->perPage(),
                'halaman_ini'   => $paginated->currentPage(),
                'total_halaman' => $paginated->lastPage(),
            ],
            'belum_dibaca' => $this->hitungBelumDibaca($userId),
        ], 'Daftar notifikasi berhasil dimuat.');
    }

    /**
     * Tandai satu notifikasi dibaca; 404 bila bukan milik pengguna.
     * POST /api/v1/notifikasi/{id}/dibaca
     */
    public function dibaca(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $notifikasi = Notifikasi::where('pengguna_id', $userId)->find($id);
        if (! $notifikasi) {
            return $this->jsonError('Notifikasi tidak ditemukan.', 404);
        }

        $notifikasi->update(['dibaca' => true]);

        return $this->jsonSuccess(['belum_dibaca' => $this->hitungBelumDibaca($userId)], 'Notifikasi ditandai dibaca.');
    }

    /**
     * Tandai semua notifikasi pengguna dibaca.
     * POST /api/v1/notifikasi/baca-semua
     */
    public function bacaSemua(Request $request): JsonResponse
    {
        Notifikasi::where('pengguna_id', $request->user()->id)->belumDibaca()->update(['dibaca' => true]);

        return $this->jsonSuccess(['belum_dibaca' => 0], 'Semua notifikasi ditandai dibaca.');
    }

    private function hitungBelumDibaca(int $userId): int
    {
        return Notifikasi::where('pengguna_id', $userId)->belumDibaca()->count();
    }

    private function format(Notifikasi $n): array
    {
        return [
            'id'           => $n->id,
            'judul'        => $n->judul,
            'pesan'        => $n->pesan,
            'tipe'         => $n->tipe ?? 'UMUM',
            'referensi_id' => $n->referensi_id,
            'tautan'       => $n->tautan,
            'dibaca'       => (bool) $n->dibaca,
            'dibuat_pada'  => $n->dibuat_pada?->toIso8601String(),
        ];
    }
}
