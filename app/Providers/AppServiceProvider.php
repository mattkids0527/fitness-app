<?php

namespace App\Providers;

use App\Support\AppVersion;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 版本資訊檔由部署流程寫在版本目錄根部，本地開發沒有這個檔案
        $this->app->singleton(AppVersion::class, fn () => new AppVersion(base_path('version.json')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
