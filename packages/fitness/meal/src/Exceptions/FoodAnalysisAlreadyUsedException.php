<?php

namespace Fitness\Meal\Exceptions;

use Exception;
use Fitness\Meal\Models\Meal;
use Illuminate\Http\RedirectResponse;

/**
 * 這筆照片分析已經建立過餐點（重複送出、或存檔後按上一頁再存一次）；一筆分析只能建立一餐。
 */
class FoodAnalysisAlreadyUsedException extends Exception
{
    public function __construct(public readonly Meal $meal)
    {
        parent::__construct("Food analysis {$meal->food_analysis_id} already has meal {$meal->id}.");
    }

    /**
     * 導向已建立的那一餐的編輯頁，並說明原因。
     */
    public function render(): RedirectResponse
    {
        return redirect()
            ->route('fitness.meal.edit', $this->meal->id)
            ->with('error', [
                'title' => trans('FitnessMeal::errors.analysis_used.title'),
                'message' => trans('FitnessMeal::errors.analysis_used.message'),
            ]);
    }
}
