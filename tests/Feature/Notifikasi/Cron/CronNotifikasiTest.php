<?php

namespace Tests\Feature\Notifikasi\Cron;

use App\Models\Aset\Aset;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Ipsrs\JadwalMaintenance;
use App\Models\Ipsrs\PenugasanMaintenance;
use App\Models\Master\Barang;
use App\Models\Master\Notifikasi;
use App\Models\Master\Unit;
use App\Models\User;
use App\Services\Notifikasi\FakePengirimPush;
use App\Services\Notifikasi\PengirimPush;
use Illuminate\Support\Facades\Cache;
use App\Support\Generator\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Uji cron notifikasi: peringatan peminjaman (H-1 & terlambat) dan SPK preventif.
 */
class CronNotifikasiTest extends TestCase
{
    use DatabaseTransactions;

    private User $pengaju;
    private User $penyetuju;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PengirimPush::class, new FakePengirimPush());

        $this->unit = Unit::where('status', 'AKTIF')->first();
        $this->pengaju = $this->user('pengaju');
        $this->penyetuju = $this->user('penyetuju');
    }

    private function user(string $nama): User
    {
        return User::create([
            'nama' => $nama, 'username' => $nama . '-cron', 'email' => $nama . '-cron@simlog.test',
            'password' => bcrypt('password'), 'status' => 'AKTIF',
        ]);
    }

    private function pinjam(string $kembali, string $status = 'DIPINJAM', bool $penyetujuSama = false): PermintaanPeminjaman
    {
        return PermintaanPeminjaman::create([
            'kode' => CodeGenerator::generate('PINJ', PermintaanPeminjaman::class, 'kode', 'Y', 3),
            'tipe_penyedia' => 'RUANGAN',
            'unit_penyedia_id' => $this->unit->id,
            'unit_id' => $this->unit->id,
            'diajukan_oleh' => $this->pengaju->id,
            'disetujui_oleh' => $penyetujuSama ? $this->pengaju->id : $this->penyetuju->id,
            'tanggal_pinjam' => now()->subDays(5)->toDateString(),
            'tanggal_kembali_rencana' => $kembali,
            'keperluan' => 'Uji cron',
            'status' => $status,
        ]);
    }

    private function notif(string $tipe, PermintaanPeminjaman $p)
    {
        return Notifikasi::where('tipe', $tipe)->where('referensi_id', (string) $p->id);
    }

    public function test_terlambat_ke_pengaju_dan_penyetuju_tanpa_duplikat_saat_dijalankan_dua_kali(): void
    {
        $p = $this->pinjam(now()->subDay()->toDateString());

        Artisan::call('simlog:alert-overdue-loans');
        Artisan::call('simlog:alert-overdue-loans');

        $this->assertSame(2, $this->notif('PINJAM_TERLAMBAT', $p)->count());
        $this->assertSame(1, $this->notif('PINJAM_TERLAMBAT', $p)->where('pengguna_id', $this->pengaju->id)->count());
        $this->assertSame(1, $this->notif('PINJAM_TERLAMBAT', $p)->where('pengguna_id', $this->penyetuju->id)->count());
        $this->assertSame(0, $this->notif('PINJAM_JATUH_TEMPO', $p)->count());
    }

    public function test_pengaju_sama_dengan_penyetuju_hanya_satu_notifikasi(): void
    {
        $p = $this->pinjam(now()->subDay()->toDateString(), 'DISETUJUI', true);

        Artisan::call('simlog:alert-overdue-loans');

        $this->assertSame(1, $this->notif('PINJAM_TERLAMBAT', $p)->count());
    }

    public function test_h_minus_1_memicu_jatuh_tempo_ke_dua_penerima_tanpa_duplikat(): void
    {
        $p = $this->pinjam(now()->addDay()->toDateString(), 'DISETUJUI');

        Artisan::call('simlog:alert-due-soon-loans');
        Artisan::call('simlog:alert-due-soon-loans');

        $this->assertSame(2, $this->notif('PINJAM_JATUH_TEMPO', $p)->count());
        $this->assertSame(
            [$this->pengaju->id, $this->penyetuju->id],
            $this->notif('PINJAM_JATUH_TEMPO', $p)->orderBy('id')->pluck('pengguna_id')->all()
        );
        $this->assertSame(0, $this->notif('PINJAM_TERLAMBAT', $p)->count());
        $this->assertGreaterThanOrEqual(1, Cache::get('simlog:last_run:alert_due_soon')['due_soon_count']);
    }

    public function test_hari_ini_tidak_memicu_h1_maupun_terlambat(): void
    {
        $p = $this->pinjam(now()->toDateString());

        Artisan::call('simlog:alert-due-soon-loans');
        Artisan::call('simlog:alert-overdue-loans');

        $this->assertSame(0, Notifikasi::where('referensi_id', (string) $p->id)->whereIn('tipe', ['PINJAM_JATUH_TEMPO', 'PINJAM_TERLAMBAT'])->count());
    }

    public function test_status_selesai_tidak_memicu(): void
    {
        $lewat = $this->pinjam(now()->subDay()->toDateString(), 'SELESAI');
        $besok = $this->pinjam(now()->addDay()->toDateString(), 'SELESAI');

        Artisan::call('simlog:alert-overdue-loans');
        Artisan::call('simlog:alert-due-soon-loans');

        $this->assertSame(0, Notifikasi::whereIn('referensi_id', [(string) $lewat->id, (string) $besok->id])->count());
    }

    public function test_preventif_mengirim_spk_preventif_ke_teknisi_dengan_referensi_penugasan(): void
    {
        $teknisi = $this->user('teknisi');
        $aset = Aset::create([
            'barang_id' => (Barang::where('status', 'AKTIF')->where('tipe', 'ASET')->first() ?: Barang::where('status', 'AKTIF')->first())->id,
            'unit_id' => $this->unit->id,
            'kode_aset' => CodeGenerator::generate('AST', Aset::class, 'kode_aset'),
            'nomor_seri' => 'SN-CRON-01',
            'tanggal_perolehan' => now()->toDateString(),
            'nilai_perolehan' => 1000000,
            'kondisi' => 'BAIK',
            'status' => 'DIGUNAKAN',
        ]);
        JadwalMaintenance::create([
            'kode' => CodeGenerator::generatePrv(),
            'aset_id' => $aset->id,
            'jenis_maintenance' => 'Kalibrasi Uji Cron',
            'frekuensi' => '3_BULAN',
            'interval_nilai' => 3,
            'interval_tipe' => 'BULAN',
            'tanggal_terakhir' => now()->subMonths(3)->toDateString(),
            'tanggal_berikutnya' => now()->toDateString(),
            'teknisi_id' => $teknisi->id,
            'status' => 'AKTIF',
            'dibuat_oleh' => $this->penyetuju->id,
        ]);

        Artisan::call('simlog:generate-preventive-maintenance');

        $spk = PenugasanMaintenance::where('teknisi_id', $teknisi->id)->firstOrFail();
        $n = Notifikasi::where('pengguna_id', $teknisi->id)->where('tipe', 'SPK_PREVENTIF')->get();
        $this->assertCount(1, $n);
        $this->assertSame((string) $spk->id, $n->first()->referensi_id);
        $this->assertSame('/teknisi/lembar-kerja', $n->first()->tautan);
    }
}
