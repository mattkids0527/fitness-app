<?php

use Fitness\Exercise\Http\Controllers\ExerciseCategoryController;
use Fitness\Exercise\Http\Controllers\ExerciseController;
use Illuminate\Support\Facades\Route;

/*
| exercise 模組路由：需登入，名稱前綴 fitness.exercise.
*/
Route::middleware(['web', 'auth'])
    ->name('fitness.exercise.')
    ->group(function () {
        // 分類與項目管理
        Route::prefix('exercises')->group(function () {
            Route::get('/', [ExerciseController::class, 'index'])->name('index');
            Route::get('create', [ExerciseController::class, 'create'])->name('create');
            Route::post('/', [ExerciseController::class, 'store'])->name('store');
            Route::get('{exercise}/edit', [ExerciseController::class, 'edit'])->whereNumber('exercise')->name('edit');
            Route::put('{exercise}', [ExerciseController::class, 'update'])->whereNumber('exercise')->name('update');
            Route::delete('{exercise}', [ExerciseController::class, 'destroy'])->whereNumber('exercise')->name('destroy');
            Route::post('{exercise}/restore', [ExerciseController::class, 'restore'])->whereNumber('exercise')->name('restore');

            Route::post('categories', [ExerciseCategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [ExerciseCategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
            Route::post('categories/{category}/move', [ExerciseCategoryController::class, 'move'])->whereNumber('category')->name('categories.move');
            Route::delete('categories/{category}', [ExerciseCategoryController::class, 'destroy'])->whereNumber('category')->name('categories.destroy');
        });
    });
