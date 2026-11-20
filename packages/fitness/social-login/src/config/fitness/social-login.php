<?php

use Fitness\SocialLogin\Repositories\Contracts\SocialAccountRepository as SocialAccountRepositoryContract;
use Fitness\SocialLogin\Repositories\SocialAccountRepository;
use Fitness\SocialLogin\Services\SocialProviderManager;

return [

    /*
    | Repository 介面 => 實作，由 SocialLoginServiceProvider::bindInstances() 綁定。
    */
    'binds' => [
        SocialAccountRepositoryContract::class => SocialAccountRepository::class,
    ],

    /*
    | 單例服務 abstract => class，由 SocialLoginServiceProvider::registerSingletons() 註冊。
    */
    'singletons' => [
        SocialProviderManager::class => SocialProviderManager::class,
    ],

    /*
    | 啟用的 provider 別名（逗號分隔，依此順序顯示）。還需要對應的 driver 模組已註冊且設定齊全才會出現。
    */
    'providers' => array_values(array_filter(array_map('trim', explode(',', (string) env('SOCIAL_LOGIN_PROVIDERS', 'google'))))),

];
