<?php

use Fitness\Exercise\Repositories\Contracts\ExerciseCategoryRepository as ExerciseCategoryRepositoryContract;
use Fitness\Exercise\Repositories\Contracts\ExerciseLogRepository as ExerciseLogRepositoryContract;
use Fitness\Exercise\Repositories\Contracts\ExerciseRepository as ExerciseRepositoryContract;
use Fitness\Exercise\Repositories\ExerciseCategoryRepository;
use Fitness\Exercise\Repositories\ExerciseLogRepository;
use Fitness\Exercise\Repositories\ExerciseRepository;

return [

    /*
    | Repository 介面 => 實作，由 ExerciseServiceProvider::bindInstances() 綁定。
    */
    'binds' => [
        ExerciseCategoryRepositoryContract::class => ExerciseCategoryRepository::class,
        ExerciseRepositoryContract::class => ExerciseRepository::class,
        ExerciseLogRepositoryContract::class => ExerciseLogRepository::class,
    ],

    /*
    | 單例服務 abstract => class，由 ExerciseServiceProvider::registerSingletons() 註冊。
    */
    'singletons' => [],

    /*
    | 新增「逐組」項目時的預設值：MET、每下秒數、組間休息秒數。
    */
    'sets_defaults' => [
        'met' => env('EXERCISE_DEFAULT_SETS_MET', 5.0),
        'sec_per_rep' => env('EXERCISE_DEFAULT_SEC_PER_REP', 3),
        'rest_sec' => env('EXERCISE_DEFAULT_REST_SEC', 90),
    ],

    /*
    | 一筆逐組紀錄最多幾組。
    */
    'max_sets' => env('EXERCISE_MAX_SETS', 20),

    /*
    | 項目「約消耗」估算的基準：填時長以幾分鐘計；逐組以幾組 × 幾下計。
    */
    'estimate' => [
        'duration_min' => 30,
        'sets' => 3,
        'reps' => 10,
    ],

    /*
    | 運動加到喝水建議量：每運動一小時加多少 ml，四捨五入到 round_to_ml。
    */
    'water_ml_per_hour' => env('EXERCISE_WATER_ML_PER_HOUR', 500),
    'water_round_to_ml' => 10,

    /*
    | 使用者完成首次設定時複製一份的預設分類與項目（之後可自由修改、刪除）。MET 參考 2024 Adult Compendium。
    | 逐組項目沒寫 sec_per_rep、rest_sec 時套用 sets_defaults。
    */
    'defaults' => [
        [
            'name' => '有氧',
            'record_type' => 'duration',
            'exercises' => [
                ['name' => '室外跑步', 'met' => 9.0],
                ['name' => '室內跑步（跑步機）', 'met' => 9.0],
                ['name' => '滑雪機', 'met' => 6.8],
                ['name' => '踩樓梯', 'met' => 9.3],
            ],
        ],
        [
            'name' => '重訓',
            'record_type' => 'sets',
            'exercises' => [
                ['name' => '槓鈴臥推', 'met' => 5.0],
                ['name' => '槓鈴肩推', 'met' => 5.0],
                ['name' => '槓鈴划船', 'met' => 5.0],
            ],
        ],
    ],

];
