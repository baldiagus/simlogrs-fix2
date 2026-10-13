<?php

namespace Tests\Feature\Security;

use App\Support\Portal\PortalResolver;
use Tests\TestCase;

class PortalResolverTest extends TestCase
{
    public function test_portal_resolver_resolves_standard_portal_segment(): void
    {
        $resolved = PortalResolver::resolve('kepala-gudang');
        $this->assertEquals('kepala-gudang', $resolved);
    }

    public function test_portal_resolver_never_resolves_to_livewire_during_livewire_ajax(): void
    {
        // Simulasi request Livewire AJAX (/livewire/update)
        request()->server->set('REQUEST_URI', '/livewire/update');
        request()->headers->set('Referer', 'http://127.0.0.1:8000/super-admin/picking-distribusi?activeTab=riwayat-sppb');

        $resolved = PortalResolver::resolve();
        $this->assertNotEquals('livewire', $resolved);
        $this->assertEquals('super-admin', $resolved);
    }

    public function test_portal_resolver_resolves_role_fallback_when_referer_missing(): void
    {
        $user = \App\Models\User::where('email', 'kepalagudang@simlogrs.local')->first();
        $this->actingAs($user);

        request()->server->set('REQUEST_URI', '/livewire/update');
        request()->headers->remove('Referer');

        $resolved = PortalResolver::resolve();
        $this->assertEquals('kepala-gudang', $resolved);
    }
}
