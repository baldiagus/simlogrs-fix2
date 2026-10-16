<?php

namespace Tests\Feature\Notifikasi\Pemicu;

use App\Models\Audit\TemuanAudit;
use App\Models\Master\Notifikasi;
use App\Models\User;
use App\Services\Notifikasi\FakePengirimPush;
use App\Services\Notifikasi\PengirimPush;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Notifikasi CAPA: pemicu observer (CAPA_BARU/TINDAK_LANJUT/DIEVALUASI) dan cron CAPA_JATUH_TEMPO.
 * Data seeder: unit IGD(1) dengan kepalaunit, unit ICU(3) dengan karu_icu, auditor 'auditor'.
 */
class CapaNotifikasiTest extends TestCase
{
    use DatabaseTransactions;

    private const UNIT_IGD = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PengirimPush::class, new FakePengirimPush());
    }

    private function u(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function temuan(array $o = []): TemuanAudit
    {
        return TemuanAudit::create(array_merge([
            'kode' => 'AUD-' . Str::upper(Str::random(8)), 'tanggal_audit' => now()->toDateString(),
            'objek_audit' => 'Uji CAPA', 'ringkasan' => 'Ringkasan uji', 'tingkat_risiko' => 'Moderat',
            'due_date_capa' => now()->addDays(14)->toDateString(), 'status_capa' => 'In Progress',
            'auditor_id' => $this->u('auditor')->id, 'unit_id' => self::UNIT_IGD,
        ], $o));
    }

    private function penerima(string $tipe, TemuanAudit $t): array
    {
        return Notifikasi::where('tipe', $tipe)->where('referensi_id', (string) $t->id)->orderBy('pengguna_id')->pluck('pengguna_id')->all();
    }

    public function test_temuan_baru_hanya_ke_kepala_unit_terperiksa(): void
    {
        $this->actingAs($this->u('auditor'));
        $t = $this->temuan();

        $this->assertSame([$this->u('kepalaunit')->id], $this->penerima('CAPA_BARU', $t));
        $this->assertSame('/kepala-unit/temuan-capa', Notifikasi::where('referensi_id', (string) $t->id)->value('tautan'));
    }

    public function test_temuan_tanpa_unit_dan_gudang_tidak_mengirim_dan_tidak_error(): void
    {
        $t = $this->temuan(['unit_id' => null, 'gudang_id' => null]);

        $this->assertSame([], $this->penerima('CAPA_BARU', $t));
    }

    public function test_tindak_lanjut_ke_auditor(): void
    {
        $t = $this->temuan();
        $t->update(['status_capa' => 'In Review']);

        $this->assertSame([$this->u('auditor')->id], $this->penerima('CAPA_TINDAK_LANJUT', $t));
        $this->assertSame('/auditor/temuan-capa', Notifikasi::where('tipe', 'CAPA_TINDAK_LANJUT')->where('referensi_id', (string) $t->id)->value('tautan'));
    }

    public function test_evaluasi_closed_dan_kembali_in_progress_ke_auditee(): void
    {
        $t = $this->temuan(['status_capa' => 'In Review']);
        $t->update(['status_capa' => 'Closed']);
        $this->assertSame([$this->u('kepalaunit')->id], $this->penerima('CAPA_DIEVALUASI', $t));

        $t2 = $this->temuan(['status_capa' => 'In Review']);
        $t2->update(['status_capa' => 'In Progress']);
        $this->assertSame([$this->u('kepalaunit')->id], $this->penerima('CAPA_DIEVALUASI', $t2));
        $this->assertSame([], $this->penerima('CAPA_TINDAK_LANJUT', $t2));
    }

    public function test_cron_jatuh_tempo_besok_ke_auditee_tanpa_duplikat(): void
    {
        $t = $this->temuan(['due_date_capa' => now()->addDay()->toDateString()]);

        Artisan::call('simlog:alert-capa-jatuh-tempo');
        Artisan::call('simlog:alert-capa-jatuh-tempo');

        $this->assertSame([$this->u('kepalaunit')->id], $this->penerima('CAPA_JATUH_TEMPO', $t));
        $this->assertSame(0, Notifikasi::where('tipe', 'CAPA_JATUH_TEMPO')->where('pengguna_id', $this->u('karu_icu')->id)->where('referensi_id', (string) $t->id)->count());
        $this->assertGreaterThanOrEqual(1, Cache::get('simlog:last_run:alert_capa_jatuh_tempo')['capa_count']);
    }

    public function test_cron_terlambat_memakai_tipe_sama_dengan_judul_berbeda(): void
    {
        $t = $this->temuan(['due_date_capa' => now()->subDays(3)->toDateString()]);

        Artisan::call('simlog:alert-capa-jatuh-tempo');

        $n = Notifikasi::where('tipe', 'CAPA_JATUH_TEMPO')->where('referensi_id', (string) $t->id)->firstOrFail();
        $this->assertStringContainsString('Terlambat', $n->judul);
    }

    public function test_cron_mengabaikan_closed_dan_in_review(): void
    {
        $closed = $this->temuan(['status_capa' => 'Closed', 'due_date_capa' => now()->addDay()->toDateString()]);
        $review = $this->temuan(['status_capa' => 'In Review', 'due_date_capa' => now()->subDay()->toDateString()]);

        Artisan::call('simlog:alert-capa-jatuh-tempo');

        $this->assertSame(0, Notifikasi::where('tipe', 'CAPA_JATUH_TEMPO')->whereIn('referensi_id', [(string) $closed->id, (string) $review->id])->count());
    }
}
