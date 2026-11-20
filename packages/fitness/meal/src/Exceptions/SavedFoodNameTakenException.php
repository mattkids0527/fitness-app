<?php

namespace Fitness\Meal\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * 常用食物裡已經有同名（不分大小寫）的項目。
 */
class SavedFoodNameTakenException extends Exception
{
    /**
     * 帶著欄位錯誤導回上一頁，前端以 errors.name 顯示。
     */
    public function render(): RedirectResponse
    {
        return back()->withInput()->withErrors(['name' => trans('FitnessMeal::errors.saved_food_name_taken')]);
    }
}
