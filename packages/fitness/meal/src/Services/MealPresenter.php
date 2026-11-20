<?php

namespace Fitness\Meal\Services;

use Fitness\Meal\Models\Meal;
use Fitness\Meal\Models\MealItem;
use Fitness\Meal\Models\SavedFood;
use Illuminate\Support\Carbon;

/**
 * 把餐點、項目、常用食物轉成前端用的陣列；日期時間一律換成使用者時區的 Y-m-d／H:i，不傳 ISO 時間給前端。
 */
class MealPresenter
{
    public function __construct(protected NutritionCalculator $nutrition) {}

    /**
     * 食物項目或常用食物的名稱、份量與營養素。
     *
     * @return array{name: string, portion: string|null, grams: float|null, kcal: float, protein_g: float|null, carbs_g: float|null, fat_g: float|null, fiber_g: float|null}
     */
    public function food(MealItem|SavedFood $food): array
    {
        return [
            'name' => $food->name,
            'portion' => $food->portion,
            'grams' => $food->grams,
            'kcal' => $food->kcal,
            'protein_g' => $food->protein_g,
            'carbs_g' => $food->carbs_g,
            'fat_g' => $food->fat_g,
            'fiber_g' => $food->fiber_g,
        ];
    }

    /**
     * 列表用的餐點摘要（飲食頁、今日頁）。
     *
     * @return array<string, mixed>
     */
    public function summary(Meal $meal, string $timezone): array
    {
        $eatenAt = $meal->eaten_at->setTimezone($timezone);

        return [
            'id' => $meal->id,
            'date' => $eatenAt->toDateString(),
            'time' => $eatenAt->format('H:i'),
            'meal_type' => $meal->meal_type->value,
            'note' => $meal->note,
            'kcal' => $this->nutrition->summarize($meal->items)['totals']['kcal'],
            'item_names' => $meal->items->pluck('name')->all(),
        ];
    }

    /**
     * 編輯表單與複製整餐用的完整內容（含所有項目）。
     *
     * @return array<string, mixed>
     */
    public function detail(Meal $meal, string $timezone): array
    {
        return [
            ...$this->summary($meal, $timezone),
            'items' => $meal->items->map(fn (MealItem $item) => $this->food($item))->all(),
        ];
    }

    /**
     * 常用食物，附 id 與最近使用日期（使用者時區）。
     *
     * @return array<string, mixed>
     */
    public function savedFood(SavedFood $food, string $timezone): array
    {
        return [
            'id' => $food->id,
            ...$this->food($food),
            'last_used_on' => $food->last_used_at?->setTimezone($timezone)->toDateString(),
        ];
    }

    /**
     * 最近吃過的項目，附最近一次吃的日期（使用者時區）。
     *
     * @return array<string, mixed>
     */
    public function recentFood(MealItem $item, string $timezone): array
    {
        return [
            ...$this->food($item),
            'last_eaten_on' => Carbon::parse($item->getAttribute('eaten_at'))->setTimezone($timezone)->toDateString(),
        ];
    }
}
