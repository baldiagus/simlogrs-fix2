<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RouteRbacTest extends TestCase
{
    use DatabaseTransactions;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'nama' => 'Route RBAC Test',
            'username' => 'route-rbac-' . uniqid(),
            'email' => 'route-rbac-' . uniqid() . '@example.test',
            'password' => 'password',
            'status' => 'AKTIF',
            'wajib_ganti_password' => false,
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_registered_route_page_permission_denies_direct_access_without_permission(): void
    {
        $user = $this->userWithPermissions(['page_PengadaanDashboard']);

        $this->actingAs($user)
            ->get('/pengadaan/master-rekanan')
            ->assertForbidden();
    }

    public function test_route_page_permission_allows_direct_access_when_registered_permission_is_granted(): void
    {
        $user = $this->userWithPermissions([
            'page_PengadaanDashboard',
            'page_MasterRekananIndex',
            'view_any_pengadaan::master::rekanan',
        ]);
        $this->actingAs($user)
            ->get('/pengadaan/master-rekanan')
            ->assertOk();
    }

    public function test_default_observer_roles_receive_read_permissions_without_mutations(): void
    {
        $this->seed(\Database\Seeders\PeranSeeder::class);
        $this->seed(\Database\Seeders\ShieldRolePermissionSeeder::class);

        foreach (['auditor', 'manajemen'] as $roleName) {
            $role = \Spatie\Permission\Models\Role::findByName($roleName, 'web');
            $permissions = $role->permissions->pluck('name')->all();

            $this->assertNotContains('create_layanan_unit::permintaan::barang', $permissions);
            $this->assertNotContains('update_layanan_unit::penerimaan::barang', $permissions);
            $this->assertNotContains('delete_master::barang', $permissions);
            $this->assertContains('view_any_master::barang', $permissions);
        }
    }



    public function test_portal_boundary_still_requires_that_portals_dashboard_permission(): void
    {
        $user = $this->userWithPermissions(['page_PengadaanDashboard', 'page_MasterRekananIndex']);

        $this->actingAs($user)
            ->get('/auditor/audit-trail')
            ->assertForbidden();
    }
}
