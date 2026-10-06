<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SidebarDuplicationTest extends TestCase
{
    public function test_superadmin_sidebar_has_no_duplicate_module_groups(): void
    {
        $user = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($user);

        $response = $this->get('/super-admin/dashboard');
        $response->assertOk();

        $content = $response->getContent();

        // Setiap nama grup heading modul harus muncul tepat 1 KALI di sidebar
        $this->assertEquals(1, substr_count($content, 'Pemeliharaan &amp; IPSRS'), 'Heading Pemeliharaan & IPSRS terduplikasi');
        $this->assertEquals(1, substr_count($content, 'Persediaan &amp; Opname'), 'Heading Persediaan & Opname terduplikasi');
        $this->assertEquals(1, substr_count($content, 'Audit Internal (SPI)'), 'Heading Audit Internal (SPI) terduplikasi');
        $this->assertEquals(1, substr_count($content, 'Pengalihan &amp; Mutasi'), 'Heading Pengalihan & Mutasi terduplikasi');
    }
}
