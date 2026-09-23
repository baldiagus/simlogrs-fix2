<?php

namespace App\Livewire\User;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Gudang;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class PenggunaIndex extends Component
{
    use HasPortalLayout, HasToast, HasTableFilters, HasConfirmationModal, WithPagination;

    #[Url(history: true)]
    public string $filterRole = '';

    #[Url(history: true)]
    public string $filterStatus = '';

    // Modal Form State
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $nama = '';
    public string $nip = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $no_telepon = '';
    public string $penugasan_tipe = 'none'; // 'none', 'unit', 'gudang'
    public ?int $unit_id = null;
    public ?int $gudang_id = null;
    public string $selectedRole = 'petugas_unit';
    public string $status = 'AKTIF';

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('page_PenggunaIndex') ||
            auth()->user()?->can('view_any_user::pengguna') ||
            auth()->user()?->can('page_SuperAdminDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang mengelola Akun Pengguna.'
        );
    }

    public function updatedPenugasanTipe(string $value): void
    {
        if ($value === 'unit') {
            $this->gudang_id = null;
        } elseif ($value === 'gudang') {
            $this->unit_id = null;
        } else {
            $this->unit_id = null;
            $this->gudang_id = null;
        }
    }

    public function openCreate(): void
    {
        abort_unless(
            auth()->user()?->can('create_user::pengguna') ||
            auth()->user()?->can('page_SuperAdminDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang menambah akun pengguna.'
        );

        $this->reset([
            'editingId',
            'nama',
            'nip',
            'username',
            'email',
            'password',
            'password_confirmation',
            'no_telepon',
            'penugasan_tipe',
            'unit_id',
            'gudang_id',
        ]);
        $this->selectedRole = 'petugas_unit';
        $this->status = 'AKTIF';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(
            auth()->user()?->can('update_user::pengguna') ||
            auth()->user()?->can('page_SuperAdminDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang mengubah akun pengguna.'
        );

        $user = User::with(['roles', 'unit', 'gudang'])->findOrFail($id);
        $this->editingId = $user->id;
        $this->nama = $user->nama ?? '';
        $this->nip = $user->nip ?? '';
        $this->username = $user->username ?? '';
        $this->email = $user->email ?? '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->no_telepon = $user->no_telepon ?? '';
        $this->unit_id = $user->unit_id;
        $this->gudang_id = $user->gudang_id;
        $this->status = $user->status ?? 'AKTIF';

        if ($user->unit_id) {
            $this->penugasan_tipe = 'unit';
        } elseif ($user->gudang_id) {
            $this->penugasan_tipe = 'gudang';
        } else {
            $this->penugasan_tipe = 'none';
        }

        // Ambil peran pertama selain 'panel_user'
        $primaryRole = $user->roles->firstWhere('name', '!=', 'panel_user');
        $this->selectedRole = $primaryRole ? $primaryRole->name : 'petugas_unit';

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'nama'         => 'required|string|max:255',
            'nip'          => 'nullable|string|max:30',
            'username'     => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('pengguna', 'username')->ignore($this->editingId)],
            'email'        => ['required', 'email', 'max:255', Rule::unique('pengguna', 'email')->ignore($this->editingId)],
            'selectedRole' => 'required|exists:roles,name',
            'no_telepon'   => 'nullable|string|max:50',
            'unit_id'      => 'nullable|exists:unit,id',
            'gudang_id'    => 'nullable|exists:gudang,id',
            'status'       => 'required|in:AKTIF,NONAKTIF',
        ];

        if (! $this->editingId) {
            $rules['password'] = 'required|string|min:6|confirmed';
        } else {
            $rules['password'] = 'nullable|string|min:6|confirmed';
        }

        $this->validate($rules);

        $payload = [
            'nama'       => $this->nama,
            'nip'        => $this->nip ?: null,
            'username'   => strtolower($this->username),
            'email'      => strtolower($this->email),
            'no_telepon' => $this->no_telepon ?: null,
            'unit_id'    => $this->penugasan_tipe === 'unit' ? $this->unit_id : null,
            'gudang_id'  => $this->penugasan_tipe === 'gudang' ? $this->gudang_id : null,
            'status'     => $this->status,
        ];

        if (! empty($this->password)) {
            $payload['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);

            // Proteksi anti self-deactivation
            if (auth()->id() === $user->id && $this->status === 'NONAKTIF') {
                $this->toast('Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang aktif.', 'danger');
                return;
            }

            $user->update($payload);
            $user->syncRoles([$this->selectedRole, 'panel_user']);
            $this->toast("Data pegawai {$user->nama} berhasil diperbarui.", 'success');
        } else {
            $user = User::create($payload);
            $user->assignRole([$this->selectedRole, 'panel_user']);
            $this->toast("Akun pegawai {$user->nama} berhasil dibuat.", 'success');
        }

        $this->showModal = false;
    }

    public function confirmResetPassword(int $id): void
    {
        $user = User::findOrFail($id);
        $this->showConfirmation(
            title: 'Reset Kata Sandi Pegawai',
            description: "Reset kata sandi akun {$user->nama} ({$user->username}) ke default kedinasan 'password'?",
            buttonText: 'Ya, Reset Sandi',
            variant: 'primary',
            actionMethod: 'executeResetPassword',
            actionParams: ['id' => $user->id]
        );
    }

    public function executeResetPassword(int $id): void
    {
        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make('password'),
        ]);

        $this->toast("Kata sandi {$user->nama} berhasil direset ke 'password'.", 'success');
    }

    public function confirmToggleStatus(int $id): void
    {
        if (auth()->id() === $id) {
            $this->toast('Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang aktif.', 'danger');
            return;
        }

        $user = User::findOrFail($id);
        $targetStatus = $user->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $labelAksi = $targetStatus === 'AKTIF' ? 'Mengaktifkan kembali' : 'Menonaktifkan (Blokir)';

        $this->showConfirmation(
            title: "Ubah Status Akun Pegawai",
            description: "{$labelAksi} akun {$user->nama} ({$user->username})? Pegawai berstatus nonaktif tidak dapat masuk ke sistem.",
            buttonText: $targetStatus === 'AKTIF' ? 'Aktifkan Akun' : 'Nonaktifkan Akun',
            variant: $targetStatus === 'AKTIF' ? 'primary' : 'danger',
            actionMethod: 'executeToggleStatus',
            actionParams: ['id' => $user->id]
        );
    }

    public function executeToggleStatus(int $id): void
    {
        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            $this->toast('Operasi dibatalkan: Akun aktif diri sendiri.', 'danger');
            return;
        }

        $newStatus = $user->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $user->update(['status' => $newStatus]);

        $this->toast("Status akun {$user->nama} diubah menjadi {$newStatus}.", 'info');
    }

    public function render()
    {
        $query = User::with(['roles', 'unit', 'gudang']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('nama', 'ilike', "%{$this->search}%")
                  ->orWhere('nip', 'ilike', "%{$this->search}%")
                  ->orWhere('username', 'ilike', "%{$this->search}%")
                  ->orWhere('email', 'ilike', "%{$this->search}%");
            });
        }

        if (! empty($this->filterStatus)) {
            $query->where('status', $this->filterStatus);
        }

        if (! empty($this->filterRole)) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $this->filterRole));
        }

        $users = $query->orderBy('nama')->paginate(10);

        $daftarUnit = Unit::where('status', 'AKTIF')->orderBy('nama')->get();
        $daftarGudang = Gudang::where('status', 'AKTIF')->orderBy('nama')->get();
        $daftarRoles = Role::whereNotIn('name', ['panel_user'])->orderBy('name')->get();

        $totalPengguna = User::count();
        $penggunaAktif = User::where('status', 'AKTIF')->count();
        $penggunaNonaktif = User::where('status', 'NONAKTIF')->count();

        return view('livewire.user.pengguna-index', [
            'users'            => $users,
            'daftarUnit'       => $daftarUnit,
            'daftarGudang'     => $daftarGudang,
            'daftarRoles'      => $daftarRoles,
            'totalPengguna'    => $totalPengguna,
            'penggunaAktif'    => $penggunaAktif,
            'penggunaNonaktif' => $penggunaNonaktif,
        ])->layout('layouts.portal', [
            'title'     => 'Manajemen Akun Pengguna',
            'portalKey' => $this->getPortalName(),
        ]);
    }
}
