<?php

namespace Fitness\Exercise\Http\Requests;

use Fitness\Exercise\Models\ExerciseCategory;
use Fitness\Exercise\Repositories\Contracts\ExerciseCategoryRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 新增／編輯項目的驗證；分類必須是自己的，逐組分類的項目要填每下秒數與組間休息。
 * 同名檢查與「只能移到同記錄方式的分類」由 ExerciseService 處理。
 */
class ExerciseRequest extends FormRequest
{
    /**
     * 已登入即可管理自己的項目；改他人的由 Service 以 404 擋下。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 項目欄位規則；秒數是否必填依所選分類的記錄方式。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSets = $this->category()?->record_type->isSets() ?? false;
        $setsRule = $isSets ? 'required' : 'nullable';

        return [
            'name' => ['required', 'string', 'max:50'],
            'category_id' => ['required', 'integer', Rule::exists('exercise_categories', 'id')->where('user_id', $this->user()->id)],
            'met' => ['required', 'numeric', 'min:0.5', 'max:30'],
            'sec_per_rep' => [$setsRule, 'integer', 'between:1,60'],
            'rest_sec' => [$setsRule, 'integer', 'between:0,600'],
            'description' => ['nullable', 'string', 'max:300'],
        ];
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

    /**
     * 表單選的分類（只找自己的）；沒選或找不到時為 null，交給 exists 規則回報錯誤。
     */
    protected function category(): ?ExerciseCategory
    {
        $id = $this->input('category_id');

        if (! is_numeric($id)) {
            return null;
        }

        return app(ExerciseCategoryRepository::class)->findForUser($this->user()->id, (int) $id);
    }
}
