<?php

use App\Http\Controllers\Api\V1\Aset\AsetKatalogApiController;
use App\Http\Controllers\Api\V1\Aset\AsetPeminjamanApiController;
use App\Http\Controllers\Api\V1\Aset\AsetPeminjamanApprovalApiController;
use App\Http\Controllers\Api\V1\Aset\AsetPengalihanApiController;
use App\Http\Controllers\Api\V1\Audit\AuditKepatuhanApiController;
use App\Http\Controllers\Api\V1\Audit\AuditTemuanApiController;
use App\Http\Controllers\Api\V1\Auth\FcmTokenApiController;
use App\Http\Controllers\Api\V1\Audit\AuditTrailApiController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\DashboardApiController;
use App\Http\Controllers\Api\V1\Distribusi\ApprovalController;
use App\Http\Controllers\Api\V1\Distribusi\DistribusiAmprahApiController;
use App\Http\Controllers\Api\V1\Distribusi\DistribusiPengirimanApiController;
use App\Http\Controllers\Api\V1\Gudang\GudangPenerimaanPoApiController;
use App\Http\Controllers\Api\V1\Gudang\GudangPengalihanBarangApiController;
use App\Http\Controllers\Api\V1\Gudang\GudangTataLetakApiController;
use App\Http\Controllers\Api\V1\Ipsrs\IpsrsJadwalApiController;
use App\Http\Controllers\Api\V1\Notifikasi\NotifikasiApiController;
use App\Http\Controllers\Api\V1\Ipsrs\IpsrsRekamMedisApiController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceLembarKerjaApiController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceSpkApiController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceTiketApiController;
use App\Http\Controllers\Api\V1\Master\MasterApiController;
use App\Http\Controllers\Api\V1\Pengadaan\AnggaranApiController;
use App\Http\Controllers\Api\V1\Pengadaan\PengadaanKatalogApiController;
use App\Http\Controllers\Api\V1\Pengadaan\PengadaanPoApiController;
use App\Http\Controllers\Api\V1\Pengadaan\PengadaanPrApiController;
use App\Http\Controllers\Api\V1\Persediaan\PersediaanMonitoringApiController;
use App\Http\Controllers\Api\V1\Persediaan\PersediaanOpnameApiController;
use App\Http\Controllers\Api\V1\Persediaan\PersediaanPenyesuaianApiController;
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

        // 3. Endpoint Terproteksi Sanctum dengan Rate Limiter
        Route::middleware(['auth:sanctum', 'api.active', 'throttle:' . config('simlog.api.throttle.max_attempts') . ',' . config('simlog.api.throttle.decay_minutes')])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/ganti-password', [AuthController::class, 'gantiPasswordWajib']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/dashboard/summary', [DashboardApiController::class, 'summary']);

        // Notifikasi inbox & token FCM (docs/NOTIFICATION_CONTRACT.md)
        Route::post('/auth/fcm-token', [FcmTokenApiController::class, 'simpan']);
        Route::delete('/auth/fcm-token', [FcmTokenApiController::class, 'hapus']);
        Route::get('/notifikasi', [NotifikasiApiController::class, 'index']);
        Route::post('/notifikasi/baca-semua', [NotifikasiApiController::class, 'bacaSemua']);
        Route::post('/notifikasi/{id}/dibaca', [NotifikasiApiController::class, 'dibaca'])->whereNumber('id');
        // 4. Persetujuan Cepat Pejabat / Karu
        Route::get('/approval/pending', [ApprovalController::class, 'pending']);
        Route::post('/approval/pr/{id}', [ApprovalController::class, 'approvePr']);
        Route::post('/approval/amprah/{id}', [ApprovalController::class, 'approveAmprah']);

        // 5. Operasional Gudang & Scanner Barcode
        Route::get('/gudang/rak', [GudangTataLetakApiController::class, 'daftarRak']);
        Route::get('/gudang/rak/{id}', [GudangTataLetakApiController::class, 'detailRak']);
        Route::post('/gudang/scan-batch', [GudangTataLetakApiController::class, 'scanBatch']);
        Route::post('/gudang/opname-count', [GudangTataLetakApiController::class, 'opnameCount']);
        Route::get('/gudang/po-inbound', [GudangPenerimaanPoApiController::class, 'poInbound']);
        Route::post('/gudang/penerimaan-po', [GudangPenerimaanPoApiController::class, 'simpanPenerimaanPo']);
        Route::get('/gudang/pengalihan-barang', [GudangPengalihanBarangApiController::class, 'daftarPengalihanBarang']);
        Route::post('/gudang/pengalihan-barang', [GudangPengalihanBarangApiController::class, 'simpanPengalihanBarang']);
        Route::post('/gudang/pengalihan-barang/{id}/otorisasi', [GudangPengalihanBarangApiController::class, 'otorisasiPengalihanBarang']);
        Route::post('/gudang/pengalihan-barang/{id}/serah-terima', [GudangPengalihanBarangApiController::class, 'serahTerimaPengalihanBarang']);
        Route::post('/gudang/pengalihan-barang/{id}/batal', [GudangPengalihanBarangApiController::class, 'batalPengalihanBarang']);
        Route::get('/gudang/stok-tersedia', [GudangPengalihanBarangApiController::class, 'stokTersedia']);


        // 6. Sirkulasi Aset & Scanner QR
        Route::post('/aset/scan-qr', [AsetKatalogApiController::class, 'scanQr']);
        Route::get('/aset/detail/{id}', [AsetKatalogApiController::class, 'detailAset']);
        Route::get('/aset/katalog-tersedia', [AsetKatalogApiController::class, 'katalogTersedia']);
        Route::get('/aset/peminjaman', [AsetPeminjamanApiController::class, 'daftarPeminjaman']);
        Route::post('/aset/peminjaman', [AsetPeminjamanApiController::class, 'simpanPeminjaman']);
        Route::post('/aset/peminjaman/{id}/batal', [AsetPeminjamanApiController::class, 'batalPeminjaman']);
        Route::post('/aset/peminjaman/{id}/perpanjang', [AsetPeminjamanApiController::class, 'perpanjangPeminjaman']);
        Route::get('/aset/approval-peminjaman', [AsetPeminjamanApprovalApiController::class, 'daftarApprovalPeminjaman']);
        Route::post('/aset/peminjaman/{id}/otorisasi', [AsetPeminjamanApprovalApiController::class, 'otorisasiPeminjaman']);
        Route::post('/aset/serah-terima', [AsetPeminjamanApprovalApiController::class, 'serahTerima']);
        Route::post('/aset/kembalikan', [AsetPeminjamanApprovalApiController::class, 'kembalikan']);
        Route::get('/aset/pengalihan', [AsetPengalihanApiController::class, 'daftarPengalihan']);
        Route::get('/aset/pengalihan/komoditas-tersedia', [AsetPengalihanApiController::class, 'komoditasAsetTersedia']);
        Route::get('/aset/pengalihan/inventaris-asal', [AsetPengalihanApiController::class, 'inventarisAsal']);
        Route::post('/aset/pengalihan', [AsetPengalihanApiController::class, 'simpanPengalihan']);
        Route::post('/aset/pengalihan/{id}/batal', [AsetPengalihanApiController::class, 'batalPengalihan']);
        Route::post('/aset/pengalihan/{id}/otorisasi', [AsetPengalihanApiController::class, 'otorisasiPengalihan']);
        Route::post('/aset/pengalihan/{id}/serah-terima', [AsetPengalihanApiController::class, 'serahTerimaPengalihan']);

        // 7. Pemeliharaan Sarana & Mobile Teknisi IPSRS
        Route::get('/maintenance/tiket', [MaintenanceTiketApiController::class, 'daftarTiketUnit']);
        Route::get('/maintenance/aset-unit', [MaintenanceTiketApiController::class, 'asetUnitTersedia']);
        Route::get('/maintenance/teknisi', [MaintenanceSpkApiController::class, 'daftarTeknisi']);
        Route::get('/maintenance/tiket/{id}', [MaintenanceTiketApiController::class, 'detailTiketUnit']);
        Route::post('/maintenance/tiket/{id}/disposisi', [MaintenanceSpkApiController::class, 'disposisiTiket']);
        Route::post('/maintenance/tiket/{id}/batal', [MaintenanceTiketApiController::class, 'batalTiketUnit']);
        Route::post('/maintenance/lapor-cepat', [MaintenanceTiketApiController::class, 'laporCepat']);
        Route::get('/maintenance/spk', [MaintenanceSpkApiController::class, 'spkList']);
        Route::get('/maintenance/spk/{id}', [MaintenanceSpkApiController::class, 'spkDetail']);
        Route::post('/maintenance/spk/{id}/log', [MaintenanceLembarKerjaApiController::class, 'submitLog']);

        // 8. Modul Pengadaan, Katalog Barang & Penerbitan PO
        Route::get('/master/barang', [PengadaanKatalogApiController::class, 'katalogBarang']);
        Route::get('/pengadaan/pr', [PengadaanPrApiController::class, 'daftarPr']);
        Route::post('/pengadaan/pr', [PengadaanPrApiController::class, 'simpanPr']);
        Route::get('/pengadaan/pemasok', [PengadaanPoApiController::class, 'daftarPemasok']);
        Route::get('/pengadaan/po', [PengadaanPoApiController::class, 'daftarPo']);
        Route::get('/pengadaan/po/{id}', [PengadaanPoApiController::class, 'detailPo']);
        Route::post('/pengadaan/po', [PengadaanPoApiController::class, 'terbitkanPo']);
        Route::post('/pengadaan/po/{id}/kirim', [PengadaanPoApiController::class, 'updateStatusKirim']);

        // 9. Modul Distribusi & Amprah Ruangan
        Route::get('/distribusi/katalog-barang', [DistribusiAmprahApiController::class, 'katalogBarang']);
        Route::get('/distribusi/amprah', [DistribusiAmprahApiController::class, 'daftarAmprah']);
        Route::get('/distribusi/amprah/{id}', [DistribusiAmprahApiController::class, 'detailAmprah']);
        Route::post('/distribusi/amprah', [DistribusiAmprahApiController::class, 'simpanAmprah']);
        Route::post('/distribusi/amprah/{id}/batal', [DistribusiAmprahApiController::class, 'batalAmprah']);
        Route::get('/distribusi/antrean-picking', [DistribusiPengirimanApiController::class, 'antreanPicking']);
        Route::post('/distribusi/picking', [DistribusiPengirimanApiController::class, 'eksekusiPicking']);
        Route::get('/distribusi/sppb-inbound', [DistribusiPengirimanApiController::class, 'sppbInbound']);
        Route::post('/distribusi/sppb/{id}/terima', [DistribusiPengirimanApiController::class, 'konfirmasiTerimaSppb']);

        // 10. Master Data Komoditas & Register Aset Lengkap (Semua Role)
        Route::get('/master/barang-lengkap', [MasterApiController::class, 'barangLengkap']);
        Route::get('/master/barang/{id}', [MasterApiController::class, 'detailBarang']);
        Route::get('/master/aset-lengkap', [MasterApiController::class, 'asetLengkap']);
        Route::get('/master/kategori', [MasterApiController::class, 'daftarKategori']);
        Route::get('/master/gudang', [MasterApiController::class, 'daftarGudang']);
        Route::get('/master/unit', [MasterApiController::class, 'daftarUnit']);
        // 11. Modul Persediaan & Opname Kepala Gudang
        Route::get('/gudang/fefo/monitoring', [PersediaanMonitoringApiController::class, 'monitoringFefo']);
        Route::get('/gudang/mutasi/buku', [PersediaanMonitoringApiController::class, 'bukuMutasi']);
        Route::get('/gudang/opname/sesi', [PersediaanOpnameApiController::class, 'daftarOpname']);
        Route::post('/gudang/opname/inisiasi', [PersediaanOpnameApiController::class, 'inisiasiOpname']);
        Route::post('/gudang/opname/{id}/count', [PersediaanOpnameApiController::class, 'simpanHitungFisik']);
        Route::get('/gudang/penyesuaian', [PersediaanPenyesuaianApiController::class, 'daftarPenyesuaian']);
        Route::post('/gudang/penyesuaian/{id}/approve', [PersediaanPenyesuaianApiController::class, 'approvePenyesuaian']);
        Route::post('/gudang/penyesuaian/{id}/reject', [PersediaanPenyesuaianApiController::class, 'rejectPenyesuaian']);

        // 12. Modul Teknisi IPSRS: Jadwal Preventif & Rekam Medis Aset
        Route::get('/maintenance/preventif/jadwal', [IpsrsJadwalApiController::class, 'jadwalPreventif']);
        Route::post('/maintenance/preventif/jadwal', [IpsrsJadwalApiController::class, 'simpanJadwalPreventif']);
        Route::post('/maintenance/preventif/{id}/eksekusi', [IpsrsJadwalApiController::class, 'eksekusiPreventif']);
        Route::get('/maintenance/rekam-medis/katalog', [IpsrsRekamMedisApiController::class, 'katalogRekamMedis']);
        Route::get('/maintenance/rekam-medis/{aset_id}', [IpsrsRekamMedisApiController::class, 'rekamMedisAset']);

        // 13. Modul Audit Internal SPI & Tata Kelola
        Route::get('/audit/summary', [AuditKepatuhanApiController::class, 'summary']);
        Route::get('/audit/trail', [AuditTrailApiController::class, 'auditTrail']);
        Route::get('/audit/trail/{id}', [AuditTrailApiController::class, 'auditTrailDetail']);
        Route::get('/audit/kepatuhan-pengadaan', [AuditKepatuhanApiController::class, 'kepatuhanPengadaan']);
        Route::post('/audit/kepatuhan-pengadaan/{po_id}/capa', [AuditTemuanApiController::class, 'buatTemuanKepatuhan']);
        Route::get('/audit/selisih-opname', [AuditKepatuhanApiController::class, 'selisihOpname']);
        Route::get('/audit/temuan-capa', [AuditTemuanApiController::class, 'registerTemuan']);
        Route::post('/audit/temuan-capa', [AuditTemuanApiController::class, 'simpanTemuan']);
        Route::post('/audit/temuan-capa/{id}/tindak-lanjut', [AuditTemuanApiController::class, 'tindakLanjutTemuan']);
        Route::post('/audit/temuan-capa/{id}/evaluasi', [AuditTemuanApiController::class, 'evaluasiTemuan']);

        // 14. Modul Pagu & Realisasi Pengeluaran Logistik & Suku Cadang IPSRS
        Route::get('/anggaran/realisasi', [AnggaranApiController::class, 'realisasi']);
        Route::get('/anggaran/unit/{unitId}', [AnggaranApiController::class, 'unitDetail']);
    });
});
