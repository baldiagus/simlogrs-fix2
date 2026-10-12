<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Dashboard\GudangDashboardBuilder;
use App\Services\Dashboard\IpsrsDashboardBuilder;
use App\Services\Dashboard\PengadaanDashboardBuilder;
use App\Services\Dashboard\PengawasanDashboardBuilder;
use App\Services\Dashboard\RuanganDashboardBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends ApiController
{
    /**
     * Endpoint terpadu ringkasan metrik hero & aktivitas terkini berbasis peran pengguna.
     * Hanya menampilkan transaksi berstatus antrean aktif (DIAJUKAN, DILAPORKAN, DITUGASKAN, MENUNGGU, dsb).
     * GET /api/v1/dashboard/summary
     */
    public function summary(
        Request $request,
        IpsrsDashboardBuilder $ipsrs,
        GudangDashboardBuilder $gudang,
        RuanganDashboardBuilder $ruangan,
        PengawasanDashboardBuilder $pengawasan,
        PengadaanDashboardBuilder $pengadaan
    ): JsonResponse {
        $user = $request->user();
        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        $roles = $user->getRoleNames()->toArray();
        $isSuperAdmin = in_array('super_admin', $roles);
        $isKepalaTeknisi = in_array('kepala_teknisi', $roles) || $user->hasRole('kepala_teknisi');
        $isTeknisi = ! $isSuperAdmin && ! $isKepalaTeknisi && (in_array('teknisi', $roles) || $user->can('page_TeknisiDashboard'));
        $isPetugasGudang = in_array('petugas_gudang', $roles);
        $isKepalaGudang = $isSuperAdmin || in_array('kepala_gudang', $roles) || in_array('admin_logistik', $roles);
        $isKaru = (in_array('kepala_unit', $roles) || $user->can('page_KepalaUnitDashboard')) && ! $isKepalaTeknisi;
        $isAuditor = in_array('auditor', $roles) || $user->can('page_AuditorDashboard');
        $isManajemen = in_array('manajemen', $roles) || $user->can('page_ManajemenDashboard');
        $isPetugasUnit = (in_array('petugas_unit', $roles) || (! empty($user->unit_id) && ! $isKaru)) && ! $isAuditor && ! $isManajemen && ! $isKepalaTeknisi;

        $data = match (true) {
            $isKepalaTeknisi => $ipsrs->buildKepalaTeknisi($user),
            $isTeknisi       => $ipsrs->buildTeknisi($user),
            $isPetugasGudang => $gudang->buildPetugasGudang($user),
            $isKepalaGudang  => $gudang->buildKepalaGudang($user),
            $isKaru          => $ruangan->buildKaru($user),
            $isPetugasUnit   => $ruangan->buildPetugasUnit($user),
            $isAuditor       => $pengawasan->buildAuditor($user),
            $isManajemen     => $pengawasan->buildManajemen($user),
            default          => $pengadaan->buildDefault($user),
        };

        return $this->jsonSuccess($data, 'Ringkasan dashboard personal berhasil dimuat.');
    }
}
