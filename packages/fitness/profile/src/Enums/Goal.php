<?php

namespace Fitness\Profile\Enums;

use Fitness\Profile\Enums\Concerns\HasOptions;

/**
 * 體態目標，決定每日熱量增減方向與蛋白質係數。
 */
enum Goal: string
{
    use HasOptions;

    case Lose = 'lose';
    case Maintain = 'maintain';
    case Gain = 'gain';

    /**
     * 取得中文名稱。
     */
    public function label(): string
    {
        return trans('FitnessProfile::options.goal.'.$this->value);
    }

    /**
     * 取得選項下方的簡短說明。
     */
    public function description(): string
    {
        return trans('FitnessProfile::options.goal_description.'.$this->value);
    }

    /**
     * 熱量調整方向：減脂為負、增肌為正、維持為零。
     */
    public function direction(): int
    {
        return match ($this) {
            self::Lose => -1,
            self::Maintain => 0,
            self::Gain => 1,
        };
    }

    /**
     * 是否需要填寫每週目標速度。
     */
    public function requiresRate(): bool
    {
        return $this !== self::Maintain;
    }

    /**
     * 取得設定檔中的蛋白質目標（g / 每公斤體重）。
     */
    public function proteinGramsPerKg(): float
    {
        return (float) config('fitness.profile.protein_g_per_kg.'.$this->value);
    }
}
