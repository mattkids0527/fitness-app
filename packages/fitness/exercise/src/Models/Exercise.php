<?php

namespace Fitness\Exercise\Models;

use App\Models\User;
use Fitness\Exercise\Enums\RecordType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 健身項目：記錄方式沿用所屬分類；逐組項目另有每下秒數與組間休息。已有紀錄的項目刪除時改為封存。
 */
class Exercise extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'met',
        'sec_per_rep',
        'rest_sec',
        'description',
        'archived_at',
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
     * 欄位型別轉換。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'met' => 'float',
            'sec_per_rep' => 'integer',
            'rest_sec' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /**
     * 是否已封存（不再出現在選單，歷史紀錄照常顯示）。
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * 記錄方式：沿用所屬分類；沒有分類（只有封存項目會發生）時，以有沒有每下秒數判斷（逐組項目一定有，填時長項目一定沒有）。
     */
    public function recordType(): RecordType
    {
        return $this->category?->record_type ?? ($this->sec_per_rep !== null ? RecordType::Sets : RecordType::Duration);
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 項目所屬的使用者。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 項目所屬的分類；只有封存項目可能沒有分類。
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExerciseCategory::class, 'category_id');
    }

    /**
     * 這個項目的健身紀錄。
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ExerciseLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * 只取未封存的項目。
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
