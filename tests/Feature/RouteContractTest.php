<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Kontrak URL + nama route portal web dan API v1.
 * Perubahan URL, nama route, atau middleware harus disengaja dan memperbarui daftar di sini.
 * Otorisasi per-permission halaman sudah diuji di RouteRbacTest; tes ini tidak mengulanginya.
 */
class RouteContractTest extends TestCase
{
    use DatabaseTransactions;

    /** Portal key => permission dashboard yang membuka batas portal (PortalAccessMiddleware). */
    private const PORTALS = [
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
     * Path relatif => sufiks nama route, dipakai oleh seluruh portal.
     * 'tracking-po' tidak diberi ->name(), sehingga Laravel memberinya nama prefix grup saja ("{portal}.").
     */
    private const SHARED_ROUTES = [
        'approval-peminjaman'   => 'approval-peminjaman',
        'approval-permintaan'   => 'approval-permintaan',
        'aset'                  => 'aset',
        'audit-trail'           => 'audit-trail',
        'barang'                => 'barang',
        'buku-mutasi'           => 'buku-mutasi',
        'dashboard'             => 'dashboard',
        'distribusi-approval'   => 'distribusi-approval',
        'gudang'                => 'gudang',
        'jadwal-preventif'      => 'jadwal-preventif',
        'kategori'              => 'kategori',
        'kepatuhan-pengadaan'   => 'kepatuhan-pengadaan',
        'lapor-servis'          => 'lapor-servis',
        'lembar-kerja'          => 'lembar-kerja',
        'lokasi-gudang'         => 'lokasi-gudang',
        'master-rekanan'        => 'master-rekanan',
        'merek'                 => 'merek',
        'monitoring-fefo'       => 'monitoring-fefo',
        'otorisasi-penyesuaian' => 'otorisasi-penyesuaian',
        'pemasok'               => 'pemasok',
        'peminjaman-aset'       => 'peminjaman-aset',
        'penerimaan-barang'     => 'penerimaan-barang',
        'penerimaan-po'         => 'penerimaan-po',
        'pengalihan-aset'       => 'pengalihan-aset',
        'pengalihan-barang'     => 'pengalihan-barang',
        'pengembalian-aset'     => 'pengembalian-aset',
        'permintaan-barang'     => 'permintaan-barang',
        'permintaan-pengadaan'  => 'permintaan-pengadaan',
        'pesanan-pembelian'     => 'pesanan-pembelian',
        'picking-distribusi'    => 'picking-distribusi',
        'realisasi-anggaran'    => 'realisasi-anggaran',
        'rekam-medis-aset'      => 'rekam-medis-aset',
        'satuan'                => 'satuan',
        'selisih-opname'        => 'selisih-opname',
        'spk'                   => 'spk',
        'spk/{id}/cetak-pdf'    => 'spk.cetak-pdf',
        'stok-opname'           => 'stok-opname',
        'stok-ruangan'          => 'stok-ruangan',
        'temuan-capa'           => 'temuan-capa',
        'tracking-po'           => '',
        'unit'                  => 'unit',
    ];

    private const SUPER_ADMIN_ONLY_ROUTES = [
        'pengguna'  => 'pengguna',
        'roles'     => 'roles',
        'scheduler' => 'scheduler',
    ];

    /** Satu-satunya endpoint API v1 tanpa auth:sanctum, sebagai "METHOD uri". */
    private const PUBLIC_API_ROUTES = [
        'GET api/v1/health',
        'POST api/v1/auth/login',
    ];

    /** @return array<string, RoutingRoute[]> portal key => routes di bawah prefix portal */
    private function portalRoutes(): array
    {
        $byPortal = array_fill_keys(array_keys(self::PORTALS), []);

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $segment = explode('/', $route->uri(), 2)[0];
            if (array_key_exists($segment, $byPortal)) {
                $byPortal[$segment][] = $route;
            }
        }

        return $byPortal;
    }

    private function relativePath(string $portal, RoutingRoute $route): string
    {
        return substr($route->uri(), strlen($portal) + 1);
    }

    /** @return RoutingRoute[] */
    private function apiV1Routes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/'),
        ));
    }

    private function primaryMethod(RoutingRoute $route): string
    {
        return array_values(array_diff($route->methods(), ['HEAD']))[0];
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'nama' => 'Route Contract Test',
            'username' => 'route-contract-' . uniqid(),
            'email' => 'route-contract-' . uniqid() . '@example.test',
            'password' => 'password',
            'status' => 'AKTIF',
            'wajib_ganti_password' => false,
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_every_portal_exposes_the_shared_paths_and_only_super_admin_adds_rbac_pages(): void
    {
        $byPortal = $this->portalRoutes();
        $shared = array_keys(self::SHARED_ROUTES);
        sort($shared);

        foreach ($byPortal as $portal => $routes) {
            $paths = array_map(fn (RoutingRoute $r) => $this->relativePath($portal, $r), $routes);
            sort($paths);

            $expected = $portal === 'super-admin'
                ? array_merge($shared, array_keys(self::SUPER_ADMIN_ONLY_ROUTES))
                : $shared;
            sort($expected);

            $this->assertSame($expected, $paths, "Path portal '{$portal}' menyimpang dari kontrak.");
        }

        $this->assertCount(41, self::SHARED_ROUTES);
        $this->assertSame(41 * 10 + 3, array_sum(array_map('count', $byPortal)));
    }

    public function test_portal_route_names_follow_portal_prefix_contract_and_are_unique(): void
    {
        $allNames = [];

        foreach ($this->portalRoutes() as $portal => $routes) {
            $contract = self::SHARED_ROUTES + ($portal === 'super-admin' ? self::SUPER_ADMIN_ONLY_ROUTES : []);

            foreach ($routes as $route) {
                $path = $this->relativePath($portal, $route);
                $this->assertArrayHasKey($path, $contract, "Path tak dikenal: {$route->uri()}");
                $this->assertSame("{$portal}.{$contract[$path]}", $route->getName(), "Nama route {$route->uri()} berubah.");
                $this->assertSame(['GET', 'HEAD'], $route->methods(), "Method {$route->uri()} berubah.");
                $allNames[] = $route->getName();
            }
        }

        $this->assertSame(array_values(array_unique($allNames)), $allNames, 'Nama route portal duplikat.');

        // Named routes resolve back to the same URL (guards against name/URL swaps).
        $this->assertSame('/teknisi/spk/7/cetak-pdf', route('teknisi.spk.cetak-pdf', ['id' => 7], false));
        $this->assertSame('/super-admin/scheduler', route('super-admin.scheduler', [], false));
        $this->assertFalse(Route::has('pengadaan.pengguna'));
    }

    public function test_every_portal_route_requires_auth_and_its_own_portal_access_middleware(): void
    {
        foreach ($this->portalRoutes() as $portal => $routes) {
            foreach ($routes as $route) {
                $this->assertSame(
                    ['web', 'auth', "portal.access:{$portal}"],
                    $route->middleware(),
                    "Middleware {$route->uri()} menyimpang.",
                );
            }
        }
    }

    public function test_guest_is_redirected_to_login_from_each_portal(): void
    {
        foreach (array_keys(self::PORTALS) as $portal) {
            $this->get("/{$portal}/dashboard")->assertRedirect(route('login'));
            $this->get("/{$portal}/spk/1/cetak-pdf")->assertRedirect(route('login'));
        }
    }

    public function test_tracking_po_redirects_to_the_same_portals_penerimaan_po(): void
    {
        $user = $this->userWithPermissions(array_values(self::PORTALS));

        foreach (array_keys(self::PORTALS) as $portal) {
            $this->actingAs($user)
                ->get("/{$portal}/tracking-po")
                ->assertRedirect("/{$portal}/penerimaan-po");
        }
    }

    public function test_tracking_po_is_still_behind_the_portal_boundary(): void
    {
        $user = $this->userWithPermissions(['page_PengadaanDashboard']);

        $this->actingAs($user)->get('/auditor/tracking-po')->assertForbidden();
    }

    public function test_only_health_and_login_api_routes_are_public(): void
    {
        $public = [];

        foreach ($this->apiV1Routes() as $route) {
            $middleware = $route->middleware();
            $key = $this->primaryMethod($route) . ' ' . $route->uri();

            $this->assertContains('api', $middleware, "{$key} keluar dari grup api.");

            if (! in_array('auth:sanctum', $middleware, true)) {
                $public[] = $key;
                continue;
            }

            $this->assertContains('api.active', $middleware, "{$key} tanpa api.active.");
        }

        sort($public);
        $expected = self::PUBLIC_API_ROUTES;
        sort($expected);

        $this->assertSame($expected, $public);
        $this->assertGreaterThan(count($expected), count($this->apiV1Routes()));
    }

    public function test_api_guest_reaches_public_endpoints_but_not_protected_ones(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('sukses', true);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    }
}
