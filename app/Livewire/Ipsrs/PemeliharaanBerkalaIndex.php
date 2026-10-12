<?php

namespace App\Livewire\Ipsrs;

use App\Enums\StatusMaintenance;
use App\Enums\StatusSpk;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Aset;
use App\Models\JadwalMaintenance;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class PemeliharaanBerkalaIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;

    // Form Jadwal Servis Rutin
    #[Validate('required|exists:aset,id', message: [
        'required' => 'Fasilitas atau unit aset yang diservis wajib dipilih.',
        'exists'   => 'Aset tidak ditemukan.',
    ])]
    public ?int $aset_id = null;

    #[Validate('required|string|min:3|max:150', message: [
        'required' => 'Nama kegiatan servis preventif wajib diisi.',
        'min'      => 'Nama kegiatan servis minimal 3 karakter.',
    ])]
    public string $jenis_maintenance = '';

    #[Validate('required|integer|min:1|max:365', message: [
        'required' => 'Nilai interval wajib diisi angka 1-365.',
        'min'      => 'Interval minimal 1.',
        'max'      => 'Interval maksimal 365.',
    ])]
    public int $interval_nilai = 1;

    #[Validate('required|in:HARI,MINGGU,BULAN', message: [
        'required' => 'Satuan interval waktu wajib dipilih.',
    ])]
    public string $interval_tipe = 'BULAN';

    #[Validate('required|exists:pengguna,id', message: [
        'required' => 'Teknisi penanggung jawab wajib ditentukan.',
        'exists'   => 'Teknisi tidak ditemukan di sistem.',
    ])]
    public ?int $teknisi_id = null;

    #[Validate('required|date', message: [
        'required' => 'Tanggal jadwal berikutnya wajib diisi.',
    ])]
    public string $tanggal_berikutnya = '';
    #[Validate('nullable|string')]
    public ?string $catatan = null;

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterTipe = '';

    public function mount(): void
    {
        $this->tanggal_berikutnya = now()->addMonth()->toDateString();
    }

    public function create(): void
    {
        abort_unless(auth()->user()?->can('create_pemeliharaan::pemeliharaan::berkala'), 403);

        $this->reset(['editingId', 'aset_id', 'jenis_maintenance', 'catatan', 'teknisi_id']);
        $this->interval_nilai = 1;
        $this->interval_tipe = 'BULAN';
        $this->tanggal_berikutnya = now()->addMonth()->toDateString();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()?->can('update_pemeliharaan::pemeliharaan::berkala'), 403);

        $jadwal = JadwalMaintenance::findOrFail($id);
        $this->editingId = $jadwal->id;
        $this->aset_id = $jadwal->aset_id;
        $this->jenis_maintenance = $jadwal->jenis_maintenance;
        $this->interval_nilai = $jadwal->interval_nilai;
        $this->interval_tipe = $jadwal->interval_tipe;
        $this->teknisi_id = $jadwal->teknisi_id;
        $this->tanggal_berikutnya = $jadwal->tanggal_berikutnya?->toDateString() ?? now()->toDateString();
        $this->catatan = $jadwal->catatan;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $aset = Aset::with(['barang', 'unit', 'gudang'])->findOrFail($this->aset_id);

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

        if ($this->teknisi_id && ! User::where('id', $this->teknisi_id)->where('status', 'AKTIF')->exists()) {
            $this->addError('teknisi_id', 'Teknisi yang dipilih sudah berstatus nonaktif.');
            return;
        }
        $user = auth()->user();

        if ($this->editingId) {
            abort_unless($user?->can('update_pemeliharaan::pemeliharaan::berkala'), 403);

            $jadwal = JadwalMaintenance::findOrFail($this->editingId);
            $jadwal->update([
                'aset_id'            => $this->aset_id,
                'jenis_maintenance'  => $this->jenis_maintenance,
                'interval_nilai'     => $this->interval_nilai,
                'interval_tipe'      => $this->interval_tipe,
                'frekuensi'          => $this->interval_tipe === 'BULAN' ? ($this->interval_nilai == 1 ? 'BULANAN' : "{$this->interval_nilai}_BULAN") : "{$this->interval_nilai}_{$this->interval_tipe}",
                'teknisi_id'         => $this->teknisi_id,
                'tanggal_berikutnya' => $this->tanggal_berikutnya,
                'catatan'            => $this->catatan,
            ]);

            $this->toast("Jadwal servis preventif '{$jadwal->kode}' berhasil diperbarui.", 'success');
        } else {
            abort_unless($user?->can('create_pemeliharaan::pemeliharaan::berkala'), 403);

            $kode = CodeGenerator::generatePrv();

            $jadwal = JadwalMaintenance::create([
                'kode'               => $kode,
                'aset_id'            => $this->aset_id,
                'jenis_maintenance'  => $this->jenis_maintenance,
                'interval_nilai'     => $this->interval_nilai,
                'interval_tipe'      => $this->interval_tipe,
                'frekuensi'          => $this->interval_tipe === 'BULAN' ? ($this->interval_nilai == 1 ? 'BULANAN' : "{$this->interval_nilai}_BULAN") : "{$this->interval_nilai}_{$this->interval_tipe}",
                'teknisi_id'         => $this->teknisi_id,
                'tanggal_berikutnya' => $this->tanggal_berikutnya,
                'catatan'            => $this->catatan,
                'status'             => 'AKTIF',
                'dibuat_oleh'        => $user->id,
            ]);

            $this->toast("Jadwal servis preventif baru '{$kode}' berhasil didaftarkan.", 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'aset_id', 'jenis_maintenance', 'catatan', 'teknisi_id']);
    }

    public function confirmToggleStatus(int $id): void
    {
        abort_unless(auth()->user()?->can('update_pemeliharaan::pemeliharaan::berkala'), 403);

        $jadwal = JadwalMaintenance::findOrFail($id);
        $nextStatus = $jadwal->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Ubah Status Jadwal Servis',
            description: "Apakah Anda yakin ingin mengubah status jadwal {$jadwal->kode} menjadi {$nextStatus}?",
            buttonText: 'Ya, Ubah Status',
            variant: 'primary'
        );
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(auth()->user()?->can('update_pemeliharaan::pemeliharaan::berkala'), 403);

        $jadwal = JadwalMaintenance::findOrFail($id);
        $newStatus = $jadwal->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $jadwal->update(['status' => $newStatus]);

        $this->toast("Status jadwal servis '{$jadwal->kode}' diubah menjadi {$newStatus}.", 'info');
    }

    public function confirmEksekusi(int $id): void
    {
        abort_unless($this->canTerbitkanSpk(), 403);

        $jadwal = JadwalMaintenance::with(['aset.barang', 'teknisi'])->findOrFail($id);

        $this->askConfirmation(
            target: $id,
            action: 'eksekusiServis',
            title: 'Terbitkan SPK Servis Rutin',
            description: "Terbitkan Surat Perintah Kerja (SPK) servis berkala untuk aset {$jadwal->aset?->barang?->nama} ({$jadwal->aset?->kode_aset}) dan tugaskan ke teknisi {$jadwal->teknisi?->nama}?",
            buttonText: 'Terbitkan SPK Sekarang',
            variant: 'primary'
        );
    }

    public function eksekusiServis(int $id): void
    {
        abort_unless($this->canTerbitkanSpk(), 403);

        $jadwal = JadwalMaintenance::with('aset')->findOrFail($id);
        $user = auth()->user();

        DB::transaction(function () use ($jadwal, $user) {
            // 1. Buat Tiket Permintaan Maintenance (Preventif)
            $kodeMnt = CodeGenerator::generateMnt();
            $tiket = PermintaanMaintenance::create([
                'kode'              => $kodeMnt,
                'aset_id'           => $jadwal->aset_id,
                'unit_id'           => $jadwal->aset?->unit_id,
                'dilaporkan_oleh'   => $user->id,
                'deskripsi_masalah' => "[Servis Preventif Rutin] {$jadwal->jenis_maintenance}",
                'prioritas'         => 'SEDANG',
                'status'            => StatusMaintenance::Ditugaskan->value,
            ]);

            // 2. Buat SPK Langsung untuk Teknisi PIC
            $kodeSpk = CodeGenerator::generateSpk();
            PenugasanMaintenance::create([
                'kode'                      => $kodeSpk,
                'permintaan_maintenance_id' => $tiket->id,
                'teknisi_id'                => $jadwal->teknisi_id ?: $user->id,
                'ditugaskan_oleh'           => $user->id,
                'tanggal_penugasan'         => now()->toDateString(),
                'target_selesai'            => now()->addDays(2)->toDateString(),
                'instruksi_khusus'          => "Servis berkala rutin sesuai jadwal {$jadwal->kode}. " . ($jadwal->catatan ?: ''),
                'status'                    => StatusSpk::Ditugaskan->value,
            ]);
            // 3. Kunci status aset ke DALAM_MAINTENANCE
            if ($jadwal->aset && $jadwal->aset->status !== 'DALAM_MAINTENANCE') {
                $jadwal->aset->update([
                    'status' => 'DALAM_MAINTENANCE',
                ]);
            }

            // 4. Catat tanggal eksekusi terakhir dan majukan tanggal berikutnya
            $jadwal->update([
                'tanggal_terakhir'   => now()->toDateString(),
                'tanggal_berikutnya' => $jadwal->hitungTanggalBerikutnya(now())->toDateString(),
            ]);
        });

        $this->toast("SPK servis preventif berhasil diterbitkan dan masuk ke antrean pengerjaan teknisi.", 'success');
    }

    /**
     * Eksekusi jadwal menerbitkan SPK, jadi mengikuti tahap disposisi Koordinator IPSRS.
     */
    public function canTerbitkanSpk(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('create_pemeliharaan::spk::perbaikan')
            && $user->hasRole(['kepala_teknisi', 'super_admin', 'admin_logistik']));
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_pemeliharaan::pemeliharaan::berkala') ||
            auth()->user()?->can('view_pemeliharaan::pemeliharaan::berkala'),
            403
        );

        $query = JadwalMaintenance::with(['aset.barang', 'aset.unit', 'teknisi'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterTipe, fn ($q) => $q->where('interval_tipe', $this->filterTipe))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('jenis_maintenance', 'ilike', "%{$this->search}%")
                        ->orWhereHas('teknisi', fn ($t) => $t->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('aset', function ($a) {
                            $a->where('kode_aset', 'ilike', "%{$this->search}%")
                                ->orWhere('nomor_seri', 'ilike', "%{$this->search}%")
                                ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                        });
                });
            });

        $daftarJadwal = $query
            ->orderBy('tanggal_berikutnya', 'asc')
            ->paginate($this->perPage);

        // Daftar Aset yang aktif
        $daftarAset = Aset::with('barang')
            ->whereHas('barang', fn ($b) => $b->where('status', 'AKTIF'))
            ->whereIn('status', ['TERSEDIA', 'DIGUNAKAN'])
            ->where(function ($q) {
                $q->whereNull('unit_id')->orWhereHas('unit', fn ($u) => $u->where('status', 'AKTIF'));
            })
            ->where(function ($q) {
                $q->whereNull('gudang_id')->orWhereHas('gudang', fn ($g) => $g->where('status', 'AKTIF'));
            })
            ->orderBy('kode_aset')
            ->get();
        // Daftar Teknisi Aktif (murni peran teknisi)
        $daftarTeknisi = User::where('status', 'AKTIF')
            ->whereHas('roles', fn ($r) => $r->where('name', 'teknisi'))
            ->orderBy('nama')
            ->get();

        return view('livewire.ipsrs.pemeliharaan-berkala-index', [
            'daftarJadwal'  => $daftarJadwal,
            'daftarAset'    => $daftarAset,
            'daftarTeknisi' => $daftarTeknisi,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Jadwal Pemeliharaan Preventif']);
    }
}
