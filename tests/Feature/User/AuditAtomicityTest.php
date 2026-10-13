<?php

namespace Tests\Feature\User;

use App\Livewire\Auth\GantiPasswordWajib;
use App\Livewire\User\PenggunaIndex;
use App\Livewire\User\RoleManager;
use App\Models\Audit\LogAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ADR 0002 B: mutasi akun/peran dan audit-nya berada dalam satu transaksi.
 * Jika penulisan log_audit gagal, mutasi wajib dibatalkan.
 */
class AuditAtomicityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama'     => 'Super Admin Atomicity',
            'username' => 'superatomicity',
            'email'    => 'superatomicity@simlog.test',
            'password' => Hash::make('password123'),
            'status'   => 'AKTIF',
        ]);
        $this->admin->assignRole(['super_admin', 'panel_user']);

        $this->target = User::create([
            'nama'     => 'Pegawai Target Atomicity',
            'username' => 'targetatomicity',
            'email'    => 'targetatomicity@simlog.test',
            'password' => Hash::make('passwordLama1'),
            'status'   => 'AKTIF',
            'wajib_ganti_password' => false,
        ]);
        $this->target->assignRole(['petugas_unit', 'panel_user']);
    }

    /**
     * Jalankan aksi dengan penulisan LogAudit dipaksa gagal, pastikan exception
     * tidak ditelan, lalu lepas kembali listener-nya.
     */
    protected function withFailingAudit(callable $action): void
    {
        LogAudit::creating(fn () => throw new RuntimeException('log_audit tidak dapat ditulis'));

        try {
            $this->assertThrows($action, RuntimeException::class, 'log_audit tidak dapat ditulis');
        } finally {
            LogAudit::flushEventListeners();
        }
    }

    protected function fillNewUserForm($component, string $username)
    {
        return $component
            ->call('openCreate')
            ->set('nama', 'Pegawai Baru Atomicity')
            ->set('username', $username)
            ->set('email', "{$username}@simlog.test")
            ->set('password', 'rahasia123')
            ->set('password_confirmation', 'rahasia123')
            ->set('selectedRole', 'auditor')
            ->set('status', 'AKTIF');
    }

    public function test_create_user_rolls_back_when_audit_fails(): void
    {
        $component = $this->fillNewUserForm(Livewire::actingAs($this->admin)->test(PenggunaIndex::class), 'baruatomicity');

        $this->withFailingAudit(fn () => $component->call('save'));

        $this->assertDatabaseMissing('pengguna', ['username' => 'baruatomicity']);
    }

    public function test_create_user_happy_path_writes_exactly_one_audit_row(): void
    {
        $before = LogAudit::count();

        $this->fillNewUserForm(Livewire::actingAs($this->admin)->test(PenggunaIndex::class), 'baruatomicity')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('username', 'baruatomicity')->firstOrFail();
        $this->assertTrue($user->hasRole('auditor'));
        $this->assertSame($before + 1, LogAudit::count());
        $this->assertSame(1, LogAudit::where('referensi_jenis', 'User')->where('referensi_id', $user->id)->where('aksi', 'TAMBAH')->count());
    }

    public function test_update_user_and_role_roll_back_when_audit_fails(): void
    {
        $component = Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
            ->call('openEdit', $this->target->id)
            ->set('nama', 'Nama Sudah Diubah')
            ->set('selectedRole', 'auditor');

        $this->withFailingAudit(fn () => $component->call('save'));

        $fresh = $this->target->fresh();
        $this->assertSame('Pegawai Target Atomicity', $fresh->nama);
        $this->assertTrue($fresh->hasRole('petugas_unit'));
        $this->assertFalse($fresh->hasRole('auditor'));
    }

    public function test_update_user_happy_path_writes_exactly_one_audit_row(): void
    {
        $before = LogAudit::count();

        Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
            ->call('openEdit', $this->target->id)
            ->set('selectedRole', 'auditor')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($this->target->fresh()->hasRole('auditor'));
        $this->assertSame($before + 1, LogAudit::count());
        $this->assertSame(1, LogAudit::where('referensi_id', $this->target->id)->where('aksi', 'RBAC_MUTATION')->count());
    }

    public function test_reset_password_rolls_back_when_audit_fails(): void
    {
        $this->target->createToken('mobile');
        $oldHash = $this->target->password;

        $component = Livewire::actingAs($this->admin)->test(PenggunaIndex::class);

        $this->withFailingAudit(fn () => $component->call('executeResetPassword', $this->target->id));

        $fresh = $this->target->fresh();
        $this->assertSame($oldHash, $fresh->password);
        $this->assertFalse((bool) $fresh->wajib_ganti_password);
        $this->assertSame(1, $fresh->tokens()->count());
    }

    public function test_reset_password_happy_path_writes_exactly_one_audit_row(): void
    {
        $before = LogAudit::count();

        Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
            ->call('executeResetPassword', $this->target->id)
            ->assertSet('showResetSuccessModal', true);

        $this->assertTrue((bool) $this->target->fresh()->wajib_ganti_password);
        $this->assertSame($before + 1, LogAudit::count());

        $log = LogAudit::where('referensi_id', $this->target->id)->where('aksi', 'RESET_PASSWORD')->sole();
        $this->assertFalse($log->data_sebelum['wajib_ganti_password']);
        $this->assertTrue($log->data_sesudah['wajib_ganti_password']);
    }

    public function test_toggle_status_rolls_back_when_audit_fails(): void
    {
        $this->target->createToken('mobile');
        $component = Livewire::actingAs($this->admin)->test(PenggunaIndex::class);

        $this->withFailingAudit(fn () => $component->call('executeToggleStatus', $this->target->id));

        $fresh = $this->target->fresh();
        $this->assertSame('AKTIF', $fresh->status);
        $this->assertSame(1, $fresh->tokens()->count());
    }

    public function test_toggle_status_happy_path_writes_exactly_one_audit_row(): void
    {
        $before = LogAudit::count();

        Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
            ->call('executeToggleStatus', $this->target->id);

        $this->assertSame('NONAKTIF', $this->target->fresh()->status);
        $this->assertSame($before + 1, LogAudit::count());
    }

    public function test_delete_user_rolls_back_when_audit_fails(): void
    {
        $this->target->createToken('mobile');
        $component = Livewire::actingAs($this->admin)->test(PenggunaIndex::class);

        $this->withFailingAudit(fn () => $component->call('delete', $this->target->id));

        $this->assertDatabaseHas('pengguna', ['id' => $this->target->id]);
        $this->assertSame(1, $this->target->tokens()->count());
    }

    public function test_delete_user_happy_path_writes_exactly_one_audit_row(): void
    {
        $before = LogAudit::count();

        Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
            ->call('delete', $this->target->id);

        $this->assertDatabaseMissing('pengguna', ['id' => $this->target->id]);
        $this->assertSame($before + 1, LogAudit::count());
        $this->assertSame(1, LogAudit::where('referensi_id', $this->target->id)->where('aksi', 'HAPUS')->count());
    }

    protected function roleHasPermission(Role $role, string $permission): bool
    {
        return $role->permissions()->where('name', $permission)->exists();
    }

    public function test_role_permission_save_rolls_back_when_audit_fails(): void
    {
        $role = Role::findByName('auditor', 'web');
        $this->assertFalse($this->roleHasPermission($role, 'page_Dashboard'), 'Prasyarat: auditor belum memiliki page_Dashboard.');

        $component = Livewire::actingAs($this->admin)->test(RoleManager::class)
            ->call('selectRole', $role->id)
            ->set('state.dashboards.page_Dashboard', true);

        $this->withFailingAudit(fn () => $component->call('save'));

        $this->assertFalse($this->roleHasPermission($role, 'page_Dashboard'));
    }

    public function test_role_permission_save_happy_path_writes_exactly_one_audit_row(): void
    {
        $role = Role::findByName('auditor', 'web');
        $before = LogAudit::count();

        Livewire::actingAs($this->admin)->test(RoleManager::class)
            ->call('selectRole', $role->id)
            ->set('state.dashboards.page_Dashboard', true)
            ->call('save');

        $this->assertTrue($this->roleHasPermission($role, 'page_Dashboard'));
        $this->assertSame($before + 1, LogAudit::count());
        $this->assertSame(1, LogAudit::where('referensi_jenis', 'Role')->where('referensi_id', $role->id)->where('aksi', 'RBAC_MUTATION')->count());
    }

    protected function fillGantiPassword()
    {
        $this->target->update(['wajib_ganti_password' => true]);

        return Livewire::actingAs($this->target)->test(GantiPasswordWajib::class)
            ->set('password_saat_ini', 'passwordLama1')
            ->set('password_baru', 'passwordBaru2')
            ->set('password_baru_confirmation', 'passwordBaru2');
    }

    public function test_forced_password_change_web_rolls_back_when_audit_fails(): void
    {
        $this->target->createToken('mobile');
        $component = $this->fillGantiPassword();

        $this->withFailingAudit(fn () => $component->call('gantiPassword'));

        $fresh = $this->target->fresh();
        $this->assertTrue(Hash::check('passwordLama1', $fresh->password));
        $this->assertTrue((bool) $fresh->wajib_ganti_password);
        $this->assertSame(1, $fresh->tokens()->count());
    }

    public function test_forced_password_change_web_happy_path_writes_exactly_one_audit_row(): void
    {
        $before = LogAudit::count();

        $this->fillGantiPassword()
            ->call('gantiPassword')
            ->assertHasNoErrors()
            ->assertRedirect();

        $fresh = $this->target->fresh();
        $this->assertTrue(Hash::check('passwordBaru2', $fresh->password));
        $this->assertFalse((bool) $fresh->wajib_ganti_password);
        $this->assertSame($before + 1, LogAudit::count());
    }

    public function test_forced_password_change_api_rolls_back_when_audit_fails(): void
    {
        $this->target->update(['wajib_ganti_password' => true]);
        $token = $this->target->createToken('mobile', ['mobile:access'])->plainTextToken;

        LogAudit::creating(fn () => throw new RuntimeException('log_audit tidak dapat ditulis'));

        try {
            $this->withToken($token)->postJson('/api/v1/auth/ganti-password', [
                'password_saat_ini'          => 'passwordLama1',
                'password_baru'              => 'passwordBaru2',
                'password_baru_confirmation' => 'passwordBaru2',
            ])->assertStatus(500);
        } finally {
            LogAudit::flushEventListeners();
        }

        $fresh = $this->target->fresh();
        $this->assertTrue(Hash::check('passwordLama1', $fresh->password));
        $this->assertTrue((bool) $fresh->wajib_ganti_password);
    }
}
