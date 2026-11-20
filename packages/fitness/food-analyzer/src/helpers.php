<?php

use Fitness\FoodAnalyzer\Services\FoodAnalyzerManager;

if (! function_exists('food_analyzer')) {
    /**
     * 取得食物照片分析 driver 的 Manager，例如 food_analyzer()->get()。
     */
    function food_analyzer(): FoodAnalyzerManager
    {
        return app(FoodAnalyzerManager::class);
    }
}
