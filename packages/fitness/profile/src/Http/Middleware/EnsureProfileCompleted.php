<?php

namespace Fitness\Profile\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 已登入但尚未完成首次設定的使用者，一律導向首次設定頁；設定檔列出的路由（設定頁、登出等）除外。
 */
class EnsureProfileCompleted
{
    /**
     * 檢查登入者是否已完成首次設定。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->onboarded_at !== null || $request->routeIs(config('fitness.profile.onboarding_except_routes', []))) {
            return $next($request);
        }

        return redirect()->route('fitness.profile.onboarding.show');
    }
}
