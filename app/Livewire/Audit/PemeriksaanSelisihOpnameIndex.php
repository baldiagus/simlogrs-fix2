<?php

namespace App\Livewire\Audit;

use App\Enums\Audit\StatusCapa;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Master\Gudang;
use App\Models\Persediaan\StokOpname;
use App\Models\Audit\TemuanAudit;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class PemeriksaanSelisihOpnameIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    #[Url(history: true)]
    public string $filterStatus = ''; // '', 'DISETUJUI', 'MENUNGGU_APPROVAL', 'BERJALAN'

    #[Url(history: true)]
    public ?int $filterGudangId = null;

    #[Url(history: true)]
    public string $filterKategoriSelisih = ''; // '', 'ADA_SELISIH', 'SELISIH_KURANG', 'SELISIH_LEBIH', 'KLOP'

    // Modal Detail Rincian Selisih Opname
    public bool $showDetailModal = false;
    public ?StokOpname $selectedOpname = null;

    // Modal Terbitkan Temuan CAPA dari Selisih
    public bool $showCapaModal = false;

    #[Validate('required|string|min:5|max:150')]
    public string $objek_audit = '';

    #[Validate('required|string|min:10')]
    public string $ringkasan = '';

    #[Validate('required|in:Mayor,Moderat,Minor')]
    public string $tingkat_risiko = 'Moderat';

    #[Validate('required|date|after_or_equal:today')]
    public string $due_date_capa = '';

    public ?int $targetGudangId = null;

    public function boot(): void
    {
        abort_unless(
            auth()->user()?->can('view_any_audit_manajemen::selisih::opname') ||
            auth()->user()?->can('view_audit_manajemen::selisih::opname'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang mengakses Pemeriksaan Selisih Opname.'
        );
    }

    public function mount(): void
    {
        $this->due_date_capa = now()->addDays(30)->toDateString();
    }

    public function showDetail(int $opnameId): void
    {
        $this->selectedOpname = StokOpname::with([
            'gudang',
            'pembuat',
            'penyetuju',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])->findOrFail($opnameId);

        $this->showDetailModal = true;
    }

    public function openTerbitkanCapa(int $opnameId): void
    {
        abort_unless(
            auth()->user()?->hasRole('auditor') && auth()->user()?->can('create_audit_manajemen::temuan::capa'),
            403,
            'Hanya Auditor SPI dengan izin yang berwenang menerbitkan temuan audit CAPA.'
        );

        $this->selectedOpname = StokOpname::with(['gudang', 'detail.barang.satuan', 'detail.stokBatch'])->findOrFail($opnameId);
        $this->targetGudangId = $this->selectedOpname->gudang_id;

        $selisihDetails = $this->selectedOpname->detail->where('selisih', '!=', 0);
        $countSelisih = $selisihDetails->count();
        $totalNilaiSelisih = $selisihDetails->sum(function ($d) {
            $harga = (float) ($d->stokBatch?->harga_satuan ?? $d->barang?->harga_beli_terakhir ?? 0);
            return abs($d->selisih) * $harga;
        });

        $this->objek_audit = "Stock Opname {$this->selectedOpname->kode} - Fasilitas {$this->selectedOpname->gudang?->nama}";
        $this->ringkasan = "Uji petik fisik Stock Opname {$this->selectedOpname->kode} menemukan ketidaksesuaian selisih pada {$countSelisih} batch komoditas fisik dengan akumulasi nilai selisih Rp " . number_format($totalNilaiSelisih, 0, ',', '.') . ". Diperlukan klarifikasi dan tindakan perbaikan penataan tata letak serta Berita Acara insiden.";
        $this->tingkat_risiko = $totalNilaiSelisih >= 1000000 ? 'Mayor' : 'Moderat';
        $this->due_date_capa = now()->addDays(30)->toDateString();

        $this->showCapaModal = true;
    }

    public function saveCapa(): void
    {
        abort_unless(auth()->user()?->hasRole('auditor') && auth()->user()?->can('create_audit_manajemen::temuan::capa'), 403);

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
            'gudang_id'      => $this->targetGudangId,
        ]);

        $this->showCapaModal = false;
        $this->reset(['objek_audit', 'ringkasan', 'targetGudangId']);
        $this->toast("Temuan audit '{$kodeAud}' berhasil diterbitkan ke register CAPA.", 'success');
    }

    public function render()
    {
        $query = StokOpname::with([
            'gudang',
            'pembuat',
            'penyetuju',
            'detail.barang',
            'detail.stokBatch',
        ])
        ->withCount([
            'detail as total_batch_count',
            'detail as selisih_count' => fn ($q) => $q->where('selisih', '!=', 0),
            'detail as minus_count'   => fn ($q) => $q->where('selisih', '<', 0),
            'detail as plus_count'    => fn ($q) => $q->where('selisih', '>', 0),
        ])
        ->when($this->filterGudangId, fn ($q) => $q->where('gudang_id', $this->filterGudangId))
        ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
        ->when($this->search, function ($q) {
            $q->where(function ($sub) {
                $sub->where('kode', 'ilike', "%{$this->search}%")
                    ->orWhere('catatan', 'ilike', "%{$this->search}%")
                    ->orWhereHas('gudang', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"));
            });
        });

        if ($this->filterKategoriSelisih === 'ADA_SELISIH') {
            $query->whereHas('detail', fn ($q) => $q->where('selisih', '!=', 0));
        } elseif ($this->filterKategoriSelisih === 'SELISIH_KURANG') {
            $query->whereHas('detail', fn ($q) => $q->where('selisih', '<', 0));
        } elseif ($this->filterKategoriSelisih === 'SELISIH_LEBIH') {
            $query->whereHas('detail', fn ($q) => $q->where('selisih', '>', 0));
        } elseif ($this->filterKategoriSelisih === 'KLOP') {
            $query->whereDoesntHave('detail', fn ($q) => $q->where('selisih', '!=', 0));
        }

        $items = $query->orderBy('id', 'desc')->paginate($this->perPage);

        $daftarGudang = Cache::remember('dropdown_gudang_aktif_audit', config('simlog.cache.dropdown_ttl'), fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());

        // Metrik Ringkasan Eksekutif Opname
        $countTotalSesi = StokOpname::count();
        $countAdaSelisih = StokOpname::whereHas('detail', fn ($q) => $q->where('selisih', '!=', 0))->count();
        $countKlop = StokOpname::whereDoesntHave('detail', fn ($q) => $q->where('selisih', '!=', 0))->where('status', '!=', 'BERJALAN')->count();

        return view('livewire.audit.pemeriksaan-selisih-opname-index', [
            'items'            => $items,
            'daftarGudang'     => $daftarGudang,
            'countTotalSesi'   => $countTotalSesi,
            'countAdaSelisih'  => $countAdaSelisih,
            'countKlop'        => $countKlop,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => 'Pemeriksaan Selisih Opname (Audit)',
        ]);
    }
}
