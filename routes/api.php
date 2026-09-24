<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\GudangApiController;
use App\Http\Controllers\Api\V1\AsetApiController;
use App\Http\Controllers\Api\V1\MaintenanceApiController;
use App\Http\Controllers\Api\V1\PengadaanApiController;
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
        Route::get('/aset/katalog-tersedia', [AsetApiController::class, 'katalogTersedia']);
        Route::get('/aset/peminjaman', [AsetApiController::class, 'daftarPeminjaman']);
        Route::post('/aset/peminjaman', [AsetApiController::class, 'simpanPeminjaman']);
        Route::post('/aset/peminjaman/{id}/batal', [AsetApiController::class, 'batalPeminjaman']);
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
    });
});
