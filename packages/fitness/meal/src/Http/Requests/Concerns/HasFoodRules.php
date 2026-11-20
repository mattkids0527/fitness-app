<?php

namespace Fitness\Meal\Http\Requests\Concerns;

/**
 * 食物項目與常用食物共用的欄位規則與中文名稱。
 */
trait HasFoodRules
{
    /**
     * 名稱、份量、重量、熱量與營養素的規則；$prefix 例如 'items.*.'。
     *
     * @return array<string, mixed>
     */
    protected function foodRules(string $prefix = ''): array
    {
        return [
            $prefix.'name' => ['required', 'string', 'max:100'],
            $prefix.'portion' => ['nullable', 'string', 'max:50'],
            $prefix.'grams' => ['nullable', 'numeric', 'between:0,5000'],
            $prefix.'kcal' => ['required', 'numeric', 'between:0,5000'],
            $prefix.'protein_g' => ['nullable', 'numeric', 'between:0,1000'],
            $prefix.'carbs_g' => ['nullable', 'numeric', 'between:0,1000'],
            $prefix.'fat_g' => ['nullable', 'numeric', 'between:0,1000'],
            $prefix.'fiber_g' => ['nullable', 'numeric', 'between:0,1000'],
        ];
    }

    /**
     * 食物欄位的中文名稱，加上 $prefix 後回傳。
     *
     * @return array<string, string>
     */
    protected function foodAttributes(string $prefix = ''): array
    {
        $names = ['name', 'portion', 'grams', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'];

        return collect($names)
            ->mapWithKeys(fn (string $field) => [$prefix.$field => trans('FitnessMeal::attributes.'.$field)])
            ->all();
    }
}
