<?php

use Fitness\SocialLogin\Services\SocialProviderManager;

if (! function_exists('social_login')) {
    /**
     * 取得第三方登入 driver 的 Manager，例如 social_login()->get('google')。
     */
    function social_login(): SocialProviderManager
    {
        return app(SocialProviderManager::class);
    }
}
