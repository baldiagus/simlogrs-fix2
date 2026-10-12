<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasToast;
use App\Models\Gudang;
use App\Models\Unit;
use App\Support\Auth\ContextManager;
use Livewire\Component;

class SuperAdminDashboard extends Component
{
    use HasToast, HasConfirmationModal;

    public bool $showGudangModal = false;
    public bool $showUnitModal = false;
    public ?int $selectedGudangId = null;
    public ?int $selectedUnitId = null;

    protected function authorizeEmulasi(): void
    {
        // Emulasi konteks hanya untuk super_admin di portalnya; page_Dashboard bukan bypass tanpa role (ADR 0001).
        abort_unless(auth()->user()?->hasRole('super_admin') && auth()->user()->can('page_Dashboard'), 403);
    }

    public function setGudangEmulasi(int $id)
    {
        $this->authorizeEmulasi();
        app(ContextManager::class)->setGudangId($id);
        $this->showGudangModal = false;
        $this->toast('Mode emulasi gudang aktif: ' . Gudang::find($id)?->nama, 'info');
    }

    public function setUnitEmulasi(int $id)
    {
        $this->authorizeEmulasi();
        app(ContextManager::class)->setUnitId($id);
        $this->showUnitModal = false;
        $this->toast('Mode emulasi unit aktif: ' . Unit::find($id)?->nama, 'info');
    }
    public function confirmResetKonteksGlobal(): void
    {
        $this->askConfirmation(
            target: null,
            action: 'resetKonteksGlobal',
            title: 'Konfirmasi Reset Mode Global',
            description: 'Kembalikan sudut pandang data ke mode global seluruh rumah sakit? Mode emulasi unit/gudang akan dinonaktifkan.',
            buttonText: 'Ya, Reset Global',
            variant: 'danger'
        );
    }


    public function resetKonteksGlobal()
    {
        $this->authorizeEmulasi();
        app(ContextManager::class)->resetGlobalContext();
        $this->toast('Konteks dikembalikan ke mode global seluruh rumah sakit.', 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('page_Dashboard') || auth()->user()?->can('page_SuperAdminDashboard'), 403);

        $daftarGudang = Gudang::where('status', 'AKTIF')->get();
        $daftarUnit = Unit::where('status', 'AKTIF')->get();
        $contextManager = app(ContextManager::class);

        return view('livewire.dashboard.super-admin-dashboard', [
            'daftarGudang' => $daftarGudang,
            'daftarUnit' => $daftarUnit,
            'contextManager' => $contextManager,
        ])->layout('layouts.portal', ['portal' => 'super-admin', 'title' => 'Dashboard Super Admin']);
    }
}
