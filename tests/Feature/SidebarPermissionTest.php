<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\HtmlString;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * ADR 0001: menu sidebar ditampilkan dengan permission yang sama dengan guard halaman,
 * yaitu page_* rute (PortalAccessMiddleware) DAN view_any_* / view_* modul.
 */
class SidebarPermissionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function makeUser(array $permissions = [], array $roles = []): User
    {
        $suffix = uniqid();
        $user = User::create([
            'nama' => 'Uji Sidebar ' . $suffix,
            'username' => 'uji_sidebar_' . $suffix,
            'email' => "uji_sidebar_{$suffix}@simlog.test",
            'password' => bcrypt('password'),
            'status' => 'AKTIF',
        ]);

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        if ($roles) {
            $user->assignRole($roles);
        }

        return $user->fresh();
    }

    private function renderSidebar(User $user, string $portal = 'pengadaan'): string
    {
        $this->actingAs($user);

        return view('layouts.portal', [
            'portal' => $portal,
            'title' => 'Uji Sidebar',
            'slot' => new HtmlString(''),
        ])->render();
    }

    private function assertMenuVisible(string $html, string $path, string $portal = 'pengadaan'): void
    {
        $this->assertStringContainsString("/{$portal}/{$path}\"", $html, "Menu /{$portal}/{$path} seharusnya tampil.");
    }

    private function assertMenuHidden(string $html, string $path, string $portal = 'pengadaan'): void
    {
        $this->assertStringNotContainsString("/{$portal}/{$path}\"", $html, "Menu /{$portal}/{$path} seharusnya tersembunyi.");
    }

    public function test_role_only_without_view_permission_hides_menu(): void
    {
        Role::findByName('pengadaan', 'web')->revokePermissionTo([
            'view_any_pengadaan::realisasi::anggaran', 'view_pengadaan::realisasi::anggaran',
            'view_any_pengadaan::master::rekanan', 'view_pengadaan::master::rekanan',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $html = $this->renderSidebar($this->makeUser(roles: ['pengadaan']));

        $this->assertMenuHidden($html, 'realisasi-anggaran');
        $this->assertMenuHidden($html, 'master-rekanan');
        $this->assertMenuVisible($html, 'permintaan-pengadaan');
    }

    public function test_page_permission_without_view_permission_hides_menu(): void
    {
        $html = $this->renderSidebar($this->makeUser([
            'page_PengadaanDashboard', 'page_PermintaanPengadaanIndex', 'page_RealisasiAnggaranIndex',
        ]));

        $this->assertMenuHidden($html, 'permintaan-pengadaan');
        $this->assertMenuHidden($html, 'realisasi-anggaran');
    }

    public function test_view_permission_without_route_page_permission_hides_menu(): void
    {
        // Rute akan menolak (403) tanpa page_*, jadi menu juga tidak boleh tampil.
        $html = $this->renderSidebar($this->makeUser([
            'view_any_pengadaan::permintaan::pengadaan', 'view_any_pengadaan::master::rekanan',
        ]));

        $this->assertMenuHidden($html, 'permintaan-pengadaan');
        $this->assertMenuHidden($html, 'master-rekanan');
    }

    public function test_page_and_view_permission_shows_menu_including_view_only_grant(): void
    {
        $html = $this->renderSidebar($this->makeUser([
            'page_MasterRekananIndex', 'view_pengadaan::master::rekanan',
            'page_PesananPembelianIndex', 'view_any_pengadaan::pesanan::pembelian',
        ]));

        $this->assertMenuVisible($html, 'master-rekanan');
        $this->assertMenuVisible($html, 'pesanan-pembelian');
        $this->assertMenuHidden($html, 'permintaan-pengadaan');
    }

    public function test_master_group_shows_items_independently_of_katalog_barang(): void
    {
        $html = $this->renderSidebar($this->makeUser([
            'page_PemasokIndex', 'view_any_master::pemasok',
        ]));

        $this->assertMenuVisible($html, 'pemasok');
        $this->assertMenuHidden($html, 'barang');
    }

    public function test_sirkulasi_menu_follows_approval_page_guard(): void
    {
        $html = $this->renderSidebar($this->makeUser([
            'page_ApprovalPeminjamanIndex', 'view_any_layanan_unit::approval::peminjaman',
        ]), 'kepala-unit');

        $this->assertMenuVisible($html, 'approval-peminjaman', 'kepala-unit');
        $this->assertMenuHidden($html, 'peminjaman-aset', 'kepala-unit');
        $this->assertMenuHidden($html, 'pengembalian-aset', 'kepala-unit');
    }

    public function test_dashboard_permission_does_not_reveal_module_menus(): void
    {
        $html = $this->renderSidebar($this->makeUser([
            'page_Dashboard', 'page_AdminLogistikDashboard', 'page_ManajemenDashboard',
        ]));

        foreach (['permintaan-pengadaan', 'pesanan-pembelian', 'penerimaan-po', 'realisasi-anggaran', 'master-rekanan', 'peminjaman-aset', 'pengalihan-aset', 'barang'] as $path) {
            $this->assertMenuHidden($html, $path);
        }
    }
}
