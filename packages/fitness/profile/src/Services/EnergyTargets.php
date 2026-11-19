<?php

namespace Fitness\Profile\Services;

/**
 * 某位使用者在目前體重下的每日熱量與蛋白質目標（不含當日運動消耗）。
 */
final class EnergyTargets
{
    public function __construct(
        public readonly float $weightKg,
        public readonly int $age,
        public readonly int $bmrCalculated,
        public readonly int $bmr,
        public readonly bool $bmrOverridden,
        public readonly int $tdee,
        public readonly int $calorieAdjustment,
        public readonly int $calorieTarget,
        public readonly int $proteinTargetG,
        public readonly int $calorieFloor,
    ) {}

    /**
     * 每日熱量目標是否低於建議下限。
     */
    public function belowFloor(): bool
    {
        return $this->calorieTarget < $this->calorieFloor;
    }

    /**
     * 轉成給前端的陣列。
     *
     * @return array<string, int|float|bool>
     */
    public function toArray(): array
    {
        return [
            'weight_kg' => $this->weightKg,
            'age' => $this->age,
            'bmr_calculated' => $this->bmrCalculated,
            'bmr' => $this->bmr,
            'bmr_overridden' => $this->bmrOverridden,
            'tdee' => $this->tdee,
            'calorie_adjustment' => $this->calorieAdjustment,
            'calorie_target' => $this->calorieTarget,
            'protein_target_g' => $this->proteinTargetG,
            'calorie_floor' => $this->calorieFloor,
            'below_floor' => $this->belowFloor(),
        ];
    }
}
