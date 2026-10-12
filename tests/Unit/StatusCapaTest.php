<?php

namespace Tests\Unit;

use App\Enums\StatusCapa as S;
use PHPUnit\Framework\TestCase;

class StatusCapaTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::InProgress->bolehKe(S::InReview));
        $this->assertTrue(S::InReview->bolehKe(S::Closed));
        $this->assertTrue(S::InReview->bolehKe(S::InProgress), 'auditor boleh mengembalikan untuk revisi');

        $this->assertFalse(S::InProgress->bolehKe(S::Closed), 'penutupan wajib lewat evaluasi');
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        $this->assertSame([], S::Closed->transisiSah());
    }

    public function test_pengelompokan_status(): void
    {
        $this->assertSame(['Closed', 'In Progress'], S::hasilEvaluasi());
        $this->assertSame(['In Progress', 'In Review'], S::aktif());
        $this->assertSame(['Closed'], S::riwayat());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['In Progress', 'In Review', 'Closed'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
