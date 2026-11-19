<?php

use Fitness\Profile\Repositories\Contracts\ProfileRepository as ProfileRepositoryContract;
use Fitness\Profile\Repositories\Contracts\WeightLogRepository as WeightLogRepositoryContract;
use Fitness\Profile\Repositories\ProfileRepository;
use Fitness\Profile\Repositories\WeightLogRepository;

return [

    /*
    | Repository 介面 => 實作，由 ProfileServiceProvider::bindInstances() 綁定。
    */
    'binds' => [
        ProfileRepositoryContract::class => ProfileRepository::class,
        WeightLogRepositoryContract::class => WeightLogRepository::class,
    ],

    /*
    | 單例服務 abstract => class，由 ProfileServiceProvider::registerSingletons() 註冊。
    */
    'singletons' => [],

    /*
    | 使用者未設定時區時的預設值。
    */
    'default_timezone' => env('PROFILE_DEFAULT_TIMEZONE', 'Asia/Taipei'),

    /*
    | 註冊的最低年齡。
    */
    'min_age' => env('PROFILE_MIN_AGE', 13),

    /*
    | 日常活動量（不含運動）係數：BMR × 係數 = 日常消耗。運動消耗另由健身紀錄計算，所以數值比一般含運動的 TDEE 係數低。
    */
    'activity_factors' => [
        'sedentary' => 1.2,
        'light' => 1.3,
        'moderate' => 1.4,
        'active' => 1.5,
    ],

    /*
    | 每公斤體重對應的熱量（kcal），用來把每週目標速度換算成每日熱量增減。
    */
    'kcal_per_kg' => 7700,

    /*
    | 每週目標速度允許範圍（kg / 週）。
    */
    'goal_rate' => [
        'lose' => ['min' => 0.1, 'max' => 1.0],
        'gain' => ['min' => 0.1, 'max' => 0.5],
    ],

    /*
    | 蛋白質目標（g / 每公斤體重），依目標而定。
    */
    'protein_g_per_kg' => [
        'lose' => 1.8,
        'maintain' => 1.2,
        'gain' => 1.6,
    ],

    /*
    | 每日熱量目標的建議下限（kcal），依性別；低於時前端只提醒、不阻擋儲存。
    */
    'calorie_floor' => [
        'male' => env('PROFILE_CALORIE_FLOOR_MALE', 1500),
        'female' => env('PROFILE_CALORIE_FLOOR_FEMALE', 1200),
    ],

    /*
    | 體重紀錄列表每次載入的筆數（「載入更多」一次多載入這麼多筆）。
    */
    'weight_logs_per_page' => 20,

    /*
    | 未完成首次設定時仍可進入的路由名稱（支援 * 萬用字元）：首次設定本身、登出、信箱驗證、密碼確認與設定頁。
    */
    'onboarding_except_routes' => [
        'fitness.profile.onboarding.*',
        'logout',
        'verification.*',
        'password.confirm',
        'profile.*',
        'password.edit',
        'password.update',
        'appearance',
    ],

];
