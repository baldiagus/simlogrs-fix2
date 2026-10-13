<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
