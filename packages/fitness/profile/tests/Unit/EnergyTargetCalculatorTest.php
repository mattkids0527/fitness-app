<?php

namespace Fitness\Profile\Tests\Unit;

use Fitness\Profile\Enums\ActivityLevel;
use Fitness\Profile\Enums\Gender;
use Fitness\Profile\Enums\Goal;
use Fitness\Profile\Services\BmrCalculator;
use Fitness\Profile\Services\EnergyTargetCalculator;
use Tests\TestCase;

/**
 * BMR 與每日熱量／蛋白質目標的計算（只用設定檔數值，不碰資料庫）。
 */
class EnergyTargetCalculatorTest extends TestCase
{
    /**
     * 取得計算器；calculate() 不會用到 Repository。
     */
    protected function calculator(): EnergyTargetCalculator
    {
        return $this->app->make(EnergyTargetCalculator::class);
    }

    public function test_bmr_for_male_uses_mifflin_st_jeor(): void
    {
        // 10×70 + 6.25×175 − 5×30 + 5 = 1648.75
        $this->assertSame(1649, (new BmrCalculator)->calculate(Gender::Male, 70, 175, 30));
    }

    public function test_bmr_for_female_uses_mifflin_st_jeor(): void
    {
        // 10×60 + 6.25×165 − 5×30 − 161 = 1320.25
        $this->assertSame(1320, (new BmrCalculator)->calculate(Gender::Female, 60, 165, 30));
    }

    public function test_lose_goal_subtracts_daily_deficit(): void
    {
        $targets = $this->calculator()->calculate(Gender::Male, 80, 180, 35, ActivityLevel::Moderate, Goal::Lose, 0.5);

        $this->assertSame(1755, $targets->bmr);
        $this->assertSame(2457, $targets->tdee);
        $this->assertSame(-550, $targets->calorieAdjustment);
        $this->assertSame(1907, $targets->calorieTarget);
        $this->assertSame(144, $targets->proteinTargetG);
    }

    public function test_maintain_goal_has_no_adjustment(): void
    {
        $targets = $this->calculator()->calculate(Gender::Female, 60, 165, 30, ActivityLevel::Sedentary, Goal::Maintain, null);

        $this->assertSame(1584, $targets->tdee);
        $this->assertSame(0, $targets->calorieAdjustment);
        $this->assertSame(1584, $targets->calorieTarget);
        $this->assertSame(72, $targets->proteinTargetG);
    }

    public function test_gain_goal_adds_daily_surplus(): void
    {
        $targets = $this->calculator()->calculate(Gender::Male, 70, 175, 30, ActivityLevel::Moderate, Goal::Gain, 0.25);

        $this->assertSame(2309, $targets->tdee);
        $this->assertSame(275, $targets->calorieAdjustment);
        $this->assertSame(2584, $targets->calorieTarget);
        $this->assertSame(112, $targets->proteinTargetG);
    }

    public function test_bmr_override_takes_precedence(): void
    {
        $targets = $this->calculator()->calculate(Gender::Male, 70, 175, 30, ActivityLevel::Sedentary, Goal::Maintain, null, 1500);

        $this->assertSame(1649, $targets->bmrCalculated);
        $this->assertSame(1500, $targets->bmr);
        $this->assertTrue($targets->bmrOverridden);
        $this->assertSame(1800, $targets->tdee);
    }

    public function test_below_floor_depends_on_gender_floor(): void
    {
        $female = $this->calculator()->calculate(Gender::Female, 50, 155, 30, ActivityLevel::Sedentary, Goal::Lose, 1.0);
        $this->assertSame(1200, $female->calorieFloor);
        $this->assertTrue($female->belowFloor());
        $this->assertTrue($female->toArray()['below_floor']);

        // 1250 × 1.2 = 1500，剛好等於男性下限
        $atFloor = $this->calculator()->calculate(Gender::Male, 70, 175, 30, ActivityLevel::Sedentary, Goal::Maintain, null, 1250);
        $this->assertSame(1500, $atFloor->calorieTarget);
        $this->assertFalse($atFloor->belowFloor());

        $belowFloor = $this->calculator()->calculate(Gender::Male, 70, 175, 30, ActivityLevel::Sedentary, Goal::Maintain, null, 1249);
        $this->assertTrue($belowFloor->belowFloor());
    }
}
