<?php

namespace Fitness\Profile\Repositories;

use App\Models\User;
use Fitness\Profile\Repositories\Contracts\ProfileRepository as ProfileRepositoryContract;
use Illuminate\Support\Arr;

/**
 * 以 Eloquent 讀寫 users 表上的 profile 欄位。
 */
class ProfileRepository implements ProfileRepositoryContract
{
    /**
     * 本模組可寫入的 users 欄位。
     */
    public const FIELDS = [
        'gender',
        'birth_date',
        'height_cm',
        'activity_level',
        'goal',
        'goal_rate_kg_per_week',
        'bmr_override',
        'timezone',
    ];

    /**
     * 只寫入 profile 欄位；用 forceFill 是因為這些欄位不開放 User 的一般 mass assignment。
     */
    public function update(User $user, array $attributes): User
    {
        $user->forceFill(Arr::only($attributes, self::FIELDS))->save();

        return $user;
    }

    /**
     * 寫入首次設定完成時間。
     */
    public function markOnboarded(User $user): User
    {
        $user->forceFill(['onboarded_at' => now()])->save();

        return $user;
    }
}
