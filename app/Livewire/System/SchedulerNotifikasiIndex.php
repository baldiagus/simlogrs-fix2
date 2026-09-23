<?php

namespace App\Livewire\System;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasToast;
use App\Models\Notifikasi;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class SchedulerNotifikasiIndex extends Component
{
    use HasPortalLayout, HasToast;

    public bool $isProcessing = false;

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('page_SchedulerNotifikasiIndex') ||
            auth()->user()?->can('view_any_system::scheduler') ||
            auth()->user()?->can('page_SuperAdminDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang mengelola Task Scheduler.'
        );
    }

    public function triggerSweepExpired(): void
    {
        $this->isProcessing = true;
        try {
            Artisan::call('simlog:sweep-expired-batches');
            $output = trim(Artisan::output());
            $this->toast("Task Sweep Expired Batches berhasil dijalankan: {$output}", 'success');
        } catch (\Throwable $e) {
            $this->toast("Gagal menjalankan Sweep Expired Batches: {$e->getMessage()}", 'danger');
        } finally {
            $this->isProcessing = false;
        }
    }

    public function triggerAlertOverdue(): void
    {
        $this->isProcessing = true;
        try {
            Artisan::call('simlog:alert-overdue-loans');
            $output = trim(Artisan::output());
            $this->toast("Task Alert Overdue Loans berhasil dijalankan: {$output}", 'success');
        } catch (\Throwable $e) {
            $this->toast("Gagal menjalankan Alert Overdue Loans: {$e->getMessage()}", 'danger');
        } finally {
            $this->isProcessing = false;
        }
    }

    public function triggerGeneratePreventive(): void
    {
        $this->isProcessing = true;
        try {
            Artisan::call('simlog:generate-preventive-maintenance');
            $output = trim(Artisan::output());
            $this->toast("Task Generate Preventive Maintenance berhasil dijalankan: {$output}", 'success');
        } catch (\Throwable $e) {
            $this->toast("Gagal menjalankan Generate Preventive: {$e->getMessage()}", 'danger');
        } finally {
            $this->isProcessing = false;
        }
    }

    public function render()
    {
        $lastSweep = Cache::get('simlog:last_run:sweep_expired', [
            'waktu' => 'Belum pernah dijalankan',
            'status' => 'Pending',
            'ringkasan' => 'Menunggu jadwal cron otomatis (00:01 WIB)',
            'expired_count' => 0,
            'warning_count' => 0,
        ]);

        $lastOverdue = Cache::get('simlog:last_run:alert_overdue', [
            'waktu' => 'Belum pernah dijalankan',
            'status' => 'Pending',
            'ringkasan' => 'Menunggu jadwal cron otomatis (06:00 WIB)',
            'overdue_count' => 0,
            'notif_count' => 0,
        ]);

        $lastPreventive = Cache::get('simlog:last_run:preventive_maintenance', [
            'waktu' => 'Belum pernah dijalankan',
            'status' => 'Pending',
            'ringkasan' => 'Menunggu jadwal cron otomatis (01:00 WIB)',
            'created_count' => 0,
        ]);

        $daftarNotifikasi = Notifikasi::with('pengguna')
            ->latest('id')
            ->take(10)
            ->get();

        return view('livewire.system.scheduler-notifikasi-index', [
            'lastSweep' => $lastSweep,
            'lastOverdue' => $lastOverdue,
            'lastPreventive' => $lastPreventive,
            'daftarNotifikasi' => $daftarNotifikasi,
        ])->layout('layouts.portal', [
            'title' => 'Task Scheduler & Cron Monitor',
            'portal' => $this->getPortalName(),
        ]);
    }
}
