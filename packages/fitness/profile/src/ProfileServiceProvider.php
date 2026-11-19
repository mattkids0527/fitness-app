<?php

namespace Fitness\Profile;

use Fitness\DailySummary\Events\DailySummaryCollecting;
use Fitness\Profile\Http\Middleware\EnsureProfileCompleted;
use Fitness\Profile\Listeners\AddProfileDailySummary;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * profile 模組的進入點，於 bootstrap/providers.php 註冊後掛載設定、容器綁定、語系、migration、路由與 middleware。
 */
class ProfileServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的設定、容器綁定、語系、migration、路由、middleware 與事件。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->bindInstances();
        $this->registerSingletons();
        $this->setupLang();
        $this->setupMigrations();
        $this->setupRoutes();
        $this->setupMiddleware();
        $this->setupEvents();
    }

    /**
     * 合併模組設定，讓 config('fitness.profile.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/profile.php', 'fitness.profile');
    }

    /**
     * 依設定檔的 binds 綁定 Repository 介面與實作。
     */
    protected function bindInstances(): void
    {
        foreach (config('fitness.profile.binds', []) as $interface => $target) {
            $this->app->bind($interface, $target);
        }
    }

    /**
     * 依設定檔的 singletons 註冊單例服務。
     */
    protected function registerSingletons(): void
    {
        foreach (config('fitness.profile.singletons', []) as $abstract => $class) {
            $this->app->singleton($abstract, $class);
        }
    }

    /**
     * 載入模組語系，以 FitnessProfile:: 前綴取用。
     */
    protected function setupLang(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'FitnessProfile');
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
     * 把「未完成首次設定就導向首次設定頁」加到 web middleware 群組，讓所有頁面都受保護。
     * 透過 HTTP Kernel 加入（不是直接改 Router），Kernel 每次處理請求重新同步群組時才不會被蓋掉。
     */
    protected function setupMiddleware(): void
    {
        $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', EnsureProfileCompleted::class);
    }

    /**
     * 註冊此模組監聽的事件：今日頁收集摘要時加入目標與體重。
     */
    protected function setupEvents(): void
    {
        Event::listen(DailySummaryCollecting::class, AddProfileDailySummary::class);
    }
}
