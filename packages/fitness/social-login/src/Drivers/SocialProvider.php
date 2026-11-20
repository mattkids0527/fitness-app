<?php

namespace Fitness\SocialLogin\Drivers;

use Fitness\SocialLogin\Drivers\Contracts\SocialProvider as SocialProviderContract;
use Fitness\SocialLogin\Exceptions\SocialAuthFailedException;
use Throwable;

/**
 * driver 的共用基底：提供預設行為，子類別只實作 redirect()、user()、label()，需要時覆寫 isConfigured()。
 */
abstract class SocialProvider implements SocialProviderContract
{
    /**
     * 預設視為設定齊全；需要金鑰的 driver 覆寫此方法檢查設定。
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * 把第三方套件丟出的例外包成模組自己的授權失敗例外，不讓第三方例外往外傳。
     *
     * @throws SocialAuthFailedException
     */
    protected function authFailed(?Throwable $previous = null): never
    {
        throw new SocialAuthFailedException(previous: $previous);
    }
}
