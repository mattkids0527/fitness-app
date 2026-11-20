<?php

namespace Fitness\Meal\Services;

/**
 * 加總食物項目的熱量與營養素；null（不知道）當 0 計算，並標記是否有項目缺蛋白質／碳水／脂肪。
 */
class NutritionCalculator
{
    /**
     * 列入加總的欄位。
     */
    const TOTAL_FIELDS = ['kcal', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'];

    /**
     * 任一為 null 就提示「部分項目沒有填營養素」的欄位；纖維常常不知道，不列入。
     */
    const REQUIRED_NUTRIENTS = ['protein_g', 'carbs_g', 'fat_g'];

    /**
     * 加總一組項目（Model 或陣列皆可），數值四捨五入到小數 1 位。
     *
     * @param  iterable<mixed>  $items
     * @return array{totals: array{kcal: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float}, has_missing_nutrients: bool}
     */
    public function summarize(iterable $items): array
    {
        $totals = array_fill_keys(self::TOTAL_FIELDS, 0.0);
        $missing = false;

        foreach ($items as $item) {
            foreach (self::TOTAL_FIELDS as $field) {
                $totals[$field] += (float) data_get($item, $field);
            }

            foreach (self::REQUIRED_NUTRIENTS as $field) {
                $missing = $missing || data_get($item, $field) === null;
            }
        }

        return [
            'totals' => array_map(fn (float $value) => round($value, 1), $totals),
            'has_missing_nutrients' => $missing,
        ];
    }
}
