# API Authorization Checks Inventory

## Overview
This report provides a read-only inventory of every authorization check (`can`, `hasRole`, data scope) across `app/Http/Controllers/Api/V1`. No route-level role/permission middlewares were found in `routes/api.php` aside from standard `auth:sanctum`. All checks are manually implemented within the controller logic.

## Capability Groups & Mapping

The application mixes Spatie roles and permissions with explicit unit/warehouse scoping to gate functionality. Roles are heavily hardcoded into controller logic instead of being abstracted entirely behind permission checks.

### 1. Dashboard & General Capabilities
| Capability / Feature | Controller | Exact Paths/Lines | Mapped Permissions / Roles | Exception / Scope / Rationale |
|---|---|---|---|---|
| View Realisasi Anggaran | `AnggaranApiController` | L23-27, L128-132 | **Roles:** auditor, manajemen, pengadaan, super_admin, admin_logistik<br>**Perms:** view_any_pengadaan::realisasi::anggaran, page_RealisasiAnggaranIndex, page_ManajemenDashboard, page_AuditorDashboard | Direct role and page-level fallback checking. |
| Dashboard Role Resolution | `DashboardApiController` | L41-47 | **Roles:** kepala_teknisi, teknisi, kepala_unit, auditor, manajemen<br>**Perms:** page_*Dashboard | Identifies specific roles for dashboard rendering. |

### 2. Approval Workflow
| Capability / Feature | Controller | Exact Paths/Lines | Mapped Permissions / Roles | Exception / Scope / Rationale |
|---|---|---|---|---|
| Access Approval Data | `ApprovalController` | L21-26, 29-85 | **Roles:** pengadaan, manajemen, auditor, super_admin, admin_logistik, kepala_unit, kepala_gudang | Complex logical flags set based on role combinations (e.g. `$isKaruOnly`, `$isAdminLogistik`). |
| Execute Approval | `ApprovalController` | L143-313 | **Perms:** update_pengadaan::permintaan::pengadaan, update_layanan_unit::approval::karu, update_gudang_stok::distribusi::approval | **Unit/Gudang Scope:** Validates if `$user->unit_id === $amprah->unit_id` (L273) or `$gudang_id === $amprah->gudang_id` (L313). Restricts actions strictly to the user's assigned scope unless they are global admins. |

### 3. Asset & Maintenance (Aset, Ipsrs, Maintenance)
| Capability / Feature | Controller | Exact Paths/Lines | Mapped Permissions / Roles | Exception / Scope / Rationale |
|---|---|---|---|---|
| Peminjaman Aset (Borrowing) | `AsetApiController` | L450-456, 570-576, 612-618 | **Perms:** create_layanan_unit::peminjaman::aset, update_..., delete_...<br>**Roles:** petugas_unit, kepala_unit, auditor, manajemen, pengadaan, super_admin | Checks check both specific capabilities and hardcoded roles simultaneously. |
| Lapor Servis / SPK | `MaintenanceApiController` | L35-43, 145-156, 243-246, 632 | **Perms:** create_layanan_unit::lapor::servis, view_any_layanan_unit::lapor::servis, create_pemeliharaan::spk::perbaikan<br>**Roles:** kepala_teknisi, teknisi, admin_logistik, dsb. | **Scope:** Validates `$user->unit_id === $item->unit_id` (L198, L236) to ensure users can only view/manage maintenance for their unit. |
| Maintenance Execution | `IpsrsApiController` | L40, 119-123, 190-192 | **Perms:** create_pemeliharaan::spk::perbaikan, create_pemeliharaan::pemeliharaan::berkala<br>**Roles:** teknisi, kepala_teknisi | Scoped query in L40 for `teknisi` lacking global admin rights. |

### 4. Distributions & Logistics (Distribusi, Gudang, Persediaan)
| Capability / Feature | Controller | Exact Paths/Lines | Mapped Permissions / Roles | Exception / Scope / Rationale |
|---|---|---|---|---|
| Permintaan Barang | `DistribusiApiController` | L161-168, 231-237 | **Perms:** view_any_layanan_unit::permintaan::barang, create_... | **Scope:** `$user->unit_id === $amprah->unit_id` (L121, L194). Users tied to a unit can only request for their unit. |
| Picking & Penerimaan | `DistribusiApiController`<br>`GudangApiController` | L373-380, 424-428<br>L198, 376-382 | **Perms:** create_gudang_stok::picking::distribusi, create_layanan_unit::penerimaan::barang | Cross-verifies roles like `petugas_gudang`, `kepala_gudang` against specific permission grants. |
| Stok Opname / Otorisasi | `PersediaanApiController` | L326-334, 405-407, 524-526, 685-686 | **Perms:** create_gudang_stok::stok::opname, update_gudang_stok::otorisasi::penyesuaian | **Scope:** Enforces `$user->gudang_id === $gudang->id` (L345, L414) meaning warehouse staff are strictly sandboxed to their assigned warehouse. |

### 5. Procurement (Pengadaan)
| Capability / Feature | Controller | Exact Paths/Lines | Mapped Permissions / Roles | Exception / Scope / Rationale |
|---|---|---|---|---|
| Permintaan Pengadaan | `PengadaanApiController` | L155-158 | **Perms:** create_pengadaan::permintaan::pengadaan | Direct gate. Checks `unitId` and `gudangId` bypass (L165). |
| Pesanan / Penerimaan | `PengadaanApiController` | L270-277, 417-420, 529-532 | **Perms:** view_any_pengadaan::pesanan::pembelian, create_... | Relies heavily on the `pengadaan` role mixed with permission fallbacks. |

## Data Scope Policies (Workflow & Sandboxing)
1. **Unit & Warehouse Filtering**: Controllers consistently employ a manual scope check pattern (`if ($user->unit_id !== $resource->unit_id)`). This is found broadly in `ApprovalController`, `MaintenanceApiController`, `DistribusiApiController`, and `PersediaanApiController`.
2. **Global Admin Bypass**: Users with `super_admin` or `admin_logistik` roles systematically bypass these scopes (e.g., `$isGlobalAdmin` variables defined at the top of controller methods).

## Missing or Contradictory Policies & Unresolved Decisions
- **Hardcoded Role Dependencies**: The API relies heavily on checking specific roles (e.g., `hasRole('kepala_unit')`, `hasRole('pengadaan')`) alongside capability permissions (e.g., `can('create_pengadaan::permintaan::pengadaan')`). This contradicts pure RBAC (Role-Based Access Control) where roles should only be containers for permissions. If a new role needs to perform a specific action, the controller code itself has to be modified rather than just attaching a permission to the role.
- **Scattered Authorization Logic**: Policy decisions and data scoping are handled within individual controller methods instead of Laravel Policies or standard middleware. This raises the risk of forgetting a scope check in new endpoints.
- **Redundant Capabilities**: Frequent checks like `$user->hasRole('petugas_unit') || $user->can('create_layanan_unit::...')` suggest that either the role seed missing the permission caused a hotfix in code, or there is an unresolved decision about whether capability strictly dictates access versus role title dictating access.
- **Seed Mapping**: `ShieldRolePermissionSeeder` plants all registered permissions and assigns all of them to `super_admin`. However, because controllers contain explicit `hasRole('super_admin')` checks, the seeded permissions for the super admin are theoretically redundant in many places.

## Conclusion
The API auth layer currently operates on a hybrid model that heavily intertwines explicit role-checking, fine-grained permissions, and manual data scoping logic. No blanket replacements are proposed, but any future refactoring should centralize the scoping rules (e.g., through Eloquent global scopes or Laravel Policies) to avoid maintaining repetitive `$user->unit_id === $model->unit_id` checks across controllers.
