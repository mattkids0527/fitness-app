<?php

namespace Fitness\Exercise\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 逐組紀錄裡的一組：次數與重量（徒手時重量為 null，不計訓練量）。
 */
class ExerciseSet extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    public $timestamps = false;

    protected $fillable = [
        'exercise_log_id',
        'set_no',
        'reps',
        'weight_kg',
    ];

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
            'set_no' => 'integer',
            'reps' => 'integer',
            'weight_kg' => 'float',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 這一組所屬的紀錄。
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(ExerciseLog::class, 'exercise_log_id');
    }
}
