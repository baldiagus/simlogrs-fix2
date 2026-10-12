<?php

namespace Tests\Unit;

use App\Enums\StatusPenyesuaianStok as S;
use PHPUnit\Framework\TestCase;

class StatusPenyesuaianStokTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Menunggu->bolehKe(S::Disetujui));
        $this->assertTrue(S::Menunggu->bolehKe(S::Ditolak));
        $this->assertFalse(S::Menunggu->bolehKe(S::Menunggu));
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Disetujui, S::Ditolak] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
        $this->assertSame(['MENUNGGU'], S::aktif());
        $this->assertSame(['DISETUJUI', 'DITOLAK'], S::riwayat());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['MENUNGGU', 'DISETUJUI', 'DITOLAK'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
