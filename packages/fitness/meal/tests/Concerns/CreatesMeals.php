<?php

namespace Fitness\Meal\Tests\Concerns;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Models\SavedFood;

/**
 * 測試用：直接寫入餐點、項目與常用食物；日期時間以 Asia/Taipei 解讀。
 */
trait CreatesMeals
{
    /**
     * 組一個食物項目／常用食物的欄位。
     *
     * @return array<string, mixed>
     */
    protected function food(string $name, float $kcal, ?float $protein = null, ?float $carbs = null, ?float $fat = null, ?float $fiber = null, ?string $portion = null, ?float $grams = null): array
    {
        return [
            'name' => $name,
            'portion' => $portion,
            'grams' => $grams,
            'kcal' => $kcal,
            'protein_g' => $protein,
            'carbs_g' => $carbs,
            'fat_g' => $fat,
            'fiber_g' => $fiber,
        ];
    }

    /**
     * 建立一餐與它的項目。
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function meal(User $user, string $date, string $time, string $type, array $items, string $status = Meal::STATUS_CONFIRMED): Meal
    {
        $meal = Meal::create([
            'user_id' => $user->id,
            'eaten_at' => CarbonImmutable::parse("{$date} {$time}", 'Asia/Taipei'),
            'meal_type' => $type,
            'source' => Meal::SOURCE_MANUAL,
            'status' => $status,
        ]);

        foreach ($items as $position => $item) {
            $meal->items()->create([...$item, 'position' => $position]);
        }

        return $meal->load('items');
    }

    /**
     * 轉成頁面 props 經過 JSON 編碼後的樣子（整數值的小數會變成整數），用來比對 assertInertia 的值。
     */
    protected function asJson(mixed $value): mixed
    {
        return json_decode(json_encode($value), true);
    }

    /**
     * 建立一個常用食物。
     */
    protected function savedFood(User $user, string $name, float $kcal = 100, ?string $lastUsedAt = null): SavedFood
    {
        return SavedFood::create([
            ...$this->food($name, $kcal),
            'user_id' => $user->id,
            'last_used_at' => $lastUsedAt ? CarbonImmutable::parse($lastUsedAt, 'Asia/Taipei') : null,
        ]);
    }
}
