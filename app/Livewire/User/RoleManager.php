<?php

namespace App\Livewire\User;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasToast;
use App\Support\Rbac\PagePermissionRegistry;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Audit\LogAudit;
use Illuminate\Support\Facades\DB;

class RoleManager extends Component
{
    use HasToast, HasConfirmationModal;

    public ?int $selectedRoleId = null;
    public array $state = [
        'dashboards' => [],
        'view_any' => [],
        'create' => [],
        'update' => [],
        'delete' => [],
    ];

    public function boot(): void
    {
        abort_unless(auth()->user()?->can('view_any_user::role') || auth()->user()?->can('view_user::role') || auth()->user()?->hasRole('super_admin'), 403);
    }

    public function mount()
    {
        $firstRole = Role::first();
        if ($firstRole) {
            $this->selectRole($firstRole->id);
        }
    }

    public function selectRole(int $roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);
        $this->selectedRoleId = $role->id;
        $this->state = PagePermissionRegistry::fromRole($role);
    }

    public function canManageSelectedRole(): bool
    {
        if (! $this->selectedRoleId) {
            return false;
        }

        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Super admin memiliki wewenang penuh mengelola semua peran
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Non-super-admin wajib memiliki wewenang update_user::role
        if (! $user->can('update_user::role')) {
            return false;
        }

        $role = Role::find($this->selectedRoleId);
        if (! $role) {
            return false;
        }

        // Non-super-admin dilarang mengubah wewenang peran super_admin
        if ($role->name === 'super_admin') {
            return false;
        }

        // Non-super-admin dilarang mengubah peran yang aktif melekat pada dirinya sendiri (anti self-promotion)
        if ($user->hasRole($role->name)) {
            return false;
        }

        return true;
    }

    /**
     * Hook reaktif Livewire: Ketika 'Lihat Data' dimatikan via wire:model,
     * otomatis matikan Tambah, Ubah, dan Hapus pada modul tersebut.
     */
    public function updated($property, $value): void
    {
        if (! $this->canManageSelectedRole()) {
            return;
        }

        if (str_starts_with($property, 'state.view_any.')) {
            $entityKey = substr($property, 15);

            // Anti self-lockout khusus super_admin pada user::role dan user::pengguna
            $role = Role::find($this->selectedRoleId);
            if ($role && $role->name === 'super_admin' && in_array($entityKey, ['user::role', 'user::pengguna'])) {
                $this->state['view_any'][$entityKey] = true;
                return;
            }

            // Jika 'Lihat Data' dimatikan, otomatis matikan izin Tambah, Ubah, dan Hapus
            if (! $value) {
                $this->state['create'][$entityKey] = false;
                $this->state['update'][$entityKey] = false;
                $this->state['delete'][$entityKey] = false;
            }
        }
    }



    /**
     * Quick Action per Domain: Aktifkan semua aksi (Lihat, Tambah, Ubah, Hapus).
     */
    public function selectAllDomain(string $domainKey)
    {
        abort_unless($this->canManageSelectedRole(), 403, 'Anda tidak diizinkan mengubah wewenang peran ini.');

        $domains = PagePermissionRegistry::getDomains();
        if (! isset($domains[$domainKey])) return;

        $role = Role::find($this->selectedRoleId);
        $isSuperAdmin = $role && $role->name === 'super_admin';

        foreach ($domains[$domainKey]['modules'] as $modKey => $mod) {
            if ($isSuperAdmin && in_array($modKey, ['user::role', 'user::pengguna'])) continue;

            if ($domainKey === 'dashboard_portal') {
                $this->state['dashboards'][$mod['permission']] = true;
            } else {
                $entity = $mod['entity'];
                $this->state['view_any'][$entity] = true;
                $this->state['create'][$entity]   = true;
                $this->state['update'][$entity]   = true;
                $this->state['delete'][$entity]   = true;
            }
        }
    }

    /**
     * Quick Action per Domain: Aktifkan hanya 'Lihat Data' (View Only).
     */
    public function selectViewOnlyDomain(string $domainKey)
    {
        abort_unless($this->canManageSelectedRole(), 403, 'Anda tidak diizinkan mengubah wewenang peran ini.');

        $domains = PagePermissionRegistry::getDomains();
        if (! isset($domains[$domainKey])) return;

        $role = Role::find($this->selectedRoleId);
        $isSuperAdmin = $role && $role->name === 'super_admin';

        foreach ($domains[$domainKey]['modules'] as $modKey => $mod) {
            if ($isSuperAdmin && in_array($modKey, ['user::role', 'user::pengguna'])) continue;

            if ($domainKey === 'dashboard_portal') {
                $this->state['dashboards'][$mod['permission']] = true;
            } else {
                $entity = $mod['entity'];
                $this->state['view_any'][$entity] = true;
                $this->state['create'][$entity]   = false;
                $this->state['update'][$entity]   = false;
                $this->state['delete'][$entity]   = false;
            }
        }
    }

    /**
     * Quick Action per Domain: Kosongkan seluruh hak akses di domain ini.
     */
    public function confirmClearDomain(string $domainKey): void
    {
        abort_unless($this->canManageSelectedRole(), 403, 'Anda tidak diizinkan mengubah wewenang peran ini.');

        $domains = PagePermissionRegistry::getDomains();
        $domainLabel = $domains[$domainKey]['label'] ?? $domainKey;
        $role = Role::find($this->selectedRoleId);

        $this->askConfirmation(
            target: $domainKey,
            action: 'clearDomain',
            title: 'Konfirmasi Kosongkan Wewenang',
            description: "Kosongkan seluruh checklist hak akses pada domain '{$domainLabel}' untuk peran '{$role?->name}'?",
            buttonText: 'Ya, Kosongkan Domain',
            variant: 'danger'
        );
    }

    public function clearDomain(string $domainKey)
    {
        abort_unless($this->canManageSelectedRole(), 403, 'Anda tidak diizinkan mengubah wewenang peran ini.');

        $domains = PagePermissionRegistry::getDomains();
        if (! isset($domains[$domainKey])) return;

        $role = Role::find($this->selectedRoleId);
        $isSuperAdmin = $role && $role->name === 'super_admin';

        foreach ($domains[$domainKey]['modules'] as $modKey => $mod) {
            if ($isSuperAdmin && in_array($modKey, ['user::role', 'user::pengguna'])) continue;

            if ($domainKey === 'dashboard_portal') {
                $this->state['dashboards'][$mod['permission']] = false;
            } else {
                $entity = $mod['entity'];
                $this->state['view_any'][$entity] = false;
                $this->state['create'][$entity]   = false;
                $this->state['update'][$entity]   = false;
                $this->state['delete'][$entity]   = false;
            }
        }
    }

    public function save()
    {
        abort_unless(
            auth()->user()?->can('update_user::role') || auth()->user()?->hasRole('super_admin'),
            403,
            'Anda tidak memiliki hak akses mengubah wewenang peran.'
        );

        $role = Role::findOrFail($this->selectedRoleId);

        // Batasi mutasi peran super_admin hanya untuk pemegang peran super_admin
        if ($role->name === 'super_admin' && ! auth()->user()?->hasRole('super_admin')) {
            abort(403, 'Wewenang peran super_admin hanya dapat diubah oleh pengguna dengan peran super_admin.');
        }

        // Larang pengguna non-super-admin mengubah wewenang peran yang sedang aktif pada dirinya sendiri (Anti Self-Promotion)
        if (! auth()->user()?->hasRole('super_admin') && auth()->user()?->hasRole($role->name)) {
            abort(403, 'Anda dilarang mengubah wewenang dari peran yang sedang aktif melekat pada akun Anda sendiri.');
        }

        // Anti self-lockout check untuk super_admin (Dashboard, user::role, dan user::pengguna)
        if ($role->name === 'super_admin') {
            $this->state['dashboards']['page_Dashboard'] = true;

            $this->state['view_any']['user::role'] = true;
            $this->state['create']['user::role']   = true;
            $this->state['update']['user::role']   = true;
            $this->state['delete']['user::role']   = true;

            $this->state['view_any']['user::pengguna'] = true;
            $this->state['create']['user::pengguna']   = true;
            $this->state['update']['user::pengguna']   = true;
            $this->state['delete']['user::pengguna']   = true;
        }

        $matrixPermissions = PagePermissionRegistry::toPermissions($this->state);

        // Pertahankan izin non-matriks (kustom) yang sudah melekat pada peran di database
        $nonMatrixPermissions = PagePermissionRegistry::getNonMatrixPermissions($role);
        $finalPermissions = array_values(array_unique(array_merge($matrixPermissions, $nonMatrixPermissions)));

        // Registrasi izin, sinkronisasi wewenang, dan audit dalam satu transaksi (ADR 0002 B)
        try {
            DB::transaction(function () use ($role, $finalPermissions) {
                // Pastikan semua permissions terdaftar di Spatie DB
                $existing = Permission::whereIn('name', $finalPermissions)->pluck('name')->toArray();
                $missing = array_diff($finalPermissions, $existing);
                foreach ($missing as $newPerm) {
                    Permission::findOrCreate($newPerm, 'web');
                }
                $oldPermissions = $role->permissions()->pluck('name')->sort()->values()->toArray();

                $role->syncPermissions($finalPermissions);

                $newPermissions = collect($finalPermissions)->sort()->values()->toArray();
                $addedPermissions = array_values(array_diff($newPermissions, $oldPermissions));
                $removedPermissions = array_values(array_diff($oldPermissions, $newPermissions));

                LogAudit::create([
                    'pengguna_id'     => auth()->id(),
                    'peran_id'        => auth()->user()?->roles?->first()?->id,
                    'referensi_jenis' => 'Role',
                    'referensi_id'    => $role->id,
                    'aksi'            => 'RBAC_MUTATION',
                    'data_sebelum'    => [
                        'role_name'        => $role->name,
                        'permission_count' => count($oldPermissions),
                        'permissions'      => $oldPermissions,
                    ],
                    'data_sesudah'    => [
                        'role_name'        => $role->name,
                        'permission_count' => count($newPermissions),
                        'permissions'      => $newPermissions,
                        'added'            => $addedPermissions,
                        'removed'          => $removedPermissions,
                    ],
                    'ip_address'      => request()->ip(),
                    'device'          => substr(request()->userAgent() ?? 'Web Browser', 0, 255),
                    'dibuat_pada'     => now(),
                ]);
            });
        } finally {
            // Reset cache permission Spatie (juga saat rollback agar cache tidak memuat state yang dibatalkan)
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->toast("Wewenang untuk peran '{$role->name}' berhasil diperbarui.", 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_user::role') || auth()->user()?->can('view_user::role'), 403);

        $roles = Role::orderBy('id')->get();
        $selectedRole = $this->selectedRoleId ? Role::with('permissions')->find($this->selectedRoleId) : null;
        $domains = PagePermissionRegistry::getDomains();

        return view('livewire.user.role-manager', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'selectedRoleId' => $this->selectedRoleId,
            'domains' => $domains,
            'canManage' => $this->canManageSelectedRole(),
        ])->layout('layouts.portal', ['portal' => 'super-admin']);
    }
}
