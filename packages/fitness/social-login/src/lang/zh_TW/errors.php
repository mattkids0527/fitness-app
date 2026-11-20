<?php

return [

    'not_linked' => [
        'title' => '這個 :provider 帳號尚未連結',
        'message' => '請先用 Email 與密碼登入，再到「設定 › 已連結帳號」連結 :provider。還沒有帳號的話，請聯絡管理員。',
    ],

    'account_taken' => [
        'title' => '這個 :provider 帳號已連結到其他帳號',
        'message' => '請改用其他 :provider 帳號，或先用那個帳號登入並解除連結。',
    ],

    'provider_already_linked' => [
        'title' => '你已經連結過 :provider',
        'message' => '如果要換成其他 :provider 帳號，請先解除目前的連結。',
    ],

    'auth_failed' => [
        'login' => [
            'title' => ':provider 登入沒有完成',
            'message' => '可能是取消了授權或連線逾時，請再試一次。',
        ],
        'link' => [
            'title' => '沒有完成 :provider 連結',
            'message' => '可能是取消了授權或連線逾時，請再試一次。',
        ],
    ],

];
