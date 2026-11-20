<?php

namespace Fitness\FoodAnalyzer\Exceptions;

/**
 * 今天的分析次數已用完；此時不呼叫 driver，也不寫紀錄。
 */
class DailyLimitExceededException extends FoodAnalyzerException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("Daily food analysis limit ({$limit}) exceeded.");
    }

    /**
     * 失敗種類。
     */
    public function errorCode(): string
    {
        return 'daily_limit';
    }

    /**
     * 文案顯示每日上限次數。
     *
     * @return array<string, mixed>
     */
    protected function langReplace(): array
    {
        return ['limit' => $this->limit];
    }
}
