<?php

namespace Tests\Unit\Enums\Pengadaan;

use App\Enums\Pengadaan\StatusPenerimaanPo as S;
use PHPUnit\Framework\TestCase;

class StatusPenerimaanPoTest extends TestCase
{
    public function test_dokumen_penerimaan_final_sejak_dibuat(): void
    {
        foreach (S::cases() as $status) {
            $this->assertSame([], $status->transisiSah());
            foreach (S::cases() as $tujuan) {
                $this->assertFalse($status->bolehKe($tujuan));
            }
        }
    }

    public function test_penerimaan_yang_memasukkan_stok(): void
    {
        $this->assertSame(['LENGKAP', 'SEBAGIAN'], S::diterima());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['LENGKAP', 'SEBAGIAN', 'DITOLAK'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
