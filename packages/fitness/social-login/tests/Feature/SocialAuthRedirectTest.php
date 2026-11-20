<?php

namespace Fitness\SocialLogin\Tests\Feature;

use App\Models\User;
use Fitness\SocialLogin\Http\Controllers\SocialAuthController;
use Fitness\SocialLogin\Tests\Concerns\FakesSocialProviders;
use Fitness\SocialLogin\Tests\Fakes\FakeSocialProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 登入頁的 provider 清單，以及出發到第三方授權頁的路由。
 */
class SocialAuthRedirectTest extends TestCase
{
    use FakesSocialProviders, RefreshDatabase;

    public function test_login_page_hides_google(): void
    {
        $this->app->offsetUnset('fitness.social_login.google');

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('social_login.providers', []));

        $this->get(route('fitness.social_login.redirect', 'google'))->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->get(route('fitness.social_login.link', 'google'))
            ->assertNotFound();
    }

    public function test_login_page_shares_providers(): void
    {
        $this->fakeProvider();

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('social_login.providers', [['key' => 'google', 'label' => 'Google']])
                ->where('flash.success', null)
                ->where('flash.error', null));
    }

    public function test_redirect_guest_only(): void
    {
        $this->fakeProvider();

        $this->get(route('fitness.social_login.redirect', 'google'))
            ->assertRedirect(FakeSocialProvider::AUTH_URL)
            ->assertSessionHas(SocialAuthController::INTENT_SESSION_KEY, ['action' => 'login', 'provider' => 'google', 'user_id' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('fitness.social_login.redirect', 'google'))
            ->assertRedirect();
        $this->assertNotSame(FakeSocialProvider::AUTH_URL, $this->get(route('fitness.social_login.redirect', 'google'))->headers->get('Location'));
    }

    public function test_link_requires_auth(): void
    {
        $this->fakeProvider();

        $this->get(route('fitness.social_login.link', 'google'))->assertRedirect(route('login'));
        $this->assertFalse(session()->has(SocialAuthController::INTENT_SESSION_KEY));
    }
}
