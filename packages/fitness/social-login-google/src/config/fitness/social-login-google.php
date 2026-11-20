<?php

return [

    /*
    | Google Cloud Console 建立的 OAuth 用戶端（網頁應用程式）。client id 或密鑰沒填時，Google 不會出現在可用清單。
    */
    'client_id' => env('GOOGLE_CLIENT_ID'),

    'client_secret' => env('GOOGLE_CLIENT_SECRET'),

    /*
    | 授權後回到的網址，必須與 Google Cloud Console「已授權的重新導向 URI」完全一致。
    | 沒設定時使用 fitness.social_login.callback 路由的網址（{目前網域}/auth/google/callback）。
    */
    'redirect' => env('GOOGLE_REDIRECT_URI'),

    /*
    | 只要求登入所需的權限，不呼叫其他 Google API。
    */
    'scopes' => ['openid', 'email', 'profile'],

    /*
    | 呼叫 Google token／userinfo API 的 Guzzle 選項；設逾時避免 Google 沒回應時 callback 卡住。
    */
    'guzzle' => [
        'timeout' => env('GOOGLE_HTTP_TIMEOUT', 10),
    ],

];
