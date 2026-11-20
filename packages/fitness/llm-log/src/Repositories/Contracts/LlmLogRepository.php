<?php

namespace Fitness\LlmLog\Repositories\Contracts;

use Fitness\LlmLog\Models\LlmLog;

/**
 * LLM API 呼叫紀錄的存取介面。
 */
interface LlmLogRepository
{
    /**
     * 寫入一筆呼叫紀錄。
     *
     * @param  array{provider: string, model: string, feature: string, content: string, response?: array<mixed>|string|null, status: string, error?: ?string, input_tokens?: ?int, output_tokens?: ?int, duration_ms: int}  $attributes
     */
    public function create(int $userId, array $attributes): LlmLog;
}
