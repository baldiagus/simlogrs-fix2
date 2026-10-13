<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

class BladeUtimeSafeTest extends TestCase
{
    public function test_touch_utime_warning_does_not_throw_500_error_exception(): void
    {
        // Emit touch() Utime failed warning manually
        $caught = false;
        try {
            trigger_error('touch(): Utime failed: Operation not permitted', E_USER_WARNING);
        } catch (\ErrorException $e) {
            $caught = true;
        }

        // Karena sudah di-intersep, ErrorException tidak boleh terlempar
        $this->assertFalse($caught, 'touch(): Utime failed warning converted to ErrorException');
    }

    public function test_petugas_unit_permintaan_barang_page_renders_successfully(): void
    {
        $user = User::where('email', 'petugasunit@simlogrs.local')->first();
        $this->actingAs($user);

        $response = $this->get('/petugas-unit/permintaan-barang');
        $response->assertOk();
        $response->assertDontSee('Internal Server Error');
    }
}
