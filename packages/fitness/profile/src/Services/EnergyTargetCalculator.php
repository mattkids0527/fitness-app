<?php

namespace Fitness\Profile\Services;

use App\Models\User;
use Fitness\Profile\Enums\ActivityLevel;
use Fitness\Profile\Enums\Gender;
use Fitness\Profile\Enums\Goal;
use Fitness\Profile\Repositories\Contracts\WeightLogRepository;

/**
 * 由 BMR、日常活動量與目標算出每日熱量目標與蛋白質目標；可直接傳數值，或以 forUser() 讀使用者資料計算。
 */
class EnergyTargetCalculator
{
    public function __construct(
        protected BmrCalculator $bmrCalculator,
        protected WeightLogRepository $weightLogs,
    ) {}

    /**
     * 以使用者的個人資料與最新體重計算目標；尚未完成首次設定或沒有體重紀錄時回傳 null。
     */
    public function forUser(User $user): ?EnergyTargets
    {
        $weightLog = $this->weightLogs->latestForUser($user->id);

        if ($user->onboarded_at === null || $weightLog === null) {
            return null;
        }

        $age = (int) $user->birth_date->diffInYears(UserCalendar::today($user));

        return $this->calculate(
            gender: Gender::from($user->gender),
            weightKg: $weightLog->weight_kg,
            heightCm: $user->height_cm,
            age: $age,
            activityLevel: ActivityLevel::from($user->activity_level),
            goal: Goal::from($user->goal),
            goalRateKgPerWeek: $user->goal_rate_kg_per_week,
            bmrOverride: $user->bmr_override,
        );
    }

    /**
     * 計算目標：BMR（手動值優先）× 活動係數 = 日常消耗，再依目標加減熱量；蛋白質 = 體重 × 目標係數；並附上依性別的熱量下限。
     */
    public function calculate(
        Gender $gender,
        float $weightKg,
        float $heightCm,
        int $age,
        ActivityLevel $activityLevel,
        Goal $goal,
        ?float $goalRateKgPerWeek,
        ?int $bmrOverride = null,
    ): EnergyTargets {
        $bmrCalculated = $this->bmrCalculator->calculate($gender, $weightKg, $heightCm, $age);
        $bmr = $bmrOverride ?? $bmrCalculated;
        $tdee = (int) round($bmr * $activityLevel->factor());
        $adjustment = $this->dailyCalorieAdjustment($goal, $goalRateKgPerWeek);

        return new EnergyTargets(
            weightKg: $weightKg,
            age: $age,
            bmrCalculated: $bmrCalculated,
            bmr: $bmr,
            bmrOverridden: $bmrOverride !== null,
            tdee: $tdee,
            calorieAdjustment: $adjustment,
            calorieTarget: $tdee + $adjustment,
            proteinTargetG: (int) round($weightKg * $goal->proteinGramsPerKg()),
            calorieFloor: (int) config('fitness.profile.calorie_floor.'.$gender->value),
        );
    }

    /**
     * 每週目標速度換算成每日熱量增減：速度 × 每公斤熱量 ÷ 7，維持目標為 0。
     */
    public function dailyCalorieAdjustment(Goal $goal, ?float $goalRateKgPerWeek): int
    {
        if (! $goal->requiresRate() || ! $goalRateKgPerWeek) {
            return 0;
        }

        $kcal = $goalRateKgPerWeek * config('fitness.profile.kcal_per_kg') / 7;

        return $goal->direction() * (int) round($kcal);
    }
}
