<?php

namespace Fitness\FoodAnalyzer\Tests\Unit;

use Fitness\FoodAnalyzer\Drivers\FoodItem;
use PHPUnit\Framework\TestCase;

/**
 * 分析項目的正規化：driver 回傳或從資料庫讀回的陣列整理成一致的格式。
 */
class FoodItemTest extends TestCase
{
    public function test_food_item_normalization(): void
    {
        $item = FoodItem::fromArray([
            'name' => '  滷雞腿 ',
            'portion' => '  ',
            'grams' => '150.26',
            'kcal' => -10,
            'protein_g' => 28.04,
            'carbs_g' => 'a lot',
            'fat_g' => null,
            'fiber_g' => 0.05,
            'confidence' => 1.4,
        ]);

        $this->assertSame([
            'name' => '滷雞腿',
            'portion' => null,
            'grams' => 150.3,
            'kcal' => 0.0,
            'protein_g' => 28.0,
            'carbs_g' => null,
            'fat_g' => null,
            'fiber_g' => 0.1,
            'confidence' => 1.0,
        ], $item->toArray());

        $this->assertSame(0.0, FoodItem::fromArray(['name' => '湯', 'confidence' => -0.3])->confidence);
        $this->assertSame(0.68, FoodItem::fromArray(['name' => '湯', 'confidence' => 0.6789])->confidence);
        $this->assertSame(0.0, FoodItem::fromArray(['name' => '湯'])->kcal);
        $this->assertSame('', FoodItem::fromArray([])->name);
    }
}
