# ADR 0006: Isolasi Portal Terikat Peran (1 Role = 1 Portal)

Status: Diterima

## Konteks

Sebelumnya, `PortalAccessMiddleware` hanya memvalidasi apakah pengguna memiliki permission dashboard terkait (`can('page_*Dashboard')`). Karena role `super_admin` diberikan seluruh permission (`Permission::all()`), akun Super Admin dapat mengakses dan membuka URL seluruh portal kerja peran lain (`/auditor/*`, `/admin-logistik/*`, `/kepala-gudang/*`, dll.).

Hal ini menimbulkan masalah:
1. **Ketiadaan Scope**: Akun Super Admin tidak terikat `unit_id` atau `gudang_id` spesifik, sehingga tampilan dashboard operasional unit/gudang menampilkan data yang tidak representatif atau membingungkan.
2. **Pelanggaran Separation of Duties**: Super Admin adalah administrator IT yang diaudit, sedangkan Auditor SPI adalah organ pengawas independen. Mengizinkan Super Admin masuk ke portal kerja Auditor melanggar tata kelola kepatuhan rumah sakit.
3. **False Affordance**: Super Admin dapat melihat antrean persetujuan peran lain tetapi akan ditolak (403) saat mencoba mengeksekusi aksi karena ADR 0001 mensyaratkan role workflow yang sah.
4. **Redundansi Rute**: Seluruh modul sistem (Master, Pengadaan, Distribusi, Aset, IPSRS, Persediaan, dan Audit Trail) sudah tersedia lengkap di dalam prefix `/super-admin/*`. Super Admin tidak perlu melompat ke portal lain untuk pengawasan data.

## Keputusan

1. **Prinsip 1 Role = 1 Portal Terisolasi**:
   Setiap pengguna hanya diizinkan mengakses ruang kerja (namespace route) yang sesuai dengan peran utama resminya:
   - `super_admin` -> `/super-admin/*`
   - `admin_logistik` -> `/admin-logistik/*`
   - `kepala_gudang` -> `/kepala-gudang/*`
   - `petugas_gudang` -> `/petugas-gudang/*`
   - `pengadaan` -> `/pengadaan/*`
   - `kepala_unit` -> `/kepala-unit/*`
   - `petugas_unit` -> `/petugas-unit/*`
   - `kepala_teknisi` / `teknisi` -> `/teknisi/*`
   - `manajemen` -> `/manajemen/*`
   - `auditor` -> `/auditor/*`

2. **Penegakan di Middleware (`PortalAccessMiddleware`)**:
   Middleware memeriksa slug portal di URL terhadap pemetaan resmi peran pengguna. Jika pengguna mencoba mengakses portal peran lain, sistem langsung melempar respon **403 Forbidden**.

3. **Wewenang Super Admin Tetap Otoritas Penuh RBAC**:
   - Super Admin tetap memiliki seluruh permission operasional, master data, dan audit trail di dalam portal `/super-admin/*`.
   - Super Admin tetap memiliki otoritas mutlak untuk mengelola, memberikan (*grant*), dan mencabut permission untuk seluruh peran melalui antarmuka Manajemen Peran (`/super-admin/roles`).
   - Sembilan permission dashboard peran lain (`page_AdminLogistikDashboard`, `page_AuditorDashboard`, dll.) dicabut dari default role `super_admin` di seeder dan matriks peran, sehingga Super Admin hanya memegang `page_Dashboard`.

## Konsekuensi

- Upaya akses lintas portal oleh role apa pun (termasuk Super Admin) akan ditolak 403.
- Pengguna yang ingin melihat data operasional lintas peran harus menggunakan akun peran terkait atau menggunakan modul resmi di dalam portal Super Admin.
