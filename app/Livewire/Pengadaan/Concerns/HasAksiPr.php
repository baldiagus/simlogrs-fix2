<?php

namespace App\Livewire\Pengadaan\Concerns;

use App\Enums\Pengadaan\StatusPermintaanPengadaan;
use App\Enums\Pengadaan\StatusPesananPembelian;
use App\Models\Pengadaan\PermintaanPengadaan;
use Illuminate\Support\Facades\DB;

trait HasAksiPr
{
    public function confirmApprove(int $id): void
    {
        $pr = PermintaanPengadaan::with(['unit', 'gudang'])->findOrFail($id);
        $this->askConfirmation(
            target: $id,
            action: 'approve',
            title: 'Konfirmasi Persetujuan PR',
            description: "Setujui permohonan pengadaan {$pr->kode} dari {$pr->nama_pemohon}?",
            buttonText: 'Ya, Setujui',
            variant: 'primary'
        );
    }

    public function confirmReject(int $id): void
    {
        $pr = PermintaanPengadaan::findOrFail($id);
        $this->askConfirmation(
            target: $id,
            action: 'reject',
            title: 'Konfirmasi Penolakan PR',
            description: "Tolak permohonan pengadaan {$pr->kode}? Permintaan yang ditolak tidak dapat diproses lebih lanjut.",
            buttonText: 'Ya, Tolak Permintaan',
            variant: 'danger'
        );
    }

    public function approve(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if (! $this->canUserApprovePr($pr)) {
            abort(403, 'Anda tidak memiliki wewenang mengesahkan permohonan dari unit ini.');
        }

        if ($pr->status !== StatusPermintaanPengadaan::Diajukan->value) {
            $this->toast('Hanya PR berstatus DIAJUKAN yang dapat disetujui.', 'danger');
            return;
        }
        DB::transaction(function () use ($pr) {
            $pr->update([
                'status'         => StatusPermintaanPengadaan::DiteruskanKePengadaan->value,
                'disetujui_oleh' => auth()->id(),
            ]);

            // Default full approval for each detail
            $pr->detail()->update(['jumlah_disetujui' => DB::raw('jumlah')]);
        });

        $this->toast("PR '{$pr->kode}' telah disetujui dan diteruskan ke bagian pengadaan.", 'success');
    }

    public function reject(int $id)
    {
        abort_unless(auth()->user()?->can('update_pengadaan::permintaan::pengadaan'), 403);

        $pr = PermintaanPengadaan::findOrFail($id);

        if (! $this->canUserApprovePr($pr)) {
            abort(403, 'Anda tidak memiliki wewenang menolak permohonan dari unit ini.');
        }

        if ($pr->status !== StatusPermintaanPengadaan::Diajukan->value) {
            $this->toast('Hanya PR berstatus DIAJUKAN yang dapat ditolak.', 'danger');
            return;
        }
        $pr->update([
            'status'         => StatusPermintaanPengadaan::Ditolak->value,
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->toast("PR '{$pr->kode}' telah ditolak.", 'info');
    }

    public function openRejectPengadaan(int $id): void
    {
        abort_unless(auth()->user()?->can('create_pengadaan::pesanan::pembelian'), 403);
        $pr = PermintaanPengadaan::findOrFail($id);

        if ($pr->status !== StatusPermintaanPengadaan::DiteruskanKePengadaan->value) {
            $this->toast('Hanya PR berstatus DITERUSKAN KE PENGADAAN yang dapat ditolak oleh bagian pengadaan.', 'danger');
            return;
        }

        $hasPo = \App\Models\Pengadaan\PesananPembelian::where('permintaan_pengadaan_id', $id)
            ->where('status', '!=', StatusPesananPembelian::Dibatalkan->value)
            ->exists();
        if ($hasPo) {
            $this->toast('PR ini sudah diproses ke Pesanan Pembelian (PO) aktif dan tidak dapat ditolak.', 'danger');
            return;
        }

        $this->rejectPrId = $id;
        $this->alasanPenolakanPengadaan = '';
        $this->showRejectPengadaanModal = true;
    }

    public function submitRejectPengadaan(): void
    {
        abort_unless(auth()->user()?->can('create_pengadaan::pesanan::pembelian'), 403);

        $this->validate([
            'alasanPenolakanPengadaan' => 'required|string|min:5|max:500',
        ], [
            'alasanPenolakanPengadaan.required' => 'Alasan penolakan dari bagian pengadaan wajib diisi.',
            'alasanPenolakanPengadaan.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $pr = PermintaanPengadaan::findOrFail($this->rejectPrId);
        if ($pr->status !== StatusPermintaanPengadaan::DiteruskanKePengadaan->value) {
            $this->toast('Hanya PR berstatus DITERUSKAN KE PENGADAAN yang dapat ditolak oleh bagian pengadaan.', 'danger');
            return;
        }

        $catatanAwal = $pr->catatan ? $pr->catatan . "\n" : '';
        $pr->update([
            'status' => StatusPermintaanPengadaan::Ditolak->value,
            'catatan' => $catatanAwal . '[Ditolak Pengadaan]: ' . trim($this->alasanPenolakanPengadaan),
            'disetujui_oleh' => auth()->id(),
        ]);

        $this->showRejectPengadaanModal = false;
        $this->toast("PR '{$pr->kode}' berhasil ditolak di meja pengadaan.", 'info');
    }
}
