# SIMLOG RS Non-Medis v2 - RBAC & Navigation Inventory Report

## 1. Route Layers & Permission Enforcement
- **Portal Routing (`routes/web.php`)**: The application registers 10 isolated portal route groups (`super-admin`, `admin-logistik`, `kepala-gudang`, `petugas-gudang`, `pengadaan`, `kepala-unit`, `petugas-unit`, `teknisi`, `manajemen`, `auditor`). Each group is strictly governed by a dynamic middleware gate.
- **Middleware Gates (`PortalAccessMiddleware.php`)**: Validates active session and enforces portal access via a zero-hardcode map (`$portalPermissionMap`). For example, accessing the `/super-admin` prefix strictly requires the `page_Dashboard` Spatie permission; `/kepala-gudang` requires `page_KepalaGudangDashboard`.
- **Component-Level Execution (`Livewire Controllers`)**: Granular authorization relies on `abort_unless(auth()->user()?->can('...'), 403)` at the top of every CRUD method (e.g., `render()`, `create()`, `edit()`, `delete()`). For example, in `BarangIndex.php`, `render()` strictly requires `view_any_master::barang` or `view_master::barang`.

## 2. Permission Definitions & Assignments
- **Registry (`PagePermissionRegistry.php`)**: Operates as the absolute source of truth for 8 functional domains and 58 modules. It dynamically generates granular Spatie permissions based on a 4-column matrix model (`view_any_{entity}`, `view_{entity}`, `create_{entity}`, `update_{entity}`, `delete_{entity}`, and `page_{PageClass}`). Selecting "View Data" auto-bundles `page_*` and `view_any_*`.
- **Seeding (`ShieldRolePermissionSeeder.php`)**: Allocates generated matrix permissions to default roles utilizing wildcard patterns (e.g., `%_layanan_unit::approval::karu%`). Super Admin is synced to all existing permissions via `Permission::all()`.

## 3. Navigation Views
- **Sidebar Menu (`resources/views/layouts/portal.blade.php`)**: UI navigation links and groups strictly utilize `@can()` Blade directives matching the Spatie matrix capabilities (e.g., `@can('view_any_master::barang')` for the "Katalog Barang" menu). 

## 4. Capability Permissions vs Role Workflow Rules
- **Capability Permission**: Handled fundamentally by Spatie roles and the generated matrix (e.g., `update_layanan_unit::pengalihan::aset`). It solely determines if a button renders or a Livewire action executes (Capability).
- **Role Workflow Rules / Data Scope**: Business logic, workflow transitions (e.g., `DIAJUKAN` to `DISETUJUI`), and status requirements sit downstream inside Services (e.g., `PengalihanAsetService`) and Livewire actions, separated entirely from capability grants. RBAC authorizes the *execution* of the approval; the service asserts the *validity* of the workflow state.

## 5. Access Test Coverage
- **Unit/Feature Tests (`tests/Feature/Aset/PengalihanAsetRbacTest.php`)**: Validates RBAC slicing precisely.
  - Users without explicit permissions (`User Polos`) hit `assertForbidden()` when mounting the page.
  - Users with view-only (`User Viewer`) can load the page but hit `assertForbidden()` trying to call `openCreateModal`.
  - Roles are logically separated into `Viewer`, `Creator`, and `Approver`, enforcing strict isolation on `create` and `update` capabilities.
- **Navigation/Hub Tests (`PengadaanHubNavigationTest.php`)**: Checks visibility of hub tabs directly via Blade rendering, ensuring menus render exclusively for authorized users.

## 6. Identified Gaps & Unresolved Policy Decisions
- **Portal Route Grouping Limitation**: Since modules reside beneath portal prefixes (e.g., `/kepala-gudang/barang`), a user who receives a granular permission for a module (`view_any_master::barang`) but lacks the parent portal permission (`page_KepalaGudangDashboard`) cannot access the module route. Cross-portal modular access requires portal-permission redundancy.
- **View Granularity**: `PagePermissionRegistry::toPermissions()` couples `view_any_{entity}` and `page_{PageClass}` automatically when "View Data" is checked. Strict separation of "Viewing the list" vs "Viewing the detail record" cannot be independently toggled through the matrix state.
- **Livewire AJAX Context Fallback**: `PortalResolver::resolve()` falls back to checking the first active role when the `Referer` header is absent during Livewire AJAX calls. If a user holds multiple roles (e.g., `admin_logistik` and `kepala_gudang`), the fallback selection is non-deterministic and could resolve the wrong portal context.
