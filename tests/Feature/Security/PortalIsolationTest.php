<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\Portal\PortalResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * ADR 0006: Isolasi portal terikat peran (1 Role = 1 Portal).
 * Memastikan pengguna hanya boleh mengakses ruang kerja yang sesuai dengan perannya.
 */
class PortalIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_can_access_super_admin_portal_but_denied_other_portals(): void
    {
        $superAdmin = User::where('username', 'superadmin')->first();

        // Diizinkan pada portal resmi super-admin
        $this->actingAs($superAdmin)
            ->get('/super-admin/dashboard')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get('/super-admin/roles')
            ->assertOk();

        // Ditolak 403 saat mencoba masuk ke portal peran lain
        $forbiddenPortals = [
            'admin-logistik',
            'kepala-gudang',
            'petugas-gudang',
            'pengadaan',
            'kepala-unit',
            'petugas-unit',
            'teknisi',
            'manajemen',
            'auditor',
        ];

        foreach ($forbiddenPortals as $portal) {
            $this->actingAs($superAdmin)
                ->get("/{$portal}/dashboard")
                ->assertForbidden();
        }
    }

    public function test_each_official_role_is_strictly_locked_to_its_designated_portal(): void
    {
        $roleUsers = [
            'adminlogistik' => 'admin-logistik',
            'kepalagudang'  => 'kepala-gudang',
            'petugasgudang' => 'petugas-gudang',
            'pengadaan'     => 'pengadaan',
            'kepalaunit'    => 'kepala-unit',
            'petugasunit'   => 'petugas-unit',
            'karu_ipsrs'    => 'teknisi',
            'teknisi'       => 'teknisi',
            'manajemen'     => 'manajemen',
            'auditor'       => 'auditor',
        ];

        foreach ($roleUsers as $username => $allowedPortal) {
            $user = User::where('username', $username)->first();
            $this->assertNotNull($user, "Pengguna {$username} tidak ditemukan.");

            // Buka portal resmi -> OK
            $this->actingAs($user)
                ->get("/{$allowedPortal}/dashboard")
                ->assertOk();

            // Coba buka portal super-admin -> 403 Ditolak
            $this->actingAs($user)
                ->get('/super-admin/dashboard')
                ->assertForbidden();

            // Coba buka portal lain yang bukan haknya -> 403 Ditolak
            $otherPortal = $allowedPortal === 'auditor' ? 'admin-logistik' : 'auditor';
            $this->actingAs($user)
                ->get("/{$otherPortal}/dashboard")
                ->assertForbidden();
        }
    }

    public function test_super_admin_retains_full_operational_permissions_excluding_cross_dashboards(): void
    {
        $superAdmin = User::where('username', 'superadmin')->first();

        // Memegang permission dashboard super admin
        $this->assertTrue($superAdmin->can('page_Dashboard'));

        // Tidak memegang permission dashboard portal lain
        $otherDashboards = [
            'page_AdminLogistikDashboard',
            'page_KepalaGudangDashboard',
            'page_GudangDashboard',
            'page_PengadaanDashboard',
            'page_KepalaUnitDashboard',
            'page_UnitDashboard',
            'page_TeknisiDashboard',
            'page_ManajemenDashboard',
            'page_AuditorDashboard',
        ];

        foreach ($otherDashboards as $dashPerm) {
            $this->assertFalse(
                $superAdmin->can($dashPerm),
                "Super Admin tidak boleh memiliki permission {$dashPerm} (ADR 0006)."
            );
        }

        // Tetap memiliki permission manajemen RBAC & master
        $this->assertTrue($superAdmin->can('view_any_user::role'));
        $this->assertTrue($superAdmin->can('update_user::role'));
        $this->assertTrue($superAdmin->can('view_any_master::barang'));
    }
}
