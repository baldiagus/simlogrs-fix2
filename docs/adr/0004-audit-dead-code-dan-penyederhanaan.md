# ADR 0004: Audit Dead Code dan Penyederhanaan

Status: Diterima

## Konteks

Setelah Fase 1 (matriks akses), Fase 2 (sentralisasi enum status), Fase 3 (audit atomik), dan Fase 4 (kontrak route & konfigurasi operasional), codebase menyisakan beberapa kode usang: konstanta legacy, metode Livewire yang tidak pernah dipanggil oleh view atau lifecycle hook, import `use` yang tidak terpakai, dan skrip sementara dari sesi investigasi sebelumnya.

Audit dilakukan secara sistematis dengan pemindaian AST, regex, dan verifikasi manual terhadap mekanisme dinamis Laravel, Livewire 3, dan Flux UI.

## Hasil Audit dan Keputusan

### A. Kode yang Dihapus (Aman / Dead Code Terverifikasi)

1. **Konstanta Legacy Model**:
   - `PermintaanPengadaan::STATUS_*` (`STATUS_DIAJUKAN`, `STATUS_DITERUSKAN_KE_PENGADAAN`, `STATUS_DITOLAK`, `STATUS_DIPROSES`, `STATUS_SELESAI`) dihapus. Pemanggil di `ApprovalController.php` dialihkan langsung ke `StatusPermintaanPengadaan::*->value`.

2. **Metode Livewire yang Tidak Pernah Dipanggil**:
   - `PermintaanPengadaanIndex::confirmTeruskan()` dan `teruskan()` dihapus (sisa alur lama sebelum `approve()` langsung meneruskan ke pengadaan).
   - `RoleManager::toggleViewAny()` dihapus (checkbox perizinan sudah terikat reaktif via `wire:model.live="state.view_any.*"` dan ditangani oleh lifecycle `updated()`).
   - Metode penutup modal manual yang tidak pernah dipanggil view karena Flux UI menangani penutupan modal secara native via backdrop dan tombol silang internal `<flux:modal>`:
     - `ApprovalPeminjamanIndex::closeDetail()`
     - `PengembalianPeminjamanIndex::closeDetailPengembalianModal()`
     - `PickingDistribusiIndex::closeDetailModal()`
     - `PenerimaanPoIndex::cancelReject()`

3. **Sentralisasi Literal Status Pengalihan**:
   - `GudangApiController.php` beralih dari literal string `'DIAJUKAN'`, `'DISETUJUI'`, dll. ke `StatusPengalihan::*->value`.
   - `DashboardApiController.php` beralih dari literal string status ke enum domain terkait (`StatusSpk`, `StatusPermintaanDistribusi`, `StatusPermintaanPengadaan`, `StatusPeminjaman`).

4. **Import `use` yang Tidak Digunakan**:
   - `AuditApiController.php`: `Pemasok`, `StokOpnameDetail`, `DB`
   - `DashboardApiController.php`: `StokBatch`
   - `ManajemenDashboard.php`: `AnggaranPengadaan`
   - `LembarKerjaPengerjaanIndex.php`: `PermintaanMaintenance`
   - `PengalihanBarangService.php`: `Barang`
   - `FileOptimizer.php`: `UploadedFile`
   - `CodeGenerator.php`: `DB`

5. **Berkas Sampah / Scratch Session**:
   - `done.sh`, `run.sh`, `auth_checks.txt` dihapus dari root repositori.

### B. Kode yang Dipertahankan (Bukan Dead Code — Dipanggil Dinamis)

Pemeriksaan manual memastikan kode berikut **tidak dihapus** karena digunakan melalui mekanisme dinamis framework:
- `submitPengembalian()` pada `PengembalianPeminjamanIndex`: dipanggil dinamis via `HasConfirmationModal` (`action: 'submitPengembalian'`).
- `rejectKaru()` pada `ApprovalPermintaanIndex`: dipanggil dinamis via `HasConfirmationModal` (`action: 'rejectKaru'`).
- `clearDomain()` pada `RoleManager`: dipanggil dinamis via `HasConfirmationModal` (`action: 'clearDomain'`).
- `mountHasTableFilters()` pada `HasTableFilters`: hook lifecycle otomatis Livewire 3 untuk trait (`mount[TraitName]`).
- `resources/views/flux/tab.blade.php` dan `tabs.blade.php`: override kustom komponen Blade Flux UI yang dirender secara dinamis oleh tag `<flux:tab>` dan `<flux:tabs>`.

## Verifikasi

Seluruh rangkaian pengujian di dalam container dijalankan setelah pembersihan:
- 295 tes lulus (2.892 assertion).
- 0 regresi terhadap fungsionalitas yang ada.
- Linting sintaks PHP pada semua berkas lulus 100%.
