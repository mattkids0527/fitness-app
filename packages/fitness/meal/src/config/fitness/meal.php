<?php

use Fitness\Meal\Repositories\Contracts\MealRepository as MealRepositoryContract;
use Fitness\Meal\Repositories\Contracts\SavedFoodRepository as SavedFoodRepositoryContract;
use Fitness\Meal\Repositories\MealRepository;
use Fitness\Meal\Repositories\SavedFoodRepository;

return [

    /*
    | Repository 介面 => 實作，由 MealServiceProvider::bindInstances() 綁定。
    */
    'binds' => [
        MealRepositoryContract::class => MealRepository::class,
        SavedFoodRepositoryContract::class => SavedFoodRepository::class,
    ],

    /*
    | 單例服務 abstract => class，由 MealServiceProvider::registerSingletons() 註冊。
    */
    'singletons' => [],

    /*
    | 一餐最多幾個食物項目。
    */
    'max_items' => env('MEAL_MAX_ITEMS', 30),

    /*
    | 「最近吃過」取近幾天、最多幾筆（依名稱取最近一次）。
    */
    'recent_foods_days' => 30,
    'recent_foods_limit' => 20,

    /*
    | 「複製整餐」可選近幾天的餐點、最多幾筆。
    */
    'copyable_meals_days' => 14,
    'copyable_meals_limit' => 50,

    /*
    | 帶入常用食物／最近吃過時可選的份數。
    */
    'servings' => [0.5, 1, 1.5, 2],

    /*
    | 依時間推測餐別：[起始小時, 結束小時)，都不符合時為點心（snack）。
    */
    'meal_type_hours' => [
        'breakfast' => [5, 10],
        'lunch' => [10, 14],
        'dinner' => [17, 21],
    ],

    /*
    | 記錄今天以外的日期時，各餐別預設的時間。
    */
    'meal_type_default_times' => [
        'breakfast' => '08:00',
        'lunch' => '12:30',
        'dinner' => '18:30',
        'snack' => '15:00',
    ],

];
