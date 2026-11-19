<?php

use Illuminate\Support\Facades\Route;

/*
| profile 模組路由：需登入，名稱前綴 fitness.profile.
*/
Route::middleware(['web', 'auth'])
    ->name('fitness.profile.')
    ->group(function () {
        //
    });
