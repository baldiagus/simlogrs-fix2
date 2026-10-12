<?php

namespace App\Livewire\User\Concerns;

use App\Models\User;
use App\Services\User\PenggunaService;

trait HasAksiPengguna
{
    public function confirmResetPassword(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) {
            $this->toast('Akun dengan peran super_admin hanya dapat dikelola oleh sesama super_admin.', 'danger');
            return;
        }

        // Cegah self-reset melalui modul admin
        if (auth()->id() === $user->id) {
            $this->toast('Untuk mengganti kata sandi akun Anda sendiri, silakan gunakan menu Pengaturan Akun.', 'warning');
            return;
        }

        $this->askConfirmation(
            target: $user->id,
            action: 'executeResetPassword',
            title: 'Reset Kata Sandi Pegawai',
            description: "Reset kata sandi akun {$user->nama} ({$user->username})? Pegawai akan diwajibkan mengganti kata sandi saat login berikutnya.",
            buttonText: 'Ya, Reset Sandi',
            variant: 'primary'
        );
    }

    public function executeResetPassword(int $id): void
    {
        abort_unless(
            auth()->user()?->can('update_user::pengguna') ||
            auth()->user()?->hasRole('super_admin'),
            403,
            'Anda tidak memiliki wewenang mereset kata sandi pengguna.'
        );

        $user = User::findOrFail($id);

        if ($user->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) {
            abort(403, 'Akun dengan peran super_admin hanya dapat dikelola oleh pengguna dengan peran super_admin.');
        }

        if (auth()->id() === $user->id) {
            $this->toast('Operasi dibatalkan: Anda tidak dapat mereset sandi akun Anda sendiri melalui modul ini.', 'warning');
            return;
        }

        $tempPassword = app(PenggunaService::class)->resetPassword($user, auth()->user());

        // JANGAN tampilkan sandi di Toast! Simpan ke modal aman satu kali tampil
        $this->tempPasswordData = [
            'nama'         => $user->nama,
            'username'     => $user->username,
            'email'        => $user->email,
            'tempPassword' => $tempPassword,
        ];
        $this->showResetSuccessModal = true;
    }

    public function closeResetSuccessModal(): void
    {
        $this->reset('tempPasswordData');
        $this->showResetSuccessModal = false;
    }

    public function confirmToggleStatus(int $id): void
    {
        if (auth()->id() === $id) {
            $this->toast('Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang aktif.', 'danger');
            return;
        }

        $user = User::findOrFail($id);

        if ($user->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) {
            $this->toast('Akun dengan peran super_admin hanya dapat dikelola oleh sesama super_admin.', 'danger');
            return;
        }
        $targetStatus = $user->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $labelAksi = $targetStatus === 'AKTIF' ? 'Mengaktifkan kembali' : 'Menonaktifkan (Blokir)';

        $this->askConfirmation(
            target: $user->id,
            action: 'executeToggleStatus',
            title: "Ubah Status Akun Pegawai",
            description: "{$labelAksi} akun {$user->nama} ({$user->username})? Pegawai berstatus nonaktif tidak dapat masuk ke sistem.",
            buttonText: $targetStatus === 'AKTIF' ? 'Aktifkan Akun' : 'Nonaktifkan Akun',
            variant: $targetStatus === 'AKTIF' ? 'primary' : 'danger'
        );
    }

    public function executeToggleStatus(int $id): void
    {
        abort_unless(
            auth()->user()?->can('update_user::pengguna') ||
            auth()->user()?->hasRole('super_admin'),
            403,
            'Anda tidak memiliki wewenang mengubah status akun pengguna.'
        );

        $user = User::findOrFail($id);

        if ($user->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) {
            abort(403, 'Akun dengan peran super_admin hanya dapat dikelola oleh pengguna dengan peran super_admin.');
        }

        if (auth()->id() === $user->id) {
            $this->toast('Operasi dibatalkan: Akun aktif diri sendiri.', 'danger');
            return;
        }

        $newStatus = app(PenggunaService::class)->toggleStatus($user, auth()->user());

        $this->toast("Status akun {$user->nama} diubah menjadi {$newStatus}.", 'info');
    }

    public function confirmDelete(int $id): void
    {
        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            $this->toast('Operasi dibatalkan: Anda tidak dapat menghapus akun Anda sendiri.', 'danger');
            return;
        }

        if ($user->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) {
            $this->toast('Akun dengan peran super_admin hanya dapat dikelola oleh sesama super_admin.', 'danger');
            return;
        }

        $this->askConfirmation(
            target: $user->id,
            action: 'delete',
            title: 'Konfirmasi Hapus Akun Pegawai',
            description: "Apakah Anda yakin ingin menghapus akun pegawai {$user->nama} ({$user->username}) secara permanen? Tindakan ini tidak dapat dibatalkan.",
            buttonText: 'Ya, Hapus Akun',
            variant: 'danger'
        );
    }

    public function delete(int $id): void
    {
        abort_unless(
            auth()->user()?->can('delete_user::pengguna') ||
            auth()->user()?->hasRole('super_admin'),
            403,
            'Anda tidak memiliki wewenang menghapus akun pengguna.'
        );

        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            $this->toast('Operasi dibatalkan: Anda tidak dapat menghapus akun Anda sendiri.', 'danger');
            return;
        }

        if ($user->hasRole('super_admin') && ! auth()->user()?->hasRole('super_admin')) {
            abort(403, 'Akun dengan peran super_admin hanya dapat dikelola oleh pengguna dengan peran super_admin.');
        }

        // Cek ketergantungan relasi operasional
        $hasDependencies = \App\Models\PermintaanPengadaan::where('diajukan_oleh', $id)->orWhere('disetujui_oleh', $id)->exists()
            || \App\Models\PesananPembelian::where('dibuat_oleh', $id)->exists()
            || \App\Models\PenerimaanBarang::where('diterima_oleh', $id)->exists()
            || \App\Models\PermintaanDistribusi::where('diajukan_oleh', $id)->orWhere('disetujui_oleh', $id)->exists()
            || \App\Models\PengirimanBarang::where('dikirim_oleh', $id)->exists()
            || \App\Models\PenerimaanDistribusi::where('diterima_oleh', $id)->exists()
            || \App\Models\PermintaanPeminjaman::where('diajukan_oleh', $id)->orWhere('disetujui_oleh', $id)->exists()
            || \App\Models\PengembalianPeminjaman::where('diperiksa_oleh', $id)->exists()
            || \App\Models\PermintaanMaintenance::where('dilaporkan_oleh', $id)->exists()
            || \App\Models\PenugasanMaintenance::where('teknisi_id', $id)->orWhere('ditugaskan_oleh', $id)->exists()
            || \App\Models\LogMaintenance::where('dikerjakan_oleh', $id)->exists()
            || \App\Models\StokOpname::where('dibuat_oleh', $id)->orWhere('disetujui_oleh', $id)->exists()
            || \App\Models\PenyesuaianStok::where('diajukan_oleh', $id)->orWhere('disetujui_oleh', $id)->exists();

        if ($hasDependencies) {
            $this->toast("Akun {$user->nama} tidak dapat dihapus karena memiliki rekam jejak transaksi kedinasan. Silakan nonaktifkan akun sebagai gantinya.", 'danger');
            return;
        }

        $namaUser = $user->nama;
        app(PenggunaService::class)->deletePengguna($user, auth()->user());

        $this->toast("Akun pegawai {$namaUser} berhasil dihapus.", 'success');
    }
}
