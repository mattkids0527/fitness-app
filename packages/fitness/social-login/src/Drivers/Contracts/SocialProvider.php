<?php

namespace Fitness\SocialLogin\Drivers\Contracts;

use Fitness\SocialLogin\Drivers\SocialUser;
use Fitness\SocialLogin\Exceptions\SocialAuthFailedException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * 第三方登入 driver 的合約；driver 模組實作後綁定到容器 fitness.social_login.<provider>。
 */
interface SocialProvider
{
    /**
     * 產生導向第三方授權頁的回應。
     */
    public function redirect(): RedirectResponse;

    /**
     * 第三方授權完成回到 callback 後，取回使用者資料。
     *
     * @throws SocialAuthFailedException 使用者取消授權、state 不符或第三方回應錯誤
     */
    public function user(): SocialUser;

    /**
     * 顯示用名稱，例如「Google」。
     */
    public function label(): string;

    /**
     * 必要設定（例如 client id）是否齊全；不齊全時不提供這個 provider。
     */
    public function isConfigured(): bool;
}
