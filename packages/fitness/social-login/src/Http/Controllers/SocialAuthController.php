<?php

namespace Fitness\SocialLogin\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\SocialLogin\Drivers\Contracts\SocialProvider;
use Fitness\SocialLogin\Drivers\SocialUser;
use Fitness\SocialLogin\Exceptions\SocialAuthFailedException;
use Fitness\SocialLogin\Exceptions\SocialLoginException;
use Fitness\SocialLogin\Services\SocialLoginService;
use Fitness\SocialLogin\Services\SocialProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * 導向第三方授權（登入或綁定）與共用的 callback。
 * 第三方的 callback 網址只能設定一條，所以出發前把意圖與發起者存進 session，回來時依意圖分流。
 */
class SocialAuthController extends Controller
{
    /**
     * session 中記錄發起意圖的鍵。
     */
    const INTENT_SESSION_KEY = 'social_login.intent';

    const ACTION_LOGIN = 'login';

    const ACTION_LINK = 'link';

    /**
     * 登入頁的「使用 Google 登入」：導向第三方授權。
     */
    public function redirect(Request $request, string $provider, SocialProviderManager $providers): Response
    {
        return $this->start($request, $provider, $providers->get($provider), self::ACTION_LOGIN);
    }

    /**
     * 已連結帳號頁的「連結」：導向第三方授權，回來後綁定到目前登入的使用者。
     */
    public function link(Request $request, string $provider, SocialProviderManager $providers): Response
    {
        return $this->start($request, $provider, $providers->get($provider), self::ACTION_LINK);
    }

    /**
     * 第三方授權完成後回到這裡；依 session 中的意圖完成登入或綁定，失敗時帶著錯誤提示回到對應頁面。
     */
    public function callback(Request $request, string $provider, SocialProviderManager $providers, SocialLoginService $service): RedirectResponse
    {
        $intent = $request->session()->pull(self::INTENT_SESSION_KEY);
        $action = ($intent['action'] ?? null) === self::ACTION_LINK ? self::ACTION_LINK : self::ACTION_LOGIN;
        $driver = $providers->get($provider);

        try {
            if (($intent['provider'] ?? null) !== $provider) {
                throw new SocialAuthFailedException;
            }

            $socialUser = $driver->user();

            return $action === self::ACTION_LINK
                ? $this->finishLink($request, $provider, $driver, $socialUser, $intent['user_id'] ?? null, $service)
                : $this->finishLogin($request, $provider, $socialUser, $service);
        } catch (SocialLoginException $e) {
            return $this->failed($request, $action, $driver, $e);
        }
    }

    /**
     * 記錄意圖後導向第三方；以 Inertia::location 回應，從 Inertia 請求發起時也會整頁跳轉。
     */
    protected function start(Request $request, string $provider, SocialProvider $driver, string $action): Response
    {
        $request->session()->put(self::INTENT_SESSION_KEY, [
            'action' => $action,
            'provider' => $provider,
            'user_id' => $request->user()?->id,
        ]);

        return Inertia::location($driver->redirect()->getTargetUrl());
    }

    /**
     * 登入成功：重新產生 session 後進入原本要去的頁面（預設今日頁）。
     */
    protected function finishLogin(Request $request, string $provider, SocialUser $socialUser, SocialLoginService $service): RedirectResponse
    {
        $service->login($provider, $socialUser);
        $request->session()->regenerate();

        return redirect()->intended(route('fitness.daily_summary.show', absolute: false));
    }

    /**
     * 綁定到發起的使用者；回來時已登出或換了帳號就視為授權失敗。
     *
     * @throws SocialLoginException
     */
    protected function finishLink(Request $request, string $provider, SocialProvider $driver, SocialUser $socialUser, ?int $initiatorId, SocialLoginService $service): RedirectResponse
    {
        $user = $request->user();

        if (! $user || $user->id !== $initiatorId) {
            throw new SocialAuthFailedException;
        }

        $service->link($user, $provider, $socialUser);

        return redirect()
            ->route('fitness.social_login.connected_accounts.index')
            ->with('success', trans('FitnessSocialLogin::messages.linked', ['provider' => $driver->label()]));
    }

    /**
     * 帶著錯誤提示回頁面：綁定且仍登入時回已連結帳號頁，其餘回登入頁。
     */
    protected function failed(Request $request, string $action, SocialProvider $driver, SocialLoginException $e): RedirectResponse
    {
        $route = $action === self::ACTION_LINK && $request->user()
            ? 'fitness.social_login.connected_accounts.index'
            : 'login';

        return redirect()->route($route)->with('error', $e->toFlash($action, $driver->label()));
    }
}
