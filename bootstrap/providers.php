<?php

use App\Providers\AppServiceProvider;
use Fitness\DailySummary\DailySummaryServiceProvider;
use Fitness\Profile\ProfileServiceProvider;
use Fitness\SocialLogin\SocialLoginServiceProvider;
use Fitness\SocialLoginGoogle\SocialLoginGoogleServiceProvider;

return [
    AppServiceProvider::class,
    ProfileServiceProvider::class,
    DailySummaryServiceProvider::class,
    SocialLoginServiceProvider::class,
    SocialLoginGoogleServiceProvider::class,
];
