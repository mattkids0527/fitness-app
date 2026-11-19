<?php

namespace Fitness\Profile\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * 以使用者時區判斷「今天」，讓切日與年齡計算不受伺服器時區影響。
 */
class UserCalendar
{
    /**
     * 取得使用者時區的時區名稱，未設定時用模組預設值。
     */
    public static function timezone(User $user): string
    {
        return $user->timezone ?: config('fitness.profile.default_timezone');
    }

    /**
     * 取得使用者時區的今天（00:00）。
     */
    public static function today(User $user): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone($user))->startOfDay();
    }
}
