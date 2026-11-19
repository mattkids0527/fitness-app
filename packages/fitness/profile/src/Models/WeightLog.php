<?php

namespace Fitness\Profile\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一筆體重量測紀錄；目前體重取最新的一筆。
 */
class WeightLog extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'user_id',
        'weight_kg',
        'measured_on',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 欄位型別轉換；日期以 Y-m-d 輸出，避免序列化成 UTC 後前端差一天。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'measured_on' => 'date:Y-m-d',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 紀錄所屬的使用者。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
