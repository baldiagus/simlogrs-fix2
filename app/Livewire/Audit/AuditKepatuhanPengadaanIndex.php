<?php

namespace App\Livewire\Audit;

use App\Enums\Audit\StatusCapa;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Master\Pemasok;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Audit\TemuanAudit;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AuditKepatuhanPengadaanIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    #[Url(history: true)]
    public string $filterAnomali = ''; // 'SEMUA', 'TANPA_PR', 'TERLAMBAT', 'SELISIH_RCV'

    #[Url(history: true)]
    public ?int $filterPemasokId = null;

    // Modal Terbitkan Temuan CAPA
    public bool $showCapaModal = false;
    public ?PesananPembelian $selectedPo = null;

    #[Validate('required|string|min:5|max:150')]
    public string $objek_audit = '';

    #[Validate('required|string|min:10')]
    public string $ringkasan = '';

    #[Validate('required|in:Mayor,Moderat,Minor')]
    public string $tingkat_risiko = 'Mayor';

    #[Validate('required|date|after_or_equal:today')]
    public string $due_date_capa = '';

    /**
     * Lifecycle hook boot Livewire untuk inisialisasi awal dan validasi otorisasi.
     */
    public function boot(): void
    {
        abort_unless(
            auth()->user()?->can('view_any_audit_manajemen::kepatuhan::pengadaan') ||
            auth()->user()?->can('view_audit_manajemen::kepatuhan::pengadaan'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang mengakses Audit Kepatuhan Pengadaan.'
        );
    }

    /**
     * Inisialisasi komponen dan persiapan data awal.
     */
    public function mount(): void
    {
        $this->due_date_capa = now()->addDays(14)->toDateString();
    }

    /**
     * Buka modal penerbitan Corrective and Preventive Action (CAPA).
     */
    public function openTerbitkanCapa(int $poId, string $tipeAnomali): void
    {
        abort_unless(
            auth()->user()?->hasRole('auditor') && auth()->user()?->can('create_audit_manajemen::temuan::capa'),
            403,
            'Hanya Auditor SPI dengan izin yang berwenang menerbitkan temuan audit kepatuhan.'
        );

        $this->selectedPo = PesananPembelian::with(['pemasok', 'permintaanPengadaan'])->findOrFail($poId);

        $this->objek_audit = "Pesanan Pembelian (PO) {$this->selectedPo->kode} - Rekanan: {$this->selectedPo->pemasok?->nama}";

        if ($tipeAnomali === 'TANPA_PR') {
            $this->ringkasan = "Ditemukan penerbitan Pesanan Pembelian (PO) nomor {$this->selectedPo->kode} senilai Rp " . number_format($this->selectedPo->total_biaya, 0, ',', '.') . " tanpa melalui dokumen Permintaan Pengadaan (PR) resmi dari unit pemohon.";
            $this->tingkat_risiko = 'Mayor';
            $this->due_date_capa = now()->addDays(14)->toDateString();
        } elseif ($tipeAnomali === 'TERLAMBAT') {
            $this->ringkasan = "Pengiriman barang PO nomor {$this->selectedPo->kode} dari vendor {$this->selectedPo->pemasok?->nama} mengalami keterlambatan melebihi estimasi kirim ({$this->selectedPo->estimasi_tanggal_kirim?->format('d/m/Y')}).";
            $this->tingkat_risiko = 'Moderat';
            $this->due_date_capa = now()->addDays(30)->toDateString();
        } else {
            $this->ringkasan = "Terdapat selisih kuantitas barang yang diterima di gudang terhadap kuantitas yang dipesan pada dokumen PO {$this->selectedPo->kode}.";
            $this->tingkat_risiko = 'Moderat';
            $this->due_date_capa = now()->addDays(30)->toDateString();
        }

        $this->showCapaModal = true;
    }

    /**
     * Simpan data tindak lanjut CAPA terhadap temuan audit.
     */
    public function saveCapa(): void
    {
        abort_unless(
            auth()->user()?->hasRole('auditor') && auth()->user()?->can('create_audit_manajemen::temuan::capa'),
            403,
            'Hanya Auditor SPI dengan izin yang berwenang menerbitkan temuan audit kepatuhan.'
        );

        $this->validate();

        $user = auth()->user();
        $kodeAud = CodeGenerator::generateAud();

        TemuanAudit::create([
            'kode'           => $kodeAud,
            'tanggal_audit'  => now()->toDateString(),
            'objek_audit'    => $this->objek_audit,
            'ringkasan'      => $this->ringkasan,
            'tingkat_risiko' => $this->tingkat_risiko,
            'due_date_capa'  => $this->due_date_capa,
            'status_capa'    => StatusCapa::InProgress->value,
            'auditor_id'     => $user->id,
            'unit_id'        => $this->selectedPo?->permintaanPengadaan?->unit_id,
        ]);

        $this->showCapaModal = false;
        $this->reset(['selectedPo', 'objek_audit', 'ringkasan']);
        $this->toast("Temuan audit '{$kodeAud}' berhasil diterbitkan ke register CAPA.", 'success');
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_audit_manajemen::kepatuhan::pengadaan') ||
            auth()->user()?->can('view_audit_manajemen::kepatuhan::pengadaan'),
            403
        );

        $query = PesananPembelian::with([
            'pemasok',
            'permintaanPengadaan.unit',
            'detail.barang',
            'penerimaanBarang.detail',
        ])
        ->when($this->filterPemasokId, fn ($q) => $q->where('pemasok_id', $this->filterPemasokId))
        ->when($this->search, function ($q) {
            $q->where(function ($sub) {
                $sub->where('kode', 'ilike', "%{$this->search}%")
                    ->orWhereHas('pemasok', fn ($p) => $p->where('nama', 'ilike', "%{$this->search}%"));
            });
        });

        if ($this->filterAnomali === 'TANPA_PR') {
            $query->whereNull('permintaan_pengadaan_id');
        } elseif ($this->filterAnomali === 'TERLAMBAT') {
            $query->whereNotNull('estimasi_tanggal_kirim')
                ->where('estimasi_tanggal_kirim', '<', now()->toDateString())
                ->where('status', '!=', 'DITERIMA_LENGKAP');
        } elseif ($this->filterAnomali === 'SELISIH_RCV') {
            $query->whereHas('detail', function ($q) {
                $q->whereColumn('jumlah_diterima', '<', 'jumlah_dipesan');
            })->where('status', 'DITERIMA_SEBAGIAN');
        }

        $items = $query->orderBy('id', 'desc')->paginate($this->perPage);

        $daftarPemasok = Cache::remember('dropdown_pemasok_aktif', config('simlog.cache.dropdown_ttl'), fn () => Pemasok::where('status', 'AKTIF')->orderBy('nama')->get());

        return view('livewire.audit.audit-kepatuhan-pengadaan-index', [
            'items'         => $items,
            'daftarPemasok' => $daftarPemasok,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Audit Kepatuhan Pengadaan PO vs PR']);
    }
}
