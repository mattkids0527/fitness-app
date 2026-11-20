<?php

namespace Fitness\SocialLogin\Tests\Feature;

use App\Models\User;
use Fitness\SocialLogin\Models\SocialAccount;
use Fitness\SocialLogin\Tests\Concerns\FakesSocialProviders;
use Fitness\SocialLogin\Tests\Fakes\FakeSocialProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 以第三方帳號登入：成功、未綁定拒絕、未完成首次設定、授權失敗與缺少發起紀錄。
 */
class SocialLoginTest extends TestCase
{
    use FakesSocialProviders, RefreshDatabase;

    public function test_login_success_remember(): void
    {
        $fake = $this->fakeProvider();
        $user = User::factory()->create();
        $this->linkAccount($user->id, 'google-sub-1', email: 'old@gmail.com');

        $response = $this->loginVia($fake, $this->socialUser('google-sub-1', 'new@gmail.com', 'New Name', 'https://example.test/new.png'));

        $response->assertRedirect(route('fitness.daily_summary.show', absolute: false));
        $response->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'email' => 'new@gmail.com',
            'name' => 'New Name',
            'avatar' => 'https://example.test/new.png',
        ]);
    }

    public function test_login_not_linked_rejected(): void
    {
        $fake = $this->fakeProvider();
        // 同 email 的帳號存在，也不能以 email 自動綁定或登入
        User::factory()->create(['email' => 'someone@gmail.com']);

        $this->loginVia($fake, $this->socialUser('google-sub-1', 'someone@gmail.com'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', [
                'title' => '這個 Google 帳號尚未連結',
                'message' => '請先用 Email 與密碼登入，再到「設定 › 已連結帳號」連結 Google。',
            ]);

        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertSame(0, SocialAccount::count());

        // 錯誤以共享的 flash.error 帶到登入頁
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('flash.error.title', '這個 Google 帳號尚未連結'));
    }

    public function test_login_not_onboarded(): void
    {
        $fake = $this->fakeProvider();
        $user = User::factory()->notOnboarded()->create();
        $this->linkAccount($user->id, 'google-sub-1');

        $this->loginVia($fake, $this->socialUser('google-sub-1'))->assertRedirect(route('fitness.daily_summary.show', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->get(route('fitness.daily_summary.show'))->assertRedirect(route('fitness.profile.onboarding.show'));
    }

    public function test_callback_auth_failed(): void
    {
        $fake = $this->fakeProvider();

        // 登入時取消授權
        $this->loginVia($fake, null)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', [
                'title' => 'Google 登入沒有完成',
                'message' => '可能是取消了授權或連線逾時，請再試一次。',
            ]);
        $this->assertGuest();

        // 綁定時取消授權
        $this->actingAs(User::factory()->create());
        $this->linkVia($fake, null)
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionHas('error.title', '沒有完成 Google 連結');
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_callback_without_intent(): void
    {
        config(['fitness.social_login.providers' => ['google', 'line']]);
        $google = $this->fakeProvider();
        $line = $this->fakeProvider('line', 'LINE');
        $user = User::factory()->create();
        $this->linkAccount($user->id, 'google-sub-1');
        $google->nextUser = $line->nextUser = $this->socialUser('google-sub-1');

        // 沒有從本站出發，直接打 callback
        $this->get(route('fitness.social_login.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error.title', 'Google 登入沒有完成');

        // 從 Google 出發，卻回到 LINE 的 callback；發起紀錄只能用一次
        $this->get(route('fitness.social_login.redirect', 'google'))->assertRedirect(FakeSocialProvider::AUTH_URL);
        $this->get(route('fitness.social_login.callback', 'line'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error.title', 'LINE 登入沒有完成');
        $this->get(route('fitness.social_login.callback', 'google'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
