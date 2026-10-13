<?php

namespace Tests\Feature\Pengadaan;

use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class PengadaanHubNavigationTest extends TestCase
{
    public function test_pengadaan_transaksi_tabs_render_for_authorized_users(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $view = $this->blade('<x-tabs.pengadaan-transaksi active="pr" />');
        $view->assertSee('Permintaan (PR)');
        $view->assertSee('Pesanan (PO)');
        $view->assertSee('Penerimaan (Inbound)');
        $view->assertDontSee('/livewire/');
    }

    public function test_pengadaan_evaluasi_tabs_render_for_authorized_users(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $view = $this->blade('<x-tabs.pengadaan-evaluasi active="anggaran" />');
        $view->assertSee('Pagu & Realisasi', false);
        $view->assertSee('Evaluasi Rekanan', false);
        $view->assertDontSee('/livewire/');
    }
}
