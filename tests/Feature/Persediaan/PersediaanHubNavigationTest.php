<?php

namespace Tests\Feature\Persediaan;

use App\Models\User;
use Tests\TestCase;

class PersediaanHubNavigationTest extends TestCase
{
    public function test_persediaan_monitoring_tabs_render_for_authorized_users(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $view = $this->blade('<x-persediaan-monitoring-tabs active="fefo" />');
        $view->assertSee('Monitoring Saldo & FEFO', false);
        $view->assertSee('Kartu Mutasi Stok', false);
        $view->assertDontSee('/livewire/');
    }

    public function test_persediaan_opname_tabs_render_for_authorized_users(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $view = $this->blade('<x-persediaan-opname-tabs active="opname" />');
        $view->assertSee('Sesi Stock Opname', false);
        $view->assertSee('Otorisasi Penyesuaian', false);
        $view->assertDontSee('/livewire/');
    }
}
