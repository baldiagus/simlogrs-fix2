<?php

namespace Tests\Unit\Enums\Ipsrs;

use App\Enums\Ipsrs\StatusSpk as S;
use PHPUnit\Framework\TestCase;

class StatusSpkTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Ditugaskan->bolehKe(S::Diterima));
        $this->assertTrue(S::Ditugaskan->bolehKe(S::Selesai));
        $this->assertTrue(S::Diterima->bolehKe(S::Diterima), 'lembar kerja lanjutan tetap DITERIMA');
        $this->assertTrue(S::Diterima->bolehKe(S::Selesai));

        $this->assertFalse(S::Ditugaskan->bolehKe(S::Dibatalkan), 'tidak ada alur pembatalan SPK');
        $this->assertFalse(S::Diterima->bolehKe(S::Ditugaskan));
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Selesai, S::Dibatalkan] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
    }

    public function test_pengelompokan_status(): void
    {
        $this->assertSame(['DITUGASKAN', 'DITERIMA'], S::aktif());
        $this->assertSame(['SELESAI', 'DIBATALKAN'], S::riwayat());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DITUGASKAN', 'DITERIMA', 'SELESAI', 'DIBATALKAN'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
