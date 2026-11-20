<?php

namespace Fitness\SocialLoginGoogle\Drivers;

use Exception;
use Fitness\SocialLogin\Drivers\SocialProvider;
use Fitness\SocialLogin\Drivers\SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * 以 Laravel Socialite 實作的 Google driver，綁定在容器 fitness.social_login.google。
 * 使用 Socialite 預設的 state 驗證（依賴 session），不存 access／refresh token。
 */
class Google extends SocialProvider
{
    /**
     * 導向 Google 授權頁（帶 state 與 openid email profile 權限）。
     */
    public function redirect(): RedirectResponse
    {
        return $this->socialite()->redirect();
    }

    /**
     * 從 callback 取回 Google 使用者；使用者按取消、state 不符或 Google 回應錯誤時丟授權失敗例外。
     */
    public function user(): SocialUser
    {
        // 使用者在 Google 按取消時會帶 error=access_denied 回來，沒有 code
        if (request()->filled('error')) {
            $this->authFailed();
        }

        try {
            $user = $this->socialite()->user();
        } catch (Exception $e) {
            $this->authFailed($e);
        }

        return new SocialUser(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            name: $user->getName(),
            avatar: $user->getAvatar(),
        );
    }

    /**
     * 顯示用名稱。
     */
    public function label(): string
    {
        return 'Google';
    }

    /**
     * client id 與密鑰都有填才算設定齊全。
     */
    public function isConfigured(): bool
    {
        return filled(config('fitness.social_login_google.client_id'))
            && filled(config('fitness.social_login_google.client_secret'));
    }

    /**
     * 依模組設定建立 Socialite 的 Google provider；每次呼叫重新建立，才會拿到當下的 request。
     */
    protected function socialite(): GoogleProvider
    {
        /** @var GoogleProvider $provider */
        $provider = Socialite::buildProvider(GoogleProvider::class, [
            'client_id' => config('fitness.social_login_google.client_id'),
            'client_secret' => config('fitness.social_login_google.client_secret'),
            'redirect' => config('fitness.social_login_google.redirect')
                ?: route('fitness.social_login.callback', ['provider' => 'google']),
            'guzzle' => config('fitness.social_login_google.guzzle', []),
        ]);

        return $provider->setScopes(config('fitness.social_login_google.scopes'));
    }
}
