<?php

use Fitness\Water\Http\Controllers\WaterLogController;
use Illuminate\Support\Facades\Route;

/*
| water 模組路由：需登入，名稱前綴 fitness.water.
*/
Route::middleware(['web', 'auth'])
    ->name('fitness.water.')
    ->group(function () {
        Route::post('water-logs', [WaterLogController::class, 'store'])->name('store');
        Route::delete('water-logs/{waterLog}', [WaterLogController::class, 'destroy'])->whereNumber('waterLog')->name('destroy');
    });
