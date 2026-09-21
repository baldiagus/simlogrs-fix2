# Master Implementation Prompts Playbook (PROMPT.MD)
## Panduan Eksekusi Bertahap Rekayasa Ulang SIMLOG RS v2
### Berbasis Laravel 13, Livewire 3, Flux UI (Free Edition), dan Zero-Hardcode RBAC Architecture

---

## DAFTAR ISI & STRUKTUR TAHAPAN

- **PANDUAN UMUM & INVARIAN WAJIB (Wajib Dibaca Sebelum Mulai)**
- **FASE 0: Inisialisasi Project, Environtment & Fondasi Framework**
- **FASE 1: Engine RBAC Dinamis, Autentikasi & Multi-Portal Architecture**
- **FASE 2: Proses Bisnis Master Data & Katalog Logistik**
- **FASE 3: Proses Bisnis 1 - Siklus Pengadaan Logistik (Procurement Cycle)**
- **FASE 4: Proses Bisnis 2 - Siklus Distribusi Ruangan & Picking FEFO (Amprah Cycle)**
- **FASE 5: Proses Bisnis 3 - Siklus Peminjaman & Sirkulasi Aset Non-Medis**
- **FASE 6: Proses Bisnis 4 - Siklus Pemeliharaan Sarana & IPSRS (Maintenance Cycle)**
- **FASE 7: Proses Bisnis 5 - Manajemen Persediaan, FEFO & Stock Opname**
- **FASE 8: Proses Bisnis 6 - Tata Kelola Audit Internal, SPI & CAPA**
- **FASE 9: Scheduler Terjadwal, Dashboard Multi-Portal & Final Quality Assurance**

---

## PANDUAN UMUM & INVARIAN WAJIB

Setiap prompt di bawah ini dirancang **berurutan dan mandiri (*step-by-step*)**. Aturan yang **TIDAK BOLEH DILANGGAR** selama pembangunan project:
1. **Docker-First Execution Invariant (Wajib Dijalankan di Docker)**:
   Proyek ini **wajib dibangun, dijalankan, dan diuji menggunakan Docker Multi-Container**. Seluruh perintah CLI (artisan, composer, npm, migrasi database, unit test, dan linting) **DILARANG** dijalankan langsung di host OS, melainkan wajib ditujukan ke container aplikasi:
   - Format Perintah Artisan: `docker compose exec app php artisan <perintah>` (atau `docker exec -i simrslogv2-app php artisan <perintah>`).
   - Format Perintah Composer: `docker compose exec app composer <perintah>`.
   - Format Perintah Tinker: `docker compose exec -it app php artisan tinker`.
   - Format Pembersihan Cache: `docker compose exec app php artisan optimize:clear`.
   - Format Reload PHP-FPM OPcache: `sudo -n kill -USR2 $(sudo -n docker top simrslogv2-app | grep 'php-fpm: master process' | awk '{print $2}')` (atau dari dalam container: `kill -USR2 1`).
2. **Livewire 3 & Flux UI Component Invariant**: Seluruh antarmuka pengguna wajib mematuhi standar **Clinical & Industrial Utility Design System** menggunakan komponen resmi **Flux UI Free** (`<flux:sidebar>`, `<flux:header>`, `<flux:navlist>`, `<flux:navitem>`, `<flux:table>`, `<flux:modal>`, `<flux:badge>`, `<flux:button>`, `<flux:input>`, `<flux:select>`, `<flux:textarea>`, dll.) dan Livewire 3 Component untuk mengelola state dan interaksi backend. Mengutamakan kepadatan data (*data density*), font monospaced tabular-nums untuk finansial/kuantitas, border tipis beresolusi tinggi, dan identitas warna fungsional 10 portal tanpa dekorasi berlebihan.
3. **Reactive Livewire Invariant**: State binding dan event reaktif wajib menggunakan mekanisme native Livewire 3 (`wire:model.live`, `wire:click`, `$this->dispatch()`, atribut `#[Url]` dan `#[Validate]`). Seluruh tabel data wajib menggunakan trait `HasTableFilters` (`WithPagination` + auto-reset page saat pencarian berubah) dan notifikasi visual wajib menggunakan dispatch toast `<flux:toast />` via trait `HasToast`. Tampilan status dan finansial wajib menggunakan komponen reusable `<x-status-badge>`, `<x-rupiah>`, `<x-empty-state>`, dan `<x-urgency-badge>`.
4. **100% Pure Dynamic RBAC Invariant (Zero-Hardcode Mutlak)**:
   Seluruh otorisasi sistem (visibilitas menu navigasi sidebar, hak akses halaman, permission CRUD, dan tombol aksi transaksi) **WAJIB 100% DIKENDALIKAN OLEH PERMISSION SPATIE DI DATABASE (`can('page_...')`, `can('view_any_...')`, `can('create_...')`)**:
   - **TIDAK ADA PENGECUALIAN HARDCODE, TERMASUK UNTUK SUPER ADMIN**: Dilarang keras menuliskan `$user->hasRoleCode('super_admin')` sebagai bypass akses di dalam Resource, Page, Action, maupun Policy.
   - Super Admin mendapatkan seluruh akses **hanya karena seeder memberikan `Permission::all()` pada role `super_admin` di database**.
   - Jika Super Admin mencabut centang izin suatu modul atau dashboard dari role `super_admin` di menu *Peran & Hak Akses*, menu dan akses tersebut **wajib langsung hilang seketika** bagi Super Admin.
   - **Satu-satunya Proteksi (Anti Self-Lockout)**: Pada form `RoleManager`, checkbox hak akses untuk mengedit role (`view_any_user::role`, `update_user::role`) dikunci (*disabled*) khusus untuk role `super_admin` agar administrator tidak dapat mengunci dirinya sendiri dari menu pengatur wewenang.
5. **Portal Isolation & URL Protection Invariant**: 10 portal mandiri diisolasi melalui Route Group Laravel resmi yang dilindungi oleh `PortalAccessMiddleware`. Middleware ini memeriksa secara dinamis izin akses dashboard portal bersangkutan (`$user->can('page_' . $portalDashboard)`), mencegah URL tampering antar portal tanpa hardcode role.
6. **Database Non-Negative & Concurrency Invariant**:
   - Tabel `stok_batch` wajib memiliki PostgreSQL check constraint: `chk_stok_batch_non_negative CHECK (jumlah >= 0 AND jumlah_dipesan >= 0)` untuk mencegah bug stok minus.
   - Alokasi pengeluaran barang FEFO wajib menggunakan **pessimistic locking** (`lockForUpdate()`) di dalam `DB::transaction()` untuk mencegah *race condition* saat concurrent picking.
   - Tabel `stok_batch` wajib memiliki composite index: `INDEX(barang_id, status, tanggal_kedaluwarsa)` untuk kecepatan query FEFO dan cron sweep.
   - Tabel `barang` wajib memiliki GIN Trigram index pada kolom `nama` (`CREATE EXTENSION IF NOT EXISTS pg_trgm; CREATE INDEX idx_barang_nama_trgm ON barang USING gin (nama gin_trgm_ops);`) untuk mempercepat Livewire search.
7. **Clean Seeder Testing Invariant**: Seeder awal memuat Master Fondasi Operasional (`unit`, `gudang`) untuk mengikat 10 akun resmi, 12 role, dan permissions RBAC. Tabel master barang, aset, dan seluruh transaksi wajib 0 record agar siap untuk pengujian manual.
8. **Transaction Safety & Operational Invariant**:
   - Seluruh tombol submit/aksi transaksi wajib memasang `wire:loading.attr="disabled"` untuk mencegah duplikasi akibat double-click.
   - Layout master portal wajib memiliki banner `<div wire:offline>` untuk proteksi koneksi terputus di area gudang/basement.
   - Penomoran seluruh dokumen transaksi wajib menggunakan generator terpusat `App\Support\Generator\CodeGenerator`.
- Master data berelasi (barang, unit, gudang) dilarang di-hard delete, melainkan dinonaktifkan (`status = 'NONAKTIF'`).

### STRUKTUR DIREKTORI RESMI PROYEK
Seluruh file yang dibuat dalam prompt-prompt di bawah wajib ditempatkan sesuai hierarki folder resmi berikut:
```text
simlog-fix2/
├── app/
│   ├── Http/
│   │   └── Middleware/PortalAccessMiddleware.php
│   ├── Livewire/
│   │   ├── Auth/LoginGateway.php
│   │   ├── Dashboard/ (10 Dashboard Portal)
│   │   ├── Master/ (9 Komponen Master Data)
│   │   ├── Pengadaan/ (PR, PO, Tracking, Inbound)
│   │   ├── Distribusi/ (Amprah, Approval, Picking FEFO, RCV)
│   │   ├── Aset/ (Pinjam, Riwayat, Kembali, Approval)
│   │   ├── Maintenance/ (Lapor, SPK, Lembar Kerja, Preventif, Rekam Medis)
│   │   ├── Persediaan/ (Monitoring FEFO, Opname, Otorisasi, Tata Letak Rak)
│   │   ├── Audit/ (Audit Trail, Selisih, Kepatuhan, Register CAPA)
│   │   ├── User/ (Pengguna, RoleManager)
│   │   ├── System/ (Scheduler, Pengaturan)
│   │   └── Concerns/ (HasTableFilters, HasToast, HasPortalLayout)
│   ├── Models/ (44 Model Eloquent + BaseModel)
│   ├── Services/ (DistribusiService, PeminjamanAsetService, NotifikasiService)
│   ├── Support/ (CodeGenerator, ContextManager, PagePermissionRegistry)
│   └── Traits/ (HasAuditLog)
├── database/
│   ├── migrations/ (44 Migrasi Tabel)
│   └── seeders/ (Unit, Gudang, Peran, Pengguna, Shield Permissions)
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── resources/
│   ├── css/app.css
│   └── views/
│       ├── components/ (status-badge, rupiah, empty-state, urgency-badge, context-banner)
│       ├── layouts/ (portal.blade.php & guest.blade.php)
│       ├── livewire/ (Blade views 58 komponen)
│       └── pdf/ (po, sppb, spk)
├── routes/
│   ├── web.php & console.php
├── docker-compose.yml, composer.json, package.json, vite.config.js
```
---

## FASE 0: Inisialisasi Project, Environtment & Fondasi Framework

### 0.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: System Administrator / DevOps Engineer (Host CLI & Docker Multi-Container).
- **Alur Proses Bisnis**:
  1. Menyiapkan kontainerisasi multi-container (`app`, `web`, `postgres`, `redis`).
  2. Memasang dependensi inti Laravel 13, Livewire 3, Flux UI Free, Tailwind CSS v4, Spatie, Dompdf, QRCode, FastExcel.
  3. Mengonfigurasi environment stateless dan koneksi PostgreSQL 16 & Redis 7.
- **Kondisi & Validasi**:
  - Docker-first execution: Seluruh CLI wajib dieksekusi di dalam container `app`.
  - 4 service wajib berstatus Up/running.
  - Verifikasi koneksi DB dan cache via `php artisan about`.

### Prompt 0.1: Scaffolding Project & Dependensi Inti
```text
Buatkan project baru Laravel 13 untuk SIMLOG RS Non-Medis v2 dengan spesifikasi:
1. Siapkan composer.json menggunakan dependensi resmi berikut:
   - "php": "^8.2" (target runtime PHP 8.3+)
   - "laravel/framework": "^13.0"
   - "livewire/livewire": "^3.5"
   - "livewire/flux": "^1.0"
   - "spatie/laravel-permission": "^6.0"
   - "spatie/laravel-activitylog": "^4.12"
   - "barryvdh/laravel-dompdf": "^3.0"
   - "simplesoftwareio/simple-qrcode": "^4.2"
   - "rap2hpoutre/fast-excel": "^5.4"
   - "laravel/sanctum": "^4.3"
   - "laravel/tinker": "^3.0"
2. Konfigurasikan file .env untuk koneksi container:
   - DB_CONNECTION=pgsql
   - DB_HOST=postgres
   - DB_PORT=5432
   - DB_DATABASE=simlog_rs
   - DB_USERNAME=simlog_user
   - DB_PASSWORD=simlog_pass
   - REDIS_HOST=redis
   - REDIS_PORT=6379
   - CACHE_STORE=redis
   - SESSION_DRIVER=redis
   - QUEUE_CONNECTION=redis
3. Konfigurasikan bootstrap/app.php dengan arsitektur stateless Laravel 13 (withRouting, withMiddleware, withExceptions).
```

### Prompt 0.2: Docker Multi-Container Environment
```text
Siapkan infrastruktur Docker Multi-Container resmi untuk SIMLOG RS v2:
1. Buat file `docker/php/Dockerfile`:
   - Base image: `php:8.3-fpm`
   - Pasang helper mlocati/docker-php-extension-installer.
   - Pasang ekstensi wajib: pdo_pgsql, pgsql, redis, bcmath, gd, zip, intl, opcache, pcntl.
   - Salin Composer binary dari `composer:2`.
   - Pasang Node.js 22 LTS untuk build asset Vite/Tailwind.
   - Working directory: `/var/www/html`.
2. Buat file `docker/nginx/default.conf`:
   - Reverse proxy port 80 ke service `app:9000`.
   - Sediakan try_files ke /index.php?$query_string, timeout fastcgi 300 detik, dan header proteksi.
3. Buat file `docker-compose.yml`:
   - Service `app` (container_name: `simrslogv2-app`, port 5173, volumes dengan named volume untuk `/var/www/html/vendor` dan `/var/www/html/node_modules` guna mencegah I/O bottleneck di Windows).
   - Service `web` (container_name: `simrslogv2-web`, port 8000:80, depends_on: app).
   - Service `postgres` (container_name: `simrslogv2-postgres`, image: `postgres:16-alpine`, port 5432).
   - Service `redis` (container_name: `simrslogv2-redis`, image: `redis:7-alpine`, port 6379).
   - Shared network: `simrslog-net`.
   - Named volumes: `postgres_data`, `app_vendor`, `app_node_modules`.
4. Jalankan `docker compose up -d --build`.
5. Eksekusi composer install di dalam container: `docker compose exec app composer install`.
6. Verifikasi kesehatan container: `docker compose ps` (seluruh service harus berstatus Up/running) dan uji `docker compose exec app php artisan about`.
```

---

## FASE 1: Engine RBAC Dinamis, Autentikasi & Multi-Portal Architecture

### 1.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Super Admin (`/super-admin`) dan Seluruh 10 Akun Pengguna Resmi.
- **Alur Proses Bisnis**:
  1. Pengguna login via `LoginGateway` (email & password) -> divalidasi & diaudit -> auto-redirect ke portal spesifik peran.
  2. Super Admin mengatur hak akses via Livewire `RoleManager` (matriks 5 kolom: Akses Halaman, Lihat, Buat, Ubah, Hapus).
  3. Super Admin mengemulasi konteks gudang/unit via `ContextManager` di header layout portal.
- **Kondisi & State Machine**:
  - Status Pengguna: `AKTIF` (diizinkan login) atau `NONAKTIF` (ditolak 403).
  - Rate Limiting: Maksimal 5 percobaan gagal per menit -> lockout sementara.
  - Anti Self-Lockout: Hak akses modul `user::role` dikunci (*disabled*) khusus untuk role `super_admin`.
  - Portal Access Guard: URL tampering antar-portal dicegah oleh `PortalAccessMiddleware` (HTTP 403).

### Prompt 1.1: Migrasi Akun Pengguna & Peran Sistem
```text
Buatkan migrasi dan model untuk Grup 1 (Hak Akses & Pengguna):
1. Tabel `pengguna` (Model `App\Models\User` extends Authenticatable):
   - Kolom: id, unit_id (nullable, FK unit), gudang_id (nullable, FK gudang), nama, nip (nullable), username (unique), email (unique), password, no_telepon, status (default 'AKTIF'), terakhir_login, ip_terakhir, remember_token, dibuat_pada, diperbarui_pada.
   - Gunakan trait HasRoles (Spatie), HasApiTokens, dan Notifiable.
2. Tabel `peran` (Model `App\Models\Peran`):
   - Kolom: id, kode (unique), nama, deskripsi, dibuat_pada, diperbarui_pada.
3. Tabel pivot `pengguna_peran` (Model `App\Models\PenggunaPeran`):
   - Kolom: id, pengguna_id (FK cascade), peran_id (FK cascade), unit_id (nullable), gudang_id (nullable), dibuat_pada.
   - Constraint: UNIQUE(pengguna_id, peran_id, unit_id, gudang_id).
4. Model BaseModel:
   - Buat abstract class `App\Models\BaseModel` dengan konstanta timestamp bahasa Indonesia (const CREATED_AT = 'dibuat_pada', const UPDATED_AT = 'diperbarui_pada') dan $guarded = ['id'].
5. Jalankan migrasi di dalam container: `docker compose exec app php artisan migrate` dan pastikan tabel tercipta di PostgreSQL.
```

### Prompt 1.2: Dynamic Page-Centric RBAC Engine (`PagePermissionRegistry`)
```text
Implementasikan mesin RBAC berbasis halaman dinamis tanpa hardcode:
1. Buat class `App\Support\Rbac\PagePermissionRegistry` yang memetakan seluruh modul sistem (58 modul) ke dalam 7 domain fungsional:
   - `dashboard_portal`: 10 Dashboard Portal (`page_Dashboard`, `page_AdminLogistikDashboard`, `page_KepalaGudangDashboard`, `page_GudangDashboard`, `page_PengadaanDashboard`, `page_KepalaUnitDashboard`, `page_UnitDashboard`, `page_TeknisiDashboard`, `page_ManajemenDashboard`, `page_AuditorDashboard`).
   - `master`: Master Data Logistik (Barang, Unit, Kategori, Satuan, Merek, Pemasok, Gudang, Lokasi).
   - `layanan_unit`: Layanan Ruangan (Permintaan Barang, Peminjaman Aset Ruangan, Lapor Servis, Stok Ruangan, Penerimaan Barang, Approval Karu).
   - `gudang_stok`: Operasional Gudang (Stok Batch, Buku Mutasi, Monitoring FEFO, Stock Opname, Otorisasi Penyesuaian, Tata Letak Rak, Picking Distribusi, Penerimaan PO).
   - `pengadaan`: Pengadaan & Vendor (Permintaan Pengadaan, Pesanan Pembelian, Tracking PO, Master Rekanan, Realisasi Anggaran).
   - `pemeliharaan`: Pemeliharaan IPSRS (SPK Perbaikan, Lembar Kerja, Pemeliharaan Berkala, Peminjaman Alat Kerja, Permintaan Sparepart, Rekam Medis Aset).
   - `audit_manajemen`: Audit SPI, Diagnostik & Sistem (Audit Trail, Selisih Opname, Kepatuhan Pengadaan, Mutasi Distribusi, Temuan CAPA, Manajemen Pengguna, Parameter Sistem, Kesehatan Sistem, Schedulers & Notifikasi).
2. Buat method helper:
   - `toPermissions(array $state)`: Mengonversi checklist toggle halaman dan centang CRUD menjadi array permission Spatie (`page_{PageName}`, `view_any_{entity}`, dll.).
   - `fromRole(Role $role)`: Membaca permissions database peran dan menyusunnya kembali ke format array UI form.
3. Buatkan Livewire Component `RoleManager` di `App\Livewire\User\RoleManager`:
   - Gunakan Flux UI dengan UI Tabbed 5 Kolom (`<flux:tabs>`, `<flux:checkbox>` untuk Akses Halaman, Lihat, Buat, Ubah, Hapus).
   - Simpan langsung ke tabel `permissions` dan `role_has_permissions`.
   - **Safeguard Anti Self-Lockout**: Kunci (*disabled*) checkbox hak akses modul `user::role` (`view_any_user::role`, `update_user::role`) khusus untuk role `super_admin` agar administrator tidak dapat mengunci dirinya sendiri dari menu pengatur wewenang.
```

### Prompt 1.3: Multi-Portal Routing, Layout Flux UI & Login Gateway
```text
Bangun arsitektur 10 portal independen dan 1 root gateway login terpusat menggunakan Livewire 3 & Flux UI:
1. Buat Middleware `App\Http\Middleware\PortalAccessMiddleware`:
   - Memeriksa otentikasi user dan status aktif (`$user->status === 'AKTIF'`).
   - Memvalidasi wewenang user terhadap portal bersangkutan secara dinamis berdasarkan permission dashboard Spatie (`$user->can('page_' . $portalDashboard)`).
2. Buat Layout Master Portal `resources/views/layouts/portal.blade.php`:
   - Menggunakan komponen Flux UI: `<flux:sidebar>`, `<flux:header>`, `<flux:navlist>`, `<flux:navitem>`, dan `<flux:brand>`.
   - Tema warna portal dinamis berbasis parameter portal (Blue, Amber, Emerald, Indigo, Cyan, Teal, Rose).
   - Menyediakan menu dropdown switcher portal untuk Super Admin agar bisa berpindah antar 10 portal tanpa logout.
   - Pasang banner deteksi koneksi terputus `<div wire:offline>` di bagian atas layout.
3. Daftarkan Rute di `routes/web.php`:
   - Route root `/` dan `/login`: Mengarah ke Livewire Component `App\Livewire\Auth\LoginGateway` (form login email & password dengan Flux UI). Saat login berhasil, me-redirect user ke portal spesifik perannya.
   - 10 Route Groups terisolasi dilindungi `['auth', 'portal.access:{portal}']`:
     - `/super-admin/*`
     - `/admin-logistik/*`
     - `/kepala-gudang/*`
     - `/petugas-gudang/*`
     - `/pengadaan/*`
     - `/kepala-unit/*`
     - `/petugas-unit/*`
     - `/teknisi/*`
     - `/manajemen/*`
     - `/auditor/*`
4. Buat Service `App\Support\Generator\CodeGenerator`:
   - Mengelola penomoran dokumen resmi unik: `PR-YYYY-XXX`, `PO-YYYY-XXX`, `SPPB-YYYYMMDD-XXXX`, `RCV-YYYYMM-XXX`, `PINJ-YYYY-XXX`, `MNT-YYYYMM-XXX`, `AUD-YYYYMM-XXX`.
5. Buat 3 Reusable Livewire Traits di `app/Livewire/Concerns/`:
   - `HasTableFilters.php`: Menggabungkan `WithPagination`, properti `#[Url] public string $search = ''`, dan method `updatedSearch()` untuk auto-reset halaman.
   - `HasToast.php`: Menyediakan helper method `$this->toast($message, $variant)` untuk dispatch notifikasi instan ke `<flux:toast />`.
   - `HasPortalLayout.php`: Mendeteksi segmen URL portal aktif secara otomatis untuk binding layout `<x-layouts.portal>`.
6. Buat 5 Reusable Blade Components di `resources/views/components/`:
   - `status-badge.blade.php`: `<x-status-badge :status="$status" />` (pemetaan warna hijau/kuning/merah/biru/abu-abu otomatis).
   - `rupiah.blade.php`: `<x-rupiah :value="$nominal" />` (format Rupiah font monospaced tabular-nums rata kanan).
   - `empty-state.blade.php`: `<x-empty-state :message="$pesan" :cols="$cols" />` (baris tabel kosong dengan ikon dan pesan).
   - `urgency-badge.blade.php`: `<x-urgency-badge :urgency="$urgensi" />` (badge prioritas CITO/Mendesak dengan animasi `animate-pulse`).
   - `context-banner.blade.php`: `<x-context-banner />` (indikator mode emulasi Super Admin dengan tombol reset global).
```

### Prompt 1.4: Context Manager untuk Emulasi Super Admin
```text
Implementasikan fitur emulasi wewenang Super Admin berbasis sesi:
1. Buat class `App\Support\Auth\ContextManager`:
   - Simpan pilihan emulasi di session (`emulated_gudang_id` dan `emulated_unit_id`).
   - Sediakan method `setGudangId(?int $id)`, `setUnitId(?int $id)`, dan `resetGlobalContext()`.
   - Sediakan method helper query: `scopeUnit(Builder $query)` dan `scopeGudang(Builder $query)`.
2. Pasang tombol header di layout portal / dashboard:
   - `pilihGudangEmulasi`: Tombol dengan modal pemilihan gudang `<flux:modal>`.
   - `pilihUnitEmulasi`: Tombol modal pemilihan unit kerja `<flux:modal>`.
   - `resetKonteksGlobal`: Tombol merah `<flux:button variant="danger">` yang muncul jika emulasi aktif untuk kembali ke mode global.
3. Buat komponen Blade `resources/views/components/context-banner.blade.php` yang menampilkan status apakah sistem sedang dalam mode emulasi atau mode akses global menggunakan `<flux:badge>`.
```

### Prompt 1.5: Seeder Resmi Akun & Role (Clean Seeding - Autentikasi Login via Email)
```text
Buatkan pipeline seeder resmi menggunakan kredensial autentikasi EMAIL tanpa data sampah:
1. `UnitSeeder` & `GudangSeeder` (Master Fondasi Operasional):
   - Menanam data unit kerja minimal (`IGD`, `IPSRS`, dll.) dan gudang (`GUD_SENTRAL`, `GUD_IPSRS`) agar relasi foreign key pada 10 akun pengguna resmi valid.
2. `PeranSeeder`: Menanam 12 role sistem (`super_admin`, `admin_logistik`, `kepala_gudang`, `petugas_gudang`, `pengadaan`, `kepala_unit`, `petugas_unit`, `teknisi`, `manajemen`, `auditor`, `staf_gudang`, `panel_user`).
3. `PenggunaSeeder`: Menanam 10 akun resmi dengan format autentikasi login via EMAIL (seluruh password default: 'password') dan mengaitkannya ke unit/gudang dinas:
   - Super Administrator:
     - Email: `superadmin@simlogrs.local` (Username: `superadmin`)
     - Role: `super_admin` | Unit: Null | Gudang: Null
   - Admin Logistik:
     - Email: `adminlogistik@simlogrs.local` (Username: `adminlogistik`)
     - Role: `admin_logistik` | Gudang Dinas: `GUD_SENTRAL` (Gudang Sentral Logistik)
   - Kepala Gudang:
     - Email: `kepalagudang@simlogrs.local` (Username: `kepalagudang`)
     - Role: `kepala_gudang` | Gudang Dinas: `GUD_SENTRAL`
   - Petugas Gudang:
     - Email: `petugasgudang@simlogrs.local` (Username: `petugasgudang`)
     - Role: `petugas_gudang` | Gudang Dinas: `GUD_SENTRAL`
   - Bagian Pengadaan:
     - Email: `pengadaan@simlogrs.local` (Username: `pengadaan`)
     - Role: `pengadaan` | Unit: Null | Gudang: Null
   - Kepala Ruangan / Unit:
     - Email: `kepalaunit@simlogrs.local` (Username: `kepalaunit`)
     - Role: `kepala_unit` | Unit Dinas: `IGD` (Instalasi Gawat Darurat)
   - Petugas / Staf Ruangan:
     - Email: `petugasunit@simlogrs.local` (Username: `petugasunit`)
     - Role: `petugas_unit` | Unit Dinas: `IGD`
   - Teknisi Sarana & IPSRS:
     - Email: `teknisi@simlogrs.local` (Username: `teknisi`)
     - Role: `teknisi` | Unit Dinas: `IPSRS` | Gudang Dinas: `GUD_IPSRS`
   - Manajemen Eksekutif / Direksi:
     - Email: `manajemen@simlogrs.local` (Username: `manajemen`)
     - Role: `manajemen` | Unit: Null | Gudang: Null
   - Auditor Internal (SPI):
     - Email: `auditor@simlogrs.local` (Username: `auditor`)
     - Role: `auditor` | Unit: Null | Gudang: Null
4. `ShieldRolePermissionSeeder`: Generate 410 permissions dan kaitkan wewenang default ke masing-masing role, serta pastikan permission `panel_user` diberikan ke semua role agar tidak terkena 403 Forbidden saat login.
5. Pastikan form login Livewire (`LoginGateway`) menerima input `email` dan `password` via komponen `<flux:input>` untuk autentikasi kredensial pengguna.
6. Jalankan seeder di dalam container: `docker compose exec app php artisan migrate:fresh --seed` dan verifikasi bahwa 10 akun dan permissions berhasil tertanam, serta tabel transaksi dan barang dalam kondisi kosong (0 records).
```

---

## FASE 2: Proses Bisnis Master Data & Katalog Logistik

### 2.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Admin Logistik (`/admin-logistik`), Kepala Unit / Teknisi.
- **Alur Proses Bisnis**:
  1. Admin Logistik mendaftarkan unit kerja, fasilitas gudang, dan tata letak rak simpan (`lokasi_gudang`).
  2. Mendaftarkan katalog komoditas barang (`HABIS_PAKAI`, `ASET`, `SPAREPART`).
  3. Mendaftarkan register aset fisik unik dan nomor seri.
- **Kondisi & State Machine**:
  - Strict Deactivation: Master data berelasi dilarang di-hard delete (ubah `status = 'NONAKTIF'`).
  - Constraint Aset XOR: `chk_aset_lokasi_xor` menjamin aset fisik berada di ruangan ATAU di gudang.
  - Search Optimization: Kolom `barang.nama` dilindungi GIN Trigram index (`pg_trgm`) untuk pencarian instan $<10\text{ ms}$.

### Prompt 2.1: Migrasi Tabel Master Data & Check Constraint
```text
Selesaikan tuntas seluruh tabel Grup 2 (Master Data):
1. Buat migrasi tabel: `unit`, `gudang`, `lokasi_gudang` (rak simpan), `kategori_barang`, `satuan_barang`, `merek_barang`, `pemasok`, `barang`, dan `aset`.
2. Terapkan Check Constraint PostgreSQL pada tabel `aset`:
   `chk_aset_lokasi_xor CHECK ((unit_id IS NOT NULL AND gudang_id IS NULL AND lokasi_gudang_id IS NULL) OR (unit_id IS NULL AND gudang_id IS NOT NULL))`
   sehingga setiap unit aset fisik dipastikan berada di ruangan ATAU di gudang, tidak boleh keduanya atau tidak berpijak di manapun.
3. Pada tabel `barang`, sertakan kolom: kode (unique), nama, tipe (HABIS_PAKAI, ASET, SPAREPART), stok_minimum, stok_maksimum, titik_pesan (ROP), harga_beli_terakhir, harga_estimasi, status (AKTIF/NONAKTIF).
4. Aktifkan ekstensi PostgreSQL `pg_trgm` dan pasang GIN Trigram index:
   `DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');`
   `DB::statement('CREATE INDEX idx_barang_nama_trgm ON barang USING gin (nama gin_trgm_ops);');`
5. Buat seluruh Eloquent Model yang bersangkutan dengan relasi BelongsTo dan HasMany lengkap, serta protected function casts(): array standar Laravel 13.
6. Jalankan migrasi di dalam container: `docker compose exec app php artisan migrate` dan pastikan constraint dan index GIN aktif di PostgreSQL.
```

### Prompt 2.2: Livewire Components Master Data dengan Flux UI (Zero-Hardcode Standard)
```text
Buatkan 9 Livewire Component untuk Master Data di `App\Livewire\Master`:
1. `BarangIndex`, `UnitIndex`, `GudangIndex`, `LokasiGudangIndex`, `KategoriBarangIndex`, `SatuanBarangIndex`, `MerekBarangIndex`, `PemasokIndex`, dan `AsetIndex`.
2. Gunakan komponen Flux UI Free:
   - Tabel data padat: `<flux:table>`, `<flux:table.columns>`, `<flux:table.row>`, `<flux:table.cell>`, dan `<flux:pagination>`.
   - Modal form tambah/edit: `<flux:modal>`, `<flux:input>`, `<flux:select>`, `<flux:button>`.
   - Filter pencarian reaktif: `wire:model.live.debounce.300ms="search"`.
3. Terapkan Otorisasi Bersih Tanpa Hardcode Role pada method `render()` di setiap komponen:
   ```php
   abort_unless(
       auth()->user()?->status === 'AKTIF' && (
           auth()->user()->can('view_any_master::{entity}') ||
           auth()->user()->can('view_master::{entity}')
       ),
       403
   );
   ```
   Dilarang keras memasukkan klausa fallback role statis apapun, TERMASUK untuk Super Admin (`|| $user->hasRoleCode('super_admin')`). Seluruh wewenang murni bersumber dari centang permission Spatie di database. Jika Super Admin mencabut izin `view_any` dari rolenya sendiri, maka menu dan halaman ini langsung tertolak (403).
4. Uji coba melalui browser: Pastikan menu master data muncul bagi Admin Logistik dan Super Admin, dan jika permission dicabut via Super Admin, menu di sidebar Flux UI otomatis hilang dan akses URL langsung tertolak secara dinamis bagi siapapun.
```

---

## FASE 3: Proses Bisnis 1 - Siklus Pengadaan Logistik (Procurement Cycle)

### 3.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Petugas Unit/Gudang (Pengaju PR), Kepala Unit/Gudang (Approval PR), Bagian Pengadaan (Penerbit PO), Petugas Gudang Inbound, Manajemen Direksi.
- **Alur Proses Bisnis & State Machine**:
  1. PR diajukan (`DIAJUKAN`) -> Otorisasi Karu/Kagud (`DISETUJUI` / `DITOLAK` / `DITERUSKAN_KE_PENGADAAN`).
  2. Pengadaan konversi PR ke PO (`DITERBITKAN`, nomor: `PO-YYYY-XXX`, cetak PDF) -> Vendor kirim (`DIKIRIM_VENDOR`).
  3. Gudang Inbound terima fisik barang, catat nomor batch, ED, rak simpan -> PO `DITERIMA_LENGKAP` -> Auto-create `stok_batch` & mutasi `MASUK` di `pergerakan_stok`.
- **Kondisi & State Machine**:
  - State PR: `DIAJUKAN` -> `DISETUJUI` -> `DITERUSKAN_KE_PENGADAAN`.
  - State PO: `DITERBITKAN` -> `DIKIRIM_VENDOR` -> `DITERIMA_SEBAGIAN` -> `DITERIMA_LENGKAP` (atau `DIBATALKAN`).
  - Validasi Anggaran: Total biaya PO wajib <= sisa pagu anggaran tahun berjalan (`bcsub`).
  - Double-Submit Protection: Seluruh tombol transaksi wajib memasang `wire:loading.attr="disabled"`.

### Prompt 3.1: Migrasi Tabel Siklus Pengadaan
```text
Selesaikan tuntas tabel Grup 4 (Pengadaan):
1. `anggaran_pengadaan`: id, kode (unique), pos_anggaran, pagu (decimal 15,2), realisasi (decimal 15,2), sisa, serapan, tahun, status, po_terkait (json), timestamps.
2. `permintaan_pengadaan` (PR): id, kode (unique), unit_id, diajukan_oleh, tanggal_permintaan, urgensi (REGULER/MENDESAK/CITO), status (DIAJUKAN/DISETUJUI/DITERUSKAN_KE_PENGADAAN/DITOLAK), catatan, disetujui_oleh, timestamps.
3. `permintaan_pengadaan_detail`: id, permintaan_pengadaan_id, barang_id, jumlah, jumlah_disetujui, catatan.
4. `pesanan_pembelian` (PO): id, kode (unique), pemasok_id, permintaan_pengadaan_id, tanggal_po, estimasi_tanggal_kirim, total_biaya, syarat_pembayaran, status (DITERBITKAN/DIKIRIM_VENDOR/DITERIMA_SEBAGIAN/DITERIMA_LENGKAP/DIBATALKAN), dibuat_oleh, timestamps.
5. `pesanan_pembelian_detail`: id, pesanan_pembelian_id, barang_id, jumlah_dipesan, jumlah_diterima, harga_satuan, subtotal.
6. `penerimaan_barang` & `penerimaan_barang_detail`: Inbound receiving untuk surat jalan vendor, nomor batch pabrik, tanggal kedaluwarsa, kuantitas diterima, kuantitas rusak.
7. Buatkan Eloquent Models dengan relasi lengkap. Jalankan migrasi di dalam container: `docker compose exec app php artisan migrate`.
```

### Prompt 3.2: Implementasi Halaman & Alur Pengadaan Transparan
```text
Bangun modul siklus pengadaan lengkap dari PR ke PO hingga Inbound:
1. `PermintaanPengadaanIndex` (Livewire Component di portal Unit & Pengadaan):
   - Form pembuatan PR oleh staf ruangan/gudang via `<flux:modal>`.
   - Otorisasi Kepala Ruangan untuk menyetujui kuantitas (`jumlah_disetujui`).
   - Tombol aksi "Terbitkan PO" (`konversiPo`) yang dilindungi murni oleh permission Spatie:
     `@can('create_pengadaan::pesanan::pembelian')`.
     Di dalam modal Flux UI, staf pengadaan memilih vendor rekanan, menetapkan harga beli satuan, dan mengisi catatan. Saat submit, secara atomik membuat rekaman `pesanan_pembelian` dan memotong sisa pagu di `anggaran_pengadaan`.
2. `PesananPembelianIndex` di portal Pengadaan:
   - Monitoring daftar PO via `<flux:table>`, cetak surat pesanan resmi dalam format PDF via `barryvdh/laravel-dompdf`, dan update status pengiriman vendor.
3. `TrackingPoIndex`:
   - Pelacakan pesanan aktif vendor beserta indikator keterlambatan kirim menggunakan `<flux:badge>`.
4. `PenerimaanPoIndex` di portal Petugas Gudang:
   - Penerimaan fisik barang inbound. Petugas mencatat nomor surat jalan vendor, memeriksa kemasan fisik, mencatat nomor batch pabrik, tanggal kedaluwarsa, dan memilih rak simpan awal (`lokasi_gudang_id`).
   - **Logika Otomatisasi Stok**: Saat penerimaan disahkan, sistem otomatis membuat rekaman di `stok_batch` dan mencatat mutasi `MASUK` pada kartu stok `pergerakan_stok`.
5. Verifikasi: Buat 1 transaksi PR -> Approve Karu -> Konversi ke PO -> Penerimaan Inbound di Gudang. Buktikan saldo `stok_batch` bertambah otomatis.
```

---

## FASE 4: Proses Bisnis 2 - Siklus Distribusi Ruangan & Picking FEFO (Amprah Cycle)

### 4.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Petugas Unit (Pengaju Amprah & Penerima RCV), Kepala Unit (Approval Karu), Admin Logistik (Otorisator Gudang), Petugas Gudang (Picking FEFO & SPPB).
- **Alur Proses Bisnis & State Machine**:
  1. Staf unit input amprah (`DIAJUKAN`, nomor: `REQ-YYYYMM-XXX`) -> Approval Karu (`DISETUJUI_KARU`) -> Otorisasi Admin Logistik (`DISETUJUI_GUDANG`).
  2. Petugas Gudang eksekusi picking FEFO -> Saldo `stok_batch` terpotong atomik, mutasi `KELUAR` tercatat, terbit SPPB (`SEDANG_DIKIRIM`, nomor: `SPPB-YYYYMMDD-XXXX`, cetak PDF).
  3. Staf unit konfirmasi terima fisik -> Saldo masuk ke `stok_ruangan` -> Transaksi `SELESAI`.
- **Kondisi & State Machine**:
  - State Amprah: `DIAJUKAN` -> `DISETUJUI_KARU` -> `DISETUJUI_GUDANG` -> `SEDANG_DIKIRIM` -> `SELESAI` (atau `DITOLAK`).
  - Concurrency FEFO: Wajib menggunakan `lockForUpdate()` dalam `DB::transaction()` dan patuh pada `chk_stok_batch_non_negative`.

### Prompt 4.1: Migrasi Tabel Siklus Distribusi
```text
Selesaikan tuntas tabel Grup 5 (Distribusi Ruangan):
1. `permintaan_distribusi`: id, kode (unique), unit_id, gudang_id, tanggal_permintaan, urgensi (REGULER/CITO), status (DIAJUKAN/DISETUJUI_KARU/DISETUJUI_GUDANG/SEDANG_DIKIRIM/SELESAI/DITOLAK), diajukan_oleh, disetujui_oleh, timestamps.
2. `permintaan_distribusi_detail`: id, permintaan_distribusi_id, barang_id, jumlah, jumlah_disetujui.
3. `pengiriman_barang` (SPPB): id, kode (unique), permintaan_distribusi_id, gudang_id, unit_id, tanggal_kirim, status (DIKIRIM/DITERIMA/BATAL), dikirim_oleh, timestamps.
4. `pengiriman_barang_detail`: id, pengiriman_barang_id, barang_id, stok_batch_id, jumlah_dikirim.
5. `penerimaan_distribusi` & `penerimaan_distribusi_detail`: Dokumen serah terima fisik ruangan.
6. Buatkan model dan relasinya. Jalankan migrasi di dalam container: `docker compose exec app php artisan migrate`.
```

### Prompt 4.2: Domain Service FEFO & Modul Distribusi
```text
Implementasikan alur amprah dan algoritma pengeluaran barang FEFO otomatis:
1. Buat Service `App\Services\Distribusi\DistribusiService`:
   - Method `eksekusiPickingFefo(PermintaanDistribusi $permintaan, array $detail, User $actor, ?int $gudangId)`:
     - Mencari batch aktif pada barang yang diminta, mengurutkannya berdasarkan `tanggal_kedaluwarsa ASC` (FEFO) menggunakan **pessimistic locking** (`->lockForUpdate()`) di dalam `DB::transaction()`.
     - Mengurangi saldo `jumlah` pada `stok_batch` secara atomik di dalam DB::transaction.
     - Mencatat mutasi `KELUAR` pada tabel `pergerakan_stok` lengkap dengan saldo sebelum dan saldo sesudah.
     - Menerbitkan dokumen SPPB dengan nomor otomatis `SPPB-YYYYMMDD-XXXX`, status `DIKIRIM`, dan tombol cetak surat jalan PDF via `barryvdh/laravel-dompdf`.
2. Buat Komponen Livewire Operasional Distribusi dengan Flux UI:
   - `PermintaanBarangIndex` (Portal Unit): Input amprah komoditas ruangan via `<flux:modal>`.
   - `ApprovalPermintaanIndex` (Portal Kepala Unit): Karu memvalidasi kebutuhan ruangan (`DISETUJUI_KARU`).
   - `DistribusiApprovalIndex` (Portal Admin Logistik): Admin menyetujui kuantitas final (`DISETUJUI_GUDANG`).
   - `PickingDistribusiIndex` (Portal Petugas Gudang): Tombol aksi untuk memicu `eksekusiPickingFefo()`.
   - `PenerimaanBarangIndex` (Portal Petugas Unit): Konfirmasi penerimaan fisik. Kuantitas otomatis masuk ke tabel `stok_ruangan` dan transaksi berstatus `SELESAI`.
3. Verifikasi: Simulasikan 1 alur amprah penuh dari pengajuan ruangan hingga penerimaan barang di ruangan. Buktikan batch terdekat yang terpotong pertama kali.
```

---

## FASE 5: Proses Bisnis 3 - Siklus Peminjaman & Sirkulasi Aset Non-Medis

### 5.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Petugas Unit Peminjam, Kepala Unit Pemilik Aset, Admin Logistik, Teknisi IPSRS.
- **Alur Proses Bisnis & State Machine**:
  1. Unit ajukan pinjam aset tersedia (`DIAJUKAN`, nomor: `PINJ-YYYY-XXX`) -> Approval Karu Pemilik (`DISETUJUI`).
  2. Serah terima fisik -> Status transaksi `DIPINJAM`, master aset berubah `status = 'DIPINJAM'` dan `unit_id` berpindah ke peminjam.
  3. Pengembalian: Jika kondisi `BAIK` -> Aset kembali `TERSEDIA` dan lokasi kembali; Jika `RUSAK_RINGAN` / `RUSAK_BERAT` -> Aset `DALAM_MAINTENANCE` dan otomatis terbit tiket servis IPSRS.
- **Kondisi & State Machine**:
  - State Pinjam: `DIAJUKAN` -> `DISETUJUI` -> `DIPINJAM` -> `SELESAI` (atau `DITOLAK`).
  - Validasi Overdue: `today() > tanggal_kembali_rencana` otomatis memicu indikator Overdue.
  - Fisik Aset: Dilengkapi stiker QR Code via `simple-qrcode`.

### Prompt 5.1: Migrasi Tabel Peminjaman Aset & Service
```text
Selesaikan tuntas tabel Grup 6 (Peminjaman Aset):
1. `permintaan_peminjaman`: id, kode (unique), unit_id (peminjam), diajukan_oleh, tanggal_pinjam, tanggal_kembali_rencana, status (DIAJUKAN/DISETUJUI/DIPINJAM/SELESAI/DITOLAK), disetujui_oleh, timestamps.
2. `permintaan_peminjaman_detail`: id, permintaan_peminjaman_id, aset_id, kondisi_saat_pinjam.
3. `pengembalian_peminjaman`: id, kode, permintaan_peminjaman_id, tanggal_kembali_aktual, diperiksa_oleh, timestamps.
4. `pengembalian_peminjaman_detail`: id, pengembalian_peminjaman_id, permintaan_peminjaman_detail_id, kondisi_saat_kembali (BAIK/RUSAK_RINGAN/RUSAK_BERAT), catatan.
5. Buat Service `App\Services\Aset\PeminjamanAsetService`:
   - `serahTerimaFisik(PermintaanPeminjaman $pinjam)`: Update status transaksi menjadi `DIPINJAM`, ubah `status` master aset menjadi `'DIPINJAM'`, dan mutasikan kolom `unit_id` aset ke unit peminjam.
   - `prosesPengembalian(PermintaanPeminjaman $pinjam, array $data, ?User $actor)`:
     - Jika kondisi BAIK: Aset kembali status `'TERSEDIA'`, lokasi kembali ke unit pemilik atau gudang sentral.
     - Jika kondisi RUSAK_RINGAN / RUSAK_BERAT: Status aset diubah menjadi `'DALAM_MAINTENANCE'`, dan otomatis terbitkan rekaman tiket `PermintaanMaintenance` baru ke antrean teknisi IPSRS dengan prioritas CITO/Sedang.
6. Buatkan model dan relasinya. Jalankan migrasi di dalam container: `docker compose exec app php artisan migrate`.

### Prompt 5.2: Antarmuka Peminjaman & Pembedaan Navigasi (Opsi C Standard)
```text
Bangun antarmuka peminjaman aset dengan komponen Flux UI yang rapi tanpa duplikasi:
1. `PeminjamanAsetIndex` (Livewire Component di `App\Livewire\Aset`):
   - Memiliki 2 tab interaktif menggunakan `<flux:tabs>`: "Peminjaman Aktif" (dengan filter jatuh tempo/overdue `<flux:badge>` dan aksi cepat pengembalian) dan "Katalog Aset Tersedia" dilengkapi tombol generate stiker QR Code aset fisik via `simplesoftwareio/simple-qrcode`.
2. `RiwayatPeminjamanIndex` (Livewire Component di `App\Livewire\Aset`):
   - Menampilkan tabel riwayat peminjaman aset via `<flux:table>`.
3. `PengembalianPeminjamanIndex`:
   - Form penerimaan pengembalian fisik aset dan pencatatan kondisi fisik via `<flux:modal>`.
4. `ApprovalPeminjamanIndex` (Portal Kepala Unit):
   - Tempat Karu ruangan pemilik mengesahkan atau menolak permohonan pinjam aset dari ruangan lain.
5. Verifikasi: Lakukan pengujian peminjaman proyektor/kursi roda -> Approve Karu -> Serah Terima -> Kembalikan dengan kondisi Rusak Ringan. Buktikan tiket maintenance otomatis tercipta di antrean IPSRS.
```

---

## FASE 6: Proses Bisnis 4 - Siklus Pemeliharaan Sarana & IPSRS (Maintenance Cycle)

### 6.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Staf Ruangan Pelapor, Koordinator IPSRS, Teknisi IPSRS, Petugas Gudang Sparepart, Scheduler Cron.
- **Alur Proses Bisnis & State Machine**:
  1. Tiket kerusakan terbit (`DILAPORKAN`, nomor: `MNT-YYYYMM-XXX`) via form lapor, pengembalian aset rusak, atau cron preventif.
  2. Disposisi SPK (`DITUGASKAN`, cetak PDF) -> Teknisi terima tugas (`SEDANG_DIKERJAKAN`).
  3. Permintaan sparepart memotong stok FEFO gudang -> Teknisi isi lembar kerja & uji fungsi bersama ruangan -> SPK ditutup (`SELESAI`) -> Aset kembali `TERSEDIA`/`DIGUNAKAN` kondisi `BAIK`.
- **Kondisi & State Machine**:
  - State Tiket: `DILAPORKAN` -> `DITUGASKAN` -> `SEDANG_DIKERJAKAN` -> `MENUNGGU_SPAREPART` -> `UJI_FUNGSI` -> `SELESAI` (atau `DIBATALKAN`).
  - Rekam Medis Aset: Seluruh riwayat servis, penggantian suku cadang, dan biaya terakumulasi otomatis.

### Prompt 6.1: Migrasi Tabel Pemeliharaan Sarana
```text
Selesaikan tuntas tabel Grup 7 (Pemeliharaan IPSRS):
1. `permintaan_maintenance`: id, kode (unique), aset_id, unit_id, pengembalian_peminjaman_detail_id (nullable), dilaporkan_oleh, deskripsi_masalah, prioritas (RENDAH/SEDANG/TINGGI/CITO), status (DILAPORKAN/DITUGASKAN/SEDANG_DIKERJAKAN/MENUNGGU_SPAREPART/UJI_FUNGSI/SELESAI/DIBATALKAN), timestamps.
2. `penugasan_maintenance` (SPK): id, permintaan_maintenance_id, teknisi_id, ditugaskan_oleh, tanggal_penugasan, status, timestamps.
3. `log_maintenance` (Lembar Kerja): id, permintaan_maintenance_id, dikerjakan_oleh, tanggal_pengerjaan, deskripsi_pekerjaan, hasil (BERHASIL/PENDING/GAGAL), timestamps.
4. `suku_cadang_maintenance`: id, permintaan_maintenance_id, barang_id, stok_batch_id, jumlah_digunakan, pergerakan_stok_id, dicatat_oleh, timestamps.
5. `jadwal_maintenance` (Preventif): id, aset_id, frekuensi (MINGGUAN/BULANAN/TRIWULAN/SEMESTER/TAHUNAN), jenis_maintenance, tanggal_terakhir, tanggal_berikutnya, status (AKTIF/NONAKTIF), timestamps.
6. Buatkan model dan relasinya. Jalankan migrasi di dalam container: `docker compose exec app php artisan migrate`.
```

### Prompt 6.2: Modul Kerja Teknisi & Rekam Medis Aset
```text
Bangun modul pemeliharaan fasilitas IPSRS menggunakan Livewire & Flux UI:
1. `LaporServisIndex` (Portal Unit): Ruangan menginput keluhan kerusakan fasilitas fisik/sarana via `<flux:modal>`.
2. `DaftarSpkPerbaikanIndex` (Portal Teknisi): Daftar disposisi surat perintah kerja teknisi via `<flux:table>` dan cetak fisik SPK resmi via `barryvdh/laravel-dompdf`.
3. `LembarKerjaPengerjaanIndex` (Portal Teknisi):
   - Teknisi mencatat diagnosis teknis, tindakan perbaikan, dan input pemakaian suku cadang.
   - Pemakaian suku cadang memotong stok_batch FEFO gudang logistik dan mencatat mutasi pengeluaran.
4. `PemeliharaanBerkalaIndex`: Kalender visual jadwal servis preventif genset, lift, chiller, AC, dll.
5. `RekamMedisAsetIndex`: Buku rekam jejak digital per aset yang menampilkan riwayat servis, penggantian suku cadang, dan akumulasi biaya pemeliharaan via `<flux:table>`.
6. Verifikasi: Simulasikan 1 laporan kerusakan AC ruangan -> Terbitkan SPK -> Teknisi input pergantian filter/freon -> Tutup SPK. Buktikan status aset kembali Normal dan stok sparepart terpotong.
```

---

## FASE 7: Proses Bisnis 5 - Manajemen Persediaan, FEFO & Stock Opname

### 7.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Petugas Gudang, Kepala Gudang, Auditor Internal (SPI).
- **Alur Proses Bisnis & State Machine**:
  1. Kepala Gudang buka jadwal opname (`BERJALAN`) -> Petugas Gudang hitung fisik *blind count* (impor/ekspor via `fast-excel`).
  2. Selisih = 0 -> Opname `DISETUJUI`/Selesai. Terdapat selisih -> Ajukan penyesuaian stok (`MENUNGGU_APPROVAL`).
  3. Kepala Gudang otorisasi penyesuaian -> Saldo `stok_batch` terupdate atomik & mutasi `PENYESUAIAN` tercatat di `pergerakan_stok`.
- **Kondisi & State Machine**:
  - State Opname: `DRAFT` -> `BERJALAN` -> `MENUNGGU_APPROVAL` -> `DISETUJUI` (atau `DITOLAK`).
  - Constraint: Penyesuaian minus dilarang menghasilkan saldo negatif (`chk_stok_batch_non_negative`).

### Prompt 7.1: Migrasi Opname, Penyesuaian Stok & Check Constraint
```text
Selesaikan tuntas tabel Grup 3 (Persediaan & Stok Opname):
1. `stok_batch`:
   - Pastikan constraint PostgreSQL `jumlah >= 0 AND jumlah_dipesan >= 0` aktif.
   - Tambahkan composite index `$table->index(['barang_id', 'status', 'tanggal_kedaluwarsa'])`.
2. `pergerakan_stok`: Kartu stok lengkap dengan jenis MASUK, KELUAR, PENYESUAIAN, TRANSFER.
3. `stok_opname`: id, kode (unique), gudang_id, tanggal_opname, status (DRAFT/BERJALAN/MENUNGGU_APPROVAL/DISETUJUI/DITOLAK), dibuat_oleh, disetujui_oleh, timestamps.
4. `stok_opname_detail`: id, stok_opname_id, barang_id, stok_batch_id, jumlah_sistem, jumlah_fisik, selisih, catatan.
5. `penyesuaian_stok`: id, kode (unique), stok_opname_id, gudang_id, tanggal, alasan, status (MENUNGGU/DISETUJUI/DITOLAK), diajukan_oleh, disetujui_oleh, timestamps.
6. `stok_ruangan`: Saldo inventaris ruangan. Jalankan migrasi di dalam container: `docker compose exec app php artisan migrate`.
```

### Prompt 7.2: Modul Monitoring FEFO & Stock Opname Lapangan
```text
Bangun halaman kontrol persediaan gudang menggunakan Livewire & Flux UI:
1. `MonitoringStokFefoIndex` (Portal Kepala Gudang):
   - Menampilkan peringatan batch kritis (<90 hari expired) dan komoditas di bawah Reorder Point (ROP) dengan badge warna `<flux:badge>`.
2. `StockOpnameIndex` (Portal Petugas Gudang):
   - Form input perhitungan fisik lapangan (*blind count*) tanpa melihat angka sistem untuk menjaga objektivitas audit.
   - Sediakan tombol ekspor lembar hitung fisik dan impor hasil opname via `rap2hpoutre/fast-excel`.
   - Sediakan cetak label QR code nomor batch persediaan via `simplesoftwareio/simple-qrcode`.
3. `OtorisasiPenyesuaianIndex` (Portal Kepala Gudang):
   - Kepala Gudang memverifikasi tabel selisih opname via `<flux:table>`.
   - Saat disahkan (`DISETUJUI`), sistem secara atomik memperbarui saldo di `stok_batch` dan mencatat mutasi jenis `PENYESUAIAN` pada kartu stok `pergerakan_stok`.
4. `TataLetakRakIndex` & `DenahLokasiIndex`:
   - Visualisasi tata letak fisik rak gudang dan kapasitas isi komoditas.
5. Verifikasi: Lakukan input stock opname dengan selisih -2 unit -> Ajukan penyesuaian -> Otorisasi Kepala Gudang. Buktikan saldo batch berkurang 2 dan tercatat di kartu stok.
```

---

## FASE 8: Proses Bisnis 6 - Tata Kelola Audit Internal, SPI & CAPA

### 8.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Auditor Internal (SPI), Unit/Gudang Terperiksa, Manajemen Direksi.
- **Alur Proses Bisnis & State Machine**:
  1. Mutasi model otomatis ditangkap `HasAuditLog` (JSONB snapshot sebelum vs sesudah).
  2. Auditor telusuri digital forensic diff & uji kepatuhan silang (PO tanpa PR, selisih SPPB vs RCV, varians opname).
  3. Ditemukan pelanggaran -> Terbitkan temuan CAPA (`In Progress`, nomor: `AUD-YYYYMM-XXX`) -> Unit unggah bukti perbaikan (`In Review`) -> Evaluasi valid -> Temuan ditutup (`Closed`).
- **Kondisi & State Machine**:
  - State CAPA: `In Progress` -> `In Review` -> `Closed`.
  - Grading Risiko: `Mayor`, `Moderat`, `Minor` dengan batas waktu (`due_date_capa`).

### Prompt 8.1: Migrasi Audit Trail & CAPA
```text
Selesaikan tuntas tabel Grup 8 (Sistem & Auditing):
1. `log_audit`: id, pengguna_id (nullable), peran_id (nullable), referensi_jenis, referensi_id, aksi (TAMBAH/UBAH/HAPUS/APPROVE/REJECT/ADJUSTMENT), data_sebelum (jsonb), data_sesudah (jsonb), ip_address, device, dibuat_pada.
   - Buat index komposit: `INDEX(referensi_jenis, referensi_id, dibuat_pada)`.
2. `temuan_audit` (CAPA): id, kode (unique), tanggal_audit, objek_audit, ringkasan, tingkat_risiko (Mayor/Moderat/Minor), due_date_capa, status_capa (In Progress/In Review/Closed), timestamps.
3. `notifikasi`: Antrean alert lonceng internal.
4. `pengaturan_sistem`: Parameter key-value aplikasi. Jalankan migrasi di dalam container: `docker compose exec app php artisan migrate`.
```

### Prompt 8.2: Observer Audit Trail Otomatis & Portal Auditor
```text
Bangun modul pengawasan independen Satuan Pemeriksaan Internal (SPI):
1. Buat Trait `App\Traits\HasAuditLog`:
   - Pasang pada Model Eloquent utama (`Barang`, `StokBatch`, `PermintaanDistribusi`, `PesananPembelian`, `PermintaanPeminjaman`, `Aset`).
   - Secara otomatis merekam snapshot diff atribut yang berubah (`$model->getDirty()`) beserta nilai lamanya ke dalam format JSONB pada tabel `log_audit` setiap kali terjadi event model `created`, `updated`, atau `deleted`. Sembunyikan kolom sensitif/noise (`password`, `remember_token`, `diperbarui_pada`).
2. Buat Komponen Livewire Portal Auditor dengan Flux UI:
   - `AuditTrailIndex`: Visualisasi log forensik digital dengan perbandingan diff visual via `<flux:table>` dan `<flux:modal>`.
   - `PemeriksaanSelisihOpnameIndex`: Penelusuran riwayat selisih stok opname fisik.
   - `AuditKepatuhanPengadaanIndex`: Deteksi transaksi PO yang tidak memiliki PR yang sah.
   - `RegisterTemuanCapaIndex`: Form pencatatan temuan audit, penetapan tingkat risiko, batas waktu tindakan perbaikan, dan evaluasi closing.
```

---

## FASE 9: Scheduler Terjadwal, Dashboard Multi-Portal & Final Quality Assurance

### 9.0 Arsitektur Proses, Aktor & Kondisi
- **Aktor**: Console Scheduler (Cron), Super Admin, Seluruh 10 Pengguna Portal.
- **Alur Proses Bisnis & State Machine**:
  1. Cron harian: sweep expired batch (00:01 WIB), alert overdue loan (06:00 WIB), generate preventive maintenance (01:00 WIB).
  2. Super Admin memantau status cron via `SchedulerNotifikasiIndex` dan dapat memicu secara manual.
  3. Pengujian menyeluruh pada 10 portal peran dan verifikasi final checklist.
- **Kondisi & State Machine**:
  - Batch Kedaluwarsa: Kunci status `KEDALUWARSA` dan dilarang dialokasikan pada picking FEFO.
  - Final QA Invariant: Wajib 0 match `hasRoleCode`, 410 permissions terpasang, 0 transaksi dan 0 barang di database seeder.

### Prompt 9.1: Console Scheduler Cron Jobs
```text
Konfigurasikan 3 task terjadwal otomatis pada `routes/console.php`:
1. `simlog:sweep-expired-batches` (dailyAt '00:01'):
   - Mengunci status batch kedaluwarsa menjadi `KEDALUWARSA` dan menandai batch <90 hari menjadi `HAMPIR_KEDALUWARSA`.
2. `simlog:alert-overdue-loans` (dailyAt '06:00'):
   - Memindai peminjaman aset yang melewati `tanggal_kembali_rencana` dan mengirimkan notifikasi internal.
3. `simlog:generate-preventive-maintenance` (dailyAt '01:00'):
   - Memindai `jadwal_maintenance` aktif dan otomatis menerbitkan tiket servis IPSRS jika tanggal berikutnya telah tiba.
4. Buat komponen `SchedulerNotifikasiIndex` di `App\Livewire\System\SchedulerNotifikasiIndex`:
   - Tampilkan kartu status cron menggunakan `<flux:card>`.
   - Sediakan tombol manual trigger di header: "Sweep Kedaluwarsa", "Cek Overdue Pinjaman", dan "Generate Tiket Preventif" (`<flux:button>`) agar Super Admin bisa memicu cron kapan saja.
```

### Prompt 9.2: Dashboard Multi-Portal & Verifikasi Menyeluruh
```text
1. Pastikan setiap dashboard portal (`AdminLogistikDashboard`, `GudangDashboard`, `TeknisiDashboard`, dll.) memvalidasi permission dinamis `$user?->can('page_' . class_basename(static::class))` pada method `render()`.
2. Bersihkan seluruh cache sistem di dalam container: `docker compose exec app php artisan optimize:clear` dan reload PHP-FPM OPcache (`sudo -n kill -USR2 $(sudo -n docker top simrslogv2-app | grep 'php-fpm: master process' | awk '{print $2}')` atau di dalam container: `kill -USR2 1`).
3. Lakukan pengujian login pada seluruh 10 akun resmi:
   - superadmin, adminlogistik, kepalagudang, petugasgudang, pengadaan, kepalaunit, petugasunit, teknisi, manajemen, auditor.
4. Pastikan setiap user diarahkan ke portal yang benar, sidebar menampilkan menu yang sesuai izin Spatie di database, dan tidak ada satupun halaman yang mengembalikan Error 500.
```

---

## PENUTUP & DAFTAR CEK AKHIR REKAYASA ULANG

Sebelum project diserahterimakan, jalankan checklist kualitas berikut di dalam container:
- [ ] `docker compose exec app php -l ...` pada seluruh file di `app/`: Tidak ada error sintaks.
- [ ] `docker compose exec app php artisan test` atau skrip verifikasi: Seluruh siklus transaksi berjalan lancar.
- [ ] `docker compose exec app grep -rn "hasRoleCode" app/`: Wajib 0 match (tidak ada sama sekali) di seluruh Policy, Resource, Page, Action, dan Dashboard. Otorisasi 100% mutlak digerakkan oleh `$user->can(...)`.
- [ ] Database indexing & search: Verifikasi composite index `stok_batch`, GIN Trigram index `idx_barang_nama_trgm` pada `barang`, dan `lockForUpdate()` terpasang pada `DistribusiService`.
- [ ] Transaction safety: Verifikasi seluruh tombol transaksi memiliki `wire:loading.attr="disabled"`, banner `wire:offline` aktif di layout, dan penomoran kode menggunakan `CodeGenerator`.
- [ ] Database fresh check: `docker compose exec app php artisan migrate:fresh --seed` menghasilkan 10 user, 12 role, 410 permissions, 0 barang, dan 0 transaksi.
