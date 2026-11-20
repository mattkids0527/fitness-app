<?php

namespace Fitness\FoodAnalyzer\Tests\Concerns;

use Fitness\FoodAnalyzer\Drivers\FoodImage;
use Fitness\FoodAnalyzer\Tests\Fakes\FakeFoodAnalyzer;

/**
 * 以假 driver 取代設定的照片分析 driver，並提供測試用的照片。
 */
trait FakesFoodAnalyzer
{
    /**
     * 綁定假 driver 到容器 fitness.food_analyzer.fake 並設為預設；要在第一次取用 Manager 前呼叫。
     */
    protected function fakeFoodAnalyzer(bool $configured = true): FakeFoodAnalyzer
    {
        $fake = new FakeFoodAnalyzer(configured: $configured);
        $this->app->instance('fitness.food_analyzer.fake', $fake);
        config(['fitness.food_analyzer.driver' => 'fake']);

        return $fake;
    }

    /**
     * 一張只在記憶體中的假照片。
     */
    protected function foodImage(): FoodImage
    {
        return new FoodImage('fake-jpeg-bytes', 'image/jpeg');
    }
}
