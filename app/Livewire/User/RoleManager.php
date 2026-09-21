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
    public string $activeTab = 'pages'; // pages, view_any, create, update, delete
    public array $state = [
        'pages' => [],
        'view_any' => [],
        'view' => [],
        'create' => [],
        'update' => [],
        'delete' => [],
    ];

    public function mount()
    {
        // Default ke role pertama jika ada
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

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function toggleAllDomain(string $domainKey, string $actionType, bool $status)
    {
        $domains = PagePermissionRegistry::getDomains();
        if (! isset($domains[$domainKey])) return;

        $role = Role::find($this->selectedRoleId);
        $isSuperAdmin = $role && $role->name === 'super_admin';

        foreach ($domains[$domainKey]['modules'] as $modKey => $mod) {
            // Anti self-lockout safeguard
            if ($isSuperAdmin && $modKey === 'user::role') {
                continue;
            }

            if ($actionType === 'pages') {
                $perm = ! empty($mod['page_only']) ? $mod['permission'] : ($mod['page'] ?? null);
                if ($perm) {
                    $this->state['pages'][$perm] = $status;
                }
            } else {
                if (empty($mod['page_only'])) {
                    $entity = $mod['entity'];
                    $this->state[$actionType][$entity] = $status;
                    if ($actionType === 'view_any') {
                        $this->state['view'][$entity] = $status;
                    }
                }
            }
        }
    }

    public function save()
    {
        abort_unless(auth()->user()?->can('update_user::role'), 403, 'Anda tidak memiliki hak akses mengubah wewenang peran.');

        $role = Role::findOrFail($this->selectedRoleId);

        // Anti self-lockout check untuk super_admin
        if ($role->name === 'super_admin') {
            $this->state['pages']['page_RoleManager'] = true;
            $this->state['view_any']['user::role'] = true;
            $this->state['update']['user::role'] = true;
        }

        $permissions = PagePermissionRegistry::toPermissions($this->state);

        // Pastikan semua permissions terdaftar di database sebelum disinkronkan
        $existing = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
        $missing = array_diff($permissions, $existing);
        foreach ($missing as $newPerm) {
            Permission::findOrCreate($newPerm, 'web');
        }

        $role->syncPermissions($permissions);

        // Reset cache permission Spatie
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->toast("Hak akses untuk peran '{$role->name}' berhasil diperbarui.", 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_user::role') || auth()->user()?->can('view_user::role'), 403);

        $roles = Role::orderBy('id')->get();
        $selectedRole = $this->selectedRoleId ? Role::find($this->selectedRoleId) : null;
        $domains = PagePermissionRegistry::getDomains();

        return view('livewire.user.role-manager', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'domains' => $domains,
        ])->layout('layouts.portal', ['portal' => 'super-admin']);
    }
}
