# ADR 0005: Struktur Modul, Batas Ukuran File, dan Dekomposisi Layanan

Status: Diterima

## Konteks

Seiring bertambahnya fitur sistem logistik rumah sakit (SIMLOG RS), beberapa komponen arsitektur tumbuh melebihi batas keterbacaan dan perawatan yang sehat (*maintainability ceiling*):
- Controller API v1 monolitik mencapai 600 hingga 1.362 baris dengan mencampur berbagai sub-domain dalam satu kelas.
- Metode tunggal `DashboardApiController::summary()` membengkak menjadi 772 baris dengan 9 cabang kondisi peran.
- Komponen Livewire mencapai 600 hingga 870 baris dengan mencampur query antrean, wizard pemilihan penyedia fisik, transaksi mutasi, dan pencatatan audit log.
- Berkas template Blade monolitik menumpuk hingga 740 baris dengan menggabungkan tabel, kartu metrik, dan 3–4 modal dialog sekaligus.

Untuk menjaga integritas dan skalabilitas kode jangka panjang, diterapkan pedoman baku organisasi kode (*Code Organization & File Size Rules*): target ideal 100–200 baris, batas normal maksimal 300 baris, batas toleransi keras 500 baris, dan ukuran fungsi ideal maksimal 30–50 baris.

## Keputusan Arsitektural

### 1. Dekomposisi Controller API V1 (`app/Http/Controllers/Api/V1/`)

Seluruh controller API raksasa dipecah berdasarkan batas domain tanggung jawab tunggal (*Single Responsibility Principle*):
- `AsetApiController` (1.128 baris kode) dipecah menjadi:
  - `AsetKatalogApiController`: pemindaian QR code dan katalog ketersediaan aset.
  - `AsetPeminjamanApiController`: siklus pengajuan dan riwayat peminjaman aset pemohon.
  - `AsetPeminjamanApprovalApiController`: antrean otorisasi, penolakan, serah terima fisik, dan pengembalian aset.
  - `AsetPengalihanApiController`: sirkulasi mutasi aset antar-ruangan/gudang.
- `GudangApiController` (772 baris kode) dipecah menjadi:
  - `GudangTataLetakApiController`: penataan rak penyimpanan dan pemindaian batch fisik.
  - `GudangPenerimaanPoApiController`: penerimaan barang pesanan pembelian vendor di gudang.
  - `GudangPengalihanBarangApiController`: sirkulasi mutasi barang habis pakai (BHP).
- `MaintenanceApiController` (674 baris kode) dipecah menjadi:
  - `MaintenanceTiketApiController`: pelaporan cepat kerusakan sarana dan antrean tiket unit.
  - `MaintenanceSpkApiController`: disposisi SPK teknisi dan pencatatan lembar kerja servis.
- `PersediaanApiController` (580 baris kode) dipecah menjadi:
  - `PersediaanMonitoringApiController`: pemantauan kedaluwarsa FEFO dan buku mutasi stok.
  - `PersediaanOpnameApiController`: sesi inisiasi opname dan pencatatan hitung fisik.
  - `PersediaanPenyesuaianApiController`: otorisasi dan pengesahan penyesuaian selisih stok.
- `AuditApiController` (546 baris kode) dipecah menjadi:
  - `AuditForensikApiController`: audit trail forensik, evaluasi kepatuhan PO, dan selisih opname.
  - `AuditTemuanApiController`: penerbitan temuan CAPA, bukti perbaikan, dan evaluasi closing.
- `DistribusiApiController` (511 baris kode) dipecah menjadi:
  - `DistribusiAmprahApiController`: katalog amprah barang dan pengajuan permohonan ruangan.
  - `DistribusiPengirimanApiController`: antrean picking FEFO gudang dan konfirmasi tanda terima SPPB.

**Invarian Kontrak Route**: Pemetaan ulang di `routes/api.php` hanya mengubah kelas target controller; seluruh 97 endpoint API v1 mempertahankan HTTP method, URI path, parameter, nama route, dan middleware persis seperti semula (diverifikasi dengan snapshot perbandingan `route:list`).

### 2. Dekomposisi Dashboard Berbasis Peran (`app/Services/Dashboard/`)

Metode monolitik 772 baris `DashboardApiController::summary()` diekstrak menjadi 5 builder layanan domain:
- `IpsrsDashboardBuilder`: menangani agenda koordinator dan SPK teknisi lapangan IPSRS.
- `GudangDashboardBuilder`: menangani tugas fisik petugas gudang dan otorisasi kepala gudang/admin logistik.
- `RuanganDashboardBuilder`: menangani antrean persetujuan karu dan status pengajuan staf ruangan.
- `PengawasanDashboardBuilder`: menangani temuan CAPA auditor SPI dan metrik makro direksi manajemen.
- `PengadaanDashboardBuilder`: menangani antrean verifikasi belanja dan fallback default.
- Trait bersama `HasDashboardFormatting`: menyeragamkan kalkulasi waktu lampau ramah pengguna (`diffForHumans`).

Controller `DashboardApiController.php` menyusut dari 815 baris menjadi 59 baris (49 baris kode bermakna), hanya bertugas menyelesaikan peran pengguna dan mendelegasikan ke builder terkait.

### 3. Pemisahan Logika Transaksi Livewire ke Layer Service

Komponen Livewire hanya bertindak sebagai pengontrol antarmuka, validasi form, dan interaksi pengguna. Seluruh mutasi database berat dan pencatatan audit dipindahkan ke layer Service domain:
- `App\Services\Pengadaan\PenerimaanPoService`:
  - `prosesPenerimaan()`: validasi normalisasi rincian terhadap PO terkunci, pemotongan stok sisa, dan pembuatan kartu stok batch.
  - `tolakKiriman()`: penerbitan dokumen penolakan fisik kiriman vendor secara terkunci.
- `App\Services\User\PenggunaService`:
  - `createPengguna()`, `updatePengguna()`, `resetPassword()`, `toggleStatus()`, dan `deletePengguna()`: mutasi akun, pengelolaan token sesi, dan pencatatan jejak audit (ADR 0002 B) terpusat.
- `App\Services\Aset\PeminjamanAsetService::ajukanPeminjaman()`:
  - Transaksi pembuatan dokumen peminjaman dan rincian alokasi aset fisik.

### 4. Ekstraksi Concerns UI Livewire (`app/Livewire/Aset/Concerns/`)

Logika wizard pemilihan fasilitas penyedia fisik dan kueri katalog pada sirkulasi aset diekstrak ke trait concerns yang kohesif:
- `HasPenyediaPeminjaman`: cakupan antrean peminjaman, kueri katalog aset operasional, pemilihan lokasi penyedia aset, kalkulasi kuantitas maksimal, dan kueri penyedia ruangan/gudang.
- `HasPenyediaPengalihan`: sinkronisasi tipe asal/tujuan fasilitas pengalihan, resolusi lokasi, pemilihan penyedia inventaris, dan kalkulasi metrik KPI berjalan.

### 5. Modularisasi Tampilan Blade Monolitik (`resources/views/livewire/<modul>/partials/<halaman>/`)

Enam berkas Blade berukuran 500–740 baris dipecah menjadi file partial modal dan tab terpisah:
- Setiap modal dialog (`modal-create`, `modal-detail`, `modal-approval`, `modal-reject`, `modal-qr`) ditempatkan dalam direktori `partials/<nama-halaman>/`.
- Berkas induk memanggil partial menggunakan arahan `@include('livewire.<modul>.partials.<halaman>.<nama-partial>')`.
- Variabel konteks Livewire dan pengikatan aksi `wire:*` tetap terhubung secara transparan ke komponen induk tanpa overhead komunikasi komponen bersarang.
- Seluruh 6 tampilan induk berhasil menyusut ke rentang 200–390 baris.

### 6. Sentralisasi Otorisasi Pengalihan (`app/Support/Auth/PengalihanAccess.php`)

Aturan otorisasi aksi mutasi aset dan barang (otorisasi kepala fasilitas asal, konfirmasi serah terima fasilitas tujuan, dan pembatalan oleh pengaju dokumen) disatukan ke dalam kelas `PengalihanAccess::boleh()`. API controller dan komponen web Livewire (`AuthorizesPengalihan`) berbagi satu logika pemeriksaan hak akses yang identik sesuai ADR 0001.

### 7. Refactoring Metode Panjang (> 100 Baris)

Metode-metode kritis yang menggabungkan validasi input HTTP, transaksi database, mutasi kartu stok, dan pemformatan respons dipecah menjadi metode yang berfokus tunggal:
- `GudangPenerimaanPoApiController::simpanPenerimaanPo` (195 baris -> 45 baris) memisahkan validasi parameter dari `eksekusiPenerimaanPo()`.
- `MaintenanceSpkApiController::submitLog` (214 baris -> 55 baris) memisahkan pemeriksaan stok dari `eksekusiLogPengerjaan()`.
- `LembarKerjaPengerjaanIndex::saveLembarKerja` (162 baris -> 25 baris) memisahkan pengecekan suku cadang dari `eksekusiSimpanLembarKerja()`.
- `PenerimaanBarangIndex::terima` dan `DistribusiPengirimanApiController::konfirmasiTerimaSppb` (140+ baris -> 42 baris) memisahkan logika rincian aset ke `prosesPenerimaanAset()` dan BHP ke `prosesDetailPenerimaan()`.
- `AsetPengalihanApiController::daftarPengalihan` (143 baris -> 15 baris) memisahkan kueri filter tab ke `filterDaftarPengalihan()` dan pemformatan item ke `formatItemPengalihan()`.

## Pengecualian Terdokumentasi (Aturan 2)

Sesuai aturan batas panjang berkas, berkas berikut dikecualikan secara sah dari batas 500 baris:
1. **Dokumentasi Perancangan Proyek**:
   - `docs/prd.md` (1.162 baris) dan `docs/prompt.md` (592 baris) merupakan berkas spesifikasi dan referensi arsitektur, bukan kode program yang dikompilasi atau dieksekusi.
2. **Database Dummy Seeders**:
   - `database/seeders/PengadaanDummySeeder.php` (665 baris) dan `database/seeders/IpsrsDummySeeder.php` (527 baris) berisi susunan data dummy deklaratif statis untuk inisialisasi lingkungan pengujian/demo. Pemecahan berkas data seeder ke banyak kelas buatan akan mengaburkan kronologi dan ketergantungan relasi antar-data uji tanpa memberikan nilai arsitektural.

## Hasil & Verifikasi

- **Kepatuhan Berkas Kode**: Nol berkas kode program (`app/` dan `resources/views/`) yang melebihi batas 500 baris.
- **Kepatuhan Kompilasi**: Seluruh berkas PHP lulus pemeriksaan sintaks (`php -l`), dan template Blade lulus kompilasi penuh (`php artisan view:cache`).
- **Verifikasi Fungsional & Regresi**: Seluruh rangkaian 251 test otomatis lulus tanpa adanya regresi perilaku baru (dua kegagalan lama pada `PeminjamanAsetTest` dan `ScreeningPengeluaranUnitTest` tetap identik dengan kondisi awal sebelum refaktorisasi).
