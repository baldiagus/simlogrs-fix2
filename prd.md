# Product Requirement Document &amp; Blueprint Rekayasa Ulang (PRD-FIX)
## Sistem Informasi Manajemen Logistik Rumah Sakit (SIMLOG RS Non-Medis) v2
### Arsitektur Modern: Laravel 13, Livewire 3 & Flux UI (Free Edition) (Zero-Hardcode Standard)

---

## 1. Informasi Project &amp; Arsitektur Utama

### 1.1 Visi dan Tujuan Sistem
SIMLOG RS Non-Medis v2 adalah platform Enterprise Resource Planning (ERP) Logistik Rumah Sakit modern yang mengelola siklus hidup pengadaan barang, manajemen persediaan fisik (FEFO), distribusi antar-ruangan (amprah), sirkulasi peminjaman aset sarana, pemeliharaan preventif/korektif IPSRS, dan tata kelola audit kepatuhan. Sistem ini dirancang dengan prinsip:
1. \*\*Integritas Persediaan Mutlak\*\*: Menjamin tidak ada saldo stok negatif melalui \*database constraint check\* pada level PostgreSQL (\`jumlah &gt;= 0 AND jumlah\_dipesan &gt;= 0\`) dan penjaminan alokasi pengeluaran barang berbasis FEFO (\*First Expired, First Out\*).
2. \*\*Dynamic Page-Centric RBAC (100% Bebas Hardcode Role)\*\*: Seluruh visibilitas menu navigasi sidebar, hak akses halaman, serta visibilitas dan eksekusi tombol aksi/CRUD dikendalikan 100% oleh permissions database Spatie (\`can('page\_...')\`, \`can('view\_any\_...')\`, \`can('create\_...')\`). Tidak diperbolehkan adanya pemeriksaan role statis (\`hasRoleCode(...)\`) di dalam file Resource, Page, Action, maupun Policy.
3. \*\*Multi-Portal Terisolasi Berbasis Peran\*\*: Menyediakan 10 portal kerja mandiri dengan tema warna visual khusus, dashboard metrik mandiri, dan menu switcher bagi Super Admin.
4. \*\*Audit Trail Komprehensif\*\*: Forensik digital otomatis yang mencatat snapshot data sebelum vs sesudah (JSONB), IP address, perangkat, serta wewenang aktif pengguna saat melakukan mutasi data.

### 1.2 Tech Stack Utama
- **Backend Framework**: **Laravel 13** (PHP 8.3+ / 8.4)
- **Frontend & UI Engine**: **Livewire 3** & **Flux UI (Free Edition)** (`livewire/flux`), **Tailwind CSS**
- **RBAC Engine**: **Spatie Laravel Permission 6** dengan Dynamic Role Matrix Engine mandiri (Zero-Hardcode)
- \*\*Database Server\*\*: PostgreSQL 16 (Didukung JSONB diffs, Foreign Key Cascades, dan Check Constraints)
- \*\*In-Memory Cache &amp; Session\*\*: Redis 7 (Session driver, application cache, background job queue)
- \*\*Web Server\*\*: Nginx (Alpine Linux reverse proxy)
- \*\*Containerization\*\*: Docker Compose (Multi-container: Web, App, PostgreSQL, Redis)

### 1.3 Arsitektur Container &amp; Docker Environment (Docker-First Execution)
Aplikasi ini **wajib dijalankan dan dioperasikan menggunakan Docker Multi-Container**:
1. **`simrslogv2-app` (Service `app`)**: Container PHP 8.3-FPM + Node.js 22 LTS, ekstensi `pdo_pgsql`, `pgsql`, `redis`, `bcmath`, `gd`, `zip`, `intl`, `opcache`, `pcntl`, dan Composer 2. Dilengkapi named volume untuk `vendor` dan `node_modules` guna mencegah I/O bottleneck pada host Windows/WSL2.
2. **`simrslogv2-web` (Service `web`)**: Nginx Alpine reverse proxy pada port 8000 yang meneruskan trafik PHP ke `app:9000` dan asset publik.
3. **`simrslogv2-postgres` (Service `postgres`)**: Database PostgreSQL 16 Alpine pada port 5432 dengan persistent volume `postgres_data`.
4. **`simrslogv2-redis` (Service `redis`)**: In-memory storage Redis 7 Alpine pada port 6379 untuk cache, session, dan queue.
\*\*Kaidah Eksekusi Perintah (Docker Invariant)\*\*:
Seluruh perintah CLI (artisan, composer, migrasi, seeder, test) \*\*wajib dieksekusi di dalam container app\*\*:
- \`docker compose exec app php artisan migrate:fresh --seed\`
- \`docker compose exec app php artisan optimize:clear\`
- \`docker compose exec app composer install\`

### 1.4 Arsitektur Sistem & Topologi Multi-Container

#### A. Topologi Tingkat Tinggi (High-Level Topology)
```
                       [ BROWSER KLIEN RS ]
             (Desktop Unit, Tablet Gudang, Mobile Karu)
                                 │
                        HTTP/HTTPS Port 8000
                                 ▼
         ┌─────────────────────────────────────────────────┐
         │       CONTAINER 1: WEB (Nginx Alpine)           │
         │   Reverse Proxy, Static Assets, SSL Termination │
         └───────────────────────┬─────────────────────────┘
                                 │ FastCGI (Port 9000)
                                 ▼
         ┌─────────────────────────────────────────────────┐
         │       CONTAINER 2: APP (PHP 8.3-FPM)            │
         │   Laravel 13 + Livewire 3 + Flux UI (Free)      │
         │                                                 │
         │   [Presentation Layer: Flux UI Blade Views]     │
         │   [Routing & Guard: PortalAccessMiddleware]     │
         │   [RBAC Engine: Spatie 100% Zero-Hardcode]      │
         │   [Domain Services: FEFO, Aset, Generator]      │
         │   [Audit Observer: HasAuditLog / ActivityLog]   │
         └─────────────┬─────────────────────┬─────────────┘
                       │                     │
          PostgreSQL TCP 5432       Redis TCP 6379
                       ▼                     ▼
┌───────────────────────────────┐   ┌───────────────────────────────┐
│ CONTAINER 3: POSTGRESQL 16    │   │ CONTAINER 4: REDIS 7          │
│ • Check Constraints           │   │ • Session Storage (480 min)   │
│ • GIN Trigram Index (pg_trgm) │   │ • Application Cache           │
│ • JSONB Audit Snapshots       │   │ • Background Queue Worker     │
│ • Persistent Volume Data      │   │ • Rate Limiter Store          │
└───────────────────────────────┘   └───────────────────────────────┘
```

#### B. Lapisan Arsitektur Aplikasi (Layered Architecture)
1. **Presentation Layer (Livewire 3 & Flux UI Free)**: 10 layout portal, 58 komponen Livewire reaktif, komponen visual reusable (`<x-status-badge>`, `<x-rupiah>`, `<x-urgency-badge>`, dll.).
2. **Routing & Access Control Layer**: 10 Route Groups terisolasi dilindungi `PortalAccessMiddleware`, 410 permissions Spatie di database, `PagePermissionRegistry`.
3. **Domain Service Layer**: `DistribusiService` (FEFO Picking + Pessimistic Locking), `PeminjamanAsetService` (mutasi lokasi & auto-trigger tiket IPSRS), `CodeGenerator` (penomoran resmi), `NotifikasiService`.
4. **Data & Persistence Layer**: 44 Model Eloquent, PostgreSQL check constraints (`chk_stok_batch_non_negative`, `chk_aset_lokasi_xor`), composite index FEFO, GIN Trigram index `pg_trgm`, `HasAuditLog` observer (JSONB diffs).
5. **Background Jobs & Infrastructure**: 3 cron scheduler harian, Redis session & queue, Dompdf PDF generator, SimpleQRCode, FastExcel streaming.

### 1.5 Struktur Direktori Proyek Resmi
```text
simlog-fix2/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/                         # API Controllers untuk Klien Mobile
│   │   │           ├── AuthController.php      # Login Bearer token, logout, profile
│   │   │           ├── ApprovalController.php  # Fast-approval PR & Amprah mobile
│   │   │           ├── GudangApiController.php # Scanner barcode/QR batch & opname
│   │   │           ├── AsetApiController.php   # Scanner QR aset & sirkulasi pinjam
│   │   │           └── MaintenanceApiController.php # Lapor cepat foto HP & lembar kerja
│   │   └── Middleware/
│   │       └── PortalAccessMiddleware.php      # Guard isolasi 10 portal & pencegah URL tampering
│   ├── Livewire/                               # 58 Modul Operasional Livewire 3
│   │   ├── Auth/LoginGateway.php               # Form login terpusat, rate limiter, & auto-redirect
│   │   ├── Dashboard/                          # 10 Dashboard Portal Spesifik
│   │   ├── Master/                             # Master Data & Katalog Logistik (9 Komponen)
│   │   ├── Pengadaan/                          # Siklus Pengadaan (PR -> PO -> Inbound)
│   │   ├── Distribusi/                         # Siklus Distribusi (Amprah & Picking FEFO)
│   │   ├── Aset/                               # Sirkulasi & Peminjaman Aset
│   │   ├── Maintenance/                        # Pemeliharaan Fasilitas & IPSRS
│   │   ├── Persediaan/                         # Monitoring FEFO & Stock Opname
│   │   ├── Audit/                              # Audit Trail & Kepatuhan SPI
│   │   ├── User/                               # Manajemen Akun & Matriks RBAC
│   │   ├── System/                             # Pengaturan & Scheduler
│   │   └── Concerns/                           # Reusable Livewire Traits (HasTableFilters, HasToast, HasPortalLayout)
│   ├── Models/                                 # 44 Model Eloquent
│   ├── Services/                               # Lapisan Logika Bisnis (Distribusi, Aset, Notifikasi)
│   ├── Support/                                # Generator, Auth Context, RBAC Registry
│   └── Traits/                                 # HasAuditLog
├── database/
│   ├── migrations/                             # 44 Migrasi Tabel
│   └── seeders/                                # Unit, Gudang, Peran, Pengguna, Shield Permissions
├── docker/
│   ├── nginx/default.conf                      # Reverse proxy port 80 -> app:9000
│   └── php/Dockerfile                          # PHP 8.3-FPM + Node.js 22 + Ekstensi
├── resources/
│   ├── css/app.css                             # Tailwind CSS v4 + Flux UI CSS
│   └── views/
│       ├── components/                         # 5 Reusable Blade Components
│       ├── layouts/                            # portal.blade.php & guest.blade.php
│       ├── livewire/                           # Blade views untuk 58 komponen
│       └── pdf/                                # Template PDF PO, SPPB, SPK
├── routes/
│   ├── web.php                                 # Root gateway & 10 Route Groups portal
│   ├── api.php                                 # RESTful API v1 (Sanctum) untuk Mobile Klien
│   └── console.php                             # 3 Task scheduler harian (cron)
├── docker-compose.yml                          # 4 Services: app, web, postgres, redis
├── composer.json
├── package.json
└── vite.config.js
```
### 1.6 Arsitektur RESTful API Mobile (Sanctum v1)

#### A. Topologi Integrasi Klien Mobile
```
[ APLIKASI MOBILE RS ]
(Android / iOS: Flutter, React Native, Kotlin)
        │
   Bearer Token (Laravel Sanctum)
        ▼
[ routes/api.php (Prefix: /api/v1/*) ]
        │
   API Controllers (JSON Response Standard)
        │
   [ Shared Domain Services ] ◄─── (Dipanggil bersama oleh Web & Mobile)
   • DistribusiService::eksekusiPickingFefo()
   • PeminjamanAsetService::serahTerimaFisik()
   • CodeGenerator::generate()
        ▼
[ PostgreSQL 16 ]
```

#### B. Standar Format Response JSON
Seluruh endpoint API mengembalikan payload JSON seragam:
```json
{
    "sukses": true,
    "pesan": "Keterangan hasil operasi",
    "data": {},
    "meta": {
        "waktu_eksekusi_ms": 12.4,
        "versi_api": "v1.0"
    }
}
```

#### C. Katalog Endpoint Resmi API v1
1. **Autentikasi & Profil Pengguna (`/api/v1/auth/*`)**:
   - `POST /api/v1/auth/login`: Autentikasi email & password -> menghasilkan `token` (Sanctum PlainTextToken).
   - `POST /api/v1/auth/logout`: Revoke token aktif (`$user->currentAccessToken()->delete()`).
   - `GET /api/v1/auth/me`: Profil pengguna, unit dinas, gudang dinas, roles, dan permissions aktif.
2. **Persetujuan Cepat Pejabat / Karu (`/api/v1/approval/*`)**:
   - `GET /api/v1/approval/pending`: Mengambil seluruh antrean PR dan amprah ruangan yang membutuhkan persetujuan.
   - `POST /api/v1/approval/pr/{id}`: Approve atau reject permohonan pengadaan (PR).
   - `POST /api/v1/approval/amprah/{id}`: Approve atau reject permohonan distribusi (amprah ruangan).
3. **Operasional Gudang & Scanner Barcode (`/api/v1/gudang/*`)**:
   - `POST /api/v1/gudang/scan-batch`: Verifikasi barcode/QR code nomor batch saat inbound dan picking FEFO.
   - `POST /api/v1/gudang/opname-count`: Input kuantitas hitung fisik *blind count* langsung di depan rak.
4. **Sirkulasi Aset & Scanner QR (`/api/v1/aset/*`)**:
   - `POST /api/v1/aset/scan-qr`: Pindai stiker QR code fisik aset -> tampilkan riwayat servis, lokasi, dan status pinjam.
   - `POST /api/v1/aset/serah-terima`: Konfirmasi serah terima fisik peminjaman aset via scan QR.
   - `POST /api/v1/aset/kembalikan`: Konfirmasi pengembalian aset dan cek kondisi fisik.
5. **Pemeliharaan Fasilitas & Teknisi (`/api/v1/maintenance/*`)**:
   - `POST /api/v1/maintenance/lapor-cepat`: Unggah foto fasilitas rusak langsung dari kamera HP dan terbitkan tiket.
   - `POST /api/v1/maintenance/spk/{id}/log`: Teknisi memperbarui lembar kerja pengerjaan dan pemakaian suku cadang.

---

## 2. Design System & UI Guidelines

### 2.1 Filosofi & Arah Estetika (Clinical & Industrial Utility)
Sistem ini dirancang untuk lingkungan kerja rumah sakit berkecepatan tinggi: perawat IGD, petugas gudang basement, staf pengadaan, teknisi IPSRS, dan auditor SPI.
1. **Data Density First**: Mengutamakan kepadatan data, garis batas tipis (*hairline rules*), dan pemanfaatan ruang vertikal secara optimal tanpa padding berlebihan.
2. **Cognitive Role Anchoring**: Setiap portal memiliki tema warna fungsional yang langsung dikenali dalam 1 detik untuk meminimalkan kesalahan wewenang.
3. **Zero Visual Noise**: Tanpa gradien dekoratif atau bayangan berlebihan. Warna hanya digunakan untuk menyampaikan makna operasional dan status.

### 2.2 Token Warna & Palet Fungsional
1. **Base Neutral (Light & Dark Mode)**:
   - Surface Background: `zinc-50` (Light) / `zinc-950` (Dark).
   - Card & Table Container: `white` (Light) / `zinc-900` (Dark) dengan border `zinc-200` / `zinc-800`.
   - Text Hierarchy: Primary `zinc-900` / `zinc-100`, Muted Meta `zinc-500` / `zinc-400`.
2. **10 Portal Themed Accents**:

| No | Portal & Role | Subpath URL | Primary Accent | Kode Tailwind / Hex | Karakteristik Peran |
|---|---|---|---|---|---|
| 1 | **Super Administrator** | `/super-admin` | Blue | `blue-600` (`#2563eb`) | Otoritas penuh & kendali teknis |
| 2 | **Admin Logistik** | `/admin-logistik` | Sky | `sky-600` (`#0284c7`) | Aliran distribusi & stok utama |
| 3 | **Kepala Gudang** | `/kepala-gudang` | Amber | `amber-600` (`#d97706`) | Pengawasan batch & verifikasi |
| 4 | **Petugas Gudang** | `/petugas-gudang` | Emerald | `emerald-600` (`#059669`) | Picking, inbound, & packing |
| 5 | **Pengadaan** | `/pengadaan` | Indigo | `indigo-600` (`#4f46e5`) | Kontrak, PO, & anggaran |
| 6 | **Kepala Unit** | `/kepala-unit` | Cyan | `cyan-600` (`#0891b2`) | Validasi amprah ruangan |
| 7 | **Petugas Unit** | `/petugas-unit` | Teal | `teal-600` (`#0d9488`) | Kebutuhan klinis & rawat inap |
| 8 | **Teknisi IPSRS** | `/teknisi` | Violet | `violet-600` (`#7c3aed`) | Rekayasa mesin & servis |
| 9 | **Manajemen Direksi** | `/manajemen` | Slate | `slate-700` (`#334155`) | Ringkasan biaya & audit makro |
| 10 | **Auditor Internal (SPI)** | `/auditor` | Rose | `rose-600` (`#e11d48`) | Forensik digital & mitigasi risiko |

3. **Status & Urgensi Semantik (Universal)**:
   - `success` (`emerald` / Hijau): `AKTIF`, `DISETUJUI`, `TERSEDIA`, `SELESAI`, `NORMAL`, `LENGKAP`, `Closed`.
   - `warning` (`amber` / Kuning): `DIAJUKAN`, `MENUNGGU_APPROVAL`, `HAMPIR_KEDALUWARSA`, `DIPINJAM`, `In Progress`.
   - `danger` (`rose` / Merah): `DITOLAK`, `KEDALUWARSA`, `RUSAK_BERAT`, `OVERDUE`, `GAGAL`.
   - `CITO` (`red-600` + `animate-pulse`): Badge merah berkedip untuk permintaan darurat medis yang wajib diproses instan.
   - `info` (`sky` / Biru): `DRAFT`, `DALAM_PENGIRIMAN`, `UJI_FUNGSI`, `SEDANG_DIKERJAKAN`, `In Review`.
   - `gray` (`zinc` / Abu-abu): `NONAKTIF`, `DIBATALKAN`.

### 2.3 Tipografi & Skala Teks
- **Font Utama Antarmuka**: Inter (`font-sans`) untuk keterbacaan tinggi pada label dan teks antarmuka.
- **Font Finansial & Kuantitas**: JetBrains Mono / Monospace Tabular (`font-mono tabular-nums`) wajib untuk seluruh nominal rupiah, kuantitas stok, nomor dokumen (`PO-YYYY-XXX`), dan tanggal expired agar angka sejajar vertikal secara presisi.
- **Skala Teks**:
  - Page Title: 20px (`text-xl font-semibold tracking-tight`)
  - Section Header: 16px (`text-base font-medium text-zinc-900 dark:text-zinc-100`)
  - Table Data: 13px (`text-xs md:text-sm leading-5`)
  - Table Header: 12px (`text-xs font-semibold text-zinc-500 uppercase tracking-wider`)
  - Badge & Tag: 11px (`text-[11px] font-semibold px-2 py-0.5 rounded-full`)

### 2.4 Layout Shell & Navigasi Flux UI
```
┌─────────────────────────────────────────────────────────────────────────────┐
│ BANNER WIRE:OFFLINE (Hanya muncul jika jaringan putus di basement)          │
├──────────┬──────────────────────────────────────────────────────────────────┤
│ SIDEBAR  │ HEADER: Breadcrumb | Mode Emulasi Badge | Notif Lonceng | Profil │
│          ├──────────────────────────────────────────────────────────────────┤
│ SIMLOG   │ KONTEN UTAMA:                                                    │
│ RS v2    │ ┌──────────────────────────────────────────────────────────────┐ │
│          │ │ Toolbar: Filter Pencarian, Filter Status, Tombol Transaksi   │ │
│ Portal:  │ ├──────────────────────────────────────────────────────────────┤ │
│ [Portal] │ │ Tabel Data Padat (Sticky Header, Tabular Nums, Paginasi)     │ │
│          │ │                                                              │ │
│ • Menu 1 │ │                                                              │ │
│ • Menu 2 │ └──────────────────────────────────────────────────────────────┘ │
│ • Menu 3 │ FOOTER / STATUS BAR                                              │
└──────────┴──────────────────────────────────────────────────────────────────┘
```
1. **Sidebar (`<flux:sidebar>`)**: Lebar tetap `w-64`, item `<flux:navitem>` ringkas dengan ikon Heroicons 20px dan aksen garis vertikal sesuai warna portal aktif.
2. **Header (`<flux:header>`)**: Tinggi `h-14` sticky, memuat breadcrumb navigasi, switcher portal Super Admin, lonceng notifikasi, dan profil.
3. **Area Konten (`max-w-7xl w-full mx-auto p-6`)**: Memaksimalkan kenyamanan inspeksi tabel lebar tanpa pemotongan data.

### 2.5 Standar Komponen Inti Flux UI
1. **Tabel Data Padat (`<flux:table>`)**: Ketinggian baris kompak (`h-10` s.d. `h-12`), header lengket (`<flux:table.columns sticky>`), angka rata kanan (`text-right`), teks deskripsi rata kiri (`text-left`).
2. **Formulir & Modal Dialog (`<flux:modal>`)**: Terpusat dengan lebar terukur (`max-w-lg` untuk form ringkas, `max-w-4xl` untuk transaksi bertingkat).
3. **Feedback & Notifikasi**: Respon aksi instan via `<flux:toast />` di pojok kanan bawah, tombol transaksi mematikan dirinya sendiri saat proses berlangsung (`wire:loading.attr="disabled"`).

## 3. Libraries &amp; Dependencies

### 3.1 Production Packages (\`composer.json\`)
\`\`\`json
```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^13.0",
        "livewire/livewire": "^3.5",
        "livewire/flux": "^1.0",
        "spatie/laravel-permission": "^6.0",
        "spatie/laravel-activitylog": "^4.12",
        "barryvdh/laravel-dompdf": "^3.0",
        "simplesoftwareio/simple-qrcode": "^4.2",
        "rap2hpoutre/fast-excel": "^5.4",
        "laravel/sanctum": "^4.3",
        "laravel/tinker": "^3.0"
    }
}
```

1. **`laravel/framework` (^13.0)**: Inti backend, routing, pipeline middleware stateless, Eloquent ORM, dan driver database PostgreSQL.
2. **`livewire/livewire` (^3.5)**: Full-stack dynamic UI engine untuk Laravel tanpa meninggalkan kenyamanan PHP.
3. **`livewire/flux` (^1.0)**: UI Component Library resmi untuk Livewire (Free Edition: `<flux:table>`, `<flux:modal>`, `<flux:sidebar>`, `<flux:input>`, dll.).
4. **`spatie/laravel-permission` (^6.0)**: Manajemen hak akses pengguna berbasis role dan direct permissions dengan caching performa tinggi.
5. **`spatie/laravel-activitylog` (^4.12)**: Pencatatan event model audit ke database dengan snapshot JSONB diffs.
6. **`barryvdh/laravel-dompdf` (^3.0)**: Cetak dokumen fisik resmi (Surat Jalan/SPPB Gudang, Purchase Order Vendor, Lembar Kerja SPK IPSRS).
7. **`simplesoftwareio/simple-qrcode` (^4.2)**: Generate stiker QR Code untuk register aset fisik dan nomor batch FEFO.
8. **`rap2hpoutre/fast-excel` (^5.4)**: Ekspor/impor Excel/CSV performa tinggi dengan konsumsi RAM rendah untuk Stock Opname dan Master Barang.
9. **`laravel/sanctum` (^4.3)**: Token otentikasi API internal jika dibutuhkan integrasi dengan SIMRS Medis/Bridging BPJS.
10. **`laravel/tinker` (^3.0)**: REPL console untuk *debugging* dan inspeksi backend langsung.

### 3.2 Fitur Standar Zero-Dependency (Bawaan Framework & Native PHP)
1. **Format Mata Uang Rupiah (`Rp`)**: Menggunakan bawaan Laravel `Illuminate\Support\Number::currency($nominal, 'IDR', 'id')`.
2. **Kalkulasi Presisi Anggaran**: Menggunakan ekstensi native PHP `bcmath` (`bcsub`, `bcadd`, `bcmul`) untuk mencegah floating-point rounding error.
3. **Input Tanggal**: Menggunakan native browser `<flux:input type="date">`.
4. **Autentikasi Pengguna**: Native Livewire Component (`LoginGateway` + `RateLimiter` + `PortalAccessMiddleware`).
---

## 4. Proses Bisnis Utama, Peran yang Terlibat &amp; Alur Siklus Transaksi

### 4.1 Proses Bisnis 1: Pengadaan Barang Logistik (Procurement Cycle)
Siklus pengadaan komoditas logistik non-medis dari identifikasi kebutuhan (PR), penerbitan kontrak/pesanan resmi (PO), pemantauan pengiriman vendor, hingga penerimaan fisik (Inbound) dan pencatatan batch di gudang.

#### A. Peran &amp; Portal yang Terlibat
| Aktor / Peran | Portal Akses | Tanggung Jawab dalam Alur Pengadaan |
|---|---|---|
| \*\*Petugas Unit / Gudang\*\* | \`/petugas-unit\` / \`/petugas-gudang\` | Menginput permohonan pengadaan barang habis pakai atau suku cadang (PR). |
| \*\*Kepala Unit / Kepala Gudang\*\* | \`/kepala-unit\` / \`/kepala-gudang\` | Meneliti urgensi dan memberikan otorisasi (\*Approve PR\*). |
| \*\*Bagian Pengadaan\*\* | \`/pengadaan\` | Memverifikasi PR yang disetujui, mengonversi PR menjadi PO, memilih vendor rekanan, dan menerbitkan PO. |
| \*\*Petugas Gudang Inbound\*\* | \`/petugas-gudang\` | Memeriksa fisik kiriman vendor, mencocokkan surat jalan terhadap PO, mencatat nomor batch &amp; expired date, dan menetapkan rak simpan awal. |
| \*\*Manajemen Direksi\*\* | \`/manajemen\` | Memantau realisasi anggaran pengadaan vs pagu tahunan yang tersedia. |

#### B. Diagram Alur Siklus Pengadaan
\`\`\`mermaid
flowchart TD
    A\[Pemohon: Input Draft PR\] --&gt;|Status: DIAJUKAN| B{Approval Karu / Kepala Gudang}
    B --&gt;|Ditolak| C\[Status: DITOLAK\]
    B --&gt;|Disetujui| D\[Status: DISETUJUI / DITERUSKAN\]
    D --&gt; E\[Pengadaan: Verifikasi &amp; Konversi ke PO\]
    E --&gt; F{Pengecekan Pagu Anggaran}
    F --&gt;|Melebihi Pagu| G\[Peringatan Anggaran / Penyesuaian Nilai\]
    F --&gt;|Anggaran Cukup| H\[PO DITERBITKAN ke Vendor Rekanan\]
    H --&gt; I\[Tracking Pengiriman Vendor\]
    I --&gt;|Barang Tiba di RS| J\[Petugas Gudang: Inbound Receiving\]
    J --&gt; K{Inspeksi Fisik &amp; Kesesuaian PO}
    K --&gt;|Rusak / Selisih| L\[Penerimaan Parsial / Retur Vendor\]
    K --&gt;|Sesuai &amp; Utuh| M\[Terbitkan Penerimaan Barang: LENGKAP\]
    M --&gt; N\[Otomatis Buat Rekaman stok\_batch &amp; Catat pergerakan\_stok MASUK\]
    N --&gt; O\[PO Berstatus DITERIMA\_LENGKAP / Selesai\]
\`\`\`

#### C. Rincian Tahapan Siklus Transaksi
1. \*\*Tahap 1 - Pengajuan Permintaan Pengadaan (\`permintaan\_pengadaan\`)\*\*:
   - Dibuat via \`PermintaanPengadaanPage\`. Pemohon mengisi daftar barang, jumlah kebutuhan, dan tingkat urgensi (\`REGULER\`, \`MENDESAK\`, \`CITO\`).
   - Status awal: \*\*\`DIAJUKAN\`\*\*.
2. \*\*Tahap 2 - Otorisasi Kepala Ruangan / Kepala Gudang\*\*:
   - Kepala Unit/Gudang meninjau kelayakan barang. Jika disetujui, kuantitas disahkan pada \`jumlah\_disetujui\` dan status beralih ke \*\*\`DISETUJUI\`\*\* atau \*\*\`DITERUSKAN\_KE\_PENGADAAN\`\*\*.
3. \*\*Tahap 3 - Penerbitan Pesanan Pembelian (\`pesanan\_pembelian\`)\*\*:
   - Staf Pengadaan membuka daftar PR yang disetujui, menekan aksi \*\*"Terbitkan PO"\*\* (\`konversiPo\`), memilih vendor (\`pemasok\_id\`), menetapkan harga beli satuan resmi, dan menentukan estimasi tanggal kirim.
   - Sistem secara otomatis menghitung \`total\_biaya\` dan mengubah status PO menjadi \*\*\`DITERBITKAN\`\*\*.
4. \*\*Tahap 4 - Validasi Pagu Anggaran (\`anggaran\_pengadaan\`)\*\*:
   - Sistem memvalidasi apakah \`total\_biaya\` PO masih mencukupi dalam sisa pagu anggaran tahun berjalan. Nilai realisasi dan serapan anggaran diperbarui secara otomatis.
5. \*\*Tahap 5 - Pelacakan Pengiriman (\`TrackingPengirimanPoPage\`)\*\*:
   - Memantau progres pengiriman logistik rekanan (\`DIKIRIM\_VENDOR\`).
6. \*\*Tahap 6 - Penerimaan Fisik di Gudang (\`penerimaan\_barang\` &amp; \`penerimaan\_barang\_detail\`)\*\*:
   - Petugas gudang membuka \`PenerimaanPoPage\`, menginput nomor surat jalan vendor, memeriksa kondisi fisik kemasan, mencatat kuantitas diterima, nomor batch pabrik, dan tanggal kedaluwarsa (\*expired date\*).
   - **Integrasi Otomatis Saldo Stok**: Sistem secara atomik membuat rekaman `stok_batch` baru pada rak yang ditentukan, dan mencatat mutasi `MASUK` pada kartu stok `pergerakan_stok`.

#### D. Kondisi, Validasi & State Machine
- **State Machine PR**: `DIAJUKAN` -> `DISETUJUI` / `DITOLAK` -> `DITERUSKAN_KE_PENGADAAN`.
- **State Machine PO**: `DITERBITKAN` -> `DIKIRIM_VENDOR` -> `DITERIMA_SEBAGIAN` -> `DITERIMA_LENGKAP` (atau `DIBATALKAN`).
- **Validasi Anggaran**: Total biaya PO wajib <= sisa pagu anggaran tahun berjalan (`bcsub`). Transaksi diblokir jika melebihi pagu.
- **Validasi Inbound**: Nomor batch dan tanggal kedaluwarsa wajib terisi saat penerimaan fisik komoditas habis pakai/reagen.

---

### 4.2 Proses Bisnis 2: Distribusi Barang Ruangan (Amprah &amp; Picking FEFO)
Siklus pemenuhan komoditas habis pakai dari ruangan rumah sakit ke gudang sentral logistik, dipandu oleh algoritma penarikan stok otomatis berdasarkan kedaluwarsa terdekat (FEFO).

#### A. Peran &amp; Portal yang Terlibat
| Aktor / Peran | Portal Akses | Tanggung Jawab dalam Alur Distribusi |
|---|---|---|
| \*\*Petugas Unit / Ruangan\*\* | \`/petugas-unit\` | Mengajukan amprah kebutuhan barang habis pakai rutin ruangan. |
| \*\*Kepala Unit / Karu\*\* | \`/kepala-unit\` | Memeriksa kuota wajar dan mengesahkan amprah ruangan (\*Approval Karu\*). |
| \*\*Admin Logistik\*\* | \`/admin-logistik\` | Memverifikasi ketersediaan stok global dan menyetujui kuantitas distribusi. |
| \*\*Petugas Gudang\*\* | \`/petugas-gudang\` | Menjalankan proses picking otomatis berbasis FEFO, packing fisik, dan menerbitkan SPPB. |
| \*\*Petugas Unit (Penerima)\*\*| \`/petugas-unit\` | Mengonfirmasi penerimaan fisik barang yang tiba di ruangan dan memasukkannya ke stok ruangan. |

#### B. Diagram Alur Siklus Distribusi
\`\`\`mermaid
flowchart TD
    A\[Staf Unit: Input Amprah Permintaan Barang\] --&gt;|Status: DIAJUKAN| B{Approval Karu Ruangan}
    B --&gt;|Ditolak| C\[Status: DITOLAK\]
    B --&gt;|Disetujui Karu| D\[Status: DISETUJUI\_KARU\]
    D --&gt; E{Approval Admin Logistik}
    E --&gt;|Ditolak / Koreksi Qty| F\[Penyesuaian Kuantitas Disetujui\]
    E --&gt;|Disetujui Gudang| G\[Status: DISETUJUI\_GUDANG\]
    G --&gt; H\[Petugas Gudang: Eksekusi Picking FEFO\]
    H --&gt; I\[Algoritma Otomatis: Urutkan stok\_batch ED Terdekat\]
    I --&gt; J\[Kunci Saldo: jumlah\_dipesan bertambah / jumlah berkurang\]
    J --&gt; K\[Terbitkan Dokumen SPPB: Status SEDANG\_DIKIRIM\]
    K --&gt; L\[Ekspedisi Internal ke Ruangan Pemohon\]
    L --&gt; M\[Staf Ruangan: Konfirmasi Penerimaan Fisik RCV\]
    M --&gt; N\[Catat pergerakan\_stok KELUAR Gudang &amp; MASUK ke stok\_ruangan\]
    N --&gt; O\[Transaksi Berstatus SELESAI\]
\`\`\`

#### C. Rincian Tahapan Siklus Transaksi
1. \*\*Tahap 1 - Pengajuan Amprah Ruangan (\`permintaan\_distribusi\`)\*\*:
   - Staf ruangan mengisi form pada \`PermintaanBarangPage\` memilih komoditas, jumlah, dan urgensi (\`REGULER\` atau \`CITO\`). Status: \*\*\`DIAJUKAN\`\*\*.
2. \*\*Tahap 2 - Otorisasi Kepala Ruangan (\`ApprovalPermintaanPage\`)\*\*:
   - Karu memeriksa riwayat pemakaian ruangan. Jika disetujui, status berubah menjadi \*\*\`DISETUJUI\_KARU\`\*\*.
3. \*\*Tahap 3 - Verifikasi &amp; Otorisasi Logistik (\`DistribusiApprovalPage\`)\*\*:
   - Admin Logistik meninjau sisa saldo di gudang sentral dan menetapkan \`jumlah\_disetujui\`. Status beralih ke \*\*\`DISETUJUI\_GUDANG\`\*\*.
4. \*\*Tahap 4 - Picking Otomatis &amp; Penerbitan SPPB (\`PickingDistribusiPage\`)\*\*:
   - Petugas gudang membuka daftar pesanan siap ambil.
   - Menekan tombol **"Eksekusi Picking FEFO"**. Service `DistribusiService` secara otomatis memindai `stok_batch` dengan kondisi `status = 'AKTIF'` dan mengurutkannya berdasarkan `tanggal_kedaluwarsa ASC` menggunakan **pessimistic locking** (`lockForUpdate()`) di dalam `DB::transaction()` untuk mencegah race condition.
   - Sistem memotong saldo fisik `stok_batch`, mencatat pengeluaran di `pergerakan_stok`, menerbitkan nomor SPPB (`SPPB-YYYYMMDD-XXXX`), dan mengubah status menjadi **`SEDANG_DIKIRIM`**.
   - Terbit dokumen penerimaan (`penerimaan_distribusi`), saldo tercatat masuk ke persediaan lokal ruangan (`stok_ruangan`), dan status transaksi ditutup **`SELESAI`**.

#### D. Kondisi, Validasi & State Machine
- **State Machine Amprah**: `DIAJUKAN` -> `DISETUJUI_KARU` -> `DISETUJUI_GUDANG` -> `SEDANG_DIKIRIM` -> `SELESAI` (atau `DITOLAK`).
- **State Machine SPPB**: `DIKIRIM` -> `DITERIMA` (atau `BATAL`).
- **Integritas Stok & FEFO Lock**: Alokasi batch wajib menggunakan `lockForUpdate()` dalam `DB::transaction()` dan mematuhi `chk_stok_batch_non_negative`. Batch kedaluwarsa dilarang dialokasikan.

---

### 4.3 Proses Bisnis 3: Peminjaman &amp; Sirkulasi Aset Non-Medis
Siklus peminjaman aktiva tetap/fasilitas inventaris rumah sakit (proyektor, ranjang cadangan, kursi roda, alat pembersih, kendaraan operasional) antar-ruangan atau dari gudang sentral logistik.

#### A. Peran &amp; Portal yang Terlibat
| Aktor / Peran | Portal Akses | Tanggung Jawab dalam Alur Peminjaman |
|---|---|---|
| \*\*Staf Unit Peminjam\*\* | \`/petugas-unit\` | Mengajukan permohonan pinjam aset yang tersedia, memantau masa pinjam, dan mengembalikan aset. |
| \*\*Kepala Unit Pemilik Aset\*\* | \`/kepala-unit\` | Memeriksa urgensi ruangan peminjam dan memberikan otorisasi peminjaman. |
| \*\*Admin Logistik\*\* | \`/admin-logistik\` | Mengelola sirkulasi aset sentral RS, melakukan serah terima fisik langsung, dan memantau status overdue. |
| \*\*Teknisi IPSRS\*\* | \`/teknisi\` | Menerima tiket perbaikan otomatis jika aset dikembalikan dalam kondisi rusak. |

#### B. Diagram Alur Siklus Peminjaman
\`\`\`mermaid
flowchart TD
    A\[Unit Peminjam: Input Form Pengajuan Pinjam\] --&gt;|Status: DIAJUKAN| B{Otorisasi Karu Pemilik Aset}
    B --&gt;|Ditolak| C\[Status: DITOLAK\]
    B --&gt;|Disetujui| D\[Status: DISETUJUI &amp; Siap Serah Terima\]
    D --&gt; E\[Eksekusi Serah Terima Fisik Barang\]
    E --&gt; F\[Status Transaksi: DIPINJAM\]
    F --&gt;|Mutasi Master Aset| G\[Aset: status DIPINJAM &amp; unit\_id beralih ke Peminjam\]
    F --&gt; H{Kebutuhan Operasional Ruangan}
    H --&gt;|Butuh Tambahan Waktu| I\[Aksi: Perpanjang Masa Peminjaman\]
    I --&gt; F
    H --&gt;|Selesai Pemakaian| J\[Aksi: Kembalikan Aset &amp; Inspeksi Fisik\]
    J --&gt;|Kondisi Fisik: BAIK| K\[Status: SELESAI&lt;br&gt;Aset kembali TERSEDIA di Lokasi Semula\]
    J --&gt;|Kondisi: RUSAK\_RINGAN / BERAT| L\[Status: SELESAI&lt;br&gt;Aset: DALAM\_MAINTENANCE\]
    L --&gt; M\[Otomatis Terbit Tiket Permintaan Maintenance IPSRS\]
\`\`\`

#### C. Rincian Tahapan Siklus Transaksi
1. \*\*Tahap 1 - Pengajuan Peminjaman (\`permintaan\_peminjaman\`)\*\*:
   - Staf unit membuka \`PeminjamanAsetUnitPage\` dan memilih aset berkondisi \`BAIK\` dan berstatus \`TERSEDIA\`.
   - Mengisi agenda keperluan, tanggal pinjam, dan estimasi tanggal kembali. Status: \*\*\`DIAJUKAN\`\*\* (nomor: \`PINJ-YYYY-XXX\`).
2. \*\*Tahap 2 - Otorisasi Kepala Ruangan Pemilik Aset (\`ApprovalPeminjamanPage\`)\*\*:
   - Karu dari ruangan asal aset meneliti permohonan. Jika disetujui, status transaksi beralih ke \*\*\`DISETUJUI\`\*\*.
3. \*\*Tahap 3 - Serah Terima Fisik Aset (\`PeminjamanAsetService::serahTerimaFisik\`)\*\*:
   - Saat fisik barang diserahkan ke peminjam, aksi \*Serah Terima Fisik\* disahkan.
   - Status transaksi menjadi \*\*\`DIPINJAM\`\*\*.
   - \*\*Mutasi Aset (\`aset\`)\*\*: Kolom \`status\` aset berubah menjadi \`'DIPINJAM'\`, dan kolom \`unit\_id\` berpindah ke unit peminjam (aset kini menjadi tanggung jawab unit peminjam).
4. \*\*Tahap 4 - Pemantauan Jatuh Tempo &amp; Perpanjangan\*\*:
   - Sistem menghitung selisih hari terhadap \`tanggal\_kembali\_rencana\`. Jika terlewat, badge otomatis bertanda \*\*Overdue\*\*.
   - Peminjam dapat mengajukan aksi \*\*"Perpanjang"\*\* jika masih membutuhkan aset tersebut.
5. \*\*Tahap 5 - Pengembalian &amp; Inspeksi Fisik (\`pengembalian\_peminjaman\`)\*\*:
   - Unit peminjam mengembalikan aset fisik dan menekan tombol \*\*"Kembalikan Aset"\*\*.
   - Petugas pemeriksa memilih kondisi fisik aktual:
     - \*\*Kondisi BAIK\*\*: Status aset kembali \*\*\`TERSEDIA\`\*\*, lokasi dikembalikan ke unit pemilik semula atau Gudang Sentral.
     - **Kondisi RUSAK_RINGAN / RUSAK_BERAT**: Status aset disetel **`DALAM_MAINTENANCE`**. Sistem secara otomatis menerbitkan tiket servis perbaikan baru (`PermintaanMaintenance`) dengan prioritas `CITO` (rusak berat) atau `SEDANG` (rusak ringan) ke antrean teknisi IPSRS.

#### D. Kondisi, Validasi & State Machine
- **State Machine Peminjaman**: `DIAJUKAN` -> `DISETUJUI` -> `DIPINJAM` -> `SELESAI` (atau `DITOLAK`).
- **State Machine Master Aset**: `TERSEDIA` -> `DIPINJAM` -> `TERSEDIA` (kondisi baik) atau `DALAM_MAINTENANCE` (kondisi rusak).
- **Validasi Overdue**: Jika `today() > tanggal_kembali_rencana` dan status masih `DIPINJAM`, badge otomatis bertanda Overdue.
- **Auto-Trigger IPSRS**: Aset kembali berkondisi `RUSAK_RINGAN` atau `RUSAK_BERAT` otomatis menerbitkan tiket `PermintaanMaintenance` ke antrean IPSRS.
---

### 4.4 Proses Bisnis 4: Pemeliharaan Sarana &amp; Prasarana IPSRS
Pengelolaan perbaikan kerusakan sarana/fasilitas (\*Corrective Maintenance\*) dan kalender jadwal pemeliharaan rutin (\*Preventive Maintenance\*) oleh Instalasi Pemeliharaan Sarana Rumah Sakit (IPSRS).

#### A. Peran &amp; Portal yang Terlibat
| Aktor / Peran | Portal Akses | Tanggung Jawab dalam Alur Pemeliharaan |
|---|---|---|
| \*\*Staf Unit Pelapor\*\* | \`/petugas-unit\` | Melaporkan insiden kerusakan sarana fisik ruangan via tiket servis. |
| \*\*Kepala Ruangan / Unit\*\* | \`/kepala-unit\` | Memantau status tiket perbaikan fasilitas ruangannya. |
| \*\*Teknisi IPSRS\*\* | \`/teknisi\` | Menerima disposisi SPK, mengambil sparepart di gudang, mengisi lembar kerja teknisi, dan melakukan uji fungsi. |
| \*\*Petugas Gudang Sparepart\*\*| \`/petugas-gudang\` | Mengeluarkan suku cadang pemeliharaan berdasarkan SPK teknisi. |
| \*\*Super Admin / Scheduler\*\* | \`/super-admin\` | Mengonfigurasi jadwal preventif rutin dan memantau cron otomatis. |

#### B. Diagram Alur Siklus Pemeliharaan IPSRS
\`\`\`mermaid
flowchart TD
    A1\[Lapor Kerusakan Ruangan: LaporServisPage\] --&gt; B\[Tiket Dibuat: status DILAPORKAN\]
    A2\[Jadwal Preventif Jatuh Tempo: Scheduler Cron\] --&gt; B
    A3\[Aset Peminjaman Kembali Rusak: Auto-Trigger\] --&gt; B
    B --&gt; C\[Disposisi SPK ke Teknisi: status DITUGASKAN\]
    C --&gt; D\[Teknisi Menerima Tugas: status DITERIMA\]
    D --&gt; E{Kebutuhan Suku Cadang?}
    E --&gt;|Butuh Sparepart| F\[Permintaan Suku Cadang ke Gudang\]
    F --&gt; G\[Gudang Mengeluarkan Suku Cadang: Alokasi FEFO\]
    G --&gt; H\[Catat suku\_cadang\_maintenance &amp; pergerakan\_stok\]
    E --&gt;|Tanpa Sparepart| H
    H --&gt; I\[Teknisi: Pengerjaan Fisik &amp; Lembar Kerja log\_maintenance\]
    I --&gt; J\[Pengujian Fungsi &amp; Kelaikan Sarana\]
    J --&gt;|Gagal / Perlu Observasi| I
    J --&gt;|Lolos Uji Fungsi| K\[Selesaikan SPK: status SELESAI\]
    K --&gt; L\[Update Master Aset: status TERSEDIA / DIGUNAKAN, kondisi BAIK\]
    L --&gt; M\[Pembaruan Rekam Medis Aset &amp; Riwayat Biaya Servis\]
\`\`\`

#### C. Rincian Tahapan Siklus Transaksi
1. \*\*Tahap 1 - Penerbitan Tiket Kerusakan (\`permintaan\_maintenance\`)\*\*:
   - Dipicu melalui 3 sumber: (1) Form \*Lapor Servis\* unit kerja, (2) Pengembalian peminjaman aset rusak, atau (3) Cron harian jadwal pemeliharaan preventif yang jatuh tempo. Status: \*\*\`DILAPORKAN\`\*\*.
2. \*\*Tahap 2 - Disposisi SPK Penugasan (\`penugasan\_maintenance\`)\*\*:
   - Koordinator IPSRS menetapkan teknisi penanggung jawab (\`teknisi\_id\`) dan surat perintah kerja diterbitkan (\`DITUGASKAN\`).
3. \*\*Tahap 3 - Pengambilan Suku Cadang Logistik (\`suku\_cadang\_maintenance\`)\*\*:
   - Jika perbaikan membutuhkan komponen pengganti (misal: bearing, kontaktor, lampu ballast, seal), teknisi mengajukan permintaan suku cadang ke gudang.
   - Petugas gudang mengeluarkan material menggunakan batch FEFO terdekat, dan tercatat otomatis pada kartu stok persediaan.
4. \*\*Tahap 4 - Pelaksanaan Teknis &amp; Lembar Kerja (\`log\_maintenance\`)\*\*:
   - Teknisi membuka \`LembarKerjaPengerjaanPage\`, menginput tindakan perbaikan, jam kerja, pergantian sparepart, dan hasil diagnosis. Status: \*\*\`SEDANG\_DIKERJAKAN\`\*\*.
5. \*\*Tahap 5 - Uji Fungsi &amp; Penyelesaian Rekam Medis (\`RekamMedisAsetPage\`)\*\*:
   - Sarana diuji kelaikannya bersama pengguna ruangan.
   - SPK ditutup berstatus **`SELESAI`**, status aset kembali **`TERSEDIA`** / **`DIGUNAKAN`** dengan kondisi **`BAIK`**, dan seluruh riwayat tersimpan permanen pada *Rekam Medis Aset*.

#### D. Kondisi, Validasi & State Machine
- **State Machine Tiket**: `DILAPORKAN` -> `DITUGASKAN` -> `SEDANG_DIKERJAKAN` -> `MENUNGGU_SPAREPART` -> `UJI_FUNGSI` -> `SELESAI` (atau `DIBATALKAN`).
- **Validasi Sparepart**: Pemakaian suku cadang memotong stok_batch FEFO gudang logistik dan mencatat mutasi pengeluaran.
- **Validasi Uji Fungsi**: Gagal uji fungsi mengembalikan status ke `SEDANG_DIKERJAKAN`; lolos uji fungsi menutup SPK dan mengembalikan aset ke kondisi `BAIK` dan status `TERSEDIA` / `DIGUNAKAN`.
---

### 4.5 Proses Bisnis 5: Manajemen Persediaan, FEFO &amp; Stock Opname
Siklus pengendalian saldo persediaan fisik di gudang rumah sakit guna memastikan kepatuhan FEFO, mencegah barang kedaluwarsa di rak, dan mencocokkan saldo buku terhadap fisik secara periodik.

#### A. Peran &amp; Portal yang Terlibat
| Aktor / Peran | Portal Akses | Tanggung Jawab dalam Manajemen Persediaan |
|---|---|---|
| \*\*Petugas Gudang\*\* | \`/petugas-gudang\` | Menata barang di rak, melakukan relokasi bin, dan menjalankan hitung fisik opname (\*blind count\*). |
| \*\*Kepala Gudang\*\* | \`/kepala-gudang\` | Memantau dashboard FEFO, menerbitkan siklus opname, dan mengotorisasi penyesuaian selisih stok. |
| \*\*Auditor Internal (SPI)\*\*| \`/auditor\` | Memeriksa kepatuhan rekonsiliasi opname dan menginvestigasi varians stok mencurigakan. |

#### B. Diagram Alur Siklus Persediaan &amp; Stock Opname
\`\`\`mermaid
flowchart TD
    A\[Barang Masuk Gudang: Catat Batch, ED &amp; Nomor Rak\] --&gt; B\[Monitoring Harian FEFO\]
    B --&gt; C\[Cron Harian: simlog:sweep-expired-batches\]
    C --&gt;|ED &lt;= Hari Ini| D\[Kunci Status: KEDALUWARSA\]
    C --&gt;|ED &lt; 90 Hari| E\[Tandai: HAMPIR\_KEDALUWARSA - Prioritas Picking\]
    B --&gt; F\[Kepala Gudang: Buka Jadwal Siklus Stock Opname\]
    F --&gt; G\[Petugas Gudang: Hitung Fisik Lapangan Blind Count\]
    G --&gt; H\[Input Kuantitas Aktual ke stok\_opname\_detail\]
    H --&gt; I{Ada Selisih Fisik vs Sistem?}
    I --&gt;|Selisih = 0| J\[Status Opname: SELESAI / Klop\]
    I --&gt;|Terdapat Selisih| K\[Ajukan Penyesuaian: penyesuaian\_stok\]
    K --&gt; L{Otorisasi Kepala Gudang}
    L --&gt;|Ditolak| M\[Investigasi Ulang Hitung Fisik\]
    L --&gt;|Disetujui| N\[Mutasi Saldo stok\_batch &amp; Jurnal Penyesuaian pergerakan\_stok\]
    N --&gt; O\[Tercatat di Audit Trail SPI\]
\`\`\`

#### C. Rincian Tahapan Siklus Transaksi
1. \*\*Tahap 1 - Penataan Fisik &amp; Pencatatan Batch\*\*:
   - Setiap komoditas yang masuk dipetakan ke rak fisik melalui \`LokasiGudang\` dan dicatat tanggal kedaluwarsanya di \`stok\_batch\`.
2. \*\*Tahap 2 - Monitoring FEFO &amp; Automated Sweep\*\*:
   - Jadwal cron harian pukul 00:01 WIB secara otomatis mengunci batch kedaluwarsa agar tidak bisa dialokasikan pada proses picking distribusi ke ruangan.
3. \*\*Tahap 3 - Pelaksanaan Stock Opname Lapangan (\`stok\_opname\`)\*\*:
   - Kepala Gudang membuat siklus opname per gudang/zona rak.
   - Petugas gudang melakukan perhitungan fisik lapangan tanpa melihat angka sistem (\*blind count\*), lalu menginput hasil hitung ke \`stok\_opname\_detail\`.
4. \*\*Tahap 4 - Otorisasi Penyesuaian Stok (\`penyesuaian\_stok\`)\*\*:
   - Jika terdapat selisih (\*variance\*), sistem mewajibkan pengajuan alasan penyesuaian (pecah, rusak, susut, selisih hitung awal).
   - Setelah disetujui Kepala Gudang, saldo `stok_batch` diselaraskan ke kuantitas fisik dan kartu stok `pergerakan_stok` mencatat mutasi jenis `PENYESUAIAN`.

#### D. Kondisi, Validasi & State Machine
- **State Machine Opname**: `DRAFT` -> `BERJALAN` -> `MENUNGGU_APPROVAL` -> `DISETUJUI` (atau `DITOLAK`).
- **State Machine Penyesuaian**: `MENUNGGU` -> `DISETUJUI` / `DITOLAK`.
- **Validasi Blind Count**: Form petugas gudang tidak menampilkan saldo sistem untuk menjaga objektivitas audit.
- **Constraint Non-Negatif**: Penyesuaian minus dilarang membuat saldo akhir batch menjadi negatif (`jumlah >= 0`).
---

### 4.6 Proses Bisnis 6: Tata Kelola Audit Internal, Rekonsiliasi SPI &amp; CAPA
Pengawasan independen terhadap transaksi logistik, penelusuran digital forensic log aktivitas, audit kepatuhan pengadaan dan persediaan, serta penertiban temuan CAPA.

#### A. Peran &amp; Portal yang Terlibat
| Aktor / Peran | Portal Akses | Tanggung Jawab dalam Alur Pengawasan SPI |
|---|---|---|
| \*\*Auditor Internal (SPI)\*\*| \`/auditor\` | Memeriksa audit trail sistem, menguji kepatuhan dokumen transaksi, dan mencatat temuan audit. |
| \*\*Unit / Gudang Terperiksa\*\*| Seluruh Portal | Memberikan klarifikasi dan melaksanakan tindakan perbaikan (\*Corrective Action\*). |
| \*\*Manajemen Direksi\*\* | \`/manajemen\` | Memantau ringkasan risiko temuan audit dan status penutupan temuan CAPA. |

#### B. Diagram Alur Siklus Audit Internal
\`\`\`mermaid
flowchart TD
    A\[Transaksi Sistem Berjalan\] --&gt; B\[Otomatis Tercatat pada log\_audit &amp; activity\_log\]
    B --&gt; C\[Auditor: Analisis Forensik Audit Trail\]
    C --&gt; D{Pemeriksaan Kepatuhan}
    D --&gt;|Uji Pengadaan| E\[Deteksi PO tanpa PR / PO Melebihi Anggaran\]
    D --&gt;|Uji Mutasi| F\[Deteksi Selisih SPPB Gudang vs RCV Ruangan\]
    D --&gt;|Uji Persediaan| G\[Investigasi Selisih Hasil Stock Opname\]
    E &amp; F &amp; G --&gt; H{Ditemukan Ketidaksesuaian Prosedur?}
    H --&gt;|Patuh &amp; Wajar| I\[Pemeriksaan Selesai / Arsip\]
    H --&gt;|Ditemukan Penyimpangan| J\[Terbitkan Temuan Audit: temuan\_audit\]
    J --&gt; K\[Klasifikasi Risiko: Mayor / Moderat / Minor &amp; Due Date CAPA\]
    K --&gt; L\[Unit Terperiksa Melaksanakan Tindakan Perbaikan\]
    L --&gt; M{Verifikasi Evaluasi Auditor}
    M --&gt;|Belum Tuntas| L
    M --&gt;|Tuntas &amp; Terbukti| N\[Status CAPA: Closed / Selesai\]
\`\`\`

#### C. Rincian Tahapan Siklus Transaksi
1. \*\*Tahap 1 - Penelusuran Forensik Digital (\`log\_audit\`)\*\*:
   - SPI memantau rekaman log audit yang memuat data sebelum vs sesudah (JSONB), IP address, perangkat, dan aktor pengubah.
2. \*\*Tahap 2 - Uji Kepatuhan Silang (Cross-Check Validation)\*\*:
   - Mencocokkan kesesuaian antara dokumen Permintaan Pengadaan (PR) dengan Pesanan Pembelian (PO), serta Surat Pengiriman (SPPB) dengan Tanda Terima Ruangan (RCV).
3. \*\*Tahap 3 - Penerbitan Temuan Audit (\`temuan\_audit\`)\*\*:
   - Menginput kode temuan (\`AUD-YYYYMM-XXX\`), tanggal audit, objek pemeriksaan, uraian ketidaksesuaian, tingkat risiko (\`Mayor\`, \`Moderat\`, \`Minor\`), dan batas waktu tindakan perbaikan (\`due\_date\_capa\`). Status: \*\*\`In Progress\`\*\*.
4. \*\*Tahap 4 - Evaluasi Tindak Lanjut &amp; Penutupan (CAPA Closing)\*\*:
   - Unit terkait mengunggah bukti perbaikan prosedur. Auditor memverifikasi bukti tersebut hingga status berubah menjadi **`Closed`**.

#### D. Kondisi, Validasi & State Machine
- **State Machine CAPA**: `In Progress` -> `In Review` -> `Closed`.
- **Kondisi Kepatuhan**: Transaksi PO tanpa PR sah atau selisih SPPB vs RCV otomatis ditandai *Non-Compliant Flag* pada dashboard Auditor.
---

### 4.7 Proses Bisnis 7: Tata Kelola Pengguna, Dynamic RBAC &amp; Emulasi Konteks
Pengelolaan akun pegawai, konfigurasi matriks wewenang berbasis permission dinamis tanpa hardcode, dan emulasi sudut pandang unit/gudang untuk audit sistem.

#### A. Peran &amp; Portal yang Terlibat
| Aktor / Peran | Portal Akses | Tanggung Jawab dalam Tata Kelola Akses |
|---|---|---|
| \*\*Super Administrator\*\* | \`/super-admin\` | Mengonfigurasi matriks wewenang peran, mengelola akun user, dan mengemulasi konteks. |
| \*\*Seluruh Pegawai RS\*\* | 10 Portal Resmi | Masuk ke portal kerja masing-masing sesuai hak akses yang diberikan. |

#### B. Diagram Alur Dynamic RBAC &amp; Emulasi
\`\`\`mermaid
flowchart TD
    A\[Super Admin: Registrasi Akun Pengguna\] --&gt; B\[Kaitkan Peran &amp; Unit/Gudang Dinas\]
    B --&gt; C\[Buka RoleResource: Matriks Izin 5 Kolom\]
    C --&gt; D\[Pilih Modul: Checklist Toggle Halaman &amp; CRUD Action\]
    D --&gt; E\[PagePermissionRegistry: Sinkronkan ke Spatie Permissions\]
    E --&gt; F\[Runtime Evaluasi: Sidebar &amp; Tombol Dinamis Otomatis\]
    F --&gt; G{Super Admin Membutuhkan Uji Sudut Pandang?}
    G --&gt;|Pilih Gudang / Unit di Dashboard| H\[ContextManager: Set Context di Session\]
    H --&gt; I\[Dashboard &amp; Kueri Otomatis Terfilter ke Gudang/Unit Pilihan\]
    I --&gt; J\[Tombol Header Berubah Warna Warning &amp; Muncul Reset Global\]
    J --&gt;|Tekan Reset Mode Global| K\[ContextManager: Kembali ke Pandangan Seluruh RS\]
\`\`\`

#### C. Rincian Tahapan Siklus Transaksi
1. \*\*Tahap 1 - Manajemen Akun Pegawai (\`PenggunaResource\`)\*\*:
   - Mendaftarkan user dengan username unik, email, password terenkripsi, serta mengaitkannya ke unit kerja atau gudang resmi.
2. \*\*Tahap 2 - Konfigurasi Matriks RBAC (\`RoleResource\`)\*\*:
   - Mengatur izin peran melalui form tabbed interaktif 5 kolom (\*Akses Halaman, Lihat, Buat, Ubah, Hapus\*) yang digerakkan oleh \`PagePermissionRegistry\`.
   - Tidak ada penetapan izin secara hardcode. Semua hak akses langsung tersimpan ke tabel \`permissions\` dan \`role\_has\_permissions\`.
3. \*\*Tahap 3 - Evaluasi Runtime Otorisasi Tanpa Hardcode\*\*:
   - Sidebar navigasi Flux UI (`<flux:navitem>`) dan tombol aksi (`<flux:button>`) otomatis memeriksa `$user->can('page_...')` dan `$user->can('{action}_{entity}')`. Jika izin dicabut di Super Admin, menu dan tombol langsung hilang seketika.
4. \*\*Tahap 4 - Emulasi Konteks Super Admin (\`ContextManager\`)\*\*:
   - Super Admin dapat beralih ke sudut pandang gudang atau unit spesifik secara instan lewat tombol header dashboard untuk menguji validasi tampilan dan kueri data operasional.

#### D. Kondisi, Validasi & State Machine
- **Status Pengguna**: `AKTIF` (diizinkan login) atau `NONAKTIF` (ditolak 403 Forbidden).
- **Anti Self-Lockout**: Hak akses `user::role` dikunci (*disabled*) khusus untuk role `super_admin`.
- **Portal Access Guard**: Mengakses URL di luar wewenang otomatis ditolak HTTP 403 oleh `PortalAccessMiddleware`.
---

### 4.8 Matriks Ringkasan Keterlibatan 10 Peran dalam Proses Bisnis

| Peran Akun | PB-1: Pengadaan | PB-2: Distribusi | PB-3: Peminjaman | PB-4: IPSRS | PB-5: Persediaan | PB-6: Audit SPI | PB-7: RBAC |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| \*\*1. super\_admin\*\* | Kontrol Pagu | Bypass / Darurat | Bypass / Darurat | Konfigurasi | Monitoring | Full View | \*\*Pengelola Penuh\*\* |
| \*\*2. admin\_logistik\*\*| Review Kebutuhan| \*\*Otorisasi Qty\*\* | \*\*Sirkulasi Sentral\*\*| Permintaan Part| Pengawasan Saldo | Obyek Audit | Evaluasi Wewenang |
| \*\*3. kepala\_gudang\*\* | Approval PR Gudang| Pengawasan SPPB | - | Otorisasi Part | \*\*Otorisasi Opname\*\*| Klarifikasi Temuan| Evaluasi Staf Gudang |
| \*\*4. petugas\_gudang\*\*| \*\*Inbound PO\*\* | \*\*Picking FEFO\*\* | - | Serah Terima Part| \*\*Blind Count Opname\*\*| - | Pengguna Portal |
| \*\*5. pengadaan\*\* | \*\*Penerbitan PO\*\* | - | - | PO Suku Cadang | Review Vendor | Obyek Audit | Pengguna Portal |
| \*\*6. kepala\_unit\*\* | \*\*Approval PR Unit\*\*| \*\*Approval Amprah\*\*| \*\*Otorisasi Pinjam\*\*| Pantau Servis | Kuota Ruangan | Klarifikasi Unit | Evaluasi Staf Unit |
| \*\*7. petugas\_unit\*\* | Input Draft PR | \*\*Input Amprah\*\* | \*\*Input Pinjam &amp; RCV\*\*| \*\*Lapor Kerusakan\*\*| Stok Ruangan | - | Pengguna Portal |
| \*\*8. teknisi\*\* | PR Sparepart | - | Pinjam Alat Kerja| \*\*Eksekusi SPK &amp; Log\*\*| Ambil Part Gudang| Obyek Kelaikan | Pengguna Portal |
| \*\*9. manajemen\*\* | Monitor Serapan | Analisis Biaya | Valuasi Aktiva | Uptime Sarana | Valuasi Persediaan | Review CAPA | Monitoring Akses |
| \*\*10. auditor\*\* | Audit PO vs PR | Audit Mutasi SPPB| Audit Overdue | Audit Riwayat Servis| \*\*Audit Rekonsiliasi\*\*| \*\*Pengelola CAPA\*\* | Audit Log Trail |

---

## 5. Struktur Database &amp; Skema Kolom

### 5.1 Pembedaan Tabel Library vs Tabel Inti Aplikasi

\`\`\`
DATABASE POSTGRESQL (simlog\_v2)
│
├── \[TABEL FRAMEWORK &amp; THIRD-PARTY LIBS\]
│   ├── cache, cache\_locks                   --&gt; Laravel Cache
│   ├── jobs, job\_batches, failed\_jobs       --&gt; Laravel Queue Database Driver
│   ├── personal\_access\_tokens               --&gt; Laravel Sanctum
│   ├── roles, permissions                   --&gt; Spatie Laravel Permission
│   ├── model\_has\_roles, model\_has\_permissions
│   ├── role\_has\_permissions
│   └── activity\_log                         --&gt; Spatie ActivityLog
│
└── \[TABEL DOMAIN INTI APLIKASI (SIMLOG RS)\]
    ├── GRUP 1: PENGGUNA &amp; PERAN
    │   ├── pengguna                         --&gt; Akun pengguna aplikasi (model: User)
    │   ├── peran                            --&gt; Kamus master peran sistem
    │   └── pengguna\_peran                   --&gt; Pivot peran aktif &amp; binding unit/gudang
    │
    ├── GRUP 2: MASTER DATA
    │   ├── unit, gudang, lokasi\_gudang
    │   ├── kategori\_barang, satuan\_barang, merek\_barang
    │   ├── pemasok, barang, aset
    │
    ├── GRUP 3: PERSEDIAAN &amp; STOK
    │   ├── stok\_batch, pergerakan\_stok
    │   ├── stok\_opname, stok\_opname\_detail
    │   ├── penyesuaian\_stok, stok\_ruangan
    │
    ├── GRUP 4: PENGADAAN (PROCUREMENT)
    │   ├── anggaran\_pengadaan
    │   ├── permintaan\_pengadaan, permintaan\_pengadaan\_detail
    │   ├── pesanan\_pembelian, pesanan\_pembelian\_detail
    │   └── penerimaan\_barang, penerimaan\_barang\_detail
    │
    ├── GRUP 5: DISTRIBUSI RUANGAN
    │   ├── permintaan\_distribusi, permintaan\_distribusi\_detail
    │   ├── pengiriman\_barang, pengiriman\_barang\_detail
    │   └── penerimaan\_distribusi, penerimaan\_distribusi\_detail
    │
    ├── GRUP 6: PEMINJAMAN ASET NON-MEDIS
    │   ├── permintaan\_peminjaman, permintaan\_peminjaman\_detail
    │   └── pengembalian\_peminjaman, pengembalian\_peminjaman\_detail
    │
    ├── GRUP 7: PEMELIHARAAN SARANA (IPSRS)
    │   ├── permintaan\_maintenance, penugasan\_maintenance
    │   ├── log\_maintenance, suku\_cadang\_maintenance
    │   └── jadwal\_maintenance
    │
    └── GRUP 8: SISTEM, NOTIFIKASI &amp; AUDITING
        ├── notifikasi, log\_audit
        ├── temuan\_audit, pengaturan\_sistem
\`\`\`

---

### 5.2 Kamus Data Kolom Tabel Inti Aplikasi

#### Grup 1: Pengguna &amp; Peran
1. \*\*\`pengguna\`\*\* (Model: \`App\\Models\\User\`, Tabel Otentikasi Utama):
   - \`id\` (bigserial, PK)
   - \`unit\_id\` (bigint, FK \`unit.id\`, nullable): Unit dinas resmi pengguna.
   - \`gudang\_id\` (bigint, FK \`gudang.id\`, nullable): Gudang dinas resmi pengguna.
   - \`nama\` (varchar 255): Nama lengkap resmi pegawai.
   - \`nip\` (varchar 30, nullable): Nomor Induk Pegawai.
   - \`username\` (varchar 100, unique): Username login.
   - \`email\` (varchar 255, unique): Alamat email resmi.
   - \`password\` (varchar 255): Hash Bcrypt kata sandi.
   - \`no\_telepon\` (varchar 50, nullable): Kontak telepon/WA.
   - \`status\` (varchar 20, default \`'AKTIF'\`): \`AKTIF\`, \`NONAKTIF\`.
   - \`terakhir\_login\` (timestamp, nullable).
   - \`ip\_terakhir\` (varchar 45, nullable).
   - \`remember\_token\` (varchar 100, nullable).
   - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

2. \*\*\`peran\`\*\* (Model: \`App\\Models\\Peran\`):
   - \`id\` (bigserial, PK)
   - \`kode\` (varchar 50, unique): \`super\_admin\`, \`admin\_logistik\`, \`kepala\_gudang\`, \`petugas\_gudang\`, \`pengadaan\`, \`kepala\_unit\`, \`petugas\_unit\`, \`teknisi\`, \`manajemen\`, \`auditor\`.
   - \`nama\` (varchar 100): Nama tampilan resmi peran.
   - \`deskripsi\` (text, nullable).
   - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

3. \*\*\`pengguna\_peran\`\*\* (Model: \`App\\Models\\PenggunaPeran\`):
   - \`id\` (bigserial, PK)
   - \`pengguna\_id\` (bigint, FK \`pengguna.id\`, cascade on delete).
   - \`peran\_id\` (bigint, FK \`peran.id\`, cascade on delete).
   - \`unit\_id\` (bigint, FK \`unit.id\`, nullable).
   - \`gudang\_id\` (bigint, FK \`gudang.id\`, nullable).
   - \`dibuat\_pada\` (timestamp).
   - \*Constraint\*: \`UNIQUE(pengguna\_id, peran\_id, unit\_id, gudang\_id)\`.

---

#### Grup 2: Master Data
4. \*\*\`unit\`\*\* (Model: \`App\\Models\\Unit\`):
   - \`id\` (bigserial, PK)
   - \`kode\` (varchar 50, unique): Kode unit (misal: \`IGD\`, \`ICU\`, \`RAWAT\_1\`).
   - \`nama\` (varchar 150): Nama ruangan / instalasi.
   - \`jenis\` (varchar 50): \`PELAYANAN\_MEDIS\`, \`PENUNJANG\_MEDIS\`, \`ADMINISTRASI\`, \`OPERASIONAL\`.
   - \`penanggung\_jawab\` (varchar 150, nullable).
   - \`lokasi\_gedung\` (varchar 100, nullable).
   - \`status\` (varchar 20, default \`'AKTIF'\`).
   - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

5. \*\*\`gudang\`\*\* (Model: \`App\\Models\\Gudang\`):
   - \`id\` (bigserial, PK)
   - \`kode\` (varchar 50, unique): \`GUD\_SENTRAL\`, \`GUD\_IPSRS\`, \`GUD\_ATK\`, \`GUD\_B3\`.
   - \`nama\` (varchar 150): Nama fasilitas simpan.
   - \`jenis\` (varchar 50): \`SENTRAL\`, \`TRANSIT\`, \`UNIT\`, \`DEPO\`.
   - \`penanggung\_jawab\` (varchar 150, nullable).
   - \`lokasi\_gedung\` (varchar 100, nullable).
   - \`status\` (varchar 20, default \`'AKTIF'\`).
   - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

6. \*\*\`lokasi\_gudang\`\*\* (Model: \`App\\Models\\LokasiGudang\`):
   - \`id\` (bigserial, PK)
   - \`gudang\_id\` (bigint, FK \`gudang.id\`, restrict on delete).
   - \`kode\` (varchar 50): Kode bin / rak (misal: \`RAK-ATK-01\`).
   - \`nama\` (varchar 100): Nama atau nomor rak simpan.
   - \`baris\` (varchar 20, nullable).
   - \`kolom\` (varchar 20, nullable).
   - \`level\` (varchar 20, nullable).
   - \`status\` (varchar 20, default \`'AKTIF'\`).
   - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).
   - \*Constraint\*: \`UNIQUE(gudang\_id, kode)\`.

7. \*\*\`kategori\_barang\`\*\* (Model: \`App\\Models\\KategoriBarang\`):
   - \`id\` (bigserial, PK)
   - \`kode\` (varchar 50, unique): \`ATK\`, \`ART\`, \`CETAKAN\`, \`SPAREPART\`, \`ELEKTRONIK\`.
   - \`nama\` (varchar 100).
   - \`deskripsi\` (text, nullable).
   - \`status\` (varchar 20, default \`'AKTIF'\`).
   - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

8. \*\*\`satuan\_barang\`\*\* (Model: \`App\\Models\\SatuanBarang\`):
   - \`id\` (bigserial, PK)
   - \`kode\` (varchar 30, unique): \`PCS\`, \`BOX\`, \`RIM\`, \`PACK\`, \`UNIT\`, \`ROLL\`.
   - \`nama\` (varchar 50).
   - \`simbol\` (varchar 20, nullable).
   - \`status\` (varchar 20, default \`'AKTIF'\`).
   - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

9. \*\*\`merek\_barang\`\*\* (Model: \`App\\Models\\MerekBarang\`):
   - \`id\` (bigserial, PK)
   - \`kode\` (varchar 50, unique).
   - \`nama\` (varchar 100).
   - \`status\` (varchar 20, default \`'AKTIF'\`).
   - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

10. \*\*\`pemasok\`\*\* (Model: \`App\\Models\\Pemasok\`):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique).
    - \`nama\` (varchar 150): Nama perusahaan vendor.
    - \`npwp\` (varchar 50, nullable).
    - \`alamat\` (text, nullable).
    - \`kota\` (varchar 100, nullable).
    - \`no\_telepon\` (varchar 50, nullable).
    - \`email\` (varchar 100, nullable).
    - \`nama\_kontak\` (varchar 100, nullable): PIC Vendor.
    - \`status\` (varchar 20, default \`'AKTIF'\`).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

11. \*\*\`barang\`\*\* (Model: \`App\\Models\\Barang\`):
    - \`id\` (bigserial, PK)
    - \`kategori\_id\` (bigint, FK \`kategori\_barang.id\`).
    - \`satuan\_id\` (bigint, FK \`satuan\_barang.id\`).
    - \`merek\_id\` (bigint, FK \`merek\_barang.id\`, nullable).
    - \`pemasok\_default\_id\` (bigint, FK \`pemasok.id\`, nullable).
    - \`kode\` (varchar 50, unique).
    - \`nama\` (varchar 255).
    - \`tipe\` (varchar 30, default \`'HABIS\_PAKAI'\`): \`HABIS\_PAKAI\`, \`ASET\`, \`SPAREPART\`.
    - \`stok\_minimum\` (integer, default 0).
    - \`stok\_maksimum\` (integer, default 0).
    - \`titik\_pesan\` (integer, default 0): Reorder Point (ROP).
    - \`harga\_beli\_terakhir\` (decimal 15,2, default 0).
    - \`harga\_estimasi\` (decimal 15,2, default 0).
    - `status` (varchar 20, default `'AKTIF'`).
    - `dibuat_pada`, `diperbarui_pada` (timestamp).
    - *PostgreSQL Trigram Index*: Ekstensi `pg_trgm` aktif dan GIN index `idx_barang_nama_trgm` pada kolom `nama` (`USING gin (nama gin_trgm_ops)`) untuk pencarian fuzzy/substring instan pada volume puluhan ribu barang.

12. \*\*\`aset\`\*\* (Model: \`App\\Models\\Aset\`):
    - \`id\` (bigserial, PK)
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`unit\_id\` (bigint, FK \`unit.id\`, nullable).
    - \`gudang\_id\` (bigint, FK \`gudang.id\`, nullable).
    - \`lokasi\_gudang\_id\` (bigint, FK \`lokasi\_gudang.id\`, nullable).
    - \`kode\_aset\` (varchar 50, unique): Kode register unik per unit fisik.
    - \`nomor\_seri\` (varchar 100, nullable).
    - \`tanggal\_perolehan\` (date).
    - \`nilai\_perolehan\` (decimal 15,2, default 0).
    - \`kondisi\` (varchar 30, default \`'BAIK'\`): \`BAIK\`, \`RUSAK\_RINGAN\`, \`RUSAK\_BERAT\`.
    - \`status\` (varchar 30, default \`'TERSEDIA'\`): \`TERSEDIA\`, \`DIGUNAKAN\`, \`DIPINJAM\`, \`DALAM\_MAINTENANCE\`, \`DIHAPUSKAN\`.
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).
    - \*PostgreSQL Constraint\*: \`chk\_aset\_lokasi\_xor CHECK ((unit\_id IS NOT NULL AND gudang\_id IS NULL) OR (unit\_id IS NULL AND gudang\_id IS NOT NULL))\`.

---

#### Grup 3: Persediaan &amp; Stok
13. \*\*\`stok\_batch\`\*\* (Model: \`App\\Models\\StokBatch\`, Single Source of Truth Persediaan):
    - \`id\` (bigserial, PK)
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`gudang\_id\` (bigint, FK \`gudang.id\`).
    - \`lokasi\_gudang\_id\` (bigint, FK \`lokasi\_gudang.id\`).
    - \`nomor\_batch\` (varchar 100).
    - \`tanggal\_kedaluwarsa\` (date, nullable, index): Penentu prioritas alokasi FEFO.
    - \`jumlah\` (integer, default 0): Saldo fisik on-hand aktual.
    - \`jumlah\_dipesan\` (integer, default 0): Saldo reserved untuk picking distribusi.
    - \`harga\_satuan\` (decimal 15,2, default 0).
    - `status` (varchar 30, default `'AKTIF'`): `AKTIF`, `HAMPIR_KEDALUWARSA`, `KEDALUWARSA`, `HABIS`.
    - `dibuat_pada`, `diperbarui_pada` (timestamp).
    - *PostgreSQL Constraint*: `chk_stok_batch_non_negative CHECK (jumlah >= 0 AND jumlah_dipesan >= 0)`.
    - *Database Composite Index*: `INDEX(barang_id, status, tanggal_kedaluwarsa)` untuk optimasi query picking FEFO dan cron sweep expired.
14. \*\*\`pergerakan\_stok\`\*\* (Model: \`App\\Models\\PergerakanStok\`, Buku Mutasi / Kartu Stok):
    - \`id\` (bigserial, PK)
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`stok\_batch\_id\` (bigint, FK \`stok\_batch.id\`).
    - \`gudang\_id\` (bigint, FK \`gudang.id\`).
    - \`lokasi\_gudang\_id\` (bigint, FK \`lokasi\_gudang.id\`, nullable).
    - \`jenis\` (varchar 30): \`MASUK\`, \`KELUAR\`, \`PENYESUAIAN\`, \`TRANSFER\`.
    - \`jumlah\` (integer): Kuantitas mutasi (positif atau negatif).
    - \`saldo\_sebelum\` (integer).
    - \`saldo\_sesudah\` (integer).
    - \`referensi\_jenis\` (varchar 50): \`PENERIMAAN\_PO\`, \`PENGIRIMAN\_DISTRIBUSI\`, \`STOCK\_OPNAME\`, \`MAINTENANCE\`.
    - \`referensi\_id\` (bigint).
    - \`keterangan\` (text, nullable).
    - \`dibuat\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\` (timestamp).

15. \*\*\`stok\_opname\`\*\* (Model: \`App\\Models\\StokOpname\`):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique).
    - \`gudang\_id\` (bigint, FK \`gudang.id\`).
    - \`tanggal\_opname\` (date).
    - \`status\` (varchar 30, default \`'DRAFT'\`): \`DRAFT\`, \`BERJALAN\`, \`MENUNGGU\_APPROVAL\`, \`DISETUJUI\`, \`DITOLAK\`.
    - \`dibuat\_oleh\`, \`disetujui\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

16. \*\*\`stok\_opname\_detail\`\*\* (Model: \`App\\Models\\StokOpnameDetail\`):
    - \`id\` (bigserial, PK)
    - \`stok\_opname\_id\` (bigint, FK \`stok\_opname.id\`, cascade).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`stok\_batch\_id\` (bigint, FK \`stok\_batch.id\`).
    - \`jumlah\_sistem\` (integer).
    - \`jumlah\_fisik\` (integer).
    - \`selisih\` (integer): \`jumlah\_fisik - jumlah\_sistem\`.
    - \`catatan\` (text, nullable).

17. \*\*\`penyesuaian\_stok\`\*\* (Model: \`App\\Models\\PenyesuaianStok\`):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique).
    - \`stok\_opname\_id\` (bigint, FK \`stok\_opname.id\`, nullable).
    - \`gudang\_id\` (bigint, FK \`gudang.id\`).
    - \`tanggal\` (date).
    - \`alasan\` (text).
    - \`status\` (varchar 30, default \`'MENUNGGU'\`): \`MENUNGGU\`, \`DISETUJUI\`, \`DITOLAK\`.
    - \`diajukan\_oleh\`, \`disetujui\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

18. \*\*\`stok\_ruangan\`\*\* (Model: \`App\\Models\\StokRuangan\`):
    - \`id\` (bigserial, PK)
    - \`unit\_id\` (bigint, FK \`unit.id\`).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`jumlah\` (integer, default 0).
    - \`stok\_minimum\`, \`stok\_maksimum\` (integer, default 0).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

---

#### Grup 4: Pengadaan Logistik
19. \*\*\`anggaran\_pengadaan\`\*\* (Model: \`App\\Models\\AnggaranPengadaan\`):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): Kode pos anggaran.
    - \`pos\_anggaran\` (varchar 200): Nama pos belanja (misal: \*Belanja Operasional ATK 2026\*).
    - \`pagu\` (decimal 15,2, default 0): Pagu maksimal tahunan.
    - \`realisasi\` (decimal 15,2, default 0): Akumulasi nilai PO diterbitkan.
    - \`sisa\` (decimal 15,2, default 0): Sisa pagu tersedia.
    - \`serapan\` (decimal 5,2, default 0): Persentase serapan (%).
    - \`tahun\` (varchar 10, default \`'2026'\`).
    - \`status\` (varchar 50, default \`'Normal'\`).
    - \`po\_terkait\` (json, nullable).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

20. \*\*\`permintaan\_pengadaan\`\*\* (Model: \`App\\Models\\PermintaanPengadaan\`, PR):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): \`PR-YYYY-XXX\`.
    - \`unit\_id\` (bigint, FK \`unit.id\`).
    - \`diajukan\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`tanggal\_permintaan\` (date).
    - \`urgensi\` (varchar 20, default \`'REGULER'\`): \`REGULER\`, \`MENDESAK\`, \`CITO\`.
    - \`status\` (varchar 30, default \`'DIAJUKAN'\`): \`DIAJUKAN\`, \`DISETUJUI\`, \`DITERUSKAN\_KE\_PENGADAAN\`, \`DITOLAK\`.
    - \`catatan\` (text, nullable).
    - \`disetujui\_oleh\` (bigint, FK \`pengguna.id\`, nullable).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

21. \*\*\`permintaan\_pengadaan\_detail\`\*\* (Model: \`App\\Models\\PermintaanPengadaanDetail\`):
    - \`id\` (bigserial, PK)
    - \`permintaan\_pengadaan\_id\` (bigint, FK \`permintaan\_pengadaan.id\`, cascade).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`jumlah\` (integer).
    - \`jumlah\_disetujui\` (integer, nullable).
    - \`catatan\` (text, nullable).

22. \*\*\`pesanan\_pembelian\`\*\* (Model: \`App\\Models\\PesananPembelian\`, PO):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): \`PO-YYYY-XXX\`.
    - \`pemasok\_id\` (bigint, FK \`pemasok.id\`).
    - \`permintaan\_pengadaan\_id\` (bigint, FK \`permintaan\_pengadaan.id\`, nullable).
    - \`tanggal\_po\` (date).
    - \`estimasi\_tanggal\_kirim\` (date, nullable).
    - \`total\_biaya\` (decimal 15,2, default 0).
    - \`syarat\_pembayaran\` (varchar 50, default \`'TEMPO\_30\_HARI'\`).
    - \`status\` (varchar 30, default \`'DITERBITKAN'\`): \`DITERBITKAN\`, \`DIKIRIM\_VENDOR\`, \`DITERIMA\_SEBAGIAN\`, \`DITERIMA\_LENGKAP\`, \`DIBATALKAN\`.
    - \`catatan\` (text, nullable).
    - \`dibuat\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

23. \*\*\`pesanan\_pembelian\_detail\`\*\* (Model: \`App\\Models\\PesananPembelianDetail\`):
    - \`id\` (bigserial, PK)
    - \`pesanan\_pembelian\_id\` (bigint, FK \`pesanan\_pembelian.id\`, cascade).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`jumlah\_dipesan\` (integer).
    - \`jumlah\_diterima\` (integer, default 0).
    - \`harga\_satuan\` (decimal 15,2).
    - \`subtotal\` (decimal 15,2).

24. \*\*\`penerimaan\_barang\`\*\* (Model: \`App\\Models\\PenerimaanBarang\`, Inbound Gudang):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique).
    - \`pesanan\_pembelian\_id\` (bigint, FK \`pesanan\_pembelian.id\`).
    - \`gudang\_id\` (bigint, FK \`gudang.id\`).
    - \`nomor\_surat\_jalan\` (varchar 100).
    - \`tanggal\_terima\` (date).
    - \`status\` (varchar 30, default \`'LENGKAP'\`): \`LENGKAP\`, \`SEBAGIAN\`, \`DITOLAK\`.
    - \`catatan\` (text, nullable).
    - \`diterima\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

25. \*\*\`penerimaan\_barang\_detail\`\*\* (Model: \`App\\Models\\PenerimaanBarangDetail\`):
    - \`id\` (bigserial, PK)
    - \`penerimaan\_barang\_id\` (bigint, FK \`penerimaan\_barang.id\`, cascade).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`lokasi\_gudang\_id\` (bigint, FK \`lokasi\_gudang.id\`).
    - \`nomor\_batch\` (varchar 100).
    - \`tanggal\_kedaluwarsa\` (date, nullable).
    - \`jumlah\_diterima\` (integer).
    - \`jumlah\_rusak\` (integer, default 0).
    - \`harga\_satuan\` (decimal 15,2).

---

#### Grup 5: Distribusi Ruangan (Amprah)
26. \*\*\`permintaan\_distribusi\`\*\* (Model: \`App\\Models\\PermintaanDistribusi\`):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): \`REQ-YYYYMM-XXX\`.
    - \`unit\_id\` (bigint, FK \`unit.id\`): Ruangan pemohon.
    - \`gudang\_id\` (bigint, FK \`gudang.id\`): Gudang tujuan amprah.
    - \`tanggal\_permintaan\` (date).
    - \`urgensi\` (varchar 20, default \`'REGULER'\`): \`REGULER\`, \`CITO\`.
    - \`status\` (varchar 30, default \`'DIAJUKAN'\`): \`DIAJUKAN\`, \`DISETUJUI\_KARU\`, \`DISETUJUI\_GUDANG\`, \`SEDANG\_DIKIRIM\`, \`SELESAI\`, \`DITOLAK\`.
    - \`diajukan\_oleh\`, \`disetujui\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

27. \*\*\`permintaan\_distribusi\_detail\`\*\* (Model: \`App\\Models\\PermintaanDistribusiDetail\`):
    - \`id\` (bigserial, PK)
    - \`permintaan\_distribusi\_id\` (bigint, FK \`permintaan\_distribusi.id\`, cascade).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`jumlah\` (integer): Kuantitas diminta unit.
    - \`jumlah\_disetujui\` (integer, nullable): Kuantitas disetujui gudang.

28. \*\*\`pengiriman\_barang\`\*\* (Model: \`App\\Models\\PengirimanBarang\`, SPPB):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): \`SPPB-YYYYMMDD-XXXX\`.
    - \`permintaan\_distribusi\_id\` (bigint, FK \`permintaan\_distribusi.id\`).
    - \`gudang\_id\` (bigint, FK \`gudang.id\`).
    - \`unit\_id\` (bigint, FK \`unit.id\`).
    - \`tanggal\_kirim\` (date).
    - \`status\` (varchar 30, default \`'DIKIRIM'\`): \`DIKIRIM\`, \`DITERIMA\`, \`BATAL\`.
    - \`dikirim\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\` (timestamp).

29. \*\*\`pengiriman\_barang\_detail\`\*\* (Model: \`App\\Models\\PengirimanBarangDetail\`):
    - \`id\` (bigserial, PK)
    - \`pengiriman\_barang\_id\` (bigint, FK \`pengiriman\_barang.id\`, cascade).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`stok\_batch\_id\` (bigint, FK \`stok\_batch.id\`): Batch FEFO yang dialokasikan.
    - \`jumlah\_dikirim\` (integer).

30. \*\*\`penerimaan\_distribusi\`\*\* (Model: \`App\\Models\\PenerimaanDistribusi\`):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): \`RCV-YYYYMM-XXX\`.
    - \`pengiriman\_barang\_id\` (bigint, FK \`pengiriman\_barang.id\`).
    - \`unit\_id\` (bigint, FK \`unit.id\`).
    - \`tanggal\_terima\` (date).
    - \`status\` (varchar 30, default \`'LENGKAP'\`): \`LENGKAP\`, \`SEBAGIAN\`, \`SELISIH\`.
    - \`diterima\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\` (timestamp).

31. \*\*\`penerimaan\_distribusi\_detail\`\*\* (Model: \`App\\Models\\PenerimaanDistribusiDetail\`):
    - \`id\` (bigserial, PK)
    - \`penerimaan\_distribusi\_id\` (bigint, FK \`penerimaan\_distribusi.id\`, cascade).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`stok\_batch\_id\` (bigint, FK \`stok\_batch.id\`).
    - \`jumlah\_diterima\` (integer).
    - \`jumlah\_rusak\` (integer, default 0).

---

#### Grup 6: Peminjaman Aset Non-Medis
32. \*\*\`permintaan\_peminjaman\`\*\* (Model: \`App\\Models\\PermintaanPeminjaman\`):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): \`PINJ-YYYY-XXX\`.
    - \`unit\_id\` (bigint, FK \`unit.id\`): Unit peminjam.
    - \`diajukan\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`tanggal\_pinjam\` (date).
    - \`tanggal\_kembali\_rencana\` (date).
    - \`status\` (varchar 30, default \`'DIAJUKAN'\`): \`DIAJUKAN\`, \`DISETUJUI\`, \`DIPINJAM\`, \`SELESAI\`, \`DITOLAK\`.
    - \`disetujui\_oleh\` (bigint, FK \`pengguna.id\`, nullable).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

33. \*\*\`permintaan\_peminjaman\_detail\`\*\* (Model: \`App\\Models\\PermintaanPeminjamanDetail\`):
    - \`id\` (bigserial, PK)
    - \`permintaan\_peminjaman\_id\` (bigint, FK \`permintaan\_peminjaman.id\`, cascade).
    - \`aset\_id\` (bigint, FK \`aset.id\`).
    - \`kondisi\_saat\_pinjam\` (varchar 30, default \`'BAIK'\`).

34. \*\*\`pengembalian\_peminjaman\`\*\* (Model: \`App\\Models\\PengembalianPeminjaman\`):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique, nullable).
    - \`permintaan\_peminjaman\_id\` (bigint, FK \`permintaan\_peminjaman.id\`).
    - \`tanggal\_kembali\_aktual\` (date).
    - \`diperiksa\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\` (timestamp).

35. \*\*\`pengembalian\_peminjaman\_detail\`\*\* (Model: \`App\\Models\\PengembalianPeminjamanDetail\`):
    - \`id\` (bigserial, PK)
    - \`pengembalian\_peminjaman\_id\` (bigint, FK \`pengembalian\_peminjaman.id\`, cascade).
    - \`permintaan\_peminjaman\_detail\_id\` (bigint, FK \`permintaan\_peminjaman\_detail.id\`).
    - \`kondisi\_saat\_kembali\` (varchar 30, default \`'BAIK'\`): \`BAIK\`, \`RUSAK\_RINGAN\`, \`RUSAK\_BERAT\`.
    - \`catatan\` (text, nullable).

---

#### Grup 7: Pemeliharaan Sarana (IPSRS)
36. \*\*\`permintaan\_maintenance\`\*\* (Model: \`App\\Models\\PermintaanMaintenance\`, Tiket Servis):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): \`MNT-YYYYMM-XXX\`.
    - \`aset\_id\` (bigint, FK \`aset.id\`).
    - \`unit\_id\` (bigint, FK \`unit.id\`).
    - \`pengembalian\_peminjaman\_detail\_id\` (bigint, FK \`pengembalian\_peminjaman\_detail.id\`, nullable).
    - \`dilaporkan\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`deskripsi\_masalah\` (text).
    - \`prioritas\` (varchar 20, default \`'SEDANG'\`): \`RENDAH\`, \`SEDANG\`, \`TINGGI\`, \`CITO\`.
    - \`status\` (varchar 30, default \`'DILAPORKAN'\`): \`DILAPORKAN\`, \`DITUGASKAN\`, \`SEDANG\_DIKERJAKAN\`, \`MENUNGGU\_SPAREPART\`, \`UJI\_FUNGSI\`, \`SELESAI\`, \`DIBATALKAN\`.
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

37. \*\*\`penugasan\_maintenance\`\*\* (Model: \`App\\Models\\PenugasanMaintenance\`, SPK):
    - \`id\` (bigserial, PK)
    - \`permintaan\_maintenance\_id\` (bigint, FK \`permintaan\_maintenance.id\`, cascade).
    - \`teknisi\_id\` (bigint, FK \`pengguna.id\`): Teknisi penanggung jawab.
    - \`ditugaskan\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`tanggal\_penugasan\` (date).
    - \`status\` (varchar 30, default \`'DITUGASKAN'\`): \`DITUGASKAN\`, \`DITERIMA\`, \`SELESAI\`.
    - \`dibuat\_pada\` (timestamp).

38. \*\*\`log\_maintenance\`\*\* (Model: \`App\\Models\\LogMaintenance\`, Lembar Kerja):
    - \`id\` (bigserial, PK)
    - \`permintaan\_maintenance\_id\` (bigint, FK \`permintaan\_maintenance.id\`, cascade).
    - \`dikerjakan\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`tanggal\_pengerjaan\` (date).
    - \`deskripsi\_pekerjaan\` (text).
    - \`hasil\` (varchar 30, default \`'BERHASIL'\`): \`BERHASIL\`, \`PENDING\`, \`GAGAL\`.
    - \`dibuat\_pada\` (timestamp).

39. \*\*\`suku\_cadang\_maintenance\`\*\* (Model: \`App\\Models\\SukuCadangMaintenance\`):
    - \`id\` (bigserial, PK)
    - \`permintaan\_maintenance\_id\` (bigint, FK \`permintaan\_maintenance.id\`, cascade).
    - \`barang\_id\` (bigint, FK \`barang.id\`).
    - \`stok\_batch\_id\` (bigint, FK \`stok\_batch.id\`).
    - \`jumlah\_digunakan\` (integer).
    - \`pergerakan\_stok\_id\` (bigint, FK \`pergerakan\_stok.id\`, nullable).
    - \`dicatat\_oleh\` (bigint, FK \`pengguna.id\`).
    - \`dibuat\_pada\` (timestamp).

40. \*\*\`jadwal\_maintenance\`\*\* (Model: \`App\\Models\\JadwalMaintenance\`, Servis Preventif):
    - \`id\` (bigserial, PK)
    - \`aset\_id\` (bigint, FK \`aset.id\`, cascade).
    - \`frekuensi\` (varchar 30): \`MINGGUAN\`, \`BULANAN\`, \`TRIWULAN\`, \`SEMESTER\`, \`TAHUNAN\`.
    - \`jenis\_maintenance\` (varchar 50, default \`'PREVENTIF'\`).
    - \`tanggal\_terakhir\` (date, nullable).
    - \`tanggal\_berikutnya\` (date).
    - \`status\` (varchar 20, default \`'AKTIF'\`): \`AKTIF\`, \`NONAKTIF\`.
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

---

#### Grup 8: Sistem, Notifikasi &amp; Tata Kelola
41. \*\*\`notifikasi\`\*\* (Model: \`App\\Models\\Notifikasi\`):
    - \`id\` (bigserial, PK)
    - \`pengguna\_id\` (bigint, FK \`pengguna.id\`, cascade).
    - \`judul\` (varchar 200).
    - \`pesan\` (text).
    - \`jenis\` (varchar 30, default \`'INFO'\`): \`INFO\`, \`PERINGATAN\`, \`URGENT\`, \`SUKSES\`.
    - \`referensi\_jenis\` (varchar 50, nullable).
    - \`referensi\_id\` (unsignedBigInteger, nullable).
    - \`sudah\_dibaca\` (boolean, default \`false\`).
    - \`dibuat\_pada\` (timestamp).

42. \*\*\`log\_audit\`\*\* (Model: \`App\\Models\\LogAudit\`, Audit Trail Tunggal):
    - \`id\` (bigserial, PK)
    - \`pengguna\_id\` (bigint, FK \`pengguna.id\`, nullable).
    - \`peran\_id\` (bigint, FK \`peran.id\`, nullable).
    - \`referensi\_jenis\` (varchar 50): Nama tabel / entitas (misal: \`barang\`, \`pesanan\_pembelian\`).
    - \`referensi\_id\` (unsignedBigInteger, nullable).
    - \`aksi\` (varchar 50): \`TAMBAH\`, \`UBAH\`, \`HAPUS\`, \`APPROVE\`, \`REJECT\`, \`ADJUSTMENT\`.
    - \`data\_sebelum\` (jsonb, nullable): Snapshot baris data sebelum mutasi.
    - \`data\_sesudah\` (jsonb, nullable): Snapshot baris data sesudah mutasi.
    - \`ip\_address\` (varchar 45, nullable).
    - \`device\` (varchar 255, nullable).
    - \`dibuat\_pada\` (timestamp).

43. \*\*\`temuan\_audit\`\*\* (Model: \`App\\Models\\TemuanAudit\`, CAPA):
    - \`id\` (bigserial, PK)
    - \`kode\` (varchar 50, unique): \`AUD-YYYYMM-XXX\`.
    - \`tanggal\_audit\` (date).
    - \`objek\_audit\` (varchar 255): Unit / proses yang diperiksa.
    - \`ringkasan\` (text): Uraian ketidaksesuaian prosedur.
    - \`tingkat\_risiko\` (varchar 50): \`Mayor\`, \`Moderat\`, \`Minor\`.
    - \`due\_date\_capa\` (date): Batas waktu penyelesaian tindakan perbaikan.
    - \`status\_capa\` (varchar 50, default \`'In Progress'\`): \`In Progress\`, \`In Review\`, \`Closed\`.
    - \`created\_at\`, \`updated\_at\` (timestamp).

44. \*\*\`pengaturan\_sistem\`\*\* (Model: \`App\\Models\\PengaturanSistem\`):
    - \`id\` (bigserial, PK)
    - \`kunci\` (varchar 100, unique): Kunci konfigurasi (misal: \`max\_loan\_duration\_days\`, \`auto\_fefo\_enabled\`).
    - \`nilai\` (text, nullable): Nilai konfigurasi.
    - \`tipe\` (varchar 30, default \`'string'\`): \`string\`, \`integer\`, \`float\`, \`boolean\`, \`json\`.
    - \`grup\` (varchar 50, default \`'UMUM'\`): \`PENGADAAN\`, \`PERSEDIAAN\`, \`KEAMANAN\`, \`DOKUMEN\`, \`UMUM\`.
    - \`deskripsi\` (varchar 255, nullable).
    - \`diperbarui\_oleh\` (bigint, FK \`pengguna.id\`, nullable).
    - \`dibuat\_pada\`, \`diperbarui\_pada\` (timestamp).

---

## 6. Reusable Content, Architecture Patterns &amp; Services

### 6.1 Dynamic Page-Centric RBAC Engine (\`PagePermissionRegistry\`)
- \*\*Lokasi\*\*: \`app/Support/Rbac/PagePermissionRegistry.php\`
- \*\*Konsep\*\*: Memetakan seluruh modul sistem (58 modul) ke dalam 7 domain fungsional terpusat:
  1. \`dashboard\_portal\`: 10 Dashboard Portal (\`page\_Dashboard\`, \`page\_AdminLogistikDashboard\`, \`page\_KepalaGudangDashboard\`, \`page\_GudangDashboard\`, \`page\_PengadaanDashboard\`, \`page\_KepalaUnitDashboard\`, \`page\_UnitDashboard\`, \`page\_TeknisiDashboard\`, \`page\_ManajemenDashboard\`, \`page\_AuditorDashboard\`)
  2. \`master\`: Master Data Logistik (Barang, Unit, Kategori, Satuan, Merek, Pemasok, Gudang, Lokasi)
  3. \`layanan\_unit\`: Layanan &amp; Permintaan Ruangan (Permintaan Barang, Peminjaman Aset Ruangan, Lapor Servis, Stok Ruangan, Penerimaan Barang, Approval Karu)
  4. \`gudang\_stok\`: Operasional Gudang &amp; Persediaan (Stok Batch, Buku Mutasi, Monitoring FEFO, Stock Opname, Otorisasi Penyesuaian, Tata Letak Rak, Picking Distribusi, Penerimaan PO)
  5. \`pengadaan\`: Pengadaan &amp; Rekanan Vendor (Permintaan Pengadaan, Pesanan Pembelian, Tracking PO, Master Rekanan, Realisasi Anggaran)
  6. \`pemeliharaan\`: Pemeliharaan Fasilitas &amp; IPSRS (SPK Perbaikan, Lembar Kerja, Pemeliharaan Berkala, Peminjaman Alat Kerja, Permintaan Sparepart, Rekam Medis Aset)
  7. \`audit\_manajemen\`: Tata Kelola, Audit &amp; Diagnostik Sistem (Audit Trail, Selisih Opname, Kepatuhan Pengadaan, Mutasi Distribusi, Temuan CAPA, Manajemen Pengguna, Parameter Sistem, Kesehatan Sistem, Schedulers &amp; Notifikasi)
- \*\*Fungsi Utama\*\*:
  - \`toPermissions(array $state)\`: Mengonversi checklist toggle halaman dan centang CRUD menjadi daftar nama permission Spatie (\`page\_{PageName}\`, \`view\_any\_{entity}\`, \`create\_{entity}\`, dll.).
  - \`fromRole(Role $role)\`: Membaca permission database peran dan memetakannya kembali ke format array UI saat form diedit.
- **Form Matrix RBAC**: Diterapkan pada Livewire Component `RoleManager` dengan UI tabbed 5 kolom (Akses Halaman, Lihat, Buat, Ubah, Hapus) memanfaatkan `<flux:tabs>` dan `<flux:checkbox>`.

### 6.2 Context Manager (\`ContextManager\`)
- \*\*Lokasi\*\*: \`app/Support/Auth/ContextManager.php\`
- \*\*Tujuan\*\*: Memungkinkan Super Administrator mengemulasi wewenang gudang atau unit tertentu secara dinamis melalui session, tanpa mengubah data pengguna di database.
- \*\*Fungsi\*\*:
  - \`setGudangId(?int $id)\`: Membatasi kueri sistem hanya ke gudang yang dipilih.
  - \`setUnitId(?int $id)\`: Membatasi kueri sistem hanya ke unit yang dipilih.
  - \`resetGlobalContext()\`: Mengembalikan sudut pandang ke mode global seluruh rumah sakit.
  - \`scopeUnit(Builder $query)\` / \`scopeGudang(Builder $query)\`: Helper scope otomatis pada model query.

### 6.3 Domain Services
1. **`DistribusiService`** (`app/Services/Distribusi/DistribusiService.php`):
   - `eksekusiPickingFefo()`: Menghitung alokasi batch stok secara otomatis berdasarkan urutan `tanggal_kedaluwarsa ASC` (FEFO) dengan **pessimistic locking** (`lockForUpdate()`) di dalam `DB::transaction()`. Mengurangi kolom `jumlah` pada `stok_batch` dan mencatat mutasi `KELUAR` pada `pergerakan_stok`.
   - Menerbitkan nomor SPPB unik secara otomatis (`SPPB-YYYYMMDD-XXXX`).
2. \*\*\`PeminjamanAsetService\`\*\* (\`app/Services/Aset/PeminjamanAsetService.php\`):
   - \`serahTerimaFisik()\`: Mengubah status aset menjadi \`DIPINJAM\` dan memindahkan lokasi aset ke unit peminjam.
   - \`prosesPengembalian()\`: Memeriksa fisik aset saat kembali. Jika rusak, otomatis menerbitkan tiket \`PermintaanMaintenance\` CITO/Sedang ke IPSRS.
3. **`NotifikasiService`** (`app/Services/NotifikasiService.php`):
   - Enqueue notifikasi sistem internal ke tabel `notifikasi` untuk disajikan pada header lonceng Flux UI (`<flux:dropdown>`).
4. **`CodeGenerator`** (`app/Support/Generator/CodeGenerator.php`):
   - Layanan generator nomor dokumen resmi terpusat untuk mencegah *number collision*:
     - `PR-YYYY-XXX` (Permintaan Pengadaan)
     - `PO-YYYY-XXX` (Pesanan Pembelian)
     - `SPPB-YYYYMMDD-XXXX` (Pengiriman Distribusi / Surat Jalan)
     - `RCV-YYYYMM-XXX` (Penerimaan Distribusi Ruangan)
     - `PINJ-YYYY-XXX` (Peminjaman Aset)
     - `MNT-YYYYMM-XXX` (Permintaan Maintenance IPSRS)
     - `AUD-YYYYMM-XXX` (Temuan Audit CAPA SPI)
### 6.4 Traits & Pola Reusable
- **`HasTableFilters`** (`app/Livewire/Concerns/HasTableFilters.php`): Menggabungkan `WithPagination`, properti `#[Url] public string $search = ''`, dan otomatis memanggil `$this->resetPage()` saat pencarian diperbarui.
- **`HasToast`** (`app/Livewire/Concerns/HasToast.php`): Helper dispatch notifikasi instan ke komponen `<flux:toast />` (`$this->toast($message, $variant)`).
- **`HasPortalLayout`** (`app/Livewire/Concerns/HasPortalLayout.php`): Mendeteksi segmen URL portal aktif secara otomatis untuk binding layout.
- **`#[Url]`** (`Livewire\Attributes\Url`): Sinkronisasi tab aktif, search term, dan filter ke query parameter URL browser.
- **`HasAuditLog`** (`app/Traits/HasAuditLog.php`): Observer model otomatis yang menangkap event `created`, `updated`, `deleted` dan menyimpannya ke tabel `log_audit` dengan merekam diff atribut yang berubah (`$model->getDirty()`) serta menyembunyikan kolom sensitif/noise (`password`, `remember_token`, `diperbarui_pada`).
- **`Double-Submit Protection`**: Seluruh tombol aksi/submit transaksi wajib memasang atribut `wire:loading.attr="disabled"` untuk mencegah duplikasi data transaksi akibat double-click.
- **`Offline Connectivity Detection`**: Layout master portal memasang banner `<div wire:offline>` untuk memperingatkan pengguna saat koneksi jaringan terputus di area gudang sentral/basement.
- **`Strict Deactivation (Master Integrity)`**: Master data (barang, unit, gudang) yang memiliki relasi data historis dilarang di-hard delete; penonaktifan dilakukan dengan mengubah kolom `status = 'NONAKTIF'`.

### 6.5 Komponen Blade Reusable (UI Helpers)
1. **`<x-status-badge :status="$status" />`** (`resources/views/components/status-badge.blade.php`):
   - Pemetaan warna otomatis untuk seluruh status transaksi (`green`, `amber`, `red`, `blue`, `zinc`).
2. **`<x-rupiah :value="$nominal" />`** (`resources/views/components/rupiah.blade.php`):
   - Format mata uang Rupiah dengan font monospaced tabular-nums rata kanan via `Illuminate\Support\Number::currency()`.
3. **`<x-empty-state :message="$pesan" :cols="$jumlahKolom" />`** (`resources/views/components/empty-state.blade.php`):
   - Tampilan baris kosong tabel seragam dengan ikon inbox dan teks informatif.
4. **`<x-urgency-badge :urgency="$urgensi" />`** (`resources/views/components/urgency-badge.blade.php`):
   - Badge urgensi CITO / Mendesak dengan animasi visual `animate-pulse` untuk prioritas tinggi.
5. **`<x-context-banner />`** (`resources/views/components/context-banner.blade.php`):
   - Banner indikator emulasi wewenang Super Admin aktif beserta tombol cepat kembali ke mode global.

### 6.6 Console Scheduled Tasks (`routes/console.php`)
1. `simlog:sweep-expired-batches` (Harian 00:01 WIB): Mengunci batch yang telah melewati tanggal kedaluwarsa menjadi status `KEDALUWARSA` dan memberi label `HAMPIR_KEDALUWARSA` untuk batch <90 hari.
2. `simlog:alert-overdue-loans` (Harian 06:00 WIB): Memindai transaksi peminjaman aset yang melewati `tanggal_kembali_rencana` dan mengirimkan alert keterlambatan.
3. `simlog:generate-preventive-maintenance` (Harian 01:00 WIB): Memindai `jadwal_maintenance` preventif aktif dan otomatis menerbitkan tiket servis IPSRS jika tanggal berikutnya telah jatuh tempo.
---

## 7. Standar Arsitektur Teknis Laravel 13, Livewire 3 & Flux UI

### 7.1 Standar Komponen UI Flux UI (Free Edition)
Seluruh antarmuka pengguna dibangun menggunakan komponen deklaratif Flux UI Free:
1. **Layout Shell**:
   - `<flux:sidebar>`: Navigasi utama portal dengan item `<flux:navitem>` yang dilindungi `@can('page_...')`.
   - `<flux:header>`: Header portal dengan breadcrumb, indikator emulasi, dan lonceng notifikasi `<flux:dropdown>`.
2. **Tabel Data Reaktif**:
   - Menggunakan `<flux:table>`, `<flux:table.columns>`, `<flux:table.row>`, `<flux:table.cell>`, dan `<flux:pagination>`.
   - Filter pencarian terikat langsung via `wire:model.live.debounce.300ms="search"`.
3. **Formulir & Modal Interaktif**:
   - Menggunakan `<flux:modal name="modal-form">`, `<flux:input>`, `<flux:select>`, `<flux:textarea>`, `<flux:checkbox>`, dan `<flux:button>`.

### 7.2 Standar Komponen Livewire 3
Setiap modul operasional diimplementasikan sebagai Livewire Component:
```php
namespace App\Livewire\Master;

use App\Models\Barang;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

class BarangIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::barang'), 403);

        return view('livewire.master.barang-index', [
            'items' => Barang::query()
                ->when($this->search, fn ($q) => $q->where('nama', 'ilike', "%{$this->search}%"))
                ->paginate(15),
        ])->layout('layouts.portal', ['portal' => 'admin-logistik']);
    }
}
```

### 7.3 Routing Multi-Portal & Keamanan `PortalAccessMiddleware`
10 Portal diisolasi menggunakan Route Group resmi Laravel dengan middleware proteksi URL tampering:
```php
// routes/web.php
Route::middleware(['auth', 'portal.access:super-admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/dashboard', App\Livewire\Dashboard\SuperAdminDashboard::class)->name('dashboard');
    // Rute modul super admin...
});
```
Middleware `PortalAccessMiddleware` secara dinamis memverifikasi apakah pengguna memiliki permission dashboard terkait (`$user->can('page_' . $portalDashboard)`).

### 7.4 Standar Autentikasi Pengguna (`LoginGateway`)
Autentikasi diimplementasikan sebagai Livewire Component tunggal (`App\Livewire\Auth\LoginGateway`):
1. **Brute Force Protection**: Menggunakan `RateLimiter` (maksimal 5 percobaan per menit per email + IP).
2. **Validasi Status Akun**: Menolak pengguna jika `status !== 'AKTIF'` dan logout seketika.
3. **Audit Login**: Memperbarui kolom `terakhir_login` dan `ip_terakhir`.
4. **Dynamic Redirection**: Memeriksa permission dashboard pengguna dan me-redirect ke portal kerja resmi secara otomatis.
### 7.5 Standar Eloquent Model Laravel 13
1. \*\*Method \`casts(): array\`\*\*:
   Di Laravel 13, casting atribut model tidak lagi menggunakan property \`$casts = \[\]\`, melainkan method \`casts(): array\`:
   \`\`\`php
   protected function casts(): array
   {
       return \[
           'tanggal\_perolehan' =&gt; 'date',
           'nilai\_perolehan' =&gt; 'decimal:2',
           'status' =&gt; 'string',
       \];
   }
   \`\`\`
2. \*\*Kustomisasi Timestamps\*\*:
   Tabel aplikasi menggunakan kolom bahasa Indonesia:
   \`\`\`php
   const CREATED\_AT = 'dibuat\_pada';
   const UPDATED\_AT = 'diperbarui\_pada';
   \`\`\`
   Atau inherit langsung dari \`App\\Models\\BaseModel\`.

### 7.6 Standar Bersih Seeding untuk Testing Manual
Ketika membuat database baru di folder project baru:
1. Jalankan di dalam container: \`docker compose exec app php artisan migrate:fresh --seed\`.
2. Pipeline seeder mengeksekusi secara berurutan:
   - `UnitSeeder` & `GudangSeeder` (Master Fondasi Operasional: menanam unit `IGD`, `IPSRS`, dll. serta gudang `GUD_SENTRAL`, `GUD_IPSRS` agar relasi FK 10 akun resmi valid).
   - `PeranSeeder` (12 role sistem).
   - \`PenggunaSeeder\` (10 akun resmi sistem dengan kredensial login EMAIL dan password default \`'password'\`):
     - \`superadmin@simlogrs.local\` (\`super\_admin\`)
     - \`adminlogistik@simlogrs.local\` (\`admin\_logistik\` -&gt; \`GUD\_SENTRAL\`)
     - \`kepalagudang@simlogrs.local\` (\`kepala\_gudang\` -&gt; \`GUD\_SENTRAL\`)
     - \`petugasgudang@simlogrs.local\` (\`petugas\_gudang\` -&gt; \`GUD\_SENTRAL\`)
     - \`pengadaan@simlogrs.local\` (\`pengadaan\`)
     - \`kepalaunit@simlogrs.local\` (\`kepala\_unit\` -&gt; \`IGD\`)
     - \`petugasunit@simlogrs.local\` (\`petugas\_unit\` -&gt; \`IGD\`)
     - \`teknisi@simlogrs.local\` (\`teknisi\` -&gt; \`IPSRS\` / \`GUD\_IPSRS\`)
     - \`manajemen@simlogrs.local\` (\`manajemen\`)
     - \`auditor@simlogrs.local\` (\`auditor\`)
   - \`MigrateSpatieRBACSeeder\` &amp; \`ShieldRolePermissionSeeder\` (410 permissions dan pengaitan wewenang default).
3. Tabel \`barang\`, \`aset\`, dan seluruh transaksi (\`permintaan\_distribusi\`, \`pesanan\_pembelian\`, dll.) \*\*wajib kosong (0 records)\*\* agar siap untuk pengujian operasional manual dari awal.
