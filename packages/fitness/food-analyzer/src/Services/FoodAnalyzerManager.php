<?php

namespace Fitness\FoodAnalyzer\Services;

use Fitness\FoodAnalyzer\Drivers\Contracts\FoodImageAnalyzer;
use Fitness\FoodAnalyzer\Exceptions\DriverNotAvailableException;

/**
 * 以別名取得食物照片分析 driver（容器 fitness.food_analyzer.<driver>），並快取已建立的實例。
 * 核心模組不知道有哪些 driver：使用哪個由設定 driver 決定，driver 由各自的模組綁定。
 */
class FoodAnalyzerManager
{
    /**
     * driver 在容器中的綁定前綴。
     */
    const BINDING_PREFIX = 'fitness.food_analyzer.';

    /**
     * 已建立的 driver 實例，以別名為鍵。
     *
     * @var array<string, FoodImageAnalyzer>
     */
    protected array $drivers = [];

    /**
     * 取得 driver（未指定時用設定的預設）；沒有綁定或設定不齊全時丟例外。
     *
     * @throws DriverNotAvailableException
     */
    public function get(?string $driver = null): FoodImageAnalyzer
    {
        $driver ??= $this->defaultDriver();

        if (! app()->bound(self::BINDING_PREFIX.$driver)) {
            throw new DriverNotAvailableException($driver);
        }

        $instance = $this->drivers[$driver] ??= app(self::BINDING_PREFIX.$driver);

        if (! $instance->isConfigured()) {
            throw new DriverNotAvailableException($driver);
        }

        return $instance;
    }

    /**
     * 設定中使用的 driver 別名。
     */
    public function defaultDriver(): string
    {
        return (string) config('fitness.food_analyzer.driver');
    }
}
