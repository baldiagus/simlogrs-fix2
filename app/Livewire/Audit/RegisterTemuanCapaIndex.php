<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Gudang;
use App\Models\TemuanAudit;
use App\Models\Unit;
use App\Support\File\FileOptimizer;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class RegisterTemuanCapaIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, WithFileUploads;

    #[Url(history: true)]
    public string $filterStatus = ''; // 'In Progress', 'In Review', 'Closed'

    #[Url(history: true)]
    public string $filterRisiko = ''; // 'Mayor', 'Moderat', 'Minor'

    // Modal Buat Temuan Baru
    public bool $showCreateModal = false;

    #[Validate('required|string|min:5|max:150')]
    public string $objek_audit = '';

    #[Validate('required|string|min:10')]
    public string $ringkasan = '';

    #[Validate('required|in:Mayor,Moderat,Minor')]
    public string $tingkat_risiko = 'Moderat';

    #[Validate('required|date')]
    public string $tanggal_audit = '';

    #[Validate('required|date|after_or_equal:tanggal_audit')]
    public string $due_date_capa = '';

    public ?int $unit_id = null;
    public ?int $gudang_id = null;

    // Modal Tindak Lanjut Unit (Auditee)
    public bool $showTindakLanjutModal = false;
    public ?int $selectedTemuanId = null;

    #[Validate('required|string|min:10')]
    public string $analisis_penyebab = '';

    #[Validate('required|string|min:10')]
    public string $tindakan_perbaikan = '';

    public $fileBuktiList = [];

    // Modal Evaluasi Closing (Auditor)
    public bool $showEvaluasiModal = false;

    #[Validate('required|in:Closed,In Progress')]
    public string $hasil_evaluasi = 'Closed';

    #[Validate('required|string|min:5')]
    public string $catatan_evaluasi = '';

    public function mount(): void
    {
        $this->tanggal_audit = now()->toDateString();
        $this->due_date_capa = now()->addDays(30)->toDateString();
    }

    public function openCreate(): void
    {
        abort_unless(
            auth()->user()?->can('create_audit_manajemen::temuan::capa') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $this->reset(['objek_audit', 'ringkasan', 'unit_id', 'gudang_id']);
        $this->tingkat_risiko = 'Moderat';
        $this->tanggal_audit = now()->toDateString();
        $this->due_date_capa = now()->addDays(30)->toDateString();
        $this->showCreateModal = true;
    }

    public function saveCreate(): void
    {
        abort_unless(
            auth()->user()?->can('create_audit_manajemen::temuan::capa') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $this->validate([
            'objek_audit'    => 'required|string|min:5|max:150',
            'ringkasan'      => 'required|string|min:10',
            'tingkat_risiko' => 'required|in:Mayor,Moderat,Minor',
            'tanggal_audit'  => 'required|date',
            'due_date_capa'  => 'required|date|after_or_equal:tanggal_audit',
        ]);

        $user = auth()->user();
        $kode = CodeGenerator::generateAud();

        TemuanAudit::create([
            'kode'           => $kode,
            'tanggal_audit'  => $this->tanggal_audit,
            'objek_audit'    => $this->objek_audit,
            'ringkasan'      => $this->ringkasan,
            'tingkat_risiko' => $this->tingkat_risiko,
            'due_date_capa'  => $this->due_date_capa,
            'status_capa'    => 'In Progress',
            'auditor_id'     => $user->id,
            'unit_id'        => $this->unit_id ?: null,
            'gudang_id'      => $this->gudang_id ?: null,
        ]);

        $this->showCreateModal = false;
        $this->reset(['objek_audit', 'ringkasan', 'unit_id', 'gudang_id']);
        $this->toast("Temuan audit '{$kode}' berhasil didaftarkan.", 'success');
    }
    public function getSelectedTemuanProperty(): ?TemuanAudit
    {
        return $this->selectedTemuanId
            ? TemuanAudit::with(['auditor', 'unit', 'gudang', 'evaluator'])->find($this->selectedTemuanId)
            : null;
    }

    protected function authorizeAuditeeAccess(TemuanAudit $temuan): void
    {
        $user = auth()->user();
        $isAuthorized = $user && (
            $user->hasRole(['super_admin', 'auditor', 'admin_logistik']) ||
            $user->can('page_Dashboard') ||
            ($temuan->unit_id && (int) $user->unit_id === (int) $temuan->unit_id) ||
            ($temuan->gudang_id && (int) $user->gudang_id === (int) $temuan->gudang_id)
        );

        abort_unless($isAuthorized, 403, 'Anda tidak memiliki wewenang mengelola tindak lanjut temuan audit unit ini.');
    }

    public function openTindakLanjut(int $id): void
    {
        $this->selectedTemuanId = $id;
        $temuan = TemuanAudit::findOrFail($id);
        $this->authorizeAuditeeAccess($temuan);
        $this->analisis_penyebab = $temuan->analisis_penyebab ?? '';
        $this->tindakan_perbaikan = $temuan->tindakan_perbaikan ?? '';
        $this->fileBuktiList = [];
        $this->showTindakLanjutModal = true;
    }

    public function saveTindakLanjut(): void
    {
        if (! $this->selectedTemuanId) {
            return;
        }

        $this->validate([
            'analisis_penyebab'  => 'required|string|min:10',
            'tindakan_perbaikan' => 'required|string|min:10',
            'fileBuktiList.*'    => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,xlsx,docx|max:10240',
        ]);

        $temuan = TemuanAudit::findOrFail($this->selectedTemuanId);
        $this->authorizeAuditeeAccess($temuan);
        $existingBukti = is_array($temuan->bukti_perbaikan) ? $temuan->bukti_perbaikan : [];

        if (! empty($this->fileBuktiList)) {
            $files = is_array($this->fileBuktiList) ? $this->fileBuktiList : [$this->fileBuktiList];
            foreach ($files as $file) {
                if ($file) {
                    $existingBukti[] = FileOptimizer::optimizeAndStore($file, 'audit/capa');
                }
            }
        }

        $temuan->update([
            'analisis_penyebab'  => $this->analisis_penyebab,
            'tindakan_perbaikan' => $this->tindakan_perbaikan,
            'bukti_perbaikan'    => $existingBukti,
            'status_capa'        => 'In Review',
        ]);

        $this->fileBuktiList = [];
        $this->showTindakLanjutModal = false;
        $this->toast("Tindak lanjut perbaikan untuk {$temuan->kode} berhasil dikirim (Status: In Review).", 'success');
    }

    public function removeTempFile(int $index): void
    {
        unset($this->fileBuktiList[$index]);
        $this->fileBuktiList = array_values($this->fileBuktiList);
    }

    public function deleteExistingBukti(int $index): void
    {
        if (! $this->selectedTemuanId) {
            return;
        }

        $temuan = TemuanAudit::findOrFail($this->selectedTemuanId);
        $this->authorizeAuditeeAccess($temuan);
        $bukti = is_array($temuan->bukti_perbaikan) ? $temuan->bukti_perbaikan : [];
        if (isset($bukti[$index])) {
            $path = $bukti[$index]['path'] ?? null;
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            unset($bukti[$index]);
            $temuan->update(['bukti_perbaikan' => array_values($bukti)]);
            $this->toast("Lampiran berkas bukti berhasil dihapus.", 'info');
        }
    }

    public function openEvaluasiClosing(int $id): void
    {
        abort_unless(
            auth()->user()?->can('update_audit_manajemen::temuan::capa') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        $this->selectedTemuanId = $id;
        $temuan = TemuanAudit::findOrFail($id);
        $this->hasil_evaluasi = 'Closed';
        $this->catatan_evaluasi = $temuan->catatan_evaluasi ?? '';
        $this->showEvaluasiModal = true;
    }

    public function saveEvaluasiClosing(): void
    {
        abort_unless(
            auth()->user()?->can('update_audit_manajemen::temuan::capa') ||
            auth()->user()?->can('page_Dashboard'),
            403
        );

        if (! $this->selectedTemuanId) {
            return;
        }

        $this->validate([
            'hasil_evaluasi'   => 'required|in:Closed,In Progress',
            'catatan_evaluasi' => 'required|string|min:5',
        ]);

        $user = auth()->user();
        $temuan = TemuanAudit::findOrFail($this->selectedTemuanId);

        $temuan->update([
            'status_capa'      => $this->hasil_evaluasi,
            'catatan_evaluasi' => $this->catatan_evaluasi,
            'dievaluasi_oleh'  => $user->id,
        ]);

        $this->showEvaluasiModal = false;
        $toastMsg = $this->hasil_evaluasi === 'Closed'
            ? "Temuan audit {$temuan->kode} berhasil ditutup resmi (Closed)."
            : "Tindak lanjut {$temuan->kode} dikembalikan untuk revisi (In Progress).";

        $this->toast($toastMsg, $this->hasil_evaluasi === 'Closed' ? 'success' : 'info');
    }
    public function render()
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('view_any_audit_manajemen::temuan::capa') ||
                $user->can('view_audit_manajemen::temuan::capa') ||
                $user->can('page_AuditorDashboard') ||
                $user->can('page_Dashboard') ||
                $user->can('page_AdminLogistikDashboard')
            ),
            403
        );

        $today = now()->toDateString();
        // Metrik Ringkasan CAPA
        $totalTemuan = TemuanAudit::count();
        $totalClosed = TemuanAudit::where('status_capa', 'Closed')->count();
        $totalMayorOpen = TemuanAudit::where('tingkat_risiko', 'Mayor')->where('status_capa', '!=', 'Closed')->count();
        $totalOverdue = TemuanAudit::where('status_capa', '!=', 'Closed')->where('due_date_capa', '<', $today)->count();
        $closingRate = $totalTemuan > 0 ? round(($totalClosed / $totalTemuan) * 100, 1) : 100;

        $query = TemuanAudit::with(['auditor', 'evaluator', 'unit', 'gudang'])
            ->when($this->filterStatus, fn ($q) => $q->where('status_capa', $this->filterStatus))
            ->when($this->filterRisiko, fn ($q) => $q->where('tingkat_risiko', $this->filterRisiko))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('objek_audit', 'ilike', "%{$this->search}%")
                        ->orWhere('ringkasan', 'ilike', "%{$this->search}%");
                });
            });

        $items = $query->orderByRaw("CASE WHEN status_capa = 'In Progress' THEN 1 WHEN status_capa = 'In Review' THEN 2 ELSE 3 END")
            ->orderBy('due_date_capa', 'asc')
            ->paginate($this->perPage);

        $daftarUnit = Cache::remember('dropdown_unit_aktif', 60, fn () => Unit::where('status', 'AKTIF')->orderBy('nama')->get());
        $daftarGudang = Cache::remember('dropdown_gudang_aktif', 60, fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());

        return view('livewire.audit.register-temuan-capa-index', [
            'items'          => $items,
            'selectedTemuan' => $this->selectedTemuan,
            'totalTemuan'    => $totalTemuan,
            'totalClosed'    => $totalClosed,
            'totalMayorOpen' => $totalMayorOpen,
            'totalOverdue'   => $totalOverdue,
            'closingRate'    => $closingRate,
            'daftarUnit'     => $daftarUnit,
            'daftarGudang'   => $daftarGudang,
            'canCreate'      => $user?->can('create_audit_manajemen::temuan::capa') || $user?->can('page_Dashboard'),
            'canEvaluate'    => $user?->can('update_audit_manajemen::temuan::capa') || $user?->can('page_Dashboard'),
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Register Temuan Audit & CAPA']);
    }
}
