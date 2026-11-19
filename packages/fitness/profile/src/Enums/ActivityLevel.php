<?php

namespace Fitness\Profile\Enums;

use Fitness\Profile\Enums\Concerns\HasOptions;

/**
 * 日常活動量（不含運動），對應 BMR 的乘數；運動消耗另由健身紀錄計算。
 */
enum ActivityLevel: string
{
    use HasOptions;

    case Sedentary = 'sedentary';
    case Light = 'light';
    case Moderate = 'moderate';
    case Active = 'active';

    /**
     * 取得中文名稱。
     */
    public function label(): string
    {
        return trans('FitnessProfile::options.activity_level.'.$this->value);
    }

    /**
     * 取得選項下方的簡短說明。
     */
    public function description(): string
    {
        return trans('FitnessProfile::options.activity_level_description.'.$this->value);
    }

    /**
     * 取得設定檔中的活動係數。
     */
    public function factor(): float
    {
        return (float) config('fitness.profile.activity_factors.'.$this->value);
    }
}
