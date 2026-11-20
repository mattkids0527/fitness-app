<?php

namespace Fitness\FoodAnalyzer\Drivers;

use Fitness\FoodAnalyzer\Drivers\Contracts\FoodImageAnalyzer as FoodImageAnalyzerContract;

/**
 * driver 的共用基底：提供預設行為，子類別實作 analyze()、model()、label()，需要時覆寫 isConfigured()。
 */
abstract class FoodImageAnalyzer implements FoodImageAnalyzerContract
{
    /**
     * 預設視為設定齊全；需要金鑰的 driver 覆寫此方法檢查設定。
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * 呼叫外部服務的逾時秒數。
     */
    protected function timeout(): int
    {
        return (int) config('fitness.food_analyzer.timeout');
    }
}
