<?php

namespace Tests\Feature\Notifikasi\Cron;

use App\Livewire\Persediaan\MonitoringStokFefoIndex;
use App\Livewire\System\SchedulerNotifikasiIndex;
use App\Models\Master\Barang;
use App\Models\Master\Notifikasi;
use App\Models\Master\SatuanBarang;
use App\Models\Master\KategoriBarang;
use App\Models\Master\Gudang;
use App\Models\Persediaan\StokBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AlertStokMinimumTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_alert_stok_minimum_command_triggers_notification_for_low_stock(): void
    {
        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-ATK-TEST'], ['nama' => 'ATK Test', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'RIM-TEST'], ['nama' => 'Rim', 'status' => 'AKTIF']);
        $gudang = Gudang::where('status', 'AKTIF')->first();

        // 1. Buat barang dengan stok min 10
        $barang = Barang::create([
            'kode'                => 'BRG-TEST-MIN-01',
            'nama'                => 'Kertas Uji Coba Minimum',
            'kategori_id'         => $kategori->id,
            'satuan_id'           => $satuan->id,
            'tipe'                => 'HABIS_PAKAI',
            'stok_minimum'        => 10,
            'titik_pesan'         => 0,
            'harga_beli_terakhir' => 50000,
            'harga_estimasi'      => 50000,
            'status'              => 'AKTIF',
        ]);

        // 2. Beri saldo batch 5 (kurang dari minimum 10)
        StokBatch::create([
            'barang_id'           => $barang->id,
            'gudang_id'           => $gudang->id,
            'nomor_batch'         => 'BATCH-MIN-01',
            'jumlah'              => 5,
            'harga_satuan'        => 50000,
            'tanggal_masuk'       => now()->toDateString(),
            'tanggal_kedaluwarsa' => null,
            'status'              => 'AKTIF',
        ]);

        // 3. Jalankan command
        Artisan::call('simlog:alert-stok-minimum');

        // 4. Verifikasi notifikasi terbit
        $notifs = Notifikasi::where('tipe', 'STOK_MINIMUM')
            ->where('referensi_id', (string) $barang->id)
            ->get();

        $this->assertNotEmpty($notifs);
        $this->assertStringContainsString('Kertas Uji Coba Minimum', $notifs->first()->pesan);
        $this->assertStringContainsString('tersisa 5', $notifs->first()->pesan);

        // 5. Verifikasi dedup: jalankan lagi, jumlah notifikasi tidak bertambah
        $initialCount = $notifs->count();
        Artisan::call('simlog:alert-stok-minimum');

        $secondCount = Notifikasi::where('tipe', 'STOK_MINIMUM')
            ->where('referensi_id', (string) $barang->id)
            ->count();

        $this->assertEquals($initialCount, $secondCount);

        // 6. Verifikasi cache status scheduler
        $cache = Cache::get('simlog:last_run:alert_stok_minimum');
        $this->assertNotNull($cache);
        $this->assertEquals('Sukses', $cache['status']);
        $this->assertGreaterThanOrEqual(1, $cache['low_stock_count']);
    }

    public function test_scheduler_notifikasi_index_renders_stok_minimum_card_and_triggers_manually(): void
    {
        $admin = User::where('email', 'superadmin@simlogrs.local')->first();
        $this->actingAs($admin);

        $test = Livewire::test(SchedulerNotifikasiIndex::class)
            ->assertSee('Alert Stok Minimum')
            ->assertSee('06:30 WIB')
            ->call('triggerAlertStokMinimum');
        $cache = Cache::get('simlog:last_run:alert_stok_minimum');
        $this->assertNotNull($cache);
        $this->assertEquals('Sukses', $cache['status']);
    }

    public function test_monitoring_stok_fefo_filter_minimum_shows_low_stock_batches(): void
    {
        $user = User::where('email', 'petugasgudang@simlogrs.local')->first();
        $this->actingAs($user);

        $kategori = KategoriBarang::firstOrCreate(['kode' => 'KAT-ATK-TEST2'], ['nama' => 'ATK Test 2', 'status' => 'AKTIF']);
        $satuan = SatuanBarang::firstOrCreate(['kode' => 'RIM-TEST2'], ['nama' => 'Rim 2', 'status' => 'AKTIF']);
        $gudang = Gudang::where('status', 'AKTIF')->first();

        $barang = Barang::create([
            'kode'                => 'BRG-TEST-MIN-02',
            'nama'                => 'Kertas Filter Min Uji',
            'kategori_id'         => $kategori->id,
            'satuan_id'           => $satuan->id,
            'tipe'                => 'HABIS_PAKAI',
            'stok_minimum'        => 20,
            'titik_pesan'         => 0,
            'harga_beli_terakhir' => 45000,
            'harga_estimasi'      => 45000,
            'status'              => 'AKTIF',
        ]);

        $batch = StokBatch::create([
            'barang_id'           => $barang->id,
            'gudang_id'           => $gudang->id,
            'nomor_batch'         => 'BATCH-MIN-02',
            'jumlah'              => 8,
            'harga_satuan'        => 45000,
            'tanggal_masuk'       => now()->toDateString(),
            'tanggal_kedaluwarsa' => null,
            'status'              => 'AKTIF',
        ]);

        $test = Livewire::test(MonitoringStokFefoIndex::class)
            ->call('setFilter', 'MINIMUM');

        $items = $test->viewData('items');
        $this->assertTrue($items->contains('id', $batch->id));
        $this->assertGreaterThanOrEqual(1, $test->viewData('countDiBawahMinimum'));
    }
}
