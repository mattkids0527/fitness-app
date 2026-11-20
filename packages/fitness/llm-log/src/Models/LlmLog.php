<?php

namespace Fitness\LlmLog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一次 LLM API 呼叫的紀錄：誰呼叫、哪個 LLM（provider／model）、哪個功能、送出的 prompt（content）與回應。
 */
class LlmLog extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    const STATUS_SUCCEEDED = 'succeeded';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'feature',
        'content',
        'response',
        'status',
        'error',
        'input_tokens',
        'output_tokens',
        'duration_ms',
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
            'response' => 'json',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * 這次呼叫是否成功。
     */
    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 呼叫 LLM 的使用者。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
