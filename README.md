# SIMLOG RS — Sistem Informasi Manajemen Logistik Rumah Sakit (Non-Medis) v2

Platform ERP Logistik Rumah Sakit modern yang mengelola siklus pengadaan, persediaan FEFO, distribusi ruangan (amprah), sirkulasi peminjaman aset, pemeliharaan IPSRS, dan audit kepatuhan SPI.

## Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 · PHP 8.3 |
| Frontend | Livewire 3 · Flux UI · Alpine.js · Tailwind CSS 4 |
| RBAC | Spatie Permission 6 · Filament Shield 4 (lihat ADR 0001) |
| Database | PostgreSQL 16 (pg_trgm, CHECK constraints) |
| Cache & Queue | Redis 7 |
| Runtime | Docker (Nginx Alpine + PHP-FPM + PostgreSQL + Redis) |

## Arsitektur Portal

Satu gateway login terpusat (`/`) dengan 10 portal peran independen:

| Portal | Path | Warna |
|---|---|---|
| Super Administrator | `/super-admin` | Blue |
| Admin Logistik | `/admin-logistik` | Blue |
| Kepala Gudang | `/kepala-gudang` | Amber |
| Petugas Gudang | `/petugas-gudang` | Emerald |
| Pengadaan | `/pengadaan` | Indigo |
| Kepala Unit | `/kepala-unit` | Cyan |
| Petugas Unit | `/petugas-unit` | Teal |
| Teknisi IPSRS | `/teknisi` | Indigo |
| Manajemen Direksi | `/manajemen` | Slate |
| Auditor Internal (SPI) | `/auditor` | Rose |

## Quick Start

```bash
# 1. Clone & setup environment
cp .env.example .env

# 2. Build & start containers
docker compose up -d --build

# 3. Install dependencies & generate key
docker compose exec app composer install
docker compose exec app php artisan key:generate

# 4. Run migrations & seed
docker compose exec app php artisan migrate:fresh --seed

# 5. Build frontend assets
docker compose exec app npm install && docker compose exec app npm run build

# 6. Akses aplikasi
# http://localhost:8000
```

## Akun Seeder (Default)

Semua password: `password`

| Email | Role |
|---|---|
| `superadmin@simlogrs.local` | Super Administrator |
| `adminlogistik@simlogrs.local` | Admin Logistik |
| `kepalagudang@simlogrs.local` | Kepala Gudang |
| `petugasgudang@simlogrs.local` | Petugas Gudang |
| `pengadaan@simlogrs.local` | Pengadaan |
| `kepalaunit@simlogrs.local` | Kepala Unit |
| `petugasunit@simlogrs.local` | Petugas Unit |
| `teknisi@simlogrs.local` | Teknisi IPSRS |
| `manajemen@simlogrs.local` | Manajemen Direksi |
| `auditor@simlogrs.local` | Auditor Internal |

## Progress Implementasi

### ✅ Fase 0 — Inisialisasi & Fondasi
- Scaffolding project Laravel 13 + Docker multi-container
- Konfigurasi PostgreSQL 16, Redis 7, Nginx Alpine

### ✅ Fase 1 — RBAC, Autentikasi & Multi-Portal
- Database migrations & model inti (User, Role, Permission)
- `PagePermissionRegistry` & `RoleManager` (matriks 4 kolom: Halaman, Lihat, Tambah/Ubah, Hapus)
- 10 portal independen + middleware + layout Flux UI sidebar
- Gateway login terpusat + dashboard per-portal
- RESTful API v1 (autentikasi Sanctum)
- Seeder: 10 user, 12 role, 410 permissions, 0 data transaksi

### ✅ Fase 2 — Master Data & Katalog Logistik
- Migrasi tabel master: Unit, Gudang, Lokasi Gudang, Kategori, Satuan, Merek, Pemasok, Barang, Aset
- pg_trgm GIN index untuk pencarian fuzzy
- CHECK constraint XOR lokasi aset (`unit_id` XOR `gudang_id`)
- CRUD Livewire + modal untuk seluruh modul master
- Katalog Barang dengan auto-code generator (`BRG-YYYY-XXX`)
- Register Aset dengan QR Code sticker dan validasi XOR
- Format angka ribuan otomatis (titik separator) untuk Rp & stok
- Parameter stok opsional (Stok Min/Max, ROP) — kosong = tanpa batas

### Status Modul Fase 3–9

Status di bawah diturunkan dari `routes/web.php`, `app/Livewire/**`, `app/Services/**`, `routes/api.php` + `app/Http/Controllers/Api/V1/*`, dan `tests/Feature/**`. Sebuah modul ditandai **Selesai** hanya jika memiliki halaman Livewire, jalur service/API, **dan** minimal satu tes fitur yang lulus. Selain itu ditandai **Berjalan** dengan keterangan apa yang belum ada.

| Fase | Modul | Halaman Livewire | Service / API v1 | Tes fitur | Status |
|---|---|---|---|---|---|
| 3 | Permintaan Pengadaan (PR), Pesanan Pembelian (PO), Master Rekanan | `Pengadaan/PermintaanPengadaanIndex`, `PesananPembelianIndex`, `MasterRekananIndex` | `PengadaanApiController`, `ApprovalController` | `tests/Feature/Pengadaan/*` | ✅ Selesai |
| 3 | Penerimaan PO | `Pengadaan/PenerimaanPoIndex` | `GudangApiController::simpanPenerimaanPo` | `Pengadaan/PenerimaanPoTest`, `Pengadaan/StatusGuardTest`, `Persediaan/AlurStokKritisTest` | ✅ Selesai — 2 cacat terbuka (lihat [Pengujian](#pengujian)) |
| 3 | Realisasi Anggaran | `Pengadaan/RealisasiAnggaranIndex` | `AnggaranRealtimeService`, `AnggaranApiController` | `AuditAtomicityTransaksiTest` (sinkronisasi), `PengadaanWebAuthorizationTest` | ✅ Selesai — `ScreeningPengeluaranUnitTest` masih gagal |
| 4 | Amprah, approval, picking FEFO, penerimaan SPPB, stok ruangan | `Distribusi/*` (6 halaman) | `DistribusiService`, `DistribusiApiController` | `tests/Feature/Distribusi/*`, `Persediaan/AlurStokKritisTest` | ✅ Selesai |
| 5 | Peminjaman, approval, pengembalian aset | `Aset/PeminjamanAsetIndex`, `ApprovalPeminjamanIndex`, `PengembalianPeminjamanIndex` | `PeminjamanAsetService`, `AsetApiController` | `tests/Feature/Aset/Peminjaman*`, `ApprovalPeminjamanTest`, `PengembalianPeminjamanViewTest` | ✅ Selesai — 1 tes pencarian modal masih gagal |
| 5 | Pengalihan aset | `Aset/PengalihanAsetIndex` | `PengalihanAsetService`, `AsetApiController` | `tests/Feature/Aset/PengalihanAset*` | ✅ Selesai |
| 6 | Lapor servis, SPK, lembar kerja, rekam medis aset, jadwal preventif | `Ipsrs/*` (5 halaman) | `MaintenanceApiController`, `IpsrsApiController` | `tests/Feature/Ipsrs/*` | ✅ Selesai |
| 7 | Pengalihan barang antar-gudang | `Persediaan/PengalihanBarangIndex` | `PengalihanBarangService`, `GudangApiController` | `Persediaan/PengalihanBarang*`, `Distribusi/PengalihanPersediaanIntegrasiTest` | ✅ Selesai |
| 7 | Stock opname & otorisasi penyesuaian | `Persediaan/StockOpnameIndex`, `OtorisasiPenyesuaianIndex` | `PersediaanApiController`, `GudangApiController::opnameCount` | `Persediaan/StatusGuardTest`, `PersediaanWebAuthorizationTest` | ✅ Selesai |
| 7 | Monitoring stok FEFO | `Persediaan/MonitoringStokFefoIndex` | `PersediaanApiController::monitoringFefo` | Hanya `KonfigOperasionalTest` (TTL cache dropdown) | 🔄 Berjalan (belum ada tes isi monitoring) |
| 7 | Buku mutasi stok | `Persediaan/BukuMutasiIndex` | `PersediaanApiController::bukuMutasi` | — | 🔄 Berjalan (belum ada tes) |
| 8 | Audit trail, kepatuhan pengadaan, selisih opname, temuan & CAPA | `Audit/*` (4 halaman) | `AuditApiController` | Hanya `AuditHubNavigationTest` (render tab navigasi) | 🔄 Berjalan (belum ada tes fungsional) |
| 9 | Dashboard per portal | `Dashboard/*` (10 halaman) | `DashboardApiController` | Akses portal di `RouteRbacTest`/`RouteContractTest`; tidak ada tes isi dashboard | 🔄 Berjalan (belum ada tes isi dashboard) |
| 9 | Scheduler & notifikasi | `System/SchedulerNotifikasiIndex` | Perintah `simlog:sweep-expired-batches`, `simlog:alert-overdue-loans`, `simlog:generate-preventive-maintenance` (`routes/console.php`); tanpa API | — | 🔄 Berjalan (belum ada tes) |

Catatan Fase 2: dari halaman master, hanya Katalog Barang (`Master/BarangDetailStockTest`) dan Register Aset (`Ipsrs/IpsrsWebAuthorizationTest`) yang memiliki tes fitur; master Unit, Gudang, Lokasi Gudang, Kategori, Satuan, Merek, dan Pemasok belum memiliki tes.

Lintas modul: kebijakan otorisasi web ([ADR 0001](docs/adr/0001-kebijakan-otorisasi-web.md)), enum transisi status dan atomisitas audit ([ADR 0002](docs/adr/0002-jaminan-pencatatan-audit-dan-transisi-status.md)), serta konfigurasi operasional dan kontrak route ([ADR 0003](docs/adr/0003-konfigurasi-operasional-dan-kontrak-route.md)) sudah diterapkan dan diuji (`RouteRbacTest`, `RouteContractTest`, `AuditAtomicityTransaksiTest`, `User/AuditAtomicityTest`, `KonfigOperasionalTest`).

## Pengujian

Tes hanya dijalankan di dalam container (memakai database PostgreSQL hasil seeder):

```bash
docker compose exec -T app php artisan test --compact
# atau sebagian:
docker compose exec -T app php artisan test --compact tests/Feature/Persediaan
```

Hasil terakhir (12 Oktober 2026): 295 lulus, 2 gagal, 2 incomplete.

Tes gagal yang sudah diketahui (belum diperbaiki):

| Tes | Keterangan |
|---|---|
| `Tests\Feature\Aset\PeminjamanAsetTest` › *searching provider in loan modal* | Pencarian penyedia di modal peminjaman |
| `Tests\Feature\Pengadaan\ScreeningPengeluaranUnitTest` | Skrining pengeluaran unit pada Realisasi Anggaran |

Tes `incomplete` di `tests/Feature/Persediaan/AlurStokKritisTest.php` menandai cacat nyata pada penerimaan PO:

- Web: `PenerimaanPoIndex::savePenerimaan` membatasi jumlah diterima dengan `sisa` dari state modal, sehingga modal yang dibuka sebelum penerimaan lain dapat menerima melebihi jumlah dipesan.
- API: `POST /api/v1/gudang/penerimaan-po` tidak memvalidasi jumlah diterima terhadap sisa PO.

Keputusan arsitektur yang menjadi acuan tes:

- [ADR 0001 — Kebijakan otorisasi web](docs/adr/0001-kebijakan-otorisasi-web.md)
- [ADR 0002 — Jaminan pencatatan audit dan transisi status](docs/adr/0002-jaminan-pencatatan-audit-dan-transisi-status.md)
- [ADR 0003 — Konfigurasi operasional dan kontrak route](docs/adr/0003-konfigurasi-operasional-dan-kontrak-route.md)

## Komponen UI Kustom

| Komponen | Fungsi |
|---|---|
| `<x-number-input>` | Input angka dengan format titik ribuan, prefix Rp opsional, placeholder "Tanpa Batas" |
| `<x-rupiah>` | Display mata uang Rupiah terformat |
| `<x-status-badge>` | Badge status AKTIF/NONAKTIF dengan warna |
| `<x-context-banner>` | Banner konteks emulasi gudang/unit |
| `<x-table-pagination>` | Pagination 3-bagian dengan per-page selector |
| `<x-empty-state>` | State kosong untuk tabel tanpa data |
| `<x-urgency-badge>` | Badge urgensi REGULER/CITO |

## Konvensi Kode

- **Otorisasi (ADR 0001)**: Lihat halaman/data via permission `view_any_*`/`view_*`; aksi (buat, ubah, hapus, eksekusi) wajib permission granular aksi tersebut, dan role saja tidak cukup. Tahap workflow terikat jabatan (approval kepala unit/gudang, auditor menerbitkan CAPA, penerbitan SPK oleh `kepala_teknisi`) memakai `hasRole()` **dan** permission aksi. Data non-global dibatasi ke `unit_id`/`gudang_id` miliknya; bypass scope global hanya untuk role `super_admin` dan `admin_logistik`. Permission `page_*Dashboard` hanya membuka portal, bukan bypass aksi atau scope. Detail: [ADR 0001](docs/adr/0001-kebijakan-otorisasi-web.md).
- **Konfigurasi Operasional**: Nilai operasional dibaca via `config('simlog.*')` (`config/simlog.php`), bukan `env()` langsung. Lihat [ADR 0003](docs/adr/0003-konfigurasi-operasional-dan-kontrak-route.md).
- **Auto Code Generator**: Kode entitas di-generate otomatis (`BRG-YYYY-XXX`, `AST-YYYY-XXX`, `MRK-YYYY-XXX`, `VDR-YYYY-XXX`). Input kode di-disable di form.
- **Toast Notifikasi**: `Flux::toast()` native dengan variant `success`, `warning`, `danger`, `info` — posisi kanan atas.
- **Docker-First**: Semua perintah artisan/composer/npm wajib via `docker compose exec app <perintah>`.

## Struktur Direktori Utama

```
app/
├── Livewire/
│   ├── Auth/              # Login gateway
│   ├── Concerns/          # HasToast, HasPortalLayout, HasTableFilters
│   ├── Dashboard/         # Dashboard per-portal (10 file)
│   ├── Master/            # CRUD master data (9 modul)
│   └── User/              # RoleManager (RBAC matrix)
├── Models/                # Eloquent models
├── Providers/             # SimlogPanelsProvider (multi-portal)
└── Support/Generator/     # CodeGenerator utility
resources/views/
├── components/            # Blade components (x-number-input, x-rupiah, dll)
├── layouts/               # portal.blade.php (Flux sidebar layout)
└── livewire/              # Views per-modul
```

## License

Proprietary — Hak cipta dilindungi.
