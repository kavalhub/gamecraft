<?php

namespace App\Providers;

use App\Services\Storage\StorageTransferPolicy;
use App\Services\Storage\UnrestrictedTransferPolicy;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StorageTransferPolicy::class, UnrestrictedTransferPolicy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }
    }
}
