<?php

namespace Fitness\FoodAnalyzer;

use Illuminate\Support\ServiceProvider;

/**
 * food-analyzer 模組的進入點，於 bootstrap/providers.php 註冊後掛載設定、容器綁定、語系與 migration。
 * driver 由各自的 driver 模組綁定到 fitness.food_analyzer.<driver>。
 */
class FoodAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的設定、容器綁定、語系與 migration。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->bindInstances();
        $this->registerSingletons();
        $this->setupLang();
        $this->setupMigrations();
    }

    /**
     * 合併模組設定，讓 config('fitness.food_analyzer.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/food-analyzer.php', 'fitness.food_analyzer');
    }

    /**
     * 依設定檔的 binds 綁定 Repository 介面與實作。
     */
    protected function bindInstances(): void
    {
        foreach (config('fitness.food_analyzer.binds', []) as $interface => $target) {
            $this->app->bind($interface, $target);
        }
    }

    /**
     * 依設定檔的 singletons 註冊單例服務（driver Manager）。
     */
    protected function registerSingletons(): void
    {
        foreach (config('fitness.food_analyzer.singletons', []) as $abstract => $class) {
            $this->app->singleton($abstract, $class);
        }
    }

    /**
     * 載入模組語系，以 FitnessFoodAnalyzer:: 前綴取用。
     */
    protected function setupLang(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'FitnessFoodAnalyzer');
    }

    /**
     * 載入模組自己的 migration。
     */
    protected function setupMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
    }
}
