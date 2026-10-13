<?php

namespace Tests\Unit\Enums\Ipsrs;

use App\Enums\Ipsrs\StatusMaintenance as S;
use PHPUnit\Framework\TestCase;

class StatusMaintenanceTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Dilaporkan->bolehKe(S::Ditugaskan));
        $this->assertTrue(S::Dilaporkan->bolehKe(S::Dibatalkan));
        $this->assertTrue(S::MenungguSpk->bolehKe(S::Ditugaskan));
        $this->assertTrue(S::Ditugaskan->bolehKe(S::Selesai));
        $this->assertTrue(S::Ditugaskan->bolehKe(S::UjiFungsi));
        $this->assertTrue(S::UjiFungsi->bolehKe(S::Selesai));
        $this->assertTrue(S::MenungguSparepart->bolehKe(S::SedangDikerjakan));
        $this->assertTrue(S::SedangDikerjakan->bolehKe(S::SedangDikerjakan), 'lembar kerja PENDING boleh diisi ulang');

        $this->assertFalse(S::Dilaporkan->bolehKe(S::Selesai), 'pengerjaan wajib lewat SPK');
        $this->assertFalse(S::Ditugaskan->bolehKe(S::Dibatalkan), 'tiket ber-SPK tidak bisa dibatalkan pelapor');
        $this->assertFalse(S::MenungguSpk->bolehKe(S::Dibatalkan), 'pembatalan hanya untuk tiket DILAPORKAN');
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Selesai, S::Dibatalkan] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
    }

    public function test_pengelompokan_status(): void
    {
        $this->assertSame(['DILAPORKAN', 'MENUNGGU_SPK'], S::menungguPenugasan());
        $this->assertSame(['DILAPORKAN', 'DITUGASKAN', 'SEDANG_DIKERJAKAN', 'MENUNGGU_SPAREPART', 'UJI_FUNGSI'], S::aktif());
        $this->assertSame(['SELESAI', 'DIBATALKAN'], S::riwayat());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DILAPORKAN', 'DITUGASKAN', 'SEDANG_DIKERJAKAN', 'MENUNGGU_SPAREPART', 'UJI_FUNGSI', 'SELESAI', 'DIBATALKAN', 'MENUNGGU_SPK'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
