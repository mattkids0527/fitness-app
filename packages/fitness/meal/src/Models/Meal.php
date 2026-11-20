<?php

namespace Fitness\Meal\Models;

use App\Models\User;
use Fitness\Meal\Enums\MealType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 一餐：用餐時間、餐別與多個食物項目。統計只計算已確認（confirmed）的餐點。
 * 由照片分析建立的餐點 source 為 ai，並以 food_analysis_id 記錄來自哪一筆分析。
 */
class Meal extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    const SOURCE_MANUAL = 'manual';

    const SOURCE_AI = 'ai';

    const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'user_id',
        'eaten_at',
        'meal_type',
        'source',
        'status',
        'note',
        'food_analysis_id',
    ];

    /**
     * 欄位皆為 timestamptz，寫入時帶時區位移，避免被資料庫連線時區誤判。
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 欄位型別轉換。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eaten_at' => 'immutable_datetime',
            'meal_type' => MealType::class,
        ];
    }

    /**
     * 是否為已確認、列入統計的餐點。
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * 是否由照片分析建立。
     */
    public function isFromPhoto(): bool
    {
        return $this->source === self::SOURCE_AI;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 餐點所屬的使用者。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 這一餐的食物項目，依輸入順序排列。
     */
    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class)->orderBy('position');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * 只取已確認的餐點。
     */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', self::STATUS_CONFIRMED);
    }
}
