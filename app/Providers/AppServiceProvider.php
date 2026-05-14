<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void

     */

    public function register()
    {
        $this->ensureFrameworkStoragePaths();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();
        Paginator::useBootstrapFour();
        Blade::withoutDoubleEncoding();

    }

    private function ensureFrameworkStoragePaths(): void
    {
        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ] as $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }
}
