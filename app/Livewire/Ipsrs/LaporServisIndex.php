<?php

namespace App\Livewire\Ipsrs;

use App\Enums\Ipsrs\StatusMaintenance;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Aset\Aset;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Support\File\FileOptimizer;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class LaporServisIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, WithFileUploads;

    public bool $showModal = false;
    public bool $showDetailModal = false;
    public ?PermintaanMaintenance $selectedTiket = null;

    #[Validate('required|exists:aset,id', message: [
        'required' => 'Fasilitas atau unit aset yang rusak wajib dipilih.',
        'exists'   => 'Aset tidak ditemukan di sistem.',
    ])]
    public ?int $aset_id = null;

    #[Validate('required|in:RENDAH,SEDANG,TINGGI,CITO', message: [
        'required' => 'Tingkat prioritas darurat wajib ditentukan.',
    ])]
    public string $prioritas = 'SEDANG';

    #[Validate('required|string|min:5', message: [
        'required' => 'Deskripsi keluhan atau kerusakan sarana wajib dijelaskan.',
        'min'      => 'Deskripsi kerusakan minimal 5 karakter.',
    ])]
    public string $deskripsi_masalah = '';

    // Aturan validasi `foto` ada di rules(): atribut #[Validate] tidak dapat memanggil config().
    public $foto = null;

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterPrioritas = '';

    /**
     * Dapatkan aturan validasi formulir pelaporan servis alat.
     */
    protected function rules(): array
    {
        return [
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:' . config('simlog.upload.max_kb'),
        ];
    }

    /**
     * Dapatkan pesan galat kustom untuk validasi pelaporan servis alat.
     */
    protected function messages(): array
    {
        return [
            'foto.image' => 'Berkas harus berupa gambar.',
            'foto.mimes' => 'Format gambar yang didukung: JPG, PNG, WEBP.',
            'foto.max'   => 'Ukuran foto maksimal adalah 10 MB.',
        ];
    }

    // Mempertahankan validasi real-time yang sebelumnya diberikan atribut #[Validate].
    public function updatedFoto(): void
    {
        $this->validateOnly('foto');
    }

    /**
     * Buka modal dialog formulir untuk penambahan data baru.
     */
    public function create(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::lapor::servis'), 403);

        $this->reset(['aset_id', 'prioritas', 'deskripsi_masalah', 'foto']);
        $this->prioritas = 'SEDANG';
        $this->showModal = true;
    }

    /**
     * Validasi isian formulir dan simpan data ke database.
     */
    public function save(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::lapor::servis'), 403);
        $this->validate();

        $user = auth()->user();
        $aset = Aset::with(['barang', 'unit', 'gudang'])->findOrFail($this->aset_id);
        abort_unless($this->canAccessUnit($aset->unit_id), 403, 'Anda hanya berhak melaporkan kerusakan aset di unit Anda.');

        if (! $aset->barang || $aset->barang->status !== 'AKTIF') {
            $this->addError('aset_id', 'Komoditas aset yang dipilih sudah berstatus nonaktif.');
            return;
        }

        if ($aset->unit && $aset->unit->status !== 'AKTIF') {
            $this->addError('aset_id', 'Unit ruangan penempatan aset sudah berstatus nonaktif.');
            return;
        }

        if ($aset->gudang && $aset->gudang->status !== 'AKTIF') {
            $this->addError('aset_id', 'Fasilitas gudang penempatan aset sudah berstatus nonaktif.');
            return;
        }
        $kode = CodeGenerator::generateMnt();

        $fotoPath = null;
        if ($this->foto) {
            $opt = FileOptimizer::optimizeAndStore($this->foto, 'maintenance/kerusakan');
            $fotoPath = $opt['path'];
        }

        // Tiket dan status aset satu transaksi agar kegagalan audit membatalkan keduanya (ADR 0002 B).
        DB::transaction(function () use ($kode, $aset, $user, $fotoPath) {
            PermintaanMaintenance::create([
                'kode'              => $kode,
                'aset_id'           => $aset->id,
                'unit_id'           => $aset->unit_id ?: $user->unit_id,
                'dilaporkan_oleh'   => $user->id,
                'deskripsi_masalah' => $this->deskripsi_masalah,
                'foto_kerusakan'    => $fotoPath,
                'prioritas'         => $this->prioritas,
                'status'            => StatusMaintenance::Dilaporkan->value,
            ]);

            $kondisiAset = match ($this->prioritas) {
                'CITO'   => 'RUSAK_BERAT',
                'TINGGI' => 'RUSAK_RINGAN',
                default  => in_array($aset->kondisi, ['RUSAK_RINGAN', 'RUSAK_BERAT']) ? $aset->kondisi : 'RUSAK_RINGAN',
            };

            $aset->update([
                'status'  => 'DALAM_MAINTENANCE',
                'kondisi' => $kondisiAset,
            ]);
        });

        $this->showModal = false;
        $this->reset(['aset_id', 'prioritas', 'deskripsi_masalah', 'foto']);
        $this->toast("Laporan kerusakan '{$kode}' berhasil dikirim ke IPSRS.", 'success');
    }
    /**
     * Hapus berkas foto kerusakan alat dari pratinjau upload.
     */
    public function hapusFotoPratinjau(): void
    {
        $this->foto = null;
    }


    /**
     * Buka modal dialog dan muat data rincian record terpilih.
     */
    public function showDetail(int $id): void
    {
        $this->selectedTiket = PermintaanMaintenance::with([
            'aset.barang',
            'unit',
            'pelapor',
            'penugasan.teknisi',
            'penugasan.penugast',
            'logMaintenance.teknisi',
            'sukuCadang.barang',
        ])->findOrFail($id);
        abort_unless($this->canAccessUnit($this->selectedTiket->unit_id), 403);

        $this->showDetailModal = true;
    }

    /**
     * Tampilkan modal dialog konfirmasi pembatalan transaksi.
     */
    public function confirmBatal(int $id): void
    {
        $tiket = PermintaanMaintenance::with('aset')->findOrFail($id);
        abort_unless($this->canBatal($tiket), 403, 'Anda tidak berwenang membatalkan laporan kerusakan ini.');

        if ($tiket->status !== StatusMaintenance::Dilaporkan->value) {
            $this->toast('Hanya tiket berstatus DILAPORKAN yang dapat dibatalkan.', 'danger');
            return;
        }

        $this->askConfirmation(
            target: $id,
            action: 'batal',
            title: 'Batalkan Laporan Kerusakan',
            description: "Apakah Anda yakin ingin membatalkan laporan tiket {$tiket->kode}?",
            buttonText: 'Ya, Batalkan Laporan',
            variant: 'danger'
        );
    }

    /**
     * Batalkan transaksi terpilih dan catat log perubahan.
     */
    public function batal(int $id): void
    {
        $tiket = PermintaanMaintenance::with('aset')->findOrFail($id);
        abort_unless($this->canBatal($tiket), 403, 'Anda tidak berwenang membatalkan laporan kerusakan ini.');

        if ($tiket->status !== StatusMaintenance::Dilaporkan->value) {
            $this->toast('Hanya tiket berstatus DILAPORKAN yang dapat dibatalkan.', 'danger');
            return;
        }

        DB::transaction(function () use ($tiket) {
            $tiket->update(['status' => StatusMaintenance::Dibatalkan->value]);

            // Jika aset terkunci dalam maintenance akibat tiket ini dan tidak ada tiket aktif lain
            if ($tiket->aset && $tiket->aset->status === 'DALAM_MAINTENANCE') {
                $hasOtherActiveTicket = PermintaanMaintenance::where('aset_id', $tiket->aset_id)
                    ->where('id', '!=', $tiket->id)
                    ->whereIn('status', StatusMaintenance::aktif())
                    ->exists();

                if (! $hasOtherActiveTicket) {
                    $newStatus = $tiket->aset->unit_id ? 'DIGUNAKAN' : 'TERSEDIA';
                    $tiket->aset->update([
                        'status'  => $newStatus,
                        'kondisi' => 'BAIK',
                    ]);
                }
            }
        });

        $this->toast("Laporan kerusakan '{$tiket->kode}' berhasil dibatalkan.", 'info');
    }

    /**
     * Pelapor membatalkan laporannya sendiri dengan permission lapor; selain itu pembatalan
     * adalah tahap pimpinan unit/gudang pemilik aset (role + permission) atau role global.
     */
    public function canBatal(PermintaanMaintenance $tiket): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ((int) $user->id === (int) $tiket->dilaporkan_oleh) {
            return $user->can('create_layanan_unit::lapor::servis');
        }

        if (! $user->can('update_layanan_unit::lapor::servis')) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin_logistik'])
            || ($user->hasRole('kepala_unit') && $user->unit_id && (int) $user->unit_id === (int) $tiket->unit_id)
            || ($user->hasRole('kepala_gudang') && $user->gudang_id && (int) $user->gudang_id === (int) $tiket->aset?->gudang_id);
    }

    /**
     * Pengguna unit dibatasi ke unit_id-nya; IPSRS (permission pemeliharaan) dan auditor/manajemen
     * tanpa unit membaca lintas unit.
     */
    private function hasDepartmentWideRead(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin_logistik'])) {
            return true;
        }

        return ! $user->unit_id
            && ($user->can('view_any_pemeliharaan::spk::perbaikan') || $user->hasRole(['auditor', 'manajemen']));
    }

    /**
     * Periksa apakah pengguna memiliki izin akses terhadap data unit kerja terkait.
     */
    private function canAccessUnit(?int $unitId): bool
    {
        $user = auth()->user();

        return $this->hasDepartmentWideRead()
            || ($user?->unit_id && (int) $user->unit_id === (int) $unitId);
    }

    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_layanan_unit::lapor::servis') ||
            auth()->user()?->can('view_layanan_unit::lapor::servis'),
            403
        );

        $user = auth()->user();
        $userUnitId = $user?->unit_id;
        $isDepartmentWide = $this->hasDepartmentWideRead();

        $query = PermintaanMaintenance::with(['aset.barang', 'unit', 'pelapor', 'penugasanAktif.teknisi'])
            ->when(! $isDepartmentWide, fn ($q) => $userUnitId ? $q->where('unit_id', $userUnitId) : $q->whereRaw('1 = 0'))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPrioritas, fn ($q) => $q->where('prioritas', $this->filterPrioritas))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('deskripsi_masalah', 'ilike', "%{$this->search}%")
                        ->orWhereHas('aset', function ($a) {
                            $a->where('kode_aset', 'ilike', "%{$this->search}%")
                                ->orWhere('nomor_seri', 'ilike', "%{$this->search}%")
                                ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                        });
                });
            });

        $daftarTiket = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        // Aset yang dapat dipilih untuk dilaporkan: aset di unit bersangkutan (atau semua aset jika tanpa unit)
        $daftarAsetTersedia = Aset::with('barang')
            ->whereHas('barang', fn ($b) => $b->where('status', 'AKTIF'))
            ->when(! $isDepartmentWide && ! $userUnitId, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($userUnitId, fn ($q) => $q->where('unit_id', $userUnitId), fn ($q) => $q->where(function ($sub) {
                $sub->whereNull('unit_id')
                    ->orWhereHas('unit', fn ($u) => $u->where('status', 'AKTIF'));
            }))
            ->where(function ($sub) {
                $sub->whereNull('gudang_id')
                    ->orWhereHas('gudang', fn ($g) => $g->where('status', 'AKTIF'));
            })
            ->whereIn('status', ['TERSEDIA', 'DIGUNAKAN', 'DIPINJAM'])
            ->orderBy('kode_aset')
            ->get();

        return view('livewire.ipsrs.lapor-servis-index', [
            'daftarTiket'        => $daftarTiket,
            'daftarAsetTersedia' => $daftarAsetTersedia,
            'userUnitId'         => $userUnitId,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Lapor Kerusakan & Servis IPSRS']);
    }
}
