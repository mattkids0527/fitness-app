<?php

namespace Fitness\Exercise\Exceptions;

use Exception;
use Fitness\Exercise\Enums\RecordType;
use Fitness\Exercise\Models\Exercise;
use Illuminate\Http\RedirectResponse;

/**
 * 要還原的封存項目原分類已刪除，而且沒有同記錄方式的分類可以放。
 */
class ExerciseRestoreNoCategoryException extends Exception
{
    public function __construct(public readonly Exercise $exercise, public readonly RecordType $type)
    {
        parent::__construct("Exercise {$exercise->id} cannot be restored: no {$type->value} category.");
    }

    /**
     * 導回上一頁並說明要先新增同記錄方式的分類。
     */
    public function render(): RedirectResponse
    {
        return back()->with('error', [
            'title' => trans('FitnessExercise::errors.restore_no_category.title', ['name' => $this->exercise->name]),
            'message' => trans('FitnessExercise::errors.restore_no_category.message', ['type' => $this->type->label()]),
        ]);
    }
}
