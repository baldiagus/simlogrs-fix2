<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\LoginGateway;
use App\Livewire\User\RoleManager;
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
use App\Livewire\Master\UnitIndex;
use App\Livewire\Master\GudangIndex;
use App\Livewire\Master\LokasiGudangIndex;
use App\Livewire\Master\KategoriBarangIndex;
use App\Livewire\Master\SatuanBarangIndex;
use App\Livewire\Master\MerekBarangIndex;
use App\Livewire\Master\PemasokIndex;
use App\Livewire\Master\BarangIndex;
// Root Gateway & Login Form
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', LoginGateway::class)->name('login');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// 1. Portal Super Administrator
Route::middleware(['auth', 'portal.access:super-admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/dashboard', SuperAdminDashboard::class)->name('dashboard');
    Route::get('/roles', RoleManager::class)->name('roles');
    Route::get('/unit', UnitIndex::class)->name('unit');
    Route::get('/gudang', GudangIndex::class)->name('gudang');
    Route::get('/lokasi-gudang', LokasiGudangIndex::class)->name('lokasi-gudang');
    Route::get('/kategori', KategoriBarangIndex::class)->name('kategori');
    Route::get('/satuan', SatuanBarangIndex::class)->name('satuan');
    Route::get('/merek', MerekBarangIndex::class)->name('merek');
    Route::get('/pemasok', PemasokIndex::class)->name('pemasok');
    Route::get('/barang', BarangIndex::class)->name('barang');
});

// 2. Portal Admin Logistik Sentral
Route::middleware(['auth', 'portal.access:admin-logistik'])->prefix('admin-logistik')->name('admin-logistik.')->group(function () {
    Route::get('/dashboard', AdminLogistikDashboard::class)->name('dashboard');
    Route::get('/unit', UnitIndex::class)->name('unit');
    Route::get('/gudang', GudangIndex::class)->name('gudang');
    Route::get('/lokasi-gudang', LokasiGudangIndex::class)->name('lokasi-gudang');
    Route::get('/kategori', KategoriBarangIndex::class)->name('kategori');
    Route::get('/satuan', SatuanBarangIndex::class)->name('satuan');
    Route::get('/merek', MerekBarangIndex::class)->name('merek');
    Route::get('/pemasok', PemasokIndex::class)->name('pemasok');
    Route::get('/barang', BarangIndex::class)->name('barang');
});

// 3. Portal Kepala Gudang
Route::middleware(['auth', 'portal.access:kepala-gudang'])->prefix('kepala-gudang')->name('kepala-gudang.')->group(function () {
    Route::get('/dashboard', KepalaGudangDashboard::class)->name('dashboard');
    Route::get('/barang', BarangIndex::class)->name('barang');
});

// 4. Portal Petugas Gudang
Route::middleware(['auth', 'portal.access:petugas-gudang'])->prefix('petugas-gudang')->name('petugas-gudang.')->group(function () {
    Route::get('/dashboard', GudangDashboard::class)->name('dashboard');
    Route::get('/barang', BarangIndex::class)->name('barang');
});

// 5. Portal Bagian Pengadaan
Route::middleware(['auth', 'portal.access:pengadaan'])->prefix('pengadaan')->name('pengadaan.')->group(function () {
    Route::get('/dashboard', PengadaanDashboard::class)->name('dashboard');
    Route::get('/barang', BarangIndex::class)->name('barang');
});

// 6. Portal Kepala Unit / Karu
Route::middleware(['auth', 'portal.access:kepala-unit'])->prefix('kepala-unit')->name('kepala-unit.')->group(function () {
    Route::get('/dashboard', KepalaUnitDashboard::class)->name('dashboard');
});

// 7. Portal Petugas / Staf Ruangan
Route::middleware(['auth', 'portal.access:petugas-unit'])->prefix('petugas-unit')->name('petugas-unit.')->group(function () {
    Route::get('/dashboard', UnitDashboard::class)->name('dashboard');
});

// 8. Portal Teknisi IPSRS
Route::middleware(['auth', 'portal.access:teknisi'])->prefix('teknisi')->name('teknisi.')->group(function () {
    Route::get('/dashboard', TeknisiDashboard::class)->name('dashboard');
});

// 9. Portal Manajemen Direksi
Route::middleware(['auth', 'portal.access:manajemen'])->prefix('manajemen')->name('manajemen.')->group(function () {
    Route::get('/dashboard', ManajemenDashboard::class)->name('dashboard');
});

// 10. Portal Auditor Internal (SPI)
Route::middleware(['auth', 'portal.access:auditor'])->prefix('auditor')->name('auditor.')->group(function () {
    Route::get('/dashboard', AuditorDashboard::class)->name('dashboard');
});
