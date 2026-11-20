<?php

namespace Fitness\SocialLogin\Tests\Fakes;

use Fitness\SocialLogin\Drivers\SocialProvider;
use Fitness\SocialLogin\Drivers\SocialUser;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * 測試用 driver：不連外，redirect 導向假的授權頁，user() 回傳預先設定的使用者；沒設定時視為使用者取消授權。
 */
class FakeSocialProvider extends SocialProvider
{
    /**
     * 假的第三方授權頁網址。
     */
    const AUTH_URL = 'https://auth.example.test/authorize';

    /**
     * 下一次 callback 回傳的第三方使用者；null 表示授權失敗。
     */
    public ?SocialUser $nextUser = null;

    public function __construct(
        protected string $label = 'Google',
        protected bool $configured = true,
    ) {}

    /**
     * 導向假的授權頁。
     */
    public function redirect(): RedirectResponse
    {
        return new RedirectResponse(self::AUTH_URL);
    }

    /**
     * 回傳預先設定的使用者，沒設定時丟授權失敗例外。
     */
    public function user(): SocialUser
    {
        return $this->nextUser ?? $this->authFailed();
    }

    /**
     * 顯示用名稱。
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * 是否視為設定齊全。
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
