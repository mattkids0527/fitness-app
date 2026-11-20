<?php

namespace Fitness\SocialLoginGoogle;

use Fitness\SocialLoginGoogle\Drivers\Google;
use Illuminate\Support\ServiceProvider;

/**
 * social-login-google 模組的進入點：合併 Google 憑證設定，並把 Google driver 綁定到 fitness.social_login.google。
 */
class SocialLoginGoogleServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組設定與 driver 綁定。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->registerDriver();
    }

    /**
     * 合併模組設定，讓 config('fitness.social_login_google.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/social-login-google.php', 'fitness.social_login_google');
    }

    /**
     * 綁定 Google driver，social-login 的 Manager 以別名 google 取得。
     */
    protected function registerDriver(): void
    {
        $this->app->bind('fitness.social_login.google', Google::class);
    }
}
