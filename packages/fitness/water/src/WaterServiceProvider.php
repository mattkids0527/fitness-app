<?php

namespace Fitness\Water;

use Fitness\DailySummary\Events\DailySummaryCollecting;
use Fitness\Water\Listeners\AddWaterDailySummary;
use Fitness\Water\Services\WaterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * water 模組的進入點，於 bootstrap/providers.php 註冊後掛載設定、容器綁定、語系、migration、路由、共享資料與事件。
 */
class WaterServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的設定、容器綁定、語系、migration、路由、共享資料與事件。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->bindInstances();
        $this->registerSingletons();
        $this->setupLang();
        $this->setupMigrations();
        $this->setupRoutes();
        $this->setupInertia();
        $this->setupEvents();
    }

    /**
     * 合併模組設定，讓 config('fitness.water.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/water.php', 'fitness.water');
    }

    /**
     * 依設定檔的 binds 綁定 Repository 介面與實作。
     */
    protected function bindInstances(): void
    {
        foreach (config('fitness.water.binds', []) as $interface => $target) {
            $this->app->bind($interface, $target);
        }
    }

    /**
     * 依設定檔的 singletons 註冊單例服務。
     */
    protected function registerSingletons(): void
    {
        foreach (config('fitness.water.singletons', []) as $abstract => $class) {
            $this->app->singleton($abstract, $class);
        }
    }

    /**
     * 載入模組語系，以 FitnessWater:: 前綴取用。
     */
    protected function setupLang(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'FitnessWater');
    }

    /**
     * 載入模組自己的 migration。
     */
    protected function setupMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
    }

    /**
     * 載入模組路由。
     */
    protected function setupRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }

    /**
     * 共享今天的喝水摘要給所有頁面的喝水面板；設為 optional，一般換頁不查詢，
     * 只有前端以 partial reload（only: ['water']）要求時才載入。
     */
    protected function setupInertia(): void
    {
        Inertia::share('water', Inertia::optional(fn () => Auth::check() ? $this->app->make(WaterService::class)->today(Auth::user()) : null));
    }

    /**
     * 註冊此模組監聽的事件：今日頁收集摘要時加入當日喝水。
     */
    protected function setupEvents(): void
    {
        Event::listen(DailySummaryCollecting::class, AddWaterDailySummary::class);
    }
}
