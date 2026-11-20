<?php

namespace Fitness\Meal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一餐裡的一個食物項目；營養素為 null 表示不知道。confidence 為照片分析的信心度（0～1），手動項目為 null。
 */
class MealItem extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'meal_id',
        'position',
        'name',
        'portion',
        'grams',
        'kcal',
        'protein_g',
        'carbs_g',
        'fat_g',
        'fiber_g',
        'confidence',
    ];

    /**
     * 欄位皆為 timestamptz，寫入時帶時區位移。
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 數值欄位轉成 float（null 維持 null）。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'grams' => 'float',
            'kcal' => 'float',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
            'confidence' => 'float',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 項目所屬的餐點。
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }
}
