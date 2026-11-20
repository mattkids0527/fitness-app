<?php

use App\Providers\AppServiceProvider;
use Fitness\DailySummary\DailySummaryServiceProvider;
use Fitness\Profile\ProfileServiceProvider;
use Fitness\SocialLogin\SocialLoginServiceProvider;

return [
    AppServiceProvider::class,
    ProfileServiceProvider::class,
    DailySummaryServiceProvider::class,
    SocialLoginServiceProvider::class,
];
