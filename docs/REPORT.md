# Authorization Policy Inventory & Analysis

This report provides a read-only inventory of the authorization checks (`can`, `hasRole`, etc.) across `app/Livewire`, `resources/views`, and `app/Http/Controllers/Api` in the Simlog application.

## 1. Capabilities & Checked Patterns

The application uses a hybrid authorization model combining granular permissions, hardcoded role checks, and data scope ownership rules.

### Typical Pattern
```php
// 1. Permission Gate or Role Check
$isGlobalAdmin = $user->hasRole(['super_admin', 'admin_logistik']) || $user->can('page_Dashboard');

// 2. Data Scope Ownership (Unit/Gudang)
$isOwner = ($user->unit_id && $user->unit_id == $model->unit_id) || 
           ($user->gudang_id && $user->gudang_id == $model->gudang_id);

// 3. Action Authorization
$canAction = $user->can('create_...') || $user->hasRole('...');
```

### Grouped Capabilities

| Capability Group | Relevant Permissions / Gates | Common Hardcoded Roles | Data Scopes Enforced |
|------------------|------------------------------|------------------------|----------------------|
| **Master Data** | `view_any_master::*`, `create_master::*`, `update_master::*`, `delete_master::*` | `super_admin`, `admin_logistik` | None (Global) |
| **Peminjaman Aset** | `layanan_unit::peminjaman::aset`, `layanan_unit::approval::peminjaman` | `petugas_unit`, `kepala_unit`, `pengadaan`, `manajemen`, `auditor` | `unit_id`, `gudang_id` |
| **Pengalihan Aset/Barang** | `layanan_unit::pengalihan::aset`, `pengalihan::barang` | `kepala_unit`, `kepala_gudang`, `petugas_unit`, `petugas_gudang` | `unit_id` (asal/tujuan), `gudang_id` (asal/tujuan) |
| **Pemeliharaan (IPSRS)** | `layanan_unit::lapor::servis`, `pemeliharaan::spk::perbaikan`, `pemeliharaan::lembar::kerja` | `kepala_teknisi`, `teknisi` | `teknisi_id`, `unit_id` |
| **Distribusi (Amprah)** | `layanan_unit::permintaan::barang`, `approval::karu`, `gudang_stok::distribusi::approval`, `picking::distribusi` | `kepala_unit`, `kepala_gudang`, `petugas_gudang` | `unit_id`, `gudang_id` |
| **Gudang & Stok** | `stok::ruangan`, `monitoring::fefo`, `buku::mutasi`, `stok::opname`, `otorisasi::penyesuaian` | `kepala_gudang`, `petugas_gudang` | `unit_id`, `gudang_id` |
| **Pengadaan** | `permintaan::pengadaan`, `pesanan::pembelian`, `penerimaan::po`, `realisasi::anggaran` | `pengadaan` | None (Usually Global or limited to Pengadaan) |
| **Audit & Manajemen**| `audit::trail`, `kepatuhan::pengadaan`, `temuan::capa`, `selisih::opname` | `auditor`, `manajemen` | None (Usually Global viewing) |

## 2. Identified Inconsistencies & Contradictory Policies

### A. Mixing `page_*` Permissions for Action/Global Admin Authorization
Many controllers and Livewire components use `page_Dashboard` or `page_AdminLogistikDashboard` to infer "Global Admin" status or to allow actions (like create/update).
*Example (`app/Livewire/Distribusi/StokRuanganIndex.php:105`)*:
```php
$isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->hasRole(['super_admin', 'admin_logistik']));
```
*Rationale/Evidence*: `page_*` should restrict view rendering, not infer action authorization or data scope bypassing.

### B. Redundant Role Fallbacks (Bypassing Permission Gates)
The code frequently checks `$user->can('create_X') || $user->hasRole('some_role')`.
*Example (`app/Http/Controllers/Api/V1/AsetApiController.php:450-456`)*:
```php
$user->can('create_layanan_unit::peminjaman::aset') || $user->hasRole('petugas_unit') || $user->hasRole('kepala_unit') || $user->hasRole('auditor') ...
```
*Rationale/Evidence*: If the role is seeded with the permission, the `hasRole` check is redundant. If it is not, this hardcoded check bypasses the dynamic RBAC system entirely, rendering UI permission toggling ineffective.

### C. Inconsistent "Global Admin" Definition
The definition of who can bypass data scopes (`unit_id` / `gudang_id`) fluctuates wildly across the application.
- In `StokRuanganIndex.php`: `$isGlobalAdmin` is `super_admin` or `admin_logistik`.
- In `AsetApiController.php:1110`: `$isGlobalAdmin` includes `manajemen` in some methods, but excludes it in others.
- In `ApprovalController.php:30`: `$isAdminOrAudit` combines `super_admin`, `admin_logistik`, `manajemen`, and `auditor`.

### D. Data Scope Inconsistencies
When checking if a user owns an asset/stock, the application usually does:
```php
$isOwner = ($user->unit_id && (int) $user->unit_id === (int) $aset->unit_id) || ($user->gudang_id && (int) $user->gudang_id === (int) $aset->gudang_id);
```
However, in some places (like `AsetApiController.php:1049`), it explicitly requires the user to also have the `kepala_unit` role to be considered an owner for updates:
```php
($user->unit_id && (int) $user->unit_id === (int) $item->unit_asal_id && $user->hasRole('kepala_unit'))
```
while in other places (like `StokRuanganIndex.php:106`), just matching the `unit_id` is sufficient regardless of role.

## 3. Unresolved Policy Decisions

1. **Role of Auditor and Manajemen:** Should `auditor` and `manajemen` have true read-only global access, or should they be considered Global Admins who can modify data? Currently, they are sometimes granted creation/update rights via hardcoded `hasRole` arrays.
2. **Permission Scope vs. Role Checks:** Should the application migrate to a purely permission-based gate (e.g., `can('update_layanan_unit::peminjaman::aset')`) and remove fallback `hasRole('kepala_unit')`, relying entirely on Spatie's Role-Permission mapping? (Currently heavily mixed).
3. **Dashboard Permissions vs. Global Overrides:** Should `page_Dashboard` continue to be used as a proxy for "I am a super admin"?

## 4. Representative Sample of Exact Lines
- **Livewire**: `app/Livewire/Distribusi/StokRuanganIndex.php:105` mixes permission/role for GlobalAdmin.
- **Livewire**: `app/Livewire/Aset/PeminjamanAsetIndex.php:617-620` mixes `page_Dashboard` and `manage_all` permissions.
- **Blade**: `resources/views/layouts/portal.blade.php:223` massive `@if` using `can()` to show/hide menus.
- **API**: `app/Http/Controllers/Api/V1/AsetApiController.php:450-456` 7-part OR condition mixing `can()` and `hasRole()`.
- **API**: `app/Http/Controllers/Api/V1/PersediaanApiController.php:253-259` Checks array of roles plus multiple `page_*` permissions to grant view access.