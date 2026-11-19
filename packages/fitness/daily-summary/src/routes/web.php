<?php

use Fitness\DailySummary\Http\Controllers\DailySummaryController;
use Illuminate\Support\Facades\Route;

/*
| daily-summary 模組路由：需登入，名稱前綴 fitness.daily_summary.
*/
Route::middleware(['web', 'auth', 'verified'])
    ->name('fitness.daily_summary.')
    ->group(function () {
        Route::get('today', [DailySummaryController::class, 'show'])->name('show');
    });
