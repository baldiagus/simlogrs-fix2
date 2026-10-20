# Spesifikasi Teknis: UI Multi-Portal Dashboard SIMLOG RS Non-Medis v2

Dokumen ini mendefinisikan kontrak arsitektur, kueri metrik, dan struktur UI untuk 10 portal dashboard peran pada SIMLOG RS Non-Medis v2.

## 1. Arsitektur & Prinsip Desain
1. **Dynamic Page-Centric RBAC (ADR 0001)**: Setiap dashboard dilindungi oleh permission `page_<ComponentClass>` (misal `page_AdminLogistikDashboard`).
2. **Konteks & Unit Scoping**: Dashboard unit (`kepala_unit`, `petugas_unit`) membatasi data berdasarkan `auth()->user()->unit_id`, sedangkan gudang membatasi berdasarkan `gudang_id`. Super admin dan auditor melihat data secara global.
3. **UI Layout Standar**:
   - Header Portal: Judul, Subjudul, Chip/Badge Context (Unit/Gudang aktif), Badge Status Sistem.
   - Grid 4 KPI Metrik: Kartu `<flux:card>` dengan indikator angka tebal tabular-nums, label uppercase, dan ikon representatif.
   - 2 Kolom Panel Operasional: Antrean persetujuan/peringatan dini (tabel ringkas `<flux:table bleed>` atau list cards).
   - Baris Pintasan Aksi Cepat: Tautan langsung ke modul kerja utama terkait.
4. **Performa**: Tidak ada komputasi berat di Blade view; seluruh kueri dieksekusi di controller Livewire dengan eager-loading relasi (`with(...)`) dan batasan `take(5)`.

## 2. Rincian 10 Dashboard Portal

| No | Portal / Rute | Komponen Livewire | View Blade | Fokus Fungsional |
|---|---|---|---|---|
| 1 | Super Admin (`/super-admin/dashboard`) | `SuperAdminDashboard` | `super-admin-dashboard.blade.php` | Emulasi Unit/Gudang, Kontrol RBAC, Scheduler |
| 2 | Manajemen Direksi (`/manajemen/dashboard`) | `ManajemenDashboard` | `manajemen-dashboard.blade.php` | Valuasi Stok, Serapan Pagu TA, Kelaikan IPSRS, Closing CAPA |
| 3 | Admin Logistik (`/admin-logistik/dashboard`) | `AdminLogistikDashboard` | `admin-logistik-dashboard.blade.php` | Otorisasi Distribusi Meja 2, Stok Kritis Sentral, Mutasi |
| 4 | Kepala Gudang (`/kepala-gudang/dashboard`) | `KepalaGudangDashboard` | `kepala-gudang-dashboard.blade.php` | FEFO Monitor, Sesi Opname, Approval Varians Stok |
| 5 | Petugas Gudang (`/petugas-gudang/dashboard`) | `GudangDashboard` | `gudang-dashboard.blade.php` | Antrean Picking SPPB, Inbound PO Vendor, Fisik Gudang |
| 6 | Bagian Pengadaan (`/pengadaan/dashboard`) | `PengadaanDashboard` | `pengadaan-dashboard.blade.php` | Konversi PR ke PO, Monitoring PO Vendor, Kontrol Pagu |
| 7 | Kepala Unit / Karu (`/kepala-unit/dashboard`) | `KepalaUnitDashboard` | `kepala-unit-dashboard.blade.php` | Approval Amprah Karu, Izin Pinjam Aset Ruangan, Servis |
| 8 | Petugas Unit (`/petugas-unit/dashboard`) | `UnitDashboard` | `unit-dashboard.blade.php` | Konfirmasi Terima (RCV) SPPB, Amprah Berjalan, Lapor Servis |
| 9 | Teknisi Sarana IPSRS (`/teknisi/dashboard`) | `TeknisiDashboard` | `teknisi-dashboard.blade.php` | Tiket CITO / Darurat, SPK Aktif Teknisi, Lembar Kerja |
| 10 | Auditor Internal SPI (`/auditor/dashboard`) | `AuditorDashboard` | `auditor-dashboard.blade.php` | Forensik Log Audit, Register CAPA Terbuka, Anomali Transaksi |
