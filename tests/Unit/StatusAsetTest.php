<?php

namespace Tests\Unit;

use App\Enums\StatusAset as S;
use PHPUnit\Framework\TestCase;

class StatusAsetTest extends TestCase
{
    public function test_hanya_transisi_sah_yang_diizinkan(): void
    {
        $this->assertTrue(S::Tersedia->bolehKe(S::Dipinjam), 'serah terima peminjaman');
        $this->assertTrue(S::Dipinjam->bolehKe(S::Tersedia), 'pengembalian kondisi baik');
        $this->assertTrue(S::Dipinjam->bolehKe(S::DalamMaintenance), 'pengembalian kondisi rusak');
        $this->assertTrue(S::Tersedia->bolehKe(S::DalamPengalihan));
        $this->assertTrue(S::Digunakan->bolehKe(S::DalamPengalihan));
        $this->assertTrue(S::DalamPengalihan->bolehKe(S::Tersedia));
        $this->assertTrue(S::DalamMaintenance->bolehKe(S::Digunakan), 'perbaikan selesai di ruangan');
        $this->assertTrue(S::DalamMaintenance->bolehKe(S::Tersedia), 'perbaikan selesai di gudang');
        $this->assertTrue(S::Tersedia->bolehKe(S::Digunakan));
        $this->assertTrue(S::Digunakan->bolehKe(S::Tersedia));

        $this->assertFalse(S::Digunakan->bolehKe(S::Dipinjam), 'peminjaman mensyaratkan aset TERSEDIA');
        $this->assertFalse(S::Dipinjam->bolehKe(S::DalamPengalihan), 'aset dipinjam tidak bisa dialihkan');
        $this->assertFalse(S::DalamMaintenance->bolehKe(S::DalamPengalihan));
        $this->assertFalse(S::DalamPengalihan->bolehKe(S::Dipinjam));
    }

    public function test_aset_dihapuskan_final_dalam_proses_bisnis(): void
    {
        $this->assertSame([], S::Dihapuskan->transisiSah());

        foreach (S::cases() as $status) {
            $this->assertFalse($status->bolehKe(S::Dihapuskan), 'penghapusan hanya lewat Master Aset');
        }
    }

    public function test_pengelompokan_status(): void
    {
        $this->assertSame(['TERSEDIA', 'DIGUNAKAN'], S::dapatDialihkan());
        $this->assertSame(['TERSEDIA', 'DIGUNAKAN', 'DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN'], S::dapatDiaturManual());
        $this->assertNotContains(S::DalamPengalihan->value, S::dapatDiaturManual());
    }

    public function test_nilai_enum_sama_dengan_nilai_kolom_database(): void
    {
        $this->assertSame(
            ['TERSEDIA', 'DIGUNAKAN', 'DIPINJAM', 'DALAM_MAINTENANCE', 'DALAM_PENGALIHAN', 'DIHAPUSKAN'],
            array_map(fn (S $s) => $s->value, S::cases())
        );
    }
}
