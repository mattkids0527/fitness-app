<?php

namespace Fitness\Meal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 使用者的常用食物，數值為一份；帶入餐點時可選份數。
 */
class SavedFood extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'saved_foods';

    protected $fillable = [
        'user_id',
        'name',
        'portion',
        'grams',
        'kcal',
        'protein_g',
        'carbs_g',
        'fat_g',
        'fiber_g',
        'last_used_at',
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
            'grams' => 'float',
            'kcal' => 'float',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 常用食物所屬的使用者。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
