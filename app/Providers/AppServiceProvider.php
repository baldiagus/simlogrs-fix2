<?php

namespace App\Providers;

use App\Services\Notifikasi\FcmPengirimPush;
use App\Services\Notifikasi\PengirimPush;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PengirimPush::class, FcmPengirimPush::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Intersep warning kegagalan touch() utime di lingkungan Docker/container agar tidak memicu 500 ErrorException
        set_error_handler(function (int $severity, string $message): bool {
            if (str_contains($message, 'touch(): Utime failed')) {
                return true;
            }

            return false;
        }, E_WARNING | E_USER_WARNING);

        // Deteksi N+1 (dev lokal saja): hanya log, tidak melempar exception
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(! app()->isProduction() && app()->environment('local') && ! app()->runningUnitTests());
        \Illuminate\Database\Eloquent\Model::handleLazyLoadingViolationUsing(
            fn ($model, $relation) => logger()->warning('lazy-load', ['model' => get_class($model), 'relation' => $relation])
        );

        // Pemicu notifikasi transaksi (satu kali untuk jalur web Livewire maupun API)
        \App\Models\Pengadaan\PermintaanPengadaan::observe(\App\Observers\PermintaanPengadaanObserver::class);
        \App\Models\Pengadaan\PesananPembelian::observe(\App\Observers\PesananPembelianObserver::class);
        \App\Models\Distribusi\PermintaanDistribusi::observe(\App\Observers\PermintaanDistribusiObserver::class);
        \App\Models\Distribusi\PengirimanBarang::observe(\App\Observers\PengirimanBarangObserver::class);
        \App\Models\Ipsrs\PermintaanMaintenance::observe(\App\Observers\PermintaanMaintenanceObserver::class);
        \App\Models\Ipsrs\PenugasanMaintenance::observe(\App\Observers\PenugasanMaintenanceObserver::class);
        \App\Models\Aset\PermintaanPeminjaman::observe(\App\Observers\PermintaanPeminjamanObserver::class);
        \App\Models\Aset\PengalihanAset::observe(\App\Observers\PengalihanAsetObserver::class);
        \App\Models\Persediaan\PengalihanBarang::observe(\App\Observers\PengalihanBarangObserver::class);
        \App\Models\Persediaan\PenyesuaianStok::observe(\App\Observers\PenyesuaianStokObserver::class);
    }
}
