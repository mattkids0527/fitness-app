<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| 首頁：未登入導向登入頁，已登入導向今日頁。
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('fitness.daily_summary.show')
        : redirect()->route('login');
})->name('home');

/*
| 底部 Tab 尚未實作的功能先顯示「即將推出」；飲食（meal）、健身（exercise）模組建立後移除這兩條路由。
*/
Route::middleware(['auth', 'verified'])->name('coming_soon.')->group(function () {
    Route::get('meals', fn () => Inertia::render('ComingSoon', [
        'title' => '飲食',
        'icon' => 'utensils',
        'items' => ['拍照交給 AI 分析熱量與營養素', '手動輸入餐點', '常用食物、最近吃過快速帶入'],
    ]))->name('meals');

    Route::get('workouts', fn () => Inertia::render('ComingSoon', [
        'title' => '健身',
        'icon' => 'dumbbell',
        'items' => ['有氧／時間型紀錄', '重訓逐組記錄次數 × 重量', '依體重估算消耗熱量'],
    ]))->name('workouts');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
