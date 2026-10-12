<?php

namespace App\Livewire\Distribusi\Concerns;

use App\Models\Aset;
use Illuminate\Support\Facades\DB;

trait HasAksiAsetRuangan
{
    public function openUbahStatusAset(int $asetId): void
    {
        $aset = Aset::with(['barang', 'unit'])->findOrFail($asetId);

        abort_unless($this->canUpdateStok(), 403, 'Anda tidak memiliki wewenang mengubah status aset operasional.');
        abort_unless($this->ownsAset($aset), 403, 'Anda tidak berwenang mengubah aset milik fasilitas lain.');
        if (in_array($aset->status, ['DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN'])) {
            abort(403, "Status aset '{$aset->kode_aset}' ({$aset->status}) tidak dapat diubah secara manual di ruangan.");
        }

        $this->selectedAsetId = $aset->id;
        $this->selectedAset = $aset;
        $this->statusAsetBaru = in_array($aset->status, ['DIGUNAKAN', 'TERSEDIA']) ? $aset->status : 'DIGUNAKAN';
        $this->kondisiAsetBaru = $aset->kondisi;
        $this->showUbahStatusAsetModal = true;
    }

    public function simpanStatusAset(): void
    {
        $this->validate([
            'statusAsetBaru'  => 'required|in:DIGUNAKAN,TERSEDIA',
            'kondisiAsetBaru' => 'required|in:BAIK,RUSAK_RINGAN,RUSAK_BERAT',
        ], [
            'statusAsetBaru.required'  => 'Status aset operasional wajib ditentukan.',
            'kondisiAsetBaru.required' => 'Kondisi fisik aset wajib dinilai.',
        ]);

        if (! $this->selectedAsetId) {
            return;
        }

        $aset = Aset::findOrFail($this->selectedAsetId);

        abort_unless($this->canUpdateStok(), 403, 'Anda tidak memiliki wewenang mengubah status aset operasional.');
        abort_unless($this->ownsAset($aset), 403, 'Anda tidak berwenang mengubah aset milik fasilitas lain.');
        if (in_array($aset->status, ['DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN'])) {
            abort(403, "Status aset '{$aset->kode_aset}' ({$aset->status}) tidak dapat diubah secara manual di ruangan.");
        }

        $aset->update([
            'status'  => $this->statusAsetBaru,
            'kondisi' => $this->kondisiAsetBaru,
        ]);

        $this->toast("Status aset '{$aset->kode_aset}' berhasil diperbarui menjadi {$this->statusAsetBaru}.", 'success');

        $this->showUbahStatusAsetModal = false;
        $this->selectedAsetId = null;
        $this->selectedAset = null;
    }

    public function openLaporServis(int $asetId): void
    {
        abort_unless($this->canLaporServis(), 403, 'Anda tidak memiliki wewenang untuk melaporkan kerusakan sarana.');

        $aset = Aset::with(['barang', 'unit', 'gudang'])->findOrFail($asetId);
        abort_unless($this->ownsAset($aset), 403, 'Anda tidak berwenang melaporkan kerusakan aset milik fasilitas lain.');

        $tiketAktif = \App\Models\PermintaanMaintenance::where('aset_id', $aset->id)
            ->whereIn('status', [\App\Enums\StatusMaintenance::Dilaporkan->value, 'DISETUJUI', 'DALAM_PENGERJAAN'])
            ->latest('id')
            ->first();

        if (in_array($aset->status, ['DALAM_MAINTENANCE', 'DIHAPUSKAN']) || $tiketAktif) {
            $kodeTiket = $tiketAktif?->kode ?? 'aktif';
            $this->toast("Aset '{$aset->kode_aset}' saat ini sudah dalam penanganan perbaikan teknisi IPSRS (Status: {$aset->status}, Tiket: {$kodeTiket}).", 'warning');
            return;
        }

        $this->servisAsetId = $aset->id;
        $this->selectedAsetServis = $aset;
        $this->servisPrioritas = 'SEDANG';
        $this->servisDeskripsi = '';
        $this->servisFoto = null;
        $this->showLaporServisModal = true;
    }

    public function simpanLaporServis(): void
    {
        $this->validate([
            'servisPrioritas' => 'required|in:RENDAH,SEDANG,TINGGI,CITO',
            'servisDeskripsi' => 'required|string|min:5',
            'servisFoto'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:' . config('simlog.upload.max_kb'),
        ], [
            'servisPrioritas.required' => 'Prioritas penanganan wajib dipilih.',
            'servisDeskripsi.required' => 'Deskripsi keluhan atau kerusakan sarana wajib dijelaskan.',
            'servisDeskripsi.min'      => 'Deskripsi kerusakan minimal 5 karakter.',
            'servisFoto.image'         => 'Berkas harus berupa foto gambar.',
            'servisFoto.max'           => 'Ukuran foto maksimal adalah 10 MB.',
        ]);

        if (! $this->servisAsetId) {
            return;
        }

        $user = auth()->user();
        $aset = Aset::with(['barang', 'unit'])->findOrFail($this->servisAsetId);

        abort_unless($this->canLaporServis(), 403, 'Anda tidak memiliki wewenang untuk melaporkan kerusakan sarana.');
        abort_unless($this->ownsAset($aset), 403, 'Anda tidak berwenang melaporkan kerusakan aset milik fasilitas lain.');

        $fotoPath = null;
        if ($this->servisFoto) {
            $opt = \App\Support\File\FileOptimizer::optimizeAndStore($this->servisFoto, 'maintenance/kerusakan');
            $fotoPath = $opt['path'];
        }

        $kode = \App\Support\Generator\CodeGenerator::generateMnt();

        \Illuminate\Support\Facades\DB::transaction(function () use ($kode, $aset, $user, $fotoPath) {
            \App\Models\PermintaanMaintenance::create([
                'kode'              => $kode,
                'aset_id'           => $aset->id,
                'unit_id'           => $aset->unit_id ?: $user->unit_id,
                'dilaporkan_oleh'   => $user->id,
                'deskripsi_masalah' => $this->servisDeskripsi,
                'foto_kerusakan'    => $fotoPath,
                'prioritas'         => $this->servisPrioritas,
                'status'            => \App\Enums\StatusMaintenance::Dilaporkan->value,
            ]);

            $kondisiAset = match ($this->servisPrioritas) {
                'CITO'   => 'RUSAK_BERAT',
                'TINGGI' => 'RUSAK_RINGAN',
                default  => in_array($aset->kondisi, ['RUSAK_RINGAN', 'RUSAK_BERAT']) ? $aset->kondisi : 'RUSAK_RINGAN',
            };

            $aset->update([
                'status'  => 'DALAM_MAINTENANCE',
                'kondisi' => $kondisiAset,
            ]);
        });

        $this->showLaporServisModal = false;
        $this->reset(['servisAsetId', 'selectedAsetServis', 'servisPrioritas', 'servisDeskripsi', 'servisFoto']);
        $this->toast("Laporan kerusakan '{$kode}' untuk {$aset->kode_aset} berhasil dikirim ke IPSRS.", 'success');
    }

    public function batalLaporServis(): void
    {
        $this->showLaporServisModal = false;
        $this->reset(['servisAsetId', 'selectedAsetServis', 'servisPrioritas', 'servisDeskripsi', 'servisFoto']);
    }

    public function hapusFotoServis(): void
    {
        $this->servisFoto = null;
    }
}
