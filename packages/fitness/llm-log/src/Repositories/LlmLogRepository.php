<?php

namespace Fitness\LlmLog\Repositories;

use Fitness\LlmLog\Models\LlmLog;
use Fitness\LlmLog\Repositories\Contracts\LlmLogRepository as LlmLogRepositoryContract;

/**
 * 以 Eloquent 寫入 llm_logs。
 */
class LlmLogRepository implements LlmLogRepositoryContract
{
    /**
     * 寫入一筆呼叫紀錄。
     */
    public function create(int $userId, array $attributes): LlmLog
    {
        return LlmLog::create(['user_id' => $userId, ...$attributes]);
    }
}
