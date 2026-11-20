<?php

use Fitness\Meal\Http\Controllers\MealAnalysisController;
use Fitness\Meal\Http\Controllers\MealController;
use Fitness\Meal\Http\Controllers\SavedFoodController;
use Illuminate\Support\Facades\Route;

/*
| meal 模組路由：需登入，名稱前綴 fitness.meal.
*/
Route::middleware(['web', 'auth'])
    ->prefix('meals')
    ->name('fitness.meal.')
    ->group(function () {
        Route::get('/', [MealController::class, 'index'])->name('index');
        Route::get('create', [MealController::class, 'create'])->name('create');
        Route::post('/', [MealController::class, 'store'])->name('store');
        Route::get('analyze', [MealAnalysisController::class, 'show'])->name('analyze');
        Route::post('analyze', [MealAnalysisController::class, 'store'])->name('analyze.store');
        Route::get('{meal}/edit', [MealController::class, 'edit'])->whereNumber('meal')->name('edit');
        Route::put('{meal}', [MealController::class, 'update'])->whereNumber('meal')->name('update');
        Route::delete('{meal}', [MealController::class, 'destroy'])->whereNumber('meal')->name('destroy');

        Route::get('saved-foods', [SavedFoodController::class, 'index'])->name('saved_foods.index');
        Route::post('saved-foods', [SavedFoodController::class, 'store'])->name('saved_foods.store');
        Route::put('saved-foods/{savedFood}', [SavedFoodController::class, 'update'])->whereNumber('savedFood')->name('saved_foods.update');
        Route::delete('saved-foods/{savedFood}', [SavedFoodController::class, 'destroy'])->whereNumber('savedFood')->name('saved_foods.destroy');
    });
