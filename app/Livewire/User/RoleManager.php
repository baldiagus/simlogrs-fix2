<?php

namespace App\Livewire\User;

use App\Livewire\Concerns\HasToast;
use App\Support\Rbac\PagePermissionRegistry;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleManager extends Component
{
    use HasToast;

    public ?int $selectedRoleId = null;
    public array $state = [
        'dashboards' => [],
        'view_any' => [],
        'create' => [],
        'update' => [],
        'delete' => [],
    ];

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

    /**
     * Hook reaktif Livewire: Ketika 'Lihat Data' dimatikan via wire:model,
     * otomatis matikan Tambah, Ubah, dan Hapus pada modul tersebut.
     */
    public function updated($property, $value): void
    {
        if (str_starts_with($property, 'state.view_any.')) {
            $entityKey = substr($property, 15);

            // Anti self-lockout khusus super_admin pada user::role
            $role = Role::find($this->selectedRoleId);
            if ($role && $role->name === 'super_admin' && $entityKey === 'user::role') {
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

    public function toggleViewAny(string $entityKey)
    {
        $role = Role::find($this->selectedRoleId);
        if ($role && $role->name === 'super_admin' && $entityKey === 'user::role') {
            return; // Anti self-lockout
        }

        $current = ! empty($this->state['view_any'][$entityKey]);
        $newStatus = ! $current;
        $this->state['view_any'][$entityKey] = $newStatus;

        if (! $newStatus) {
            $this->state['create'][$entityKey] = false;
            $this->state['update'][$entityKey] = false;
            $this->state['delete'][$entityKey] = false;
        }
    }

    /**
     * Quick Action per Domain: Aktifkan semua aksi (Lihat, Tambah, Ubah, Hapus).
     */
    public function selectAllDomain(string $domainKey)
    {
        $domains = PagePermissionRegistry::getDomains();
        if (! isset($domains[$domainKey])) return;

        $role = Role::find($this->selectedRoleId);
        $isSuperAdmin = $role && $role->name === 'super_admin';

        foreach ($domains[$domainKey]['modules'] as $modKey => $mod) {
            if ($isSuperAdmin && $modKey === 'user::role') continue;

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
        $domains = PagePermissionRegistry::getDomains();
        if (! isset($domains[$domainKey])) return;

        $role = Role::find($this->selectedRoleId);
        $isSuperAdmin = $role && $role->name === 'super_admin';

        foreach ($domains[$domainKey]['modules'] as $modKey => $mod) {
            if ($isSuperAdmin && $modKey === 'user::role') continue;

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
    public function clearDomain(string $domainKey)
    {
        $domains = PagePermissionRegistry::getDomains();
        if (! isset($domains[$domainKey])) return;

        $role = Role::find($this->selectedRoleId);
        $isSuperAdmin = $role && $role->name === 'super_admin';

        foreach ($domains[$domainKey]['modules'] as $modKey => $mod) {
            if ($isSuperAdmin && $modKey === 'user::role') continue;

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
        abort_unless(auth()->user()?->can('update_user::role'), 403, 'Anda tidak memiliki hak akses mengubah wewenang peran.');

        $role = Role::findOrFail($this->selectedRoleId);

        // Anti self-lockout check untuk super_admin
        if ($role->name === 'super_admin') {
            $this->state['dashboards']['page_Dashboard'] = true;
            $this->state['view_any']['user::role'] = true;
            $this->state['create']['user::role']   = true;
            $this->state['update']['user::role']   = true;
            $this->state['delete']['user::role']   = true;
        }

        $permissions = PagePermissionRegistry::toPermissions($this->state);

        // Pastikan semua permissions terdaftar di Spatie DB
        $existing = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
        $missing = array_diff($permissions, $existing);
        foreach ($missing as $newPerm) {
            Permission::findOrCreate($newPerm, 'web');
        }

        $role->syncPermissions($permissions);

        // Reset cache permission Spatie
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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
        ])->layout('layouts.portal', ['portal' => 'super-admin']);
    }
}
