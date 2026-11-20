<?php

use Fitness\Water\Repositories\Contracts\WaterLogRepository as WaterLogRepositoryContract;
use Fitness\Water\Repositories\WaterLogRepository;

return [

    /*
    | Repository 介面 => 實作，由 WaterServiceProvider::bindInstances() 綁定。
    */
    'binds' => [
        WaterLogRepositoryContract::class => WaterLogRepository::class,
    ],

    /*
    | 單例服務 abstract => class，由 WaterServiceProvider::registerSingletons() 註冊。
    */
    'singletons' => [],

    /*
    | 每日建議飲水量：體重（kg）× ml_per_kg，四捨五入到 round_to_ml。
    */
    'ml_per_kg' => env('WATER_ML_PER_KG', 35),
    'round_to_ml' => 50,

    /*
    | 單筆喝水量的上下限（ml）。
    */
    'min_ml' => 10,
    'max_ml' => 2000,

    /*
    | 快速按鈕的喝水量（ml）。
    */
    'quick_amounts' => [250, 500],

];
