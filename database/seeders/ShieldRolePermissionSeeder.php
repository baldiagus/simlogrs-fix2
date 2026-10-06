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

        // Build entity→page mapping from registry
        $entityPageMap = [];
        foreach (PagePermissionRegistry::getDomains() as $domain) {
            foreach ($domain['modules'] as $mod) {
                if (! empty($mod['entity']) && ! empty($mod['page'])) {
                    $entityPageMap[$mod['entity']] = $mod['page'];
                }
            }
        }

        // Helper untuk filter permissions + auto-grant page_* for view_any_*
        $givePermissions = function (string $roleName, array $patterns) use ($entityPageMap) {
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

            // Auto-grant page_* for every view_any_* that was matched
            $pagePerms = [];
            foreach ($matched as $perm) {
                if (str_starts_with($perm, 'view_any_')) {
                    $entity = substr($perm, 9); // strip 'view_any_'
                    if (isset($entityPageMap[$entity])) {
                        $pagePerms[] = $entityPageMap[$entity];
                    }
                }
            }

            $all = array_unique(array_merge(['panel_user'], $matched, $pagePerms));
            $role->syncPermissions($all);
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
            '%_layanan_unit::approval::peminjaman%',
            '%_layanan_unit::pengembalian::aset%',
            'view_any_layanan_unit::peminjaman::aset%',
            'view_layanan_unit::peminjaman::aset%',
            '%_layanan_unit::pengalihan::aset%',
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
            '%_gudang_stok::pengalihan::barang%',
            'view_any_gudang_stok::monitoring::fefo',
            'view_gudang_stok::monitoring::fefo',
            'view_any_gudang_stok::buku::mutasi',
            'view_gudang_stok::buku::mutasi',
            'view_any_master::%',
            'view_master::%',
            'view_any_layanan_unit::pengalihan::aset',
            'view_layanan_unit::pengalihan::aset',
            'create_layanan_unit::pengalihan::aset',
            'update_layanan_unit::pengalihan::aset',
        ]);
        // 8. Role: pengadaan
        $givePermissions('pengadaan', [
            'page_PengadaanDashboard',
            'view_any_pengadaan::permintaan::pengadaan',
            'view_pengadaan::permintaan::pengadaan',
            '%_pengadaan::pesanan::pembelian%',
            '%_pengadaan::master::rekanan%',
            '%_pengadaan::realisasi::anggaran%',
            '%_master::pemasok%',
            'view_any_master::barang%',
            'view_master::barang%',
            // Layanan unit: peminjaman aset, amprah, lapor servis (selaras DB aktual)
            'create_layanan_unit::peminjaman::aset',
            'view_any_layanan_unit::peminjaman::aset',
            'view_layanan_unit::peminjaman::aset',
            'create_layanan_unit::approval::peminjaman',
            'view_any_layanan_unit::approval::karu',
            'view_layanan_unit::approval::karu',
            'view_any_layanan_unit::approval::peminjaman',
            'view_layanan_unit::approval::peminjaman',
            'create_layanan_unit::permintaan::barang',
            'view_any_layanan_unit::permintaan::barang',
            'view_layanan_unit::permintaan::barang',
            'create_layanan_unit::pengembalian::aset',
            'view_any_layanan_unit::pengembalian::aset',
            'view_layanan_unit::pengembalian::aset',
            'create_layanan_unit::lapor::servis',
            'view_any_layanan_unit::lapor::servis',
            'view_layanan_unit::lapor::servis',
            'create_layanan_unit::stok::ruangan',
            'view_any_layanan_unit::stok::ruangan',
            'view_layanan_unit::stok::ruangan',
            'create_layanan_unit::penerimaan::barang',
            'view_any_layanan_unit::penerimaan::barang',
            'view_layanan_unit::penerimaan::barang',
            'view_any_layanan_unit::pengalihan::aset',
            'view_layanan_unit::pengalihan::aset',
            'create_layanan_unit::pengalihan::aset',
        ]);

        // 9. Role: kepala_unit
        $givePermissions('kepala_unit', [
            'page_KepalaUnitDashboard',
            '%_layanan_unit::approval::karu%',
            '%_layanan_unit::approval::peminjaman%',
            '%_layanan_unit::permintaan::barang%',
            '%_layanan_unit::peminjaman::aset%',
            '%_layanan_unit::pengembalian::aset%',
            '%_layanan_unit::lapor::servis%',
            '%_layanan_unit::pengalihan::aset%',
            '%_layanan_unit::stok::ruangan%',
            '%_pengadaan::permintaan::pengadaan%',
            'view_any_master::barang%',
            'view_master::barang%',
            'view_any_master::aset%',
            'view_master::aset%',
        ]);

        // 10. Role: petugas_unit
        $givePermissions('petugas_unit', [
            'page_UnitDashboard',
            'create_layanan_unit::permintaan::barang',
            'view_any_layanan_unit::permintaan::barang',
            'view_layanan_unit::permintaan::barang',
            'create_layanan_unit::peminjaman::aset',
            'view_any_layanan_unit::peminjaman::aset',
            'view_layanan_unit::peminjaman::aset',
            'view_any_layanan_unit::pengembalian::aset',
            'update_layanan_unit::pengembalian::aset',
            'view_layanan_unit::pengembalian::aset',
            'create_layanan_unit::lapor::servis',
            'create_layanan_unit::pengalihan::aset',
            'view_any_layanan_unit::pengalihan::aset',
            'view_layanan_unit::pengalihan::aset',
            'update_layanan_unit::pengalihan::aset',
            'view_any_layanan_unit::lapor::servis',
            'view_layanan_unit::lapor::servis',
            'view_any_layanan_unit::stok::ruangan',
            'view_layanan_unit::stok::ruangan',
            'update_layanan_unit::penerimaan::barang',
            'view_any_layanan_unit::penerimaan::barang',
            'view_layanan_unit::penerimaan::barang',
            'create_pengadaan::permintaan::pengadaan',
            'view_any_pengadaan::permintaan::pengadaan',
            'view_pengadaan::permintaan::pengadaan',
            'view_any_master::barang',
            'view_master::barang',
            'view_any_master::aset',
            'view_master::aset',
        ]);
        // 11. Role: kepala_teknisi (Koordinator IPSRS)
        $givePermissions('kepala_teknisi', [
            'page_TeknisiDashboard',
            '%_pemeliharaan::%',
            '%_layanan_unit::lapor::servis%',
            '%_pengadaan::permintaan::pengadaan%',
            'create_layanan_unit::peminjaman::aset',
            'view_any_layanan_unit::peminjaman::aset',
            'view_layanan_unit::peminjaman::aset',
            'create_layanan_unit::permintaan::barang',
            'view_any_layanan_unit::permintaan::barang',
            'view_layanan_unit::permintaan::barang',
            'view_any_layanan_unit::approval::karu',
            'view_layanan_unit::approval::karu',
            'update_layanan_unit::approval::karu',
            'view_any_master::%',
            'view_master::%',
            'view_any_gudang_stok::stok::batch%',
            'view_gudang_stok::stok::batch%',
        ]);

        // 12. Role: teknisi
        $givePermissions('teknisi', [
            'page_TeknisiDashboard',
            'view_any_pemeliharaan::%',
            'view_pemeliharaan::%',
            '%_pemeliharaan::spk::perbaikan%',
            '%_pemeliharaan::lembar::kerja%',
            '%_pemeliharaan::peminjaman::alat%',
            'create_pemeliharaan::permintaan::sparepart',
            'view_any_master::aset%',
            'view_master::aset%',
            'view_any_gudang_stok::stok::batch%',
            'view_gudang_stok::stok::batch%',
            'view_any_layanan_unit::lapor::servis',
            'view_layanan_unit::lapor::servis',
        ]);
        // 12. Role: manajemen
        $givePermissions('manajemen', [
            'page_ManajemenDashboard',
            'view_any_master::%',
            'view_any_pengadaan::%',
            'view_any_layanan_unit::%',
            'view_any_gudang_stok::%',
            'view_any_pemeliharaan::%',
            'view_any_audit_manajemen::%',
            'view_master::%',
            'view_pengadaan::%',
            'view_layanan_unit::%',
            'view_gudang_stok::%',
            'view_pemeliharaan::%',
            'view_audit_manajemen::%',
            // Create layanan unit (selaras DB aktual: manajemen boleh meminjam/mengajukan)
            'create_layanan_unit::approval::peminjaman',
            'create_layanan_unit::lapor::servis',
            'create_layanan_unit::peminjaman::aset',
            'create_layanan_unit::penerimaan::barang',
            'create_layanan_unit::pengembalian::aset',
            'create_layanan_unit::permintaan::barang',
            'create_layanan_unit::stok::ruangan',
            'create_layanan_unit::pengalihan::aset',
        ]);

        // 13. Role: auditor
        $givePermissions('auditor', [
            'page_AuditorDashboard',
            '%_audit_manajemen::%',
            'view_any_master::%',
            'view_any_pengadaan::%',
            'view_any_layanan_unit::%',
            'view_any_gudang_stok::%',
            'view_any_pemeliharaan::%',
            'view_master::%',
            'view_pengadaan::%',
            'view_layanan_unit::%',
            'view_gudang_stok::%',
            'view_pemeliharaan::%',
            // Create layanan unit (selaras DB aktual: auditor boleh meminjam/mengajukan)
            'create_layanan_unit::approval::peminjaman',
            'create_layanan_unit::lapor::servis',
            'create_layanan_unit::peminjaman::aset',
            'create_layanan_unit::penerimaan::barang',
            'create_layanan_unit::pengembalian::aset',
            'create_layanan_unit::permintaan::barang',
            'create_layanan_unit::stok::ruangan',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
