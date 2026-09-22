<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\LokasiGudang;
use App\Models\Unit;
use App\Support\Generator\CodeGenerator;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AsetIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    public bool $showModal = false;
    public bool $showQrModal = false;
    public ?int $editingId = null;
    public ?Aset $selectedAset = null;
    public ?string $qrCodeSvg = null;

    #[Url(history: true)]
    public string $filterLokasi = ''; // 'RUANGAN', 'GUDANG', ''

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterKondisi = '';

    #[Url(history: true)]
    public ?int $filterUnitId = null;

    #[Url(history: true)]
    public ?int $filterGudangId = null;

    #[Validate('required|exists:barang,id')]
    public ?int $barang_id = null;

    #[Validate('required|in:RUANGAN,GUDANG')]
    public string $lokasi_tipe = 'RUANGAN';

    public ?int $unit_id = null;
    public ?int $gudang_id = null;
    public ?int $lokasi_gudang_id = null;

    #[Validate('nullable|string|max:50')]
    public string $kode_aset = '';

    #[Validate('nullable|string|max:100')]
    public ?string $nomor_seri = null;

    #[Validate('required|date')]
    public string $tanggal_perolehan = '';

    #[Validate('required|numeric|min:0')]
    public float $nilai_perolehan = 0;

    #[Validate('required|in:BAIK,RUSAK_RINGAN,RUSAK_BERAT')]
    public string $kondisi = 'BAIK';

    #[Validate('required|in:TERSEDIA,DIGUNAKAN,DIPINJAM,DALAM_MAINTENANCE,DIHAPUSKAN')]
    public string $status = 'TERSEDIA';

    public function mount()
    {
        $this->tanggal_perolehan = date('Y-m-d');
    }

    public function updatedFilterLokasi(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKondisi(): void
    {
        $this->resetPage();
    }

    public function updatedLokasiTipe(): void
    {
        if ($this->lokasi_tipe === 'RUANGAN') {
            $this->gudang_id = null;
            $this->lokasi_gudang_id = null;
            $this->unit_id = Unit::where('status', 'AKTIF')->value('id');
        } else {
            $this->unit_id = null;
            $this->gudang_id = Gudang::where('status', 'AKTIF')->value('id');
        }
    }

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::aset'), 403);

        $this->reset([
            'editingId',
            'barang_id',
            'nomor_seri',
            'lokasi_gudang_id',
            'nilai_perolehan',
        ]);

        $this->kode_aset = CodeGenerator::generate('AST', Aset::class, 'kode_aset');
        $this->barang_id = Barang::where('tipe', 'ASET')->where('status', 'AKTIF')->value('id');
        $this->lokasi_tipe = 'RUANGAN';
        $this->unit_id = Unit::where('status', 'AKTIF')->value('id');
        $this->gudang_id = null;
        $this->tanggal_perolehan = date('Y-m-d');
        $this->kondisi = 'BAIK';
        $this->status = 'TERSEDIA';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::aset'), 403);

        $aset = Aset::with(['barang', 'unit', 'gudang', 'lokasiGudang'])->findOrFail($id);
        $this->editingId = $aset->id;
        $this->barang_id = $aset->barang_id;
        $this->kode_aset = $aset->kode_aset;
        $this->nomor_seri = $aset->nomor_seri;
        $this->tanggal_perolehan = $aset->tanggal_perolehan->format('Y-m-d');
        $this->nilai_perolehan = (float) $aset->nilai_perolehan;
        $this->kondisi = $aset->kondisi;
        $this->status = $aset->status;

        if ($aset->unit_id) {
            $this->lokasi_tipe = 'RUANGAN';
            $this->unit_id = $aset->unit_id;
            $this->gudang_id = null;
            $this->lokasi_gudang_id = null;
        } else {
            $this->lokasi_tipe = 'GUDANG';
            $this->unit_id = null;
            $this->gudang_id = $aset->gudang_id;
            $this->lokasi_gudang_id = $aset->lokasi_gudang_id;
        }

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'barang_id'         => 'required|exists:barang,id',
            'lokasi_tipe'       => 'required|in:RUANGAN,GUDANG',
            'kode_aset'         => 'nullable|string|max:50|unique:aset,kode_aset,' . ($this->editingId ?? 'NULL') . ',id',
            'nomor_seri'        => 'nullable|string|max:100',
            'tanggal_perolehan' => 'required|date',
            'nilai_perolehan'   => 'required|numeric|min:0',
            'kondisi'           => 'required|in:BAIK,RUSAK_RINGAN,RUSAK_BERAT',
            'status'            => 'required|in:TERSEDIA,DIGUNAKAN,DIPINJAM,DALAM_MAINTENANCE,DIHAPUSKAN',
        ]);

        // Validasi Lokasi XOR
        if ($this->lokasi_tipe === 'RUANGAN') {
            $this->validate([
                'unit_id' => 'required|exists:unit,id',
            ], [
                'unit_id.required' => 'Ruangan penempatan aset wajib dipilih.',
            ]);
            $unitId = $this->unit_id;
            $gudangId = null;
            $lokasiGudangId = null;
        } else {
            $this->validate([
                'gudang_id' => 'required|exists:gudang,id',
                'lokasi_gudang_id' => 'nullable|exists:lokasi_gudang,id',
            ], [
                'gudang_id.required' => 'Gudang penyimpanan aset wajib dipilih.',
            ]);
            $unitId = null;
            $gudangId = $this->gudang_id;
            $lokasiGudangId = $this->lokasi_gudang_id;
        }

        $kodeFinal = ! empty($this->kode_aset)
            ? strtoupper(trim($this->kode_aset))
            : CodeGenerator::generate('AST', Aset::class, 'kode_aset');

        $data = [
            'barang_id'         => $this->barang_id,
            'unit_id'           => $unitId,
            'gudang_id'         => $gudangId,
            'lokasi_gudang_id'  => $lokasiGudangId,
            'kode_aset'         => $kodeFinal,
            'nomor_seri'        => $this->nomor_seri,
            'tanggal_perolehan' => $this->tanggal_perolehan,
            'nilai_perolehan'   => $this->nilai_perolehan,
            'kondisi'           => $this->kondisi,
            'status'            => $this->status,
        ];

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::aset'), 403);
            $aset = Aset::findOrFail($this->editingId);
            $aset->update($data);
            $this->toast("Register aset '{$aset->kode_aset}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::aset'), 403);
            $aset = Aset::create($data);
            $this->toast("Aset baru '{$aset->kode_aset}' berhasil didaftarkan.", 'success');
        }

        $this->showModal = false;
    }

    public function showQr(int $id)
    {
        $this->selectedAset = Aset::with(['barang', 'unit', 'gudang'])->findOrFail($id);
        $this->qrCodeSvg = QrCode::size(200)
            ->format('svg')
            ->generate($this->selectedAset->kode_aset);
        $this->showQrModal = true;
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::aset') || auth()->user()?->can('view_master::aset'), 403);

        $daftarBarangAset = Cache::remember('dropdown_barang_aset_aktif', 60, fn () => Barang::where('tipe', 'ASET')->where('status', 'AKTIF')->get());
        $daftarUnit = Cache::remember('dropdown_unit_aktif', 60, fn () => Unit::where('status', 'AKTIF')->get());
        $daftarGudang = Cache::remember('dropdown_gudang_aktif', 60, fn () => Gudang::where('status', 'AKTIF')->get());
        $daftarRak = $this->gudang_id
            ? LokasiGudang::where('gudang_id', $this->gudang_id)->where('status', 'AKTIF')->get()
            : collect();

        $items = Aset::with(['barang', 'unit', 'gudang', 'lokasiGudang'])
            ->when($this->filterLokasi === 'RUANGAN', fn ($q) => $q->whereNotNull('unit_id'))
            ->when($this->filterLokasi === 'GUDANG', fn ($q) => $q->whereNotNull('gudang_id'))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterKondisi, fn ($q) => $q->where('kondisi', $this->filterKondisi))
            ->when($this->filterUnitId, fn ($q) => $q->where('unit_id', $this->filterUnitId))
            ->when($this->filterGudangId, fn ($q) => $q->where('gudang_id', $this->filterGudangId))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode_aset', 'ilike', "%{$this->search}%")
                        ->orWhere('nomor_seri', 'ilike', "%{$this->search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.master.aset-index', [
            'items' => $items,
            'daftarBarangAset' => $daftarBarangAset,
            'daftarUnit' => $daftarUnit,
            'daftarGudang' => $daftarGudang,
            'daftarRak' => $daftarRak,
            'editingId' => $this->editingId,
            'lokasi_tipe' => $this->lokasi_tipe,
            'selectedAset' => $this->selectedAset,
            'qrCodeSvg' => $this->qrCodeSvg,
            'filterLokasi' => $this->filterLokasi,
            'filterStatus' => $this->filterStatus,
            'filterKondisi' => $this->filterKondisi,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Register Master Aset']);
    }
}
