<?php

use Fitness\Profile\Http\Controllers\OnboardingController;
use Fitness\Profile\Http\Controllers\ProfileController;
use Fitness\Profile\Http\Controllers\WeightLogController;
use Illuminate\Support\Facades\Route;

/*
| profile 模組路由：需登入，名稱前綴 fitness.profile.
*/
Route::middleware(['web', 'auth'])
    ->name('fitness.profile.')
    ->group(function () {
        Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

        Route::get('me', [ProfileController::class, 'show'])->name('show');
        Route::put('me', [ProfileController::class, 'update'])->name('update');

        Route::get('me/weight-logs', [WeightLogController::class, 'index'])->name('weight_logs.index');
        Route::post('me/weight-logs', [WeightLogController::class, 'store'])->name('weight_logs.store');
        Route::delete('me/weight-logs/{weightLog}', [WeightLogController::class, 'destroy'])
            ->whereNumber('weightLog')
            ->name('weight_logs.destroy');
    });
