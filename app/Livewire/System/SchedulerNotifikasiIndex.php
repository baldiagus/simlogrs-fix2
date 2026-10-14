<?php

namespace App\Livewire\System;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasToast;
use App\Models\Master\Notifikasi;
use App\Services\Notifikasi\StatusAntrean;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class SchedulerNotifikasiIndex extends Component
{
    use HasPortalLayout, HasToast, HasConfirmationModal;

    public bool $isProcessing = false;
    public bool $showDetailNotifModal = false;
    public ?Notifikasi $selectedNotifikasi = null;

    public function showDetailNotif(int $id): void
    {
        $this->selectedNotifikasi = Notifikasi::with('pengguna')->find($id);
        $this->showDetailNotifModal = true;
    }

    public function boot(): void
    {
        abort_unless(
            auth()->user()?->can('page_SchedulerNotifikasiIndex') ||
            auth()->user()?->can('view_any_system::scheduler') ||
            auth()->user()?->can('page_SuperAdminDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang mengelola Task Scheduler.'
        );
    }

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
    public function confirmSweepExpired(): void
    {
        $this->askConfirmation(
            target: null,
            action: 'triggerSweepExpired',
            title: 'Konfirmasi Sweep Kedaluwarsa',
            description: 'Jalankan pemindaian kedaluwarsa sekarang? Seluruh batch persediaan di gudang yang telah melewati tanggal kedaluwarsa akan langsung dikunci statusnya menjadi KEDALUWARSA (dilarang alokasi FEFO).',
            buttonText: 'Ya, Jalankan Sweep',
            variant: 'primary'
        );
    }

    public function confirmAlertOverdue(): void
    {
        $this->askConfirmation(
            target: null,
            action: 'triggerAlertOverdue',
            title: 'Konfirmasi Cek Overdue Pinjaman',
            description: 'Jalankan pemeriksaan keterlambatan peminjaman aset sekarang? Notifikasi lonceng internal akan langsung diterbitkan kepada staf peminjam dan kepala unit penanggung jawab.',
            buttonText: 'Ya, Jalankan Cek',
            variant: 'primary'
        );
    }

    public function confirmGeneratePreventive(): void
    {
        $this->askConfirmation(
            target: null,
            action: 'triggerGeneratePreventive',
            title: 'Konfirmasi Generate Preventif',
            description: 'Jalankan penerbitan tiket servis preventif sekarang? Jadwal pemeliharaan berkala aktif yang telah jatuh tempo akan langsung dibuatkan tiket SPK resmi.',
            buttonText: 'Ya, Generate Tiket',
            variant: 'primary'
        );
    }

    public function triggerSweepExpired(): void
    {
        abort_unless(
            auth()->user()?->can('create_system::scheduler') ||
            auth()->user()?->hasRole('super_admin'),
            403,
            'Akses ditolak: Hanya Super Admin yang berwenang mengeksekusi task scheduler.'
        );

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
        abort_unless(
            auth()->user()?->can('create_system::scheduler') ||
            auth()->user()?->hasRole('super_admin'),
            403,
            'Akses ditolak: Hanya Super Admin yang berwenang mengeksekusi task scheduler.'
        );

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
        abort_unless(
            auth()->user()?->can('create_system::scheduler') ||
            auth()->user()?->hasRole('super_admin'),
            403,
            'Akses ditolak: Hanya Super Admin yang berwenang mengeksekusi task scheduler.'
        );

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
        abort_unless(
            auth()->user()?->can('page_SchedulerNotifikasiIndex') ||
            auth()->user()?->can('view_any_system::scheduler') ||
            auth()->user()?->can('page_SuperAdminDashboard'),
            403
        );

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
        $jadwalSiapCount = \App\Models\Ipsrs\JadwalMaintenance::where('status', 'AKTIF')
            ->whereDate('tanggal_berikutnya', '<=', now()->toDateString())
            ->count();

        $daftarNotifikasi = Notifikasi::with('pengguna')
            ->latest('id')
            ->take(10)
            ->get();

        $statusAntrean = app(StatusAntrean::class);
        $detak = $statusAntrean->detakTerakhir();
        $statusPush = [
            'tertunda' => $statusAntrean->jumlahTertunda(),
            'detak' => $detak?->diffForHumans() ?? '-',
            'sehat' => $statusAntrean->sehat(),
            'perangkat' => $statusAntrean->jumlahTokenTerdaftar(),
            'pengguna' => $statusAntrean->jumlahPenggunaBerToken(),
        ];

        return view('livewire.system.scheduler-notifikasi-index', [
            'lastSweep' => $lastSweep,
            'lastOverdue' => $lastOverdue,
            'lastPreventive' => $lastPreventive,
            'jadwalSiapCount' => $jadwalSiapCount,
            'daftarNotifikasi' => $daftarNotifikasi,
            'statusPush' => $statusPush,
        ])->layout('layouts.portal', [
            'title' => 'Task Scheduler & Cron Monitor',
            'portal' => $this->getPortalName(),
        ]);
    }
}
