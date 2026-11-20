<?php

namespace Fitness\SocialLogin\Tests\Feature;

use App\Models\User;
use Fitness\SocialLogin\Models\SocialAccount;
use Fitness\SocialLogin\Tests\Concerns\FakesSocialProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 已連結帳號頁、解除綁定，以及刪除帳號時一併刪除綁定。
 */
class ConnectedAccountsTest extends TestCase
{
    use FakesSocialProviders, RefreshDatabase;

    public function test_connected_accounts_page(): void
    {
        config(['fitness.social_login.providers' => ['google', 'line']]);
        $this->fakeProvider();
        $this->fakeProvider('line', 'LINE');
        $user = User::factory()->create();
        $account = $this->linkAccount($user->id, 'google-sub-1', email: 'me@gmail.com');
        $this->linkAccount(User::factory()->create()->id, 'line-user-2', 'line', 'other@line.test');

        $this->actingAs($user)
            ->get(route('fitness.social_login.connected_accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('social-login/ConnectedAccounts')
                ->has('providers', 2)
                ->where('providers.0', [
                    'key' => 'google',
                    'label' => 'Google',
                    'account' => [
                        'email' => 'me@gmail.com',
                        'name' => 'Someone',
                        'avatar' => null,
                        'linked_at' => $account->created_at->toIso8601String(),
                    ],
                ])
                ->where('providers.1', ['key' => 'line', 'label' => 'LINE', 'account' => null]));

        auth()->logout();
        $this->get(route('fitness.social_login.connected_accounts.index'))->assertRedirect(route('login'));
    }

    public function test_unlink_own_only(): void
    {
        $this->fakeProvider();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->linkAccount($user->id, 'google-sub-1');
        $this->linkAccount($other->id, 'google-sub-2');

        $this->actingAs($user)
            ->delete(route('fitness.social_login.connected_accounts.destroy', 'google'))
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionHas('success', '已解除連結 Google');

        $this->assertSame([$other->id], SocialAccount::pluck('user_id')->all());

        // 已經沒有綁定時再解除：不報錯也不顯示成功提示
        $this->flushSession();
        $this->delete(route('fitness.social_login.connected_accounts.destroy', 'google'))
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionMissing('success');

        auth()->logout();
        $this->delete(route('fitness.social_login.connected_accounts.destroy', 'google'))->assertRedirect(route('login'));
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_delete_user_cascades(): void
    {
        $user = User::factory()->create();
        $this->linkAccount($user->id, 'google-sub-1');
        $this->linkAccount(User::factory()->create()->id, 'google-sub-2');

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertModelMissing($user);
        $this->assertSame(['google-sub-2'], SocialAccount::pluck('provider_user_id')->all());
    }
}
