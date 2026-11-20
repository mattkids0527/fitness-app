<?php

namespace Fitness\FoodAnalyzer\Exceptions;

/**
 * 設定的 driver 沒有被任何模組綁定，或設定（例如 API 金鑰）不齊全；此時不呼叫外部服務，也不寫紀錄。
 */
class DriverNotAvailableException extends FoodAnalyzerException
{
    public function __construct(public readonly string $driver)
    {
        parent::__construct("Food analyzer driver [{$driver}] is not available.");
    }

    /**
     * 失敗種類。
     */
    public function errorCode(): string
    {
        return 'unavailable';
    }
}
