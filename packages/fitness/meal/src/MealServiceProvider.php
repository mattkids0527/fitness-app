<?php

namespace Fitness\Meal;

use Fitness\DailySummary\Events\DailySummaryCollecting;
use Fitness\Meal\Listeners\AddMealDailySummary;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * meal 模組的進入點，於 bootstrap/providers.php 註冊後掛載設定、容器綁定、語系、migration、路由與事件。
 */
class MealServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的設定、容器綁定、語系、migration、路由與事件。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->bindInstances();
        $this->registerSingletons();
        $this->setupLang();
        $this->setupMigrations();
        $this->setupRoutes();
        $this->setupEvents();
    }

    /**
     * 合併模組設定，讓 config('fitness.meal.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/meal.php', 'fitness.meal');
    }

    /**
     * 依設定檔的 binds 綁定 Repository 介面與實作。
     */
    protected function bindInstances(): void
    {
        foreach (config('fitness.meal.binds', []) as $interface => $target) {
            $this->app->bind($interface, $target);
        }
    }

    /**
     * 依設定檔的 singletons 註冊單例服務。
     */
    protected function registerSingletons(): void
    {
        foreach (config('fitness.meal.singletons', []) as $abstract => $class) {
            $this->app->singleton($abstract, $class);
        }
    }

    /**
     * 載入模組語系，以 FitnessMeal:: 前綴取用。
     */
    protected function setupLang(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'FitnessMeal');
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
     * 註冊此模組監聽的事件：今日頁收集摘要時加入當日攝取與餐點。
     */
    protected function setupEvents(): void
    {
        Event::listen(DailySummaryCollecting::class, AddMealDailySummary::class);
    }
}
