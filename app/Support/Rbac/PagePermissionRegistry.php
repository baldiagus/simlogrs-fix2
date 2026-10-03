<?php

namespace App\Support\Rbac;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PagePermissionRegistry
{
    /**
     * Definisi 8 domain fungsional dan 58 modul sistem.
     */
    public static function getDomains(): array
    {
        return [
            'dashboard_portal' => [
                'label' => 'Dashboard Portal',
                'deskripsi' => '10 Dashboard Portal Terisolasi Berbasis Peran',
                'modules' => [
                    'Dashboard' => ['label' => 'Dashboard Super Admin', 'page_only' => true, 'permission' => 'page_Dashboard'],
                    'AdminLogistikDashboard' => ['label' => 'Dashboard Admin Logistik', 'page_only' => true, 'permission' => 'page_AdminLogistikDashboard'],
                    'KepalaGudangDashboard' => ['label' => 'Dashboard Kepala Gudang', 'page_only' => true, 'permission' => 'page_KepalaGudangDashboard'],
                    'GudangDashboard' => ['label' => 'Dashboard Petugas Gudang', 'page_only' => true, 'permission' => 'page_GudangDashboard'],
                    'PengadaanDashboard' => ['label' => 'Dashboard Pengadaan', 'page_only' => true, 'permission' => 'page_PengadaanDashboard'],
                    'KepalaUnitDashboard' => ['label' => 'Dashboard Kepala Unit', 'page_only' => true, 'permission' => 'page_KepalaUnitDashboard'],
                    'UnitDashboard' => ['label' => 'Dashboard Petugas Unit', 'page_only' => true, 'permission' => 'page_UnitDashboard'],
                    'TeknisiDashboard' => ['label' => 'Dashboard Teknisi IPSRS', 'page_only' => true, 'permission' => 'page_TeknisiDashboard'],
                    'ManajemenDashboard' => ['label' => 'Dashboard Manajemen Direksi', 'page_only' => true, 'permission' => 'page_ManajemenDashboard'],
                    'AuditorDashboard' => ['label' => 'Dashboard Auditor Internal (SPI)', 'page_only' => true, 'permission' => 'page_AuditorDashboard'],
                ],
            ],

            'master' => [
                'label' => 'Master Data & Katalog Logistik',
                'deskripsi' => 'Kamus data komoditas, fasilitas simpan, dan aset',
                'modules' => [
                    'master::barang' => ['label' => 'Master Barang', 'entity' => 'master::barang', 'page' => 'page_BarangIndex'],
                    'master::unit' => ['label' => 'Unit Kerja & Ruangan', 'entity' => 'master::unit', 'page' => 'page_UnitIndex'],
                    'master::gudang' => ['label' => 'Gudang Logistik', 'entity' => 'master::gudang', 'page' => 'page_GudangIndex'],
                    'master::lokasi::gudang' => ['label' => 'Tata Letak Rak & Bin', 'entity' => 'master::lokasi::gudang', 'page' => 'page_LokasiGudangIndex'],
                    'master::kategori::barang' => ['label' => 'Kategori Komoditas', 'entity' => 'master::kategori::barang', 'page' => 'page_KategoriBarangIndex'],
                    'master::satuan::barang' => ['label' => 'Satuan Kemasan', 'entity' => 'master::satuan::barang', 'page' => 'page_SatuanBarangIndex'],
                    'master::merek::barang' => ['label' => 'Merek Komoditas', 'entity' => 'master::merek::barang', 'page' => 'page_MerekBarangIndex'],
                    'master::pemasok' => ['label' => 'Pemasok & Rekanan Vendor', 'entity' => 'master::pemasok', 'page' => 'page_PemasokIndex'],
                    'master::aset' => ['label' => 'Register Master Aset', 'entity' => 'master::aset', 'page' => 'page_AsetIndex'],
                ],
            ],

            'layanan_unit' => [
                'label' => 'Layanan & Operasional Ruangan',
                'deskripsi' => 'Pengajuan amprah, peminjaman, dan lapor kerusakan ruangan',
                'modules' => [
                    'layanan_unit::permintaan::barang' => ['label' => 'Permintaan Barang (Amprah)', 'entity' => 'layanan_unit::permintaan::barang', 'page' => 'page_PermintaanBarangIndex'],
                    'layanan_unit::peminjaman::aset' => ['label' => 'Peminjaman Aset Ruangan', 'entity' => 'layanan_unit::peminjaman::aset', 'page' => 'page_PeminjamanAsetIndex'],
                    'layanan_unit::approval::peminjaman' => ['label' => 'Otorisasi Peminjaman Aset', 'entity' => 'layanan_unit::approval::peminjaman', 'page' => 'page_ApprovalPeminjamanIndex'],
                    'layanan_unit::pengembalian::aset' => ['label' => 'Pengembalian & Cek Fisik Aset', 'entity' => 'layanan_unit::pengembalian::aset', 'page' => 'page_PengembalianPeminjamanIndex'],
                    'layanan_unit::lapor::servis' => ['label' => 'Lapor Kerusakan (Servis)', 'entity' => 'layanan_unit::lapor::servis', 'page' => 'page_LaporServisIndex'],
                    'layanan_unit::stok::ruangan' => ['label' => 'Persediaan Lokal Ruangan', 'entity' => 'layanan_unit::stok::ruangan', 'page' => 'page_StokRuanganIndex'],
                    'layanan_unit::penerimaan::barang' => ['label' => 'Konfirmasi Penerimaan Distribusi', 'entity' => 'layanan_unit::penerimaan::barang', 'page' => 'page_PenerimaanBarangIndex'],
                    'layanan_unit::approval::karu' => ['label' => 'Otorisasi Kepala Ruangan (Approval Karu)', 'entity' => 'layanan_unit::approval::karu', 'page' => 'page_ApprovalPermintaanIndex'],
                ],
            ],

            'gudang_stok' => [
                'label' => 'Operasional Gudang & Persediaan',
                'deskripsi' => 'Manajemen batch fisik, FEFO, picking, dan opname',
                'modules' => [
                    'gudang_stok::stok::batch' => ['label' => 'Stok Batch & Expired Date', 'entity' => 'gudang_stok::stok::batch', 'page' => 'page_StokBatchIndex'],
                    'gudang_stok::buku::mutasi' => ['label' => 'Kartu Stok & Mutasi', 'entity' => 'gudang_stok::buku::mutasi', 'page' => 'page_BukuMutasiIndex'],
                    'gudang_stok::monitoring::fefo' => ['label' => 'Monitoring Kedaluwarsa FEFO', 'entity' => 'gudang_stok::monitoring::fefo', 'page' => 'page_MonitoringStokFefoIndex'],
                    'gudang_stok::stok::opname' => ['label' => 'Stock Opname Fisik Lapangan', 'entity' => 'gudang_stok::stok::opname', 'page' => 'page_StockOpnameIndex'],
                    'gudang_stok::otorisasi::penyesuaian' => ['label' => 'Otorisasi Penyesuaian Opname', 'entity' => 'gudang_stok::otorisasi::penyesuaian', 'page' => 'page_OtorisasiPenyesuaianIndex'],
                    'gudang_stok::tata::letak::rak' => ['label' => 'Visualisasi Denah & Rak Gudang', 'entity' => 'gudang_stok::tata::letak::rak', 'page' => 'page_TataLetakRakIndex'],
                    'gudang_stok::distribusi::approval' => ['label' => 'Otorisasi Distribusi (Admin Logistik)', 'entity' => 'gudang_stok::distribusi::approval', 'page' => 'page_DistribusiApprovalIndex'],
                    'gudang_stok::picking::distribusi' => ['label' => 'Picking FEFO & SPPB', 'entity' => 'gudang_stok::picking::distribusi', 'page' => 'page_PickingDistribusiIndex'],
                    'gudang_stok::penerimaan::po' => ['label' => 'Penerimaan Inbound Vendor (PO)', 'entity' => 'gudang_stok::penerimaan::po', 'page' => 'page_PenerimaanPoIndex'],
                ],
            ],

            'pengadaan' => [
                'label' => 'Pengadaan & Rekanan Vendor',
                'deskripsi' => 'Siklus pengadaan komoditas, pesanan PO, dan serapan anggaran',
                'modules' => [
                    'pengadaan::permintaan::pengadaan' => ['label' => 'Permintaan Pengadaan (PR)', 'entity' => 'pengadaan::permintaan::pengadaan', 'page' => 'page_PermintaanPengadaanIndex'],
                    'pengadaan::pesanan::pembelian' => ['label' => 'Pesanan Pembelian (PO)', 'entity' => 'pengadaan::pesanan::pembelian', 'page' => 'page_PesananPembelianIndex'],
                    'pengadaan::tracking::po' => ['label' => 'Pelacakan Pengiriman PO Vendor', 'entity' => 'pengadaan::tracking::po', 'page' => 'page_TrackingPoIndex'],
                    'pengadaan::master::rekanan' => ['label' => 'Evaluasi Kinerja Vendor', 'entity' => 'pengadaan::master::rekanan', 'page' => 'page_MasterRekananIndex'],
                    'pengadaan::realisasi::anggaran' => ['label' => 'Pagu & Realisasi Anggaran Pengadaan', 'entity' => 'pengadaan::realisasi::anggaran', 'page' => 'page_RealisasiAnggaranIndex'],
                ],
            ],

            'pemeliharaan' => [
                'label' => 'Pemeliharaan Fasilitas & IPSRS',
                'deskripsi' => 'Surat Perintah Kerja, lembar kerja teknisi, dan preventif',
                'modules' => [
                    'pemeliharaan::spk::perbaikan' => ['label' => 'Disposisi SPK Perbaikan', 'entity' => 'pemeliharaan::spk::perbaikan', 'page' => 'page_DaftarSpkPerbaikanIndex'],
                    'pemeliharaan::lembar::kerja' => ['label' => 'Lembar Kerja Pengerjaan Teknisi', 'entity' => 'pemeliharaan::lembar::kerja', 'page' => 'page_LembarKerjaPengerjaanIndex'],
                    'pemeliharaan::pemeliharaan::berkala' => ['label' => 'Jadwal Pemeliharaan Preventif', 'entity' => 'pemeliharaan::pemeliharaan::berkala', 'page' => 'page_PemeliharaanBerkalaIndex'],
                    'pemeliharaan::peminjaman::alat' => ['label' => 'Peminjaman Alat Kerja Teknisi', 'entity' => 'pemeliharaan::peminjaman::alat', 'page' => 'page_PeminjamanAlatIndex'],
                    'pemeliharaan::permintaan::sparepart' => ['label' => 'Permintaan Suku Cadang IPSRS', 'entity' => 'pemeliharaan::permintaan::sparepart', 'page' => 'page_PermintaanSparepartIndex'],
                    'pemeliharaan::rekam::medis::aset' => ['label' => 'Rekam Medis & Riwayat Servis Aset', 'entity' => 'pemeliharaan::rekam::medis::aset', 'page' => 'page_RekamMedisAsetIndex'],
                ],
            ],

            'audit_spi' => [
                'label' => 'Audit Internal & Pengawasan (SPI)',
                'deskripsi' => 'Pengawasan kepatuhan, forensik log, selisih opname, dan CAPA',
                'modules' => [
                    'audit_manajemen::audit::trail'        => ['label' => 'Forensik Digital Audit Trail', 'entity' => 'audit_manajemen::audit::trail', 'page' => 'page_AuditTrailIndex'],
                    'audit_manajemen::selisih::opname'      => ['label' => 'Pemeriksaan Selisih Opname', 'entity' => 'audit_manajemen::selisih::opname', 'page' => 'page_PemeriksaanSelisihOpnameIndex'],
                    'audit_manajemen::kepatuhan::pengadaan' => ['label' => 'Audit Kepatuhan PO vs PR', 'entity' => 'audit_manajemen::kepatuhan::pengadaan', 'page' => 'page_AuditKepatuhanPengadaanIndex'],
                    'audit_manajemen::mutasi::distribusi'   => ['label' => 'Audit Mutasi SPPB vs RCV', 'entity' => 'audit_manajemen::mutasi::distribusi', 'page' => 'page_AuditMutasiDistribusiIndex'],
                    'audit_manajemen::temuan::capa'         => ['label' => 'Register Temuan Audit & CAPA', 'entity' => 'audit_manajemen::temuan::capa', 'page' => 'page_RegisterTemuanCapaIndex'],
                ],
            ],

            'system_admin' => [
                'label' => 'Tata Kelola Sistem & Konfigurasi (IT)',
                'deskripsi' => 'Manajemen akun pengguna, matriks peran RBAC, parameter, dan cron task',
                'modules' => [
                    'user::pengguna'     => ['label' => 'Manajemen Akun Pengguna', 'entity' => 'user::pengguna', 'page' => 'page_PenggunaIndex'],
                    'user::role'         => ['label' => 'Manajemen Peran & Hak Akses (RBAC)', 'entity' => 'user::role', 'page' => 'page_RoleManager'],
                    'system::pengaturan' => ['label' => 'Parameter Sistem & Konfigurasi', 'entity' => 'system::pengaturan', 'page' => 'page_PengaturanSistemIndex'],
                    'system::kesehatan'  => ['label' => 'Diagnostik Kesehatan Sistem', 'entity' => 'system::kesehatan', 'page' => 'page_KesehatanSistemIndex'],
                    'system::scheduler'  => ['label' => 'Pengendali Task Scheduler & Cron', 'entity' => 'system::scheduler', 'page' => 'page_SchedulerNotifikasiIndex'],
                ],
            ],
        ];
    }

    /**
     * Menghasilkan seluruh daftar permission Spatie resmi yang ada di sistem.
     */
    public static function getAllPermissions(): array
    {
        $permissions = ['panel_user'];
        $domains = static::getDomains();

        foreach ($domains as $domainKey => $domain) {
            foreach ($domain['modules'] as $modKey => $mod) {
                if (! empty($mod['page_only'])) {
                    $permissions[] = $mod['permission'];
                } else {
                    $entity = $mod['entity'];
                    if (! empty($mod['page'])) {
                        $permissions[] = $mod['page'];
                    }
                    $permissions[] = "view_any_{$entity}";
                    $permissions[] = "view_{$entity}";
                    $permissions[] = "create_{$entity}";
                    $permissions[] = "update_{$entity}";
                    $permissions[] = "delete_{$entity}";
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Mengonversi state matriks 4-kolom ke array permission Spatie.
     * Centang 'Lihat Data' otomatis mencakup 'Akses Halaman' (page_*) dan view_any_*.
     */
    public static function toPermissions(array $state): array
    {
        $assigned = ['panel_user'];
        $domains = static::getDomains();

        // 1. Dashboard portal permissions
        if (! empty($state['dashboards']) && is_array($state['dashboards'])) {
            foreach ($state['dashboards'] as $perm => $checked) {
                if ($checked) {
                    $assigned[] = $perm;
                }
            }
        }

        // 2. Operasional 4-Kolom Actions
        foreach ($domains as $domainKey => $domain) {
            if ($domainKey === 'dashboard_portal') continue;

            foreach ($domain['modules'] as $modKey => $mod) {
                $entity = $mod['entity'];

                // Jika "Lihat Data" dicentang -> otomatis sertakan Akses Halaman + view_any + view
                if (! empty($state['view_any'][$entity])) {
                    $assigned[] = "view_any_{$entity}";
                    $assigned[] = "view_{$entity}";
                    if (! empty($mod['page'])) {
                        $assigned[] = $mod['page'];
                    }
                }

                if (! empty($state['create'][$entity])) {
                    $assigned[] = "create_{$entity}";
                }
                if (! empty($state['update'][$entity])) {
                    $assigned[] = "update_{$entity}";
                }
                if (! empty($state['delete'][$entity])) {
                    $assigned[] = "delete_{$entity}";
                }
            }
        }

        return array_values(array_unique($assigned));
    }

    /**
     * Membaca permission database peran dan menyusun kembali ke format state matriks 4-kolom.
     */
    public static function fromRole(Role $role): array
    {
        $rolePermissions = $role->permissions->pluck('name')->flip()->toArray();

        $state = [
            'dashboards' => [],
            'view_any' => [],
            'create' => [],
            'update' => [],
            'delete' => [],
        ];

        $domains = static::getDomains();
        foreach ($domains as $domainKey => $domain) {
            foreach ($domain['modules'] as $modKey => $mod) {
                if ($domainKey === 'dashboard_portal') {
                    $perm = $mod['permission'];
                    $state['dashboards'][$perm] = isset($rolePermissions[$perm]);
                } else {
                    $entity = $mod['entity'];
                    // Lihat data aktif jika memiliki view_any atau akses halaman
                    $pagePerm = $mod['page'] ?? null;
                    $state['view_any'][$entity] = isset($rolePermissions["view_any_{$entity}"])
                        || ($pagePerm && isset($rolePermissions[$pagePerm]));

                    $state['create'][$entity] = isset($rolePermissions["create_{$entity}"]);
                    $state['update'][$entity] = isset($rolePermissions["update_{$entity}"]);
                    $state['delete'][$entity] = isset($rolePermissions["delete_{$entity}"]);
                }
            }
        }

        return $state;
    }

    /**
     * Mengambil daftar izin non-matriks (kustom) yang dimiliki peran di database,
     * yaitu izin yang tidak termasuk dalam daftar yang dikelola oleh PagePermissionRegistry.
     */
    public static function getNonMatrixPermissions(Role $role): array
    {
        $managedPermissions = static::getAllPermissions();
        $rolePermissions = $role->permissions()->pluck('name')->toArray();

        return array_values(array_diff($rolePermissions, $managedPermissions));
    }
}
