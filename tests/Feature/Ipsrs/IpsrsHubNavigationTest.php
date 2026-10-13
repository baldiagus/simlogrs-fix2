<?php

namespace Tests\Feature\Ipsrs;

use App\Models\User;
use Tests\TestCase;

class IpsrsHubNavigationTest extends TestCase
{
    public function test_ipsrs_servis_tabs_render_for_authorized_users(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $view = $this->blade('<x-tabs.ipsrs-servis active="lapor" />');
        $view->assertSee('Lapor Kerusakan');
        $view->assertSee('Perintah Kerja (SPK)');
        $view->assertSee('Lembar Kerja Servis');
        $view->assertDontSee('/livewire/');
    }

    public function test_ipsrs_rutin_tabs_render_for_authorized_users(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $view = $this->blade('<x-tabs.ipsrs-rutin active="preventif" />');
        $view->assertSee('Jadwal Preventif');
        $view->assertSee('Rekam Medis Aset');
        $view->assertDontSee('/livewire/');
    }
}
