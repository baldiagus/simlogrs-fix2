<?php

use App\Models\JadwalMaintenance;
use App\Models\Notifikasi;
use App\Models\PenugasanMaintenance;
use App\Models\PermintaanMaintenance;
use App\Models\PermintaanPeminjaman;
use App\Models\StokBatch;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Console Commands & Scheduled Tasks (SIMLOG RS Non-Medis v2 - Fase 9)
|--------------------------------------------------------------------------
*/

/**
 * 1. Sweep Expired Batches (Jadwal: Harian 00:01 WIB)
 * Mengunci status batch kedaluwarsa menjadi KEDALUWARSA dan menandai
 * batch yang mendekati kedaluwarsa (<= 90 hari) menjadi HAMPIR_KEDALUWARSA.
 */
Artisan::command('simlog:sweep-expired-batches', function () {
    $this->info('[' . now()->toDateTimeString() . '] Memulai sweeping stok batch kedaluwarsa...');

    $today = now()->toDateString();
    $in90Days = now()->addDays(90)->toDateString();

    // Batch yang telah melewati tanggal kedaluwarsa -> KEDALUWARSA
    $expiredCount = StokBatch::whereNotNull('tanggal_kedaluwarsa')
        ->where('tanggal_kedaluwarsa', '<=', $today)
        ->whereNotIn('status', ['KEDALUWARSA', 'HABIS'])
        ->update(['status' => 'KEDALUWARSA']);

    // Batch yang akan kedaluwarsa dalam 90 hari (pantauan FEFO aktif)
    $warningCount = StokBatch::whereNotNull('tanggal_kedaluwarsa')
        ->where('tanggal_kedaluwarsa', '>', $today)
        ->where('tanggal_kedaluwarsa', '<=', $in90Days)
        ->where('status', 'AKTIF')
        ->count();

    Cache::put('simlog:last_run:sweep_expired', [
        'waktu' => now()->toDateTimeString(),
        'status' => 'Sukses',
        'ringkasan' => "{$expiredCount} batch dikunci KEDALUWARSA, {$warningCount} batch dalam pantauan <90 hari",
        'expired_count' => $expiredCount,
        'warning_count' => $warningCount,
    ]);

    $this->info("Sweeping selesai: {$expiredCount} batch kedaluwarsa, {$warningCount} batch aktif dalam pantauan FEFO <90 hari.");
})->purpose('Mengunci status batch kedaluwarsa dan memantau batch <90 hari')->dailyAt('00:01');

/**
 * 2. Alert Overdue Loans (Jadwal: Harian 06:00 WIB)
 * Memindai peminjaman sarana aset yang melewati tanggal rencana pengembalian
 * dan menerbitkan notifikasi lonceng internal kepada peminjam & penanggung jawab.
 */
Artisan::command('simlog:alert-overdue-loans', function () {
    $this->info('[' . now()->toDateTimeString() . '] Memeriksa peminjaman aset yang melewati jatuh tempo...');

    $today = now()->toDateString();

    $overdueLoans = PermintaanPeminjaman::with(['pengaju', 'unitPeminjam', 'penyetuju'])
        ->whereIn('status', ['DISETUJUI', 'DIPINJAM'])
        ->where('tanggal_kembali_rencana', '<', $today)
        ->get();

    $notifCount = 0;
    foreach ($overdueLoans as $loan) {
        $alreadyNotified = Notifikasi::where('pengguna_id', $loan->diajukan_oleh)
            ->where('judul', 'like', "%{$loan->kode}%")
            ->whereDate('dibuat_pada', $today)
            ->exists();

        if (! $alreadyNotified) {
            Notifikasi::create([
                'pengguna_id' => $loan->diajukan_oleh,
                'judul' => "Peringatan Keterlambatan Pengembalian Aset ({$loan->kode})",
                'pesan' => "Peminjaman aset {$loan->kode} telah melewati batas rencana kembali ({$loan->tanggal_kembali_rencana->format('d/m/Y')}). Mohon segera lakukan pengembalian fisik ke unit penyedia.",
                'tautan' => '/petugas-unit/peminjaman-aset',
                'dibaca' => false,
                'dibuat_pada' => now(),
            ]);
            $notifCount++;
        }

        if ($loan->disetujui_oleh && $loan->disetujui_oleh !== $loan->diajukan_oleh) {
            $alreadyNotifiedKaru = Notifikasi::where('pengguna_id', $loan->disetujui_oleh)
                ->where('judul', 'like', "%{$loan->kode}%")
                ->whereDate('dibuat_pada', $today)
                ->exists();

            if (! $alreadyNotifiedKaru) {
                Notifikasi::create([
                    'pengguna_id' => $loan->disetujui_oleh,
                    'judul' => "Pemberitahuan Peminjaman Melewati Jatuh Tempo ({$loan->kode})",
                    'pesan' => "Peminjaman aset {$loan->kode} oleh unit {$loan->unitPeminjam?->nama} belum dikembalikan melewati batas {$loan->tanggal_kembali_rencana->format('d/m/Y')}.",
                    'tautan' => '/kepala-unit/approval-peminjaman',
                    'dibaca' => false,
                    'dibuat_pada' => now(),
                ]);
                $notifCount++;
            }
        }
    }

    Cache::put('simlog:last_run:alert_overdue', [
        'waktu' => now()->toDateTimeString(),
        'status' => 'Sukses',
        'ringkasan' => "{$overdueLoans->count()} peminjaman overdue terdeteksi, {$notifCount} notifikasi diterbitkan",
        'overdue_count' => $overdueLoans->count(),
        'notif_count' => $notifCount,
    ]);

    $this->info("Pemeriksaan selesai: {$overdueLoans->count()} overdue, {$notifCount} notifikasi terkirim.");
})->purpose('Mendeteksi peminjaman aset overdue dan mengirim notifikasi internal')->dailyAt('06:00');

/**
 * 3. Generate Preventive Maintenance (Jadwal: Harian 01:00 WIB)
 * Memindai jadwal pemeliharaan preventif berkala yang jatuh tempo dan
 * otomatis menerbitkan tiket servis IPSRS serta memajukan tanggal berikutnya.
 */
Artisan::command('simlog:generate-preventive-maintenance', function () {
    $this->info('[' . now()->toDateTimeString() . '] Memindai jadwal maintenance preventif IPSRS...');

    $today = now()->toDateString();
    $jadwals = JadwalMaintenance::with(['aset.barang', 'teknisi'])
        ->where('status', 'AKTIF')
        ->where('tanggal_berikutnya', '<=', $today)
        ->get();

    $defaultUser = User::where('email', 'teknisi@simlogrs.local')->first() ?? User::first();
    $createdCount = 0;

    foreach ($jadwals as $jadwal) {
        $kodeMnt = CodeGenerator::generateMnt();
        $pelaporId = $jadwal->dibuat_oleh ?? $defaultUser?->id;

        $statusTiket = $jadwal->teknisi_id ? 'DITUGASKAN' : 'DILAPORKAN';

        $tiket = PermintaanMaintenance::create([
            'kode' => $kodeMnt,
            'aset_id' => $jadwal->aset_id,
            'unit_id' => $jadwal->aset?->unit_id,
            'dilaporkan_oleh' => $pelaporId,
            'deskripsi_masalah' => "Pemeliharaan Preventif Terjadwal: {$jadwal->jenis_maintenance} (ID Jadwal: {$jadwal->kode})",
            'prioritas' => 'SEDANG',
            'status' => $statusTiket,
            'dibuat_pada' => now(),
        ]);

        if ($jadwal->teknisi_id) {
            PenugasanMaintenance::create([
                'kode' => CodeGenerator::generateSpk(),
                'permintaan_maintenance_id' => $tiket->id,
                'teknisi_id' => $jadwal->teknisi_id,
                'ditugaskan_oleh' => $pelaporId,
                'tanggal_penugasan' => now()->toDateString(),
                'target_selesai' => now()->addDays(3)->toDateString(),
                'instruksi_khusus' => "Lakukan pemeliharaan preventif berkala sesuai SOP: {$jadwal->jenis_maintenance}",
                'status' => 'DITUGASKAN',
                'dibuat_pada' => now(),
            ]);

            Notifikasi::create([
                'pengguna_id' => $jadwal->teknisi_id,
                'judul' => "SPK Preventif Baru: {$tiket->kode}",
                'pesan' => "Anda ditugaskan pada pemeliharaan preventif " . ($jadwal->aset?->barang?->nama ?? 'Aset') . " ({$jadwal->jenis_maintenance}).",
                'tautan' => '/teknisi/lembar-kerja',
                'dibaca' => false,
                'dibuat_pada' => now(),
            ]);
        }
        if ($jadwal->aset && $jadwal->aset->status !== 'DALAM_MAINTENANCE') {
            $jadwal->aset->update(['status' => 'DALAM_MAINTENANCE']);
        }


        $jadwal->update([
            'tanggal_terakhir' => $today,
            'tanggal_berikutnya' => $jadwal->hitungTanggalBerikutnya(now())->toDateString(),
        ]);

        $createdCount++;
    }

    Cache::put('simlog:last_run:preventive_maintenance', [
        'waktu' => now()->toDateTimeString(),
        'status' => 'Sukses',
        'ringkasan' => "{$createdCount} tiket pemeliharaan preventif diterbitkan",
        'created_count' => $createdCount,
    ]);

    $this->info("Pemindaian selesai: {$createdCount} tiket preventif otomatis diterbitkan.");
})->purpose('Menerbitkan tiket servis preventif IPSRS otomatis dari jadwal berkala')->dailyAt('01:00');
