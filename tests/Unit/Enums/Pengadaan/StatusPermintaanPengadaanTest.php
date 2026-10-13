<?php

namespace Tests\Unit\Enums\Pengadaan;

use App\Enums\Pengadaan\StatusPermintaanPengadaan as S;
use App\Models\Pengadaan\PermintaanPengadaan;
use PHPUnit\Framework\TestCase;

class StatusPermintaanPengadaanTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Diajukan->bolehKe(S::DiteruskanKePengadaan));
        $this->assertTrue(S::Diajukan->bolehKe(S::Ditolak));
        $this->assertTrue(S::Disetujui->bolehKe(S::DiteruskanKePengadaan), 'data lama DISETUJUI masih bisa diteruskan');
        $this->assertTrue(S::Disetujui->bolehKe(S::Ditolak), 'DISETUJUI non-final, boleh ditolak (ADR 0002 A7)');
        $this->assertTrue(S::DiteruskanKePengadaan->bolehKe(S::Diproses));
        $this->assertTrue(S::DiteruskanKePengadaan->bolehKe(S::Ditolak), 'penolakan oleh bagian pengadaan');
        $this->assertTrue(S::Diproses->bolehKe(S::Diproses), 'PO tambahan dari PR yang sudah diproses');
        $this->assertTrue(S::Diproses->bolehKe(S::Selesai));

        $this->assertFalse(S::Diajukan->bolehKe(S::Diproses), 'PO wajib lewat penerusan ke pengadaan');
        $this->assertFalse(S::Diajukan->bolehKe(S::Selesai));
        $this->assertFalse(S::Diproses->bolehKe(S::Ditolak), 'PR dengan PO aktif tidak bisa ditolak');
    }

    public function test_status_final_tidak_bisa_berpindah(): void
    {
        foreach ([S::Selesai, S::Ditolak] as $final) {
            $this->assertSame([], $final->transisiSah());
        }
        $this->assertSame(['SELESAI', 'DITOLAK'], S::riwayat());
    }

    public function test_status_siap_po_sesuai_guard_api(): void
    {
        $this->assertSame(['DITERUSKAN_KE_PENGADAAN', 'DIPROSES', 'DISETUJUI'], S::siapPo());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['DIAJUKAN', 'DISETUJUI', 'DITERUSKAN_KE_PENGADAAN', 'DIPROSES', 'SELESAI', 'DITOLAK'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }

    public function test_konstanta_legacy_tidak_lagi_didefinisikan_pada_model(): void
    {
        $reflector = new \ReflectionClass(PermintaanPengadaan::class);
        $this->assertFalse($reflector->hasConstant('STATUS_DIAJUKAN'));
        $this->assertFalse($reflector->hasConstant('STATUS_DITERUSKAN_KE_PENGADAAN'));
        $this->assertFalse($reflector->hasConstant('STATUS_DITOLAK'));
        $this->assertFalse($reflector->hasConstant('STATUS_DIPROSES'));
        $this->assertFalse($reflector->hasConstant('STATUS_SELESAI'));
    }
}
