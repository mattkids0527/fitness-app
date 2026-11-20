<?php

namespace Fitness\Meal\Tests\Unit;

use Fitness\Meal\Enums\MealType;
use Tests\TestCase;

/**
 * 依時間推測餐別（時段來自設定檔，所以需要框架）。
 */
class MealTypeTest extends TestCase
{
    public function test_meal_type_for_time(): void
    {
        $expected = [
            '04:59' => MealType::Snack,
            '05:00' => MealType::Breakfast,
            '09:59' => MealType::Breakfast,
            '10:00' => MealType::Lunch,
            '13:59' => MealType::Lunch,
            '14:00' => MealType::Snack,
            '16:59' => MealType::Snack,
            '17:00' => MealType::Dinner,
            '20:59' => MealType::Dinner,
            '21:00' => MealType::Snack,
            '00:30' => MealType::Snack,
        ];

        foreach ($expected as $time => $type) {
            $this->assertSame($type, MealType::forTime($time), $time);
        }
    }
}
