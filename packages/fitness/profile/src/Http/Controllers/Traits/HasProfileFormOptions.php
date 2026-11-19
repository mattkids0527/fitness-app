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
     * 性別、日常活動量、目標的選項、每週目標速度的允許範圍，以及前端即時預覽 BMR／每日目標用的計算係數。
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
            'calculation' => [
                'activity_factors' => config('fitness.profile.activity_factors'),
                'kcal_per_kg' => config('fitness.profile.kcal_per_kg'),
                'protein_g_per_kg' => config('fitness.profile.protein_g_per_kg'),
                'calorie_floor' => config('fitness.profile.calorie_floor'),
                'min_age' => config('fitness.profile.min_age'),
            ],
        ];
    }
}
