<?php

use App\Providers\AppServiceProvider;
use Fitness\DailySummary\DailySummaryServiceProvider;
use Fitness\Profile\ProfileServiceProvider;

return [
    AppServiceProvider::class,
    ProfileServiceProvider::class,
    DailySummaryServiceProvider::class,
];
