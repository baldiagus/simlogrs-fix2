<?php

namespace Tests\Unit\Enums\Aset;

use App\Enums\Aset\StatusPengalihan as S;
use PHPUnit\Framework\TestCase;

class StatusPengalihanTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Diajukan->bolehKe(S::Disetujui));
        $this->assertTrue(S::Diajukan->bolehKe(S::Dibatalkan));
        $this->assertTrue(S::Disetujui->bolehKe(S::Selesai));
        $this->assertTrue(S::Disetujui->bolehKe(S::Ditolak));

        $this->assertFalse(S::Diajukan->bolehKe(S::Selesai), 'serah terima wajib lewat persetujuan');
        $this->assertFalse(S::Disetujui->bolehKe(S::Dibatalkan), 'dokumen disetujui tidak bisa dibatalkan pengaju');
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Selesai, S::Ditolak, S::Dibatalkan] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DIAJUKAN', 'DISETUJUI', 'SELESAI', 'DITOLAK', 'DIBATALKAN'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
