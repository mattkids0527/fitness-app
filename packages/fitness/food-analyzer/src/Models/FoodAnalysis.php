<?php

namespace Fitness\FoodAnalyzer\Models;

use App\Models\User;
use Fitness\FoodAnalyzer\Drivers\FoodItem;
use Fitness\LlmLog\Models\LlmLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一次照片分析的紀錄（成功或失敗都有），不含照片；result 為正規化後的食物項目，供確認頁帶入。
 * 呼叫 LLM 的細節在關聯的 llmLog。
 */
class FoodAnalysis extends Model
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
        'llm_log_id',
        'status',
        'error',
        'hint',
        'result',
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
            'result' => 'array',
        ];
    }

    /**
     * 是否分析成功。
     */
    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    /**
     * 分析出的食物項目；失敗的紀錄為空陣列。
     *
     * @return list<FoodItem>
     */
    public function items(): array
    {
        return array_map(fn (array $item) => FoodItem::fromArray($item), $this->result ?? []);
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 送出分析的使用者。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 這次分析呼叫 LLM 的紀錄（provider、model、prompt、回應、token、耗時）。
     */
    public function llmLog(): BelongsTo
    {
        return $this->belongsTo(LlmLog::class);
    }
}
