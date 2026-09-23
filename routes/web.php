<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\LoginGateway;
use App\Livewire\Dashboard\SuperAdminDashboard;
use App\Livewire\Dashboard\AdminLogistikDashboard;
use App\Livewire\Dashboard\KepalaGudangDashboard;
use App\Livewire\Dashboard\GudangDashboard;
use App\Livewire\Dashboard\PengadaanDashboard;
use App\Livewire\Dashboard\KepalaUnitDashboard;
use App\Livewire\Dashboard\UnitDashboard;
use App\Livewire\Dashboard\TeknisiDashboard;
use App\Livewire\Dashboard\ManajemenDashboard;
use App\Livewire\Dashboard\AuditorDashboard;
use App\Livewire\User\RoleManager;
use App\Livewire\Master\UnitIndex;
use App\Livewire\Master\GudangIndex;
use App\Livewire\Master\LokasiGudangIndex;
use App\Livewire\Master\KategoriBarangIndex;
use App\Livewire\Master\SatuanBarangIndex;
use App\Livewire\Master\MerekBarangIndex;
use App\Livewire\Master\PemasokIndex;
use App\Livewire\Master\BarangIndex;
use App\Livewire\Master\AsetIndex;
use App\Livewire\Pengadaan\PermintaanPengadaanIndex;
use App\Livewire\Pengadaan\PesananPembelianIndex;
use App\Livewire\Pengadaan\TrackingPoIndex;
use App\Livewire\Pengadaan\PenerimaanPoIndex;
use App\Livewire\Distribusi\PermintaanBarangIndex;
use App\Livewire\Distribusi\ApprovalPermintaanIndex;
use App\Livewire\Distribusi\DistribusiApprovalIndex;
use App\Livewire\Distribusi\PickingDistribusiIndex;
use App\Livewire\Distribusi\PenerimaanBarangIndex;
use App\Livewire\Aset\PeminjamanAsetIndex;
use App\Livewire\Aset\ApprovalPeminjamanIndex;
use App\Livewire\Aset\PengembalianPeminjamanIndex;
use App\Livewire\Ipsrs\LaporServisIndex;
use App\Livewire\Ipsrs\DaftarSpkPerbaikanIndex;
use App\Livewire\Ipsrs\LembarKerjaPengerjaanIndex;
use App\Livewire\Ipsrs\RekamMedisAsetIndex;
use App\Livewire\Ipsrs\PemeliharaanBerkalaIndex;
use App\Livewire\Persediaan\MonitoringStokFefoIndex;
use App\Livewire\Persediaan\BukuMutasiIndex;
use App\Livewire\Persediaan\StockOpnameIndex;
use App\Livewire\Persediaan\OtorisasiPenyesuaianIndex;
use App\Livewire\Audit\AuditTrailIndex;
use App\Livewire\Audit\AuditKepatuhanPengadaanIndex;
use App\Livewire\Audit\RegisterTemuanCapaIndex;
use App\Http\Controllers\SpkPrintController;

// Root Gateway & Login Form
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', LoginGateway::class)->name('login');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// Peta 10 Portal Independen & Dashboard Masing-Masing
$portals = [
    'super-admin'    => SuperAdminDashboard::class,
    'admin-logistik' => AdminLogistikDashboard::class,
    'kepala-gudang'  => KepalaGudangDashboard::class,
    'petugas-gudang' => GudangDashboard::class,
    'pengadaan'      => PengadaanDashboard::class,
    'kepala-unit'    => KepalaUnitDashboard::class,
    'petugas-unit'   => UnitDashboard::class,
    'teknisi'        => TeknisiDashboard::class,
    'manajemen'      => ManajemenDashboard::class,
    'auditor'        => AuditorDashboard::class,
];

// Registrasi Seluruh Route Portal (100% Zero-Hardcode RBAC)
// Visibilitas dan eksekusi dikendalikan dinamis oleh permission Spatie di level komponen
foreach ($portals as $portalKey => $dashboardClass) {
    Route::middleware(['auth', "portal.access:{$portalKey}"])
        ->prefix($portalKey)
        ->name("{$portalKey}.")
        ->group(function () use ($portalKey, $dashboardClass) {
            // Dashboard Resmi Peran
            Route::get('/dashboard', $dashboardClass)->name('dashboard');

            // Manajemen RBAC Khusus Super Admin
            if ($portalKey === 'super-admin') {
                Route::get('/roles', RoleManager::class)->name('roles');
            }

            // Master Data & Katalog Logistik (Grup 2)
            Route::get('/barang', BarangIndex::class)->name('barang');
            Route::get('/aset', AsetIndex::class)->name('aset');
            Route::get('/unit', UnitIndex::class)->name('unit');
            Route::get('/gudang', GudangIndex::class)->name('gudang');
            Route::get('/lokasi-gudang', LokasiGudangIndex::class)->name('lokasi-gudang');
            Route::get('/kategori', KategoriBarangIndex::class)->name('kategori');
            Route::get('/satuan', SatuanBarangIndex::class)->name('satuan');
            Route::get('/merek', MerekBarangIndex::class)->name('merek');
            Route::get('/pemasok', PemasokIndex::class)->name('pemasok');

            // Siklus Pengadaan Logistik (Grup 4)
            Route::get('/permintaan-pengadaan', PermintaanPengadaanIndex::class)->name('permintaan-pengadaan');
            Route::get('/pesanan-pembelian', PesananPembelianIndex::class)->name('pesanan-pembelian');
            Route::get('/tracking-po', TrackingPoIndex::class)->name('tracking-po');
            Route::get('/penerimaan-po', PenerimaanPoIndex::class)->name('penerimaan-po');

            // Siklus Distribusi Ruangan & Picking FEFO (Grup 5)
            Route::get('/permintaan-barang', PermintaanBarangIndex::class)->name('permintaan-barang');
            Route::get('/approval-permintaan', ApprovalPermintaanIndex::class)->name('approval-permintaan');
            Route::get('/distribusi-approval', DistribusiApprovalIndex::class)->name('distribusi-approval');
            Route::get('/picking-distribusi', PickingDistribusiIndex::class)->name('picking-distribusi');
            Route::get('/penerimaan-barang', PenerimaanBarangIndex::class)->name('penerimaan-barang');

            // Siklus Peminjaman & Sirkulasi Aset Non-Medis (Grup 6)
            Route::get('/peminjaman-aset', PeminjamanAsetIndex::class)->name('peminjaman-aset');
            Route::get('/approval-peminjaman', ApprovalPeminjamanIndex::class)->name('approval-peminjaman');
            Route::get('/pengembalian-aset', PengembalianPeminjamanIndex::class)->name('pengembalian-aset');

            // Siklus Pemeliharaan Sarana & IPSRS (Grup 7)
            Route::get('/lapor-servis', LaporServisIndex::class)->name('lapor-servis');
            Route::get('/spk', DaftarSpkPerbaikanIndex::class)->name('spk');
            Route::get('/spk/{id}/cetak-pdf', [SpkPrintController::class, 'cetakPdf'])->name('spk.cetak-pdf');
            Route::get('/lembar-kerja', LembarKerjaPengerjaanIndex::class)->name('lembar-kerja');
            Route::get('/rekam-medis-aset', RekamMedisAsetIndex::class)->name('rekam-medis-aset');
            Route::get('/jadwal-preventif', PemeliharaanBerkalaIndex::class)->name('jadwal-preventif');

            // Siklus Persediaan & Stok Opname (Grup 3)
            Route::get('/monitoring-fefo', MonitoringStokFefoIndex::class)->name('monitoring-fefo');
            Route::get('/buku-mutasi', BukuMutasiIndex::class)->name('buku-mutasi');
            Route::get('/stok-opname', StockOpnameIndex::class)->name('stok-opname');
            Route::get('/otorisasi-penyesuaian', OtorisasiPenyesuaianIndex::class)->name('otorisasi-penyesuaian');
            Route::get('/selisih-opname', OtorisasiPenyesuaianIndex::class)->name('selisih-opname');

            // Tata Kelola Audit Internal, SPI & CAPA (Grup 8)
            Route::get('/audit-trail', AuditTrailIndex::class)->name('audit-trail');
            Route::get('/kepatuhan-pengadaan', AuditKepatuhanPengadaanIndex::class)->name('kepatuhan-pengadaan');
            Route::get('/temuan-capa', RegisterTemuanCapaIndex::class)->name('temuan-capa');
        });
}
