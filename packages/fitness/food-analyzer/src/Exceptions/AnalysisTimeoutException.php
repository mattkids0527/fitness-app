<?php

namespace Fitness\FoodAnalyzer\Exceptions;

/**
 * 外部服務在設定的逾時秒數內沒有回應。
 */
class AnalysisTimeoutException extends FoodAnalyzerException
{
    /**
     * 失敗種類。
     */
    public function errorCode(): string
    {
        return 'timeout';
    }
}
