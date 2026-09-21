<?php

namespace Database\Seeders;

use App\Support\Rbac\PagePermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ShieldRolePermissionSeeder extends Seeder
{
    /**
     * Menanam seluruh permission resmi dan mengaitkan wewenang default ke masing-masing role.
     */
    public function run(): void
    {
        // 1. Reset cache Spatie
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Generate seluruh permissions terdaftar
        $allPermissions = PagePermissionRegistry::getAllPermissions();
        foreach ($allPermissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        // 3. Berikan permission dasar 'panel_user' ke seluruh role
        $allRoles = Role::all();
        foreach ($allRoles as $role) {
            $role->givePermissionTo('panel_user');
        }

        // 4. Role: super_admin (Otoritas Penuh: Permission::all())
        $superAdmin = Role::findByName('super_admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        // Helper untuk filter permissions
        $givePermissions = function (string $roleName, array $patterns) {
            $role = Role::findByName($roleName, 'web');
            if (! $role) return;

            $matched = Permission::where(function ($query) use ($patterns) {
                foreach ($patterns as $pattern) {
                    if (str_contains($pattern, '%')) {
                        $query->orWhere('name', 'like', $pattern);
                    } else {
                        $query->orWhere('name', $pattern);
                    }
                }
            })->pluck('name')->toArray();

            $role->givePermissionTo(array_unique(array_merge(['panel_user'], $matched)));
        };

        // 5. Role: admin_logistik
        $givePermissions('admin_logistik', [
            'page_AdminLogistikDashboard',
            '%_master::%',
            '%_gudang_stok::%',
            '%_layanan_unit::%',
            '%_pengadaan::permintaan::pengadaan%',
        ]);

        // 6. Role: kepala_gudang
        $givePermissions('kepala_gudang', [
            'page_KepalaGudangDashboard',
            '%_gudang_stok::%',
            'view_any_master::%',
            'view_master::%',
            '%_layanan_unit::approval::karu%',
            '%_pengadaan::permintaan::pengadaan%',
        ]);

        // 7. Role: petugas_gudang
        $givePermissions('petugas_gudang', [
            'page_GudangDashboard',
            '%_gudang_stok::stok::batch%',
            '%_gudang_stok::picking::distribusi%',
            '%_gudang_stok::penerimaan::po%',
            '%_gudang_stok::stok::opname%',
            '%_gudang_stok::tata::letak::rak%',
            'view_any_master::%',
            'view_master::%',
        ]);

        // 8. Role: pengadaan
        $givePermissions('pengadaan', [
            'page_PengadaanDashboard',
            '%_pengadaan::%',
            '%_master::pemasok%',
            'view_any_master::barang%',
            'view_master::barang%',
        ]);

        // 9. Role: kepala_unit
        $givePermissions('kepala_unit', [
            'page_KepalaUnitDashboard',
            '%_layanan_unit::approval::karu%',
            '%_layanan_unit::permintaan::barang%',
            '%_layanan_unit::peminjaman::aset%',
            '%_layanan_unit::stok::ruangan%',
            '%_pengadaan::permintaan::pengadaan%',
            'view_any_master::barang%',
            'view_any_master::aset%',
        ]);

        // 10. Role: petugas_unit
        $givePermissions('petugas_unit', [
            'page_UnitDashboard',
            'create_layanan_unit::permintaan::barang',
            'view_any_layanan_unit::permintaan::barang',
            'view_layanan_unit::permintaan::barang',
            'create_layanan_unit::peminjaman::aset',
            'view_any_layanan_unit::peminjaman::aset',
            'create_layanan_unit::lapor::servis',
            'view_any_layanan_unit::lapor::servis',
            'view_any_layanan_unit::stok::ruangan',
            'update_layanan_unit::penerimaan::barang',
            'view_any_layanan_unit::penerimaan::barang',
            'create_pengadaan::permintaan::pengadaan',
            'view_any_pengadaan::permintaan::pengadaan',
            'view_any_master::barang',
            'view_any_master::aset',
        ]);

        // 11. Role: teknisi
        $givePermissions('teknisi', [
            'page_TeknisiDashboard',
            '%_pemeliharaan::%',
            'view_any_master::aset%',
            'view_master::aset%',
            'view_any_gudang_stok::stok::batch%',
            'view_gudang_stok::stok::batch%',
        ]);

        // 12. Role: manajemen
        $givePermissions('manajemen', [
            'page_ManajemenDashboard',
            'view_any_%',
            'view_%',
        ]);

        // 13. Role: auditor
        $givePermissions('auditor', [
            'page_AuditorDashboard',
            '%_audit_manajemen::%',
            'view_any_%',
            'view_%',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
