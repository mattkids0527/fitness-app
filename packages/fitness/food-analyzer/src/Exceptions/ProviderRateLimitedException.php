<?php

namespace Fitness\FoodAnalyzer\Exceptions;

/**
 * 外部服務的次數限制（例如 Gemini 免費層回 429），稍等再試即可。
 */
class ProviderRateLimitedException extends FoodAnalyzerException
{
    /**
     * 失敗種類。
     */
    public function errorCode(): string
    {
        return 'rate_limited';
    }
}
