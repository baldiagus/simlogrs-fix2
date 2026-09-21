# SIMLOG RS — Sistem Informasi Manajemen Logistik Rumah Sakit (Non-Medis) v2

Platform ERP Logistik Rumah Sakit modern yang mengelola siklus pengadaan, persediaan FEFO, distribusi ruangan (amprah), sirkulasi peminjaman aset, pemeliharaan IPSRS, dan audit kepatuhan SPI.

## Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 · PHP 8.3 |
| Frontend | Livewire 3 · Flux UI · Alpine.js · Tailwind CSS 4 |
| RBAC | Spatie Permission 6 · Filament Shield 4 (Zero-Hardcode) |
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

### ⬜ Fase 3 — Siklus Pengadaan (Procurement)
### ⬜ Fase 4 — Distribusi Ruangan & FEFO (Amprah)
### ⬜ Fase 5 — Peminjaman & Sirkulasi Aset
### ⬜ Fase 6 — Pemeliharaan Sarana & IPSRS
### ⬜ Fase 7 — Manajemen Persediaan & Stock Opname
### ⬜ Fase 8 — Audit Internal & CAPA
### ⬜ Fase 9 — Scheduler, Dashboard & QA

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

- **Zero-Hardcode RBAC**: Tidak ada `hasRoleCode()` di manapun. Otorisasi 100% via `$user->can('permission_name')`.
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
