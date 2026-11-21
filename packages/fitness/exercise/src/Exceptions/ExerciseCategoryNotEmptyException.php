<?php

namespace Fitness\Exercise\Exceptions;

use Exception;
use Fitness\Exercise\Models\ExerciseCategory;
use Illuminate\Http\RedirectResponse;

/**
 * 分類底下還有未封存的項目，不能刪除。
 */
class ExerciseCategoryNotEmptyException extends Exception
{
    public function __construct(public readonly ExerciseCategory $category, public readonly int $count)
    {
        parent::__construct("Exercise category {$category->id} still has {$count} active exercises.");
    }

    /**
     * 導回上一頁並說明要先刪除或移走項目。
     */
    public function render(): RedirectResponse
    {
        return back()->with('error', [
            'title' => trans('FitnessExercise::errors.category_not_empty.title', ['name' => $this->category->name]),
            'message' => trans('FitnessExercise::errors.category_not_empty.message', [
                'count' => $this->count,
                'type' => $this->category->record_type->label(),
            ]),
        ]);
    }
}
