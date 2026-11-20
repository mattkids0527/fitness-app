<?php

namespace Fitness\SocialLoginGoogle\Tests\Feature;

use App\Models\User;
use Fitness\SocialLoginGoogle\Drivers\Google;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Google driver：設定判斷、授權網址、取消授權與 state 驗證，以及 Google 使用者資料的轉換；不連外。
 */
class GoogleDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fitness.social_login.providers' => ['google'],
            'fitness.social_login_google.client_id' => 'test-client-id.apps.googleusercontent.com',
            'fitness.social_login_google.client_secret' => 'test-secret',
            'fitness.social_login_google.redirect' => 'http://localhost:8000/auth/google/callback',
        ]);
    }

    /**
     * 出發到 Google 後回傳授權網址的 query 參數。
     *
     * @return array<string, string>
     */
    protected function startRedirect(): array
    {
        $location = $this->get(route('fitness.social_login.redirect', 'google'))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_not_configured_without_credentials(): void
    {
        $this->assertTrue(app('fitness.social_login.google')->isConfigured());

        config(['fitness.social_login_google.client_secret' => null]);
        $this->assertFalse(app('fitness.social_login.google')->isConfigured());

        config(['fitness.social_login_google.client_id' => null, 'fitness.social_login_google.client_secret' => 'test-secret']);
        $this->assertFalse(app('fitness.social_login.google')->isConfigured());
        $this->assertSame([], social_login()->available());
        $this->get(route('fitness.social_login.redirect', 'google'))->assertNotFound();
    }

    public function test_redirect_url(): void
    {
        $this->assertInstanceOf(Google::class, social_login()->get('google'));

        $location = $this->get(route('fitness.social_login.redirect', 'google'))->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth?', $location);

        $query = $this->startRedirect();
        $this->assertSame('test-client-id.apps.googleusercontent.com', $query['client_id']);
        $this->assertSame('http://localhost:8000/auth/google/callback', $query['redirect_uri']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(session('state'), $query['state']);
        $this->assertSame(40, strlen($query['state']));

        // 沒設定 GOOGLE_REDIRECT_URI 時改用目前網域的 callback 路由
        config(['fitness.social_login_google.redirect' => null]);
        $this->assertSame(route('fitness.social_login.callback', 'google'), $this->startRedirect()['redirect_uri']);
    }

    public function test_cancel_access_denied(): void
    {
        $this->startRedirect();

        $this->get(route('fitness.social_login.callback', ['provider' => 'google', 'error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error.title', 'Google 登入沒有完成');
        $this->assertGuest();
    }

    public function test_invalid_state(): void
    {
        $this->startRedirect();

        $this->get(route('fitness.social_login.callback', ['provider' => 'google', 'code' => 'auth-code', 'state' => 'not-the-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error.title', 'Google 登入沒有完成');
        $this->assertGuest();
    }

    public function test_maps_google_user(): void
    {
        // 以 MockHandler 模擬 Google 的 token 與 userinfo 回應，並記錄送出的請求
        $sent = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'fake-access-token', 'token_type' => 'Bearer', 'expires_in' => 3599])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'sub' => '108234567890123456789',
                'name' => '王小明',
                'email' => 'ming@gmail.com',
                'email_verified' => true,
                'picture' => 'https://lh3.googleusercontent.com/a/photo',
            ])),
        ]));
        $stack->push(Middleware::history($sent));
        config(['fitness.social_login_google.guzzle' => ['handler' => $stack]]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $location = $this->get(route('fitness.social_login.link', 'google'))->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->get(route('fitness.social_login.callback', ['provider' => 'google', 'code' => 'auth-code', 'state' => $query['state']]))
            ->assertRedirect(route('fitness.social_login.connected_accounts.index'))
            ->assertSessionHas('success', '已連結 Google');

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => '108234567890123456789',
            'email' => 'ming@gmail.com',
            'name' => '王小明',
            'avatar' => 'https://lh3.googleusercontent.com/a/photo',
        ]);

        // token 請求帶 code 與 redirect_uri；userinfo 以 access token 取得
        $this->assertCount(2, $sent);
        parse_str((string) $sent[0]['request']->getBody(), $tokenFields);
        $this->assertSame('auth-code', $tokenFields['code']);
        $this->assertSame('http://localhost:8000/auth/google/callback', $tokenFields['redirect_uri']);
        $this->assertSame('Bearer fake-access-token', $sent[1]['request']->getHeaderLine('Authorization'));
    }
}
