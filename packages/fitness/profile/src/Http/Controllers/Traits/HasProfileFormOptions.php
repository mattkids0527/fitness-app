<?php

namespace Fitness\Profile\Http\Controllers\Traits;

use Fitness\Profile\Enums\ActivityLevel;
use Fitness\Profile\Enums\Gender;
use Fitness\Profile\Enums\Goal;

/**
 * 提供個人資料表單需要的下拉選項與數值範圍。
 */
trait HasProfileFormOptions
{
    /**
     * 性別、日常活動量、目標的選項，以及每週目標速度的允許範圍。
     *
     * @return array<string, mixed>
     */
    protected function profileFormOptions(): array
    {
        return [
            'genders' => Gender::options(),
            'activity_levels' => ActivityLevel::options(),
            'goals' => Goal::options(),
            'goal_rate' => config('fitness.profile.goal_rate'),
        ];
    }
}
