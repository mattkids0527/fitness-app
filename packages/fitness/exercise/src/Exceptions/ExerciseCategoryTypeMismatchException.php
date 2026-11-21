<?php

namespace Fitness\Exercise\Exceptions;

use Exception;
use Fitness\Exercise\Enums\RecordType;
use Illuminate\Http\RedirectResponse;

/**
 * 項目只能移到同記錄方式的分類，避免同一項目新舊紀錄算法不同。
 */
class ExerciseCategoryTypeMismatchException extends Exception
{
    public function __construct(public readonly RecordType $type)
    {
        parent::__construct("Exercise can only move to a {$type->value} category.");
    }

    /**
     * 帶著欄位錯誤導回上一頁，前端以 errors.category_id 顯示。
     */
    public function render(): RedirectResponse
    {
        return back()->withInput()->withErrors([
            'category_id' => trans('FitnessExercise::errors.category_type_mismatch', ['type' => $this->type->label()]),
        ]);
    }
}
