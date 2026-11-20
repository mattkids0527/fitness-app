<?php

use Fitness\FoodAnalyzer\Repositories\Contracts\FoodAnalysisRepository as FoodAnalysisRepositoryContract;
use Fitness\FoodAnalyzer\Repositories\FoodAnalysisRepository;
use Fitness\FoodAnalyzer\Services\FoodAnalyzerManager;

return [

    /*
    | Repository 介面 => 實作，由 FoodAnalyzerServiceProvider::bindInstances() 綁定。
    */
    'binds' => [
        FoodAnalysisRepositoryContract::class => FoodAnalysisRepository::class,
    ],

    /*
    | 單例服務 abstract => class，由 FoodAnalyzerServiceProvider::registerSingletons() 註冊。
    */
    'singletons' => [
        FoodAnalyzerManager::class => FoodAnalyzerManager::class,
    ],

    /*
    | 使用的 driver 別名，對應容器綁定 fitness.food_analyzer.<driver>（由 driver 模組提供）。
    */
    'driver' => env('FOOD_ANALYZER_DRIVER', 'gemini'),

    /*
    | 每人每天（使用者時區切日）最多送出幾次分析；含失敗與重新分析。
    */
    'daily_limit' => (int) env('FOOD_ANALYZER_DAILY_LIMIT', 20),

    /*
    | 呼叫外部服務的逾時秒數，要小於 PHP max_execution_time（30 秒）。
    */
    'timeout' => (int) env('FOOD_ANALYZER_TIMEOUT', 25),

    /*
    | 補充說明最多幾個字。
    */
    'hint_max_length' => 200,

];
