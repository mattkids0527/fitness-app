<?php

namespace Fitness\SocialLogin\Tests\Feature;

use App\Models\User;
use Fitness\SocialLogin\Drivers\SocialUser;
use Fitness\SocialLogin\Models\SocialAccount;
use Fitness\SocialLogin\Repositories\Contracts\SocialAccountRepository as SocialAccountRepositoryContract;
use Fitness\SocialLogin\Repositories\SocialAccountRepository;
use Fitness\SocialLogin\Tests\Concerns\FakesSocialProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 綁定第三方帳號：成功、各種拒絕原因、同時綁定的唯一鍵衝突、回來時換了使用者。
 */
class LinkSocialAccountTest extends TestCase
{
    use FakesSocialProviders, RefreshDatabase;

    public function test_link_success(): void
    {
        $fake = $this->fakeProvider();
        $user = User::factory()->create(['email' => 'me@example.com']);
        $this->actingAs($user);

        // Google email 與帳號 email 不同也可以綁定
        $this->linkVia($fake, $this->socialUser('google-sub-1', 'me.other@gmail.com', 'Me', 'https://example.test/me.png'))
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionHas('success', '已連結 Google');

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-1',
            'email' => 'me.other@gmail.com',
            'name' => 'Me',
            'avatar' => 'https://example.test/me.png',
        ]);
        $this->assertSame(1, $user->socialAccounts()->count());
    }

    public function test_link_account_taken(): void
    {
        $fake = $this->fakeProvider();
        $owner = User::factory()->create();
        $this->linkAccount($owner->id, 'google-sub-1');

        $this->actingAs(User::factory()->create());

        $this->linkVia($fake, $this->socialUser('google-sub-1'))
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionHas('error', [
                'title' => '這個 Google 帳號已連結到其他帳號',
                'message' => '請改用其他 Google 帳號，或先用那個帳號登入並解除連結。',
            ]);

        $this->assertSame(1, SocialAccount::count());
        $this->assertSame($owner->id, SocialAccount::first()->user_id);
    }

    public function test_link_provider_already_linked(): void
    {
        $fake = $this->fakeProvider();
        $user = User::factory()->create();
        $this->linkAccount($user->id, 'google-sub-1');
        $this->actingAs($user);

        $this->linkVia($fake, $this->socialUser('google-sub-2'))
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionHas('error.title', '你已經連結過 Google');

        $this->assertSame(['google-sub-1'], SocialAccount::pluck('provider_user_id')->all());
    }

    public function test_link_unique_violation(): void
    {
        $fake = $this->fakeProvider();
        $other = User::factory()->create();

        // 模擬另一個請求在「檢查通過後、寫入前」搶先綁定同一個 Google 帳號：第一次檢查回傳沒有綁定，回傳後立刻寫入搶先的那筆
        $this->app->bind(SocialAccountRepositoryContract::class, fn () => new class($other->id) extends SocialAccountRepository
        {
            protected bool $raced = false;

            public function __construct(protected int $racerId) {}

            public function findByProviderUser(string $provider, string $providerUserId): ?SocialAccount
            {
                $found = parent::findByProviderUser($provider, $providerUserId);

                if (! $this->raced) {
                    $this->raced = true;
                    parent::create($this->racerId, $provider, new SocialUser($providerUserId));
                }

                return $found;
            }
        });

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->linkVia($fake, $this->socialUser('google-sub-1'))
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionHas('error.title', '這個 Google 帳號已連結到其他帳號');

        // 衝突只回滾自己的寫入，搶先的那筆仍在
        $this->assertSame([$other->id], SocialAccount::pluck('user_id')->all());
    }

    public function test_link_callback_user_changed(): void
    {
        $fake = $this->fakeProvider();
        $user = User::factory()->create();

        // 出發後登出：回到登入頁
        $this->actingAs($user);
        $fake->nextUser = $this->socialUser();
        $this->get(route('fitness.social_login.link', 'google'));
        auth()->logout();
        $this->get(route('fitness.social_login.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error.title', '沒有完成 Google 連結');

        // 出發後換成另一個使用者：回已連結帳號頁
        $this->actingAs($user);
        $this->get(route('fitness.social_login.link', 'google'));
        $this->actingAs(User::factory()->create());
        $this->get(route('fitness.social_login.callback', 'google'))
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionHas('error.title', '沒有完成 Google 連結');

        $this->assertSame(0, SocialAccount::count());
    }
}
