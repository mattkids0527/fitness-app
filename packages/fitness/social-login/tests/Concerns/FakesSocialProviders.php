<?php

namespace Fitness\SocialLogin\Tests\Concerns;

use Fitness\SocialLogin\Drivers\SocialUser;
use Fitness\SocialLogin\Models\SocialAccount;
use Fitness\SocialLogin\Tests\Fakes\FakeSocialProvider;
use Illuminate\Testing\TestResponse;

/**
 * 以假 driver 取代容器中的 fitness.social_login.<provider>，並提供走完「出發 → callback」的捷徑。
 */
trait FakesSocialProviders
{
    /**
     * 綁定假 driver 到容器；要在第一次取用 Manager 前呼叫。
     */
    protected function fakeProvider(string $provider = 'google', string $label = 'Google', bool $configured = true): FakeSocialProvider
    {
        $fake = new FakeSocialProvider($label, $configured);
        $this->app->instance('fitness.social_login.'.$provider, $fake);

        return $fake;
    }

    /**
     * 建立一組第三方使用者資料。
     */
    protected function socialUser(string $id = 'google-sub-1', ?string $email = 'someone@gmail.com', ?string $name = 'Someone', ?string $avatar = 'https://example.test/a.png'): SocialUser
    {
        return new SocialUser($id, $email, $name, $avatar);
    }

    /**
     * 從登入頁出發，第三方回傳 $socialUser（null 為取消授權）後回到 callback。
     */
    protected function loginVia(FakeSocialProvider $fake, ?SocialUser $socialUser, string $provider = 'google'): TestResponse
    {
        $fake->nextUser = $socialUser;
        $this->get(route('fitness.social_login.redirect', $provider))->assertRedirect(FakeSocialProvider::AUTH_URL);

        return $this->get(route('fitness.social_login.callback', $provider));
    }

    /**
     * 以目前登入的使用者從已連結帳號頁出發綁定，第三方回傳 $socialUser 後回到 callback。
     */
    protected function linkVia(FakeSocialProvider $fake, ?SocialUser $socialUser, string $provider = 'google'): TestResponse
    {
        $fake->nextUser = $socialUser;
        $this->get(route('fitness.social_login.link', $provider))->assertRedirect(FakeSocialProvider::AUTH_URL);

        return $this->get(route('fitness.social_login.callback', $provider));
    }

    /**
     * 直接寫入一筆綁定紀錄。
     */
    protected function linkAccount(int $userId, string $providerUserId = 'google-sub-1', string $provider = 'google', ?string $email = 'someone@gmail.com'): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $email,
            'name' => 'Someone',
            'avatar' => null,
        ]);
    }
}
