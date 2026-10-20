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

        // 2. Wajib mengganti password sementara jika flag wajib_ganti_password aktif
        if ($user->wajib_ganti_password) {
            return redirect()->route('password.ganti-wajib')->with('warning', 'Demi keamanan, Anda wajib mengganti kata sandi sementara sebelum mengakses portal sistem.');
        }
        // 3. Isolasi portal terikat peran: Setiap peran resmi hanya boleh mengakses ruang kerjanya sendiri (ADR 0006)
        $userPortal = \App\Support\Portal\PortalResolver::resolveUserPortal($user);
        if ($userPortal !== null && $userPortal !== $portal) {
            abort(403, "Akses ke portal '{$portal}' ditolak. Ruang kerja resmi Anda adalah '{$userPortal}'.");
        }

        // 4. Ambil permission yang dibutuhkan untuk portal ini
        $requiredPermission = $this->portalPermissionMap[$portal] ?? null;
        $routePermission = $this->routePagePermission($request);
        if ($routePermission && ! $user->can($routePermission)) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        if (! $requiredPermission || ! $user->can($requiredPermission)) {
            abort(403, "Akses ke portal '{$portal}' ditolak. Anda tidak memiliki izin wewenang.");
        }

        return $next($request);
    }

    private function routePagePermission(Request $request): ?string
    {
        $controller = $request->route()?->getControllerClass();
        if (! $controller) {
            return null;
        }

        $permission = 'page_' . class_basename($controller);
        return in_array($permission, \App\Support\Rbac\PagePermissionRegistry::getAllPermissions(), true)
            ? $permission
            : null;
    }
}

