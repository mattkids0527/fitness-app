<?php

namespace Fitness\Profile\Services;

use Fitness\Profile\Enums\Gender;

/**
 * 以 Mifflin-St Jeor 公式計算基礎代謝率（kcal / 日）。
 */
class BmrCalculator
{
    /**
     * 男：10×體重 + 6.25×身高 − 5×年齡 + 5；女：常數項改為 −161。結果四捨五入為整數。
     */
    public function calculate(Gender $gender, float $weightKg, float $heightCm, int $age): int
    {
        $base = 10 * $weightKg + 6.25 * $heightCm - 5 * $age;

        return (int) round($base + ($gender === Gender::Male ? 5 : -161));
    }
}
