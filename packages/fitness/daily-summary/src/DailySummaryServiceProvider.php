<?php

namespace Fitness\DailySummary;

use Illuminate\Support\ServiceProvider;

/**
 * daily-summary 模組的進入點，於 bootstrap/providers.php 註冊後掛載路由。
 */
class DailySummaryServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的路由。
     */
    public function boot(): void
    {
        $this->setupRoutes();
    }

    /**
     * 載入模組路由。
     */
    protected function setupRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }
}
