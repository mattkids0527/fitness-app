<?php

namespace Fitness\Profile\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * 嘗試刪除使用者僅剩的一筆體重紀錄。
 */
class CannotDeleteLastWeightLogException extends Exception
{
    /**
     * 帶著錯誤訊息導回上一頁，前端以 errors.weight_log 顯示。
     */
    public function render(): RedirectResponse
    {
        return back()->withErrors(['weight_log' => trans('FitnessProfile::errors.cannot_delete_last_weight_log')]);
    }
}
