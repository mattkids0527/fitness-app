<?php

namespace Fitness\Exercise\Models;

use App\Models\User;
use Fitness\Exercise\Enums\RecordType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 一筆健身紀錄：一個項目的一次運動。分類／項目名稱、記錄方式、MET、秒數、體重與消耗熱量都是存檔當下的快照，
 * 之後項目改名、改 MET 或體重變動都不影響歷史。逐組紀錄的 duration_min 為估算時間。
 */
class ExerciseLog extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'user_id',
        'exercise_id',
        'performed_at',
        'category_name',
        'exercise_name',
        'record_type',
        'met',
        'sec_per_rep',
        'rest_sec',
        'body_weight_kg',
        'duration_min',
        'distance_km',
        'kcal',
        'volume_kg',
        'note',
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
            'performed_at' => 'immutable_datetime',
            'record_type' => RecordType::class,
            'met' => 'float',
            'sec_per_rep' => 'integer',
            'rest_sec' => 'integer',
            'body_weight_kg' => 'float',
            'duration_min' => 'float',
            'distance_km' => 'float',
            'kcal' => 'float',
            'volume_kg' => 'float',
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

    /**
     * 紀錄對應的項目；項目被刪除時為 null，畫面改用名稱快照。
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * 逐組紀錄的每一組，依組別排列。
     */
    public function sets(): HasMany
    {
        return $this->hasMany(ExerciseSet::class)->orderBy('set_no');
    }
}
