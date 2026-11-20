<?php

use Fitness\SocialLogin\Http\Controllers\ConnectedAccountController;
use Fitness\SocialLogin\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

/*
| social-login 模組路由：名稱前綴 fitness.social_login.
| {provider} 為 provider 別名（例如 google），未啟用或未設定時回 404。
*/
Route::middleware('web')
    ->name('fitness.social_login.')
    ->where(['provider' => '[a-z0-9_]+'])
    ->group(function () {
        Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->middleware('guest')
            ->name('redirect');
        Route::get('auth/{provider}/link', [SocialAuthController::class, 'link'])
            ->middleware('auth')
            ->name('link');
        // 登入與綁定共用，未登入／已登入都會進來，依 session 中的意圖分流
        Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('callback');

        Route::middleware('auth')->group(function () {
            Route::get('settings/connected-accounts', [ConnectedAccountController::class, 'index'])->name('connected_accounts.index');
            Route::delete('settings/connected-accounts/{provider}', [ConnectedAccountController::class, 'destroy'])->name('connected_accounts.destroy');
        });
    });
