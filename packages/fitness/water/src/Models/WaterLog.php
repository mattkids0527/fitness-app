<?php

namespace Fitness\Water\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一筆喝水紀錄；喝水時間即記錄當下。
 */
class WaterLog extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'user_id',
        'amount_ml',
        'drank_at',
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
            'amount_ml' => 'integer',
            'drank_at' => 'immutable_datetime',
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
