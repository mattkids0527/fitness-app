<?php

namespace Fitness\Exercise\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 分類往上／往下移的驗證。
 */
class ExerciseCategoryMoveRequest extends FormRequest
{
    /**
     * 已登入即可排序自己的分類；改他人的由 Service 以 404 擋下。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 方向只能是 up 或 down。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['direction' => ['required', 'in:up,down']];
    }

    /**
     * 往上為 -1、往下為 1。
     */
    public function step(): int
    {
        return $this->validated('direction') === 'up' ? -1 : 1;
    }

    /**
     * 欄位中文名稱。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('FitnessExercise::attributes');
    }
}
