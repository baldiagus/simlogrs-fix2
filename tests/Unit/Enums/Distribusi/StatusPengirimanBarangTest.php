<?php

namespace Tests\Unit\Enums\Distribusi;

use App\Enums\Distribusi\StatusPengirimanBarang as S;
use PHPUnit\Framework\TestCase;

class StatusPengirimanBarangTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Dikirim->bolehKe(S::Diterima));

        $this->assertFalse(S::Dikirim->bolehKe(S::Batal), 'belum ada alur pembatalan SPPB di kode');
        $this->assertFalse(S::Diterima->bolehKe(S::Dikirim));
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Diterima, S::Batal] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
    }

    public function test_pengelompokan_status(): void
    {
        $this->assertSame(['DIKIRIM'], S::aktif());
        $this->assertSame(['DITERIMA', 'BATAL'], S::riwayat());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DIKIRIM', 'DITERIMA', 'BATAL'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
