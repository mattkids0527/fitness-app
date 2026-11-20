<?php

namespace Fitness\SocialLogin;

use App\Models\User;
use Fitness\SocialLogin\Models\SocialAccount;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * social-login 模組的進入點，於 bootstrap/providers.php 註冊後掛載設定、容器綁定、語系、migration、路由、
 * User 關聯與 Inertia 共享資料。driver 由各自的 driver 模組綁定到 fitness.social_login.<provider>。
 */
class SocialLoginServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的設定、容器綁定、語系、migration、路由、關聯與共享資料。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->bindInstances();
        $this->registerSingletons();
        $this->setupLang();
        $this->setupMigrations();
        $this->setupRoutes();
        $this->setupRelations();
        $this->setupSharedData();
    }

    /**
     * 合併模組設定，讓 config('fitness.social_login.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/social-login.php', 'fitness.social_login');
    }

    /**
     * 依設定檔的 binds 綁定 Repository 介面與實作。
     */
    protected function bindInstances(): void
    {
        foreach (config('fitness.social_login.binds', []) as $interface => $target) {
            $this->app->bind($interface, $target);
        }
    }

    /**
     * 依設定檔的 singletons 註冊單例服務（driver Manager）。
     */
    protected function registerSingletons(): void
    {
        foreach (config('fitness.social_login.singletons', []) as $abstract => $class) {
            $this->app->singleton($abstract, $class);
        }
    }

    /**
     * 載入模組語系，以 FitnessSocialLogin:: 前綴取用。
     */
    protected function setupLang(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'FitnessSocialLogin');
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
     * 在 User 上動態加入 socialAccounts 關聯，讓 app/ 的 User 不必依賴本模組。
     */
    protected function setupRelations(): void
    {
        User::resolveRelationUsing('socialAccounts', fn (User $user) => $user->hasMany(SocialAccount::class));
    }

    /**
     * 把可用的 provider 清單共享給所有 Inertia 頁面（social_login.providers），登入頁據此顯示按鈕；
     * 由模組自己加入，app/ 的 HandleInertiaRequests 不必知道這個模組。
     */
    protected function setupSharedData(): void
    {
        Inertia::share('social_login', fn () => ['providers' => social_login()->options()]);
    }
}
