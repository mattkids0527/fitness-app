<?php

use App\Providers\AppServiceProvider;
use Fitness\DailySummary\DailySummaryServiceProvider;
use Fitness\FoodAnalyzer\FoodAnalyzerServiceProvider;
use Fitness\Meal\MealServiceProvider;
use Fitness\Profile\ProfileServiceProvider;
use Fitness\SocialLogin\SocialLoginServiceProvider;
use Fitness\SocialLoginGoogle\SocialLoginGoogleServiceProvider;
use Fitness\Water\WaterServiceProvider;

return [
    AppServiceProvider::class,
    ProfileServiceProvider::class,
    DailySummaryServiceProvider::class,
    MealServiceProvider::class,
    FoodAnalyzerServiceProvider::class,
    SocialLoginServiceProvider::class,
    SocialLoginGoogleServiceProvider::class,
    WaterServiceProvider::class,
];
