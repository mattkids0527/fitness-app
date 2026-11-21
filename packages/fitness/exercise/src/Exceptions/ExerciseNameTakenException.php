<?php

namespace Fitness\Exercise\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * 使用者已經有同名（不分大小寫）且未封存的項目。
 */
class ExerciseNameTakenException extends Exception
{
    /**
     * 帶著欄位錯誤導回上一頁，前端以 errors.name 顯示。
     */
    public function render(): RedirectResponse
    {
        return back()->withInput()->withErrors(['name' => trans('FitnessExercise::errors.exercise_name_taken')]);
    }
}
