<?php

namespace Fitness\Exercise\Models;

use App\Models\User;
use Fitness\Exercise\Enums\RecordType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 使用者自建的健身分類；記錄方式（填時長／逐組）決定底下項目怎麼記錄，建立後不能改。
 */
class ExerciseCategory extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'user_id',
        'name',
        'record_type',
        'position',
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
            'record_type' => RecordType::class,
            'position' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 分類所屬的使用者。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 分類底下的項目（含封存）。
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class, 'category_id');
    }
}
