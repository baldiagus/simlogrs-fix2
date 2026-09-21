<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalAccessMiddleware
{
    /**
     * Peta ID portal ke permission dashboard Spatie resminya di database.
     * 100% Zero-Hardcode Role.
     */
    protected array $portalPermissionMap = [
        'super-admin'    => 'page_Dashboard',
        'admin-logistik' => 'page_AdminLogistikDashboard',
        'kepala-gudang'  => 'page_KepalaGudangDashboard',
        'petugas-gudang' => 'page_GudangDashboard',
        'pengadaan'      => 'page_PengadaanDashboard',
        'kepala-unit'    => 'page_KepalaUnitDashboard',
        'petugas-unit'   => 'page_UnitDashboard',
        'teknisi'        => 'page_TeknisiDashboard',
        'manajemen'      => 'page_ManajemenDashboard',
        'auditor'        => 'page_AuditorDashboard',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $user = $request->user();

        // 1. Wajib terautentikasi dan berstatus AKTIF
        if (! $user || $user->status !== 'AKTIF') {
            auth()->logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Sesi login telah berakhir atau akun Anda dinonaktifkan.',
            ]);
        }

        // 2. Ambil permission yang dibutuhkan untuk portal ini
        $requiredPermission = $this->portalPermissionMap[$portal] ?? null;

        // 3. Evaluasi murni berbasis Spatie Permission (Zero Hardcode)
        if (! $requiredPermission || ! $user->can($requiredPermission)) {
            abort(403, "Akses ke portal '{$portal}' ditolak. Anda tidak memiliki izin wewenang.");
        }

        return $next($request);
    }
}
