<?php

namespace Tests\Unit\Enums\Persediaan;

use App\Enums\Persediaan\StatusStokOpname as S;
use PHPUnit\Framework\TestCase;

class StatusStokOpnameTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Berjalan->bolehKe(S::ProsesCount));
        $this->assertTrue(S::Berjalan->bolehKe(S::MenungguApproval));
        $this->assertTrue(S::Berjalan->bolehKe(S::Disetujui), 'tanpa selisih langsung disetujui');
        $this->assertTrue(S::ProsesCount->bolehKe(S::MenungguApproval));
        $this->assertTrue(S::MenungguApproval->bolehKe(S::Disetujui));
        $this->assertTrue(S::MenungguApproval->bolehKe(S::Ditolak), 'penolakan penyesuaian (web dan API)');
        $this->assertFalse(S::MenungguApproval->bolehKe(S::Dibatalkan), 'DIBATALKAN tidak lagi ditulis (ADR 0002 A1)');

        $this->assertFalse(S::Berjalan->bolehKe(S::Ditolak), 'penolakan hanya lewat dokumen penyesuaian');
        $this->assertFalse(S::Draft->bolehKe(S::Berjalan), 'DRAFT tidak dipakai kode');
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Disetujui, S::Ditolak, S::Dibatalkan] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
        $this->assertSame(['DISETUJUI', 'DITOLAK', 'DIBATALKAN'], S::riwayat());
    }

    public function test_sesi_yang_bisa_dihitung_sesuai_guard_api(): void
    {
        $this->assertSame(['BERJALAN', 'PROSES_COUNT', 'MENUNGGU_APPROVAL'], S::bisaDihitung());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DRAFT', 'BERJALAN', 'PROSES_COUNT', 'MENUNGGU_APPROVAL', 'DISETUJUI', 'DITOLAK', 'DIBATALKAN'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
