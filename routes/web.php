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
        });
}
