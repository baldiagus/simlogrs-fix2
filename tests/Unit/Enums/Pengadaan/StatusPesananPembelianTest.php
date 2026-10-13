<?php

namespace Tests\Unit\Enums\Pengadaan;

use App\Enums\Pengadaan\StatusPesananPembelian as S;
use PHPUnit\Framework\TestCase;

class StatusPesananPembelianTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Diterbitkan->bolehKe(S::DikirimVendor));
        $this->assertTrue(S::DikirimVendor->bolehKe(S::DiterimaSebagian));
        $this->assertTrue(S::DikirimVendor->bolehKe(S::DiterimaLengkap));
        $this->assertTrue(S::DiterimaSebagian->bolehKe(S::DiterimaSebagian), 'penerimaan parsial berikutnya');
        $this->assertTrue(S::DiterimaSebagian->bolehKe(S::DiterimaLengkap));

        $this->assertFalse(S::Diterbitkan->bolehKe(S::DiterimaLengkap), 'barang belum dikirim vendor');
        $this->assertFalse(S::DiterimaSebagian->bolehKe(S::DikirimVendor));
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::DiterimaLengkap, S::Dibatalkan] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
    }

    public function test_pengelompokan_status(): void
    {
        $this->assertSame(['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'], S::bisaDiterima());
        $this->assertSame(['DITERBITKAN', 'DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'], S::aktif());
        $this->assertSame(['DITERIMA_LENGKAP', 'DIBATALKAN'], S::riwayat());
        $this->assertNotContains('DIBATALKAN', S::tidakDibatalkan());
        $this->assertCount(count(S::cases()) - 1, S::tidakDibatalkan());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DITERBITKAN', 'DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN', 'DITERIMA_LENGKAP', 'DIBATALKAN'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
