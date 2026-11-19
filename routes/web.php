<?php

use Illuminate\Support\Facades\Route;

/*
| 首頁：未登入導向登入頁，已登入導向今日頁。
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('fitness.daily_summary.show')
        : redirect()->route('login');
})->name('home');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
