<?php

namespace Tests\Feature\Aset;

use App\Models\User;
use Tests\TestCase;

class SirkulasiAsetHubNavigationTest extends TestCase
{
    public function test_sirkulasi_peminjaman_tabs_render_for_authorized_users(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $view = $this->blade('<x-sirkulasi-peminjaman-tabs active="pinjam" />');
        $view->assertSee('Permohonan Pinjam');
        $view->assertSee('Otorisasi Peminjaman');
        $view->assertDontSee('/livewire/');
    }
}
