<?php

use App\Models\Ipsrs\JadwalMaintenance;
use App\Models\Master\Notifikasi;
use App\Models\Ipsrs\PenugasanMaintenance;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Persediaan\StokBatch;
use App\Models\User;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\Artisan;
use App\Services\Notifikasi\NotifikasiService;
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
 * Helper bersama command peringatan peminjaman: kirim ke pengaju dan penyetuju (bila beda)
 * lewat NotifikasiService, dengan dedup harian per (pengguna, tipe, referensi_id).
 * Mengembalikan jumlah notifikasi yang benar-benar diterbitkan.
 */
$kirimPeringatanPinjaman = function (
    PermintaanPeminjaman $loan,
    string $tipe,
    string $judulPengaju,
    string $pesanPengaju,
    string $judulPenyetuju,
    string $pesanPenyetuju,
): int {
    $sudahTerkirim = fn (int $penggunaId): bool => Notifikasi::where('pengguna_id', $penggunaId)
        ->where('tipe', $tipe)
        ->where('referensi_id', (string) $loan->id)
        ->whereDate('dibuat_pada', now()->toDateString())
        ->exists();

    $service = app(NotifikasiService::class);
    $count = 0;

    if ($loan->diajukan_oleh && ! $sudahTerkirim($loan->diajukan_oleh)) {
        $count += $service->kirim([$loan->diajukan_oleh], $judulPengaju, $pesanPengaju, $tipe, (string) $loan->id, '/petugas-unit/peminjaman-aset')->count();
    }

    if ($loan->disetujui_oleh && $loan->disetujui_oleh !== $loan->diajukan_oleh && ! $sudahTerkirim($loan->disetujui_oleh)) {
        $count += $service->kirim([$loan->disetujui_oleh], $judulPenyetuju, $pesanPenyetuju, $tipe, (string) $loan->id, '/kepala-unit/approval-peminjaman')->count();
    }

    return $count;
};

/**
 * 2. Alert Overdue Loans (Jadwal: Harian 06:00 WIB)
 * Memindai peminjaman sarana aset yang melewati tanggal rencana pengembalian
 * dan menerbitkan notifikasi lonceng internal kepada peminjam & penanggung jawab.
 */
Artisan::command('simlog:alert-overdue-loans', function () use ($kirimPeringatanPinjaman) {
    $this->info('[' . now()->toDateTimeString() . '] Memeriksa peminjaman aset yang melewati jatuh tempo...');

    $overdueLoans = PermintaanPeminjaman::with(['pengaju', 'unitPeminjam', 'penyetuju'])
        ->whereIn('status', ['DISETUJUI', 'DIPINJAM'])
        ->where('tanggal_kembali_rencana', '<', now()->toDateString())
        ->get();

    $notifCount = 0;
    foreach ($overdueLoans as $loan) {
        $tgl = $loan->tanggal_kembali_rencana->format('d/m/Y');
        $notifCount += $kirimPeringatanPinjaman(
            $loan,
            'PINJAM_TERLAMBAT',
            "Peringatan Keterlambatan Pengembalian Aset ({$loan->kode})",
            "Peminjaman aset {$loan->kode} telah melewati batas rencana kembali ({$tgl}). Mohon segera lakukan pengembalian fisik ke unit penyedia.",
            "Pemberitahuan Peminjaman Melewati Jatuh Tempo ({$loan->kode})",
            "Peminjaman aset {$loan->kode} oleh unit {$loan->unitPeminjam?->nama} belum dikembalikan melewati batas {$tgl}.",
        );
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
 * 2b. Alert Due-Soon Loans (Jadwal: Harian 07:00 WIB)
 * Peminjaman aset yang jatuh tempo besok (H-1) diingatkan ke peminjam & penyetuju.
 */
Artisan::command('simlog:alert-due-soon-loans', function () use ($kirimPeringatanPinjaman) {
    $this->info('[' . now()->toDateTimeString() . '] Memeriksa peminjaman aset yang jatuh tempo besok...');

    $dueSoonLoans = PermintaanPeminjaman::with(['pengaju', 'unitPeminjam', 'penyetuju'])
        ->whereIn('status', ['DISETUJUI', 'DIPINJAM'])
        ->whereDate('tanggal_kembali_rencana', now()->addDay()->toDateString())
        ->get();

    $notifCount = 0;
    foreach ($dueSoonLoans as $loan) {
        $tgl = $loan->tanggal_kembali_rencana->format('d/m/Y');
        $notifCount += $kirimPeringatanPinjaman(
            $loan,
            'PINJAM_JATUH_TEMPO',
            "Pengingat Pengembalian Aset Besok ({$loan->kode})",
            "Peminjaman aset {$loan->kode} jatuh tempo besok ({$tgl}). Mohon siapkan pengembalian fisik ke unit penyedia.",
            "Pemberitahuan Peminjaman Jatuh Tempo Besok ({$loan->kode})",
            "Peminjaman aset {$loan->kode} oleh unit {$loan->unitPeminjam?->nama} jatuh tempo besok ({$tgl}).",
        );
    }

    Cache::put('simlog:last_run:alert_due_soon', [
        'waktu' => now()->toDateTimeString(),
        'status' => 'Sukses',
        'ringkasan' => "{$dueSoonLoans->count()} peminjaman jatuh tempo besok, {$notifCount} notifikasi diterbitkan",
        'due_soon_count' => $dueSoonLoans->count(),
        'notif_count' => $notifCount,
    ]);

    $this->info("Pemeriksaan selesai: {$dueSoonLoans->count()} jatuh tempo besok, {$notifCount} notifikasi terkirim.");
})->purpose('Mengingatkan peminjaman aset yang jatuh tempo besok (H-1)')->dailyAt('07:00');

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
            $penugasan = PenugasanMaintenance::create([
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

            app(NotifikasiService::class)->kirim(
                [$jadwal->teknisi_id],
                "SPK Preventif Baru: {$tiket->kode}",
                "Anda ditugaskan pada pemeliharaan preventif " . ($jadwal->aset?->barang?->nama ?? 'Aset') . " ({$jadwal->jenis_maintenance}).",
                'SPK_PREVENTIF',
                (string) $penugasan->id,
                '/teknisi/lembar-kerja',
            );
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
