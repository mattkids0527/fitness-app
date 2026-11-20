<?php

namespace Fitness\SocialLogin\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\SocialLogin\Services\SocialLoginService;
use Fitness\SocialLogin\Services\SocialProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 設定頁的「已連結帳號」：列出可用的 provider 與綁定狀態、解除綁定。
 */
class ConnectedAccountController extends Controller
{
    /**
     * 顯示已連結帳號頁。
     */
    public function index(Request $request, SocialLoginService $service): Response
    {
        return Inertia::render('social-login/ConnectedAccounts', [
            'providers' => $service->connectedAccounts($request->user()),
        ]);
    }

    /**
     * 解除自己在某個平台的綁定；本來就沒綁定時不顯示提示。
     */
    public function destroy(Request $request, string $provider, SocialProviderManager $providers, SocialLoginService $service): RedirectResponse
    {
        $driver = $providers->get($provider);
        $redirect = redirect()->route('fitness.social_login.connected_accounts.index');

        if (! $service->unlink($request->user(), $provider)) {
            return $redirect;
        }

        return $redirect->with('success', trans('FitnessSocialLogin::messages.unlinked', ['provider' => $driver->label()]));
    }
}
