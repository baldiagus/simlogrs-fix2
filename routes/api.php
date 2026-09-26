<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\GudangApiController;
use App\Http\Controllers\Api\V1\AsetApiController;
use App\Http\Controllers\Api\V1\MaintenanceApiController;
use App\Http\Controllers\Api\V1\PengadaanApiController;
use App\Http\Controllers\Api\V1\DistribusiApiController;
use App\Http\Controllers\Api\V1\DashboardApiController;
use App\Http\Controllers\Api\V1\MasterApiController;
use App\Http\Controllers\Api\V1\PersediaanApiController;
use App\Http\Controllers\Api\V1\IpsrsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 1. Health Check
    Route::get('/health', function () {
        return response()->json([
            'sukses' => true,
            'pesan'  => 'SIMLOG RS API v1 Online',
            'meta'   => [
                'versi_api' => 'v1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    });

    // 2. Autentikasi Publik Mobile
    Route::post('/auth/login', [AuthController::class, 'login']);

    // 3. Endpoint Terproteksi Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/dashboard/summary', [DashboardApiController::class, 'summary']);
        // 4. Persetujuan Cepat Pejabat / Karu
        Route::get('/approval/pending', [ApprovalController::class, 'pending']);
        Route::post('/approval/pr/{id}', [ApprovalController::class, 'approvePr']);
        Route::post('/approval/amprah/{id}', [ApprovalController::class, 'approveAmprah']);

        // 5. Operasional Gudang & Scanner Barcode
        Route::get('/gudang/rak', [GudangApiController::class, 'daftarRak']);
        Route::get('/gudang/rak/{id}', [GudangApiController::class, 'detailRak']);
        Route::post('/gudang/scan-batch', [GudangApiController::class, 'scanBatch']);
        Route::post('/gudang/opname-count', [GudangApiController::class, 'opnameCount']);
        Route::get('/gudang/po-inbound', [GudangApiController::class, 'poInbound']);
        Route::post('/gudang/penerimaan-po', [GudangApiController::class, 'simpanPenerimaanPo']);

        // 6. Sirkulasi Aset & Scanner QR
        Route::post('/aset/scan-qr', [AsetApiController::class, 'scanQr']);
        Route::get('/aset/detail/{id}', [AsetApiController::class, 'detailAset']);
        Route::get('/aset/katalog-tersedia', [AsetApiController::class, 'katalogTersedia']);
        Route::get('/aset/peminjaman', [AsetApiController::class, 'daftarPeminjaman']);
        Route::post('/aset/peminjaman', [AsetApiController::class, 'simpanPeminjaman']);
        Route::post('/aset/peminjaman/{id}/batal', [AsetApiController::class, 'batalPeminjaman']);
        Route::post('/aset/peminjaman/{id}/perpanjang', [AsetApiController::class, 'perpanjangPeminjaman']);
        Route::get('/aset/approval-peminjaman', [AsetApiController::class, 'daftarApprovalPeminjaman']);
        Route::post('/aset/peminjaman/{id}/otorisasi', [AsetApiController::class, 'otorisasiPeminjaman']);
        Route::post('/aset/serah-terima', [AsetApiController::class, 'serahTerima']);
        Route::post('/aset/kembalikan', [AsetApiController::class, 'kembalikan']);

        // 7. Pemeliharaan Sarana & Mobile Teknisi IPSRS
        Route::get('/maintenance/tiket', [MaintenanceApiController::class, 'daftarTiketUnit']);
        Route::get('/maintenance/aset-unit', [MaintenanceApiController::class, 'asetUnitTersedia']);
        Route::get('/maintenance/tiket/{id}', [MaintenanceApiController::class, 'detailTiketUnit']);
        Route::post('/maintenance/tiket/{id}/batal', [MaintenanceApiController::class, 'batalTiketUnit']);
        Route::post('/maintenance/lapor-cepat', [MaintenanceApiController::class, 'laporCepat']);
        Route::get('/maintenance/spk', [MaintenanceApiController::class, 'spkList']);
        Route::get('/maintenance/spk/{id}', [MaintenanceApiController::class, 'spkDetail']);
        Route::post('/maintenance/spk/{id}/log', [MaintenanceApiController::class, 'submitLog']);

        // 8. Modul Pengadaan & Katalog Barang (Petugas Unit & Karu)
        Route::get('/master/barang', [PengadaanApiController::class, 'katalogBarang']);
        Route::get('/pengadaan/pr', [PengadaanApiController::class, 'daftarPr']);
        Route::post('/pengadaan/pr', [PengadaanApiController::class, 'simpanPr']);

        // 9. Modul Distribusi & Amprah Ruangan
        Route::get('/distribusi/katalog-barang', [DistribusiApiController::class, 'katalogBarang']);
        Route::get('/distribusi/amprah', [DistribusiApiController::class, 'daftarAmprah']);
        Route::get('/distribusi/amprah/{id}', [DistribusiApiController::class, 'detailAmprah']);
        Route::post('/distribusi/amprah', [DistribusiApiController::class, 'simpanAmprah']);
        Route::post('/distribusi/amprah/{id}/batal', [DistribusiApiController::class, 'batalAmprah']);
        Route::get('/distribusi/antrean-picking', [DistribusiApiController::class, 'antreanPicking']);
        Route::post('/distribusi/picking', [DistribusiApiController::class, 'eksekusiPicking']);
        Route::get('/distribusi/sppb-inbound', [DistribusiApiController::class, 'sppbInbound']);
        Route::post('/distribusi/sppb/{id}/terima', [DistribusiApiController::class, 'konfirmasiTerimaSppb']);

        // 10. Master Data Komoditas & Register Aset Lengkap (Semua Role)
        Route::get('/master/barang-lengkap', [MasterApiController::class, 'barangLengkap']);
        Route::get('/master/barang/{id}', [MasterApiController::class, 'detailBarang']);
        Route::get('/master/aset-lengkap', [MasterApiController::class, 'asetLengkap']);
        Route::get('/master/kategori', [MasterApiController::class, 'daftarKategori']);

        // 11. Modul Persediaan & Opname Kepala Gudang
        Route::get('/gudang/fefo/monitoring', [PersediaanApiController::class, 'monitoringFefo']);
        Route::get('/gudang/mutasi/buku', [PersediaanApiController::class, 'bukuMutasi']);
        Route::get('/gudang/opname/sesi', [PersediaanApiController::class, 'daftarOpname']);
        Route::post('/gudang/opname/inisiasi', [PersediaanApiController::class, 'inisiasiOpname']);
        Route::post('/gudang/opname/{id}/count', [PersediaanApiController::class, 'simpanHitungFisik']);
        Route::get('/gudang/penyesuaian', [PersediaanApiController::class, 'daftarPenyesuaian']);
        Route::post('/gudang/penyesuaian/{id}/approve', [PersediaanApiController::class, 'approvePenyesuaian']);
        Route::post('/gudang/penyesuaian/{id}/reject', [PersediaanApiController::class, 'rejectPenyesuaian']);

        // 12. Modul Teknisi IPSRS: Jadwal Preventif & Rekam Medis Aset
        Route::get('/maintenance/preventif/jadwal', [IpsrsApiController::class, 'jadwalPreventif']);
        Route::post('/maintenance/preventif/{id}/eksekusi', [IpsrsApiController::class, 'eksekusiPreventif']);
        Route::get('/maintenance/rekam-medis/katalog', [IpsrsApiController::class, 'katalogRekamMedis']);
        Route::get('/maintenance/rekam-medis/{aset_id}', [IpsrsApiController::class, 'rekamMedisAset']);
    });
});
