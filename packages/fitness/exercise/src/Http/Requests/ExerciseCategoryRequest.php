<?php

namespace Fitness\Exercise\Http\Requests;

use Fitness\Exercise\Enums\RecordType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 新增分類（名稱＋記錄方式）與改名（只有名稱，記錄方式建立後不能改）的驗證；同名檢查由 ExerciseCategoryService 處理。
 */
class ExerciseCategoryRequest extends FormRequest
{
    /**
     * 已登入即可管理自己的分類；改他人的由 Service 以 404 擋下。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 新增時才收記錄方式；改名時即使送了也不會進到 validated()。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['name' => ['required', 'string', 'max:30']];

        if ($this->isMethod('post')) {
            $rules['record_type'] = ['required', Rule::enum(RecordType::class)];
        }

        return $rules;
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
