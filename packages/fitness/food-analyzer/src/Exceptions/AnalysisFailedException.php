<?php

namespace Fitness\FoodAnalyzer\Exceptions;

/**
 * 其他沒有完成的分析：外部服務錯誤、回應格式不符，或 driver 丟出非預期的例外。
 */
class AnalysisFailedException extends FoodAnalyzerException
{
    /**
     * 失敗種類。
     */
    public function errorCode(): string
    {
        return 'failed';
    }
}
