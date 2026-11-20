<?php

namespace Fitness\Meal\Tests\Unit;

use Fitness\Meal\Services\NutritionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * 加總規則：null 當 0；缺蛋白質／碳水／脂肪才標記，只缺纖維不標記。
 */
class NutritionCalculatorTest extends TestCase
{
    public function test_nutrition_calculator(): void
    {
        $calculator = new NutritionCalculator;

        $this->assertSame(
            ['totals' => ['kcal' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0, 'fiber_g' => 0.0], 'has_missing_nutrients' => false],
            $calculator->summarize([]),
        );

        $complete = $calculator->summarize([
            ['kcal' => 380, 'protein_g' => 18, 'carbs_g' => 42, 'fat_g' => 15, 'fiber_g' => 1.5],
            ['kcal' => 132.25, 'protein_g' => 13.04, 'carbs_g' => 4, 'fat_g' => 7, 'fiber_g' => null],
        ]);
        $this->assertSame(['kcal' => 512.3, 'protein_g' => 31.0, 'carbs_g' => 46.0, 'fat_g' => 22.0, 'fiber_g' => 1.5], $complete['totals']);
        $this->assertFalse($complete['has_missing_nutrients']);

        $missing = $calculator->summarize([
            ['kcal' => 380, 'protein_g' => 18, 'carbs_g' => 42, 'fat_g' => 15, 'fiber_g' => 1.5],
            ['kcal' => 450, 'protein_g' => 9, 'carbs_g' => null, 'fat_g' => 18, 'fiber_g' => 2],
        ]);
        $this->assertSame(830.0, $missing['totals']['kcal']);
        $this->assertSame(42.0, $missing['totals']['carbs_g']);
        $this->assertTrue($missing['has_missing_nutrients']);
    }
}
