<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Tests\TestCase;

class AuditHubNavigationTest extends TestCase
{
    public function test_audit_kepatuhan_tabs_render_for_authorized_users(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $view = $this->blade('<x-tabs.audit-kepatuhan active="trail" />');
        $view->assertSee('Audit Trail (Forensik)', false);
        $view->assertSee('Kepatuhan Pengadaan', false);
        $view->assertSee('Selisih Opname (Audit)', false);
        $view->assertDontSee('/livewire/');
    }
}
