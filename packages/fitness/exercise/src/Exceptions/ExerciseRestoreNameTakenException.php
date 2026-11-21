<?php

namespace Fitness\Exercise\Exceptions;

use Exception;
use Fitness\Exercise\Models\Exercise;
use Illuminate\Http\RedirectResponse;

/**
 * 要還原的封存項目，名稱已被其他未封存的項目使用。
 */
class ExerciseRestoreNameTakenException extends Exception
{
    public function __construct(public readonly Exercise $exercise)
    {
        parent::__construct("Exercise {$exercise->id} cannot be restored: name is taken.");
    }

    /**
     * 導回上一頁並說明要先改名或封存同名項目。
     */
    public function render(): RedirectResponse
    {
        return back()->with('error', [
            'title' => trans('FitnessExercise::errors.restore_name_taken.title', ['name' => $this->exercise->name]),
            'message' => trans('FitnessExercise::errors.restore_name_taken.message'),
        ]);
    }
}
