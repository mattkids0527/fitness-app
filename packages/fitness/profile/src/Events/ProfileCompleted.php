<?php

namespace Fitness\Profile\Events;

use App\Models\User;

/**
 * 通知型事件：使用者完成首次設定後發出，讓其他模組建立自己的初始資料（例如健身的預設分類與項目）。
 */
class ProfileCompleted
{
    public function __construct(
        public readonly User $user,
    ) {}
}
