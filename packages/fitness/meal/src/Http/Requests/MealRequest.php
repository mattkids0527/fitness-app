<?php

namespace Fitness\Meal\Http\Requests;

use Fitness\Meal\Enums\MealType;
use Fitness\Meal\Http\Requests\Concerns\HasFoodRules;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 新增／修改餐點的驗證：日期不能晚於使用者時區的今天，至少一個項目，名稱與熱量必填。
 */
class MealRequest extends FormRequest
{
    use HasFoodRules;

    /**
     * 已登入即可記錄自己的餐點；修改他人餐點由 Service 以 404 擋下。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 餐點與項目規則。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.UserCalendar::today($this->user())->toDateString()],
            'time' => ['required', 'date_format:H:i'],
            'meal_type' => ['required', Rule::enum(MealType::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:'.config('fitness.meal.max_items')],
            ...$this->foodRules('items.*.'),
            'items.*.saved_food_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * 沒有任何項目時的訊息。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => trans('FitnessMeal::errors.items_required'),
            'items.min' => trans('FitnessMeal::errors.items_required'),
        ];
    }

    /**
     * 欄位中文名稱。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => trans('FitnessMeal::attributes.date'),
            'time' => trans('FitnessMeal::attributes.time'),
            'meal_type' => trans('FitnessMeal::attributes.meal_type'),
            'note' => trans('FitnessMeal::attributes.note'),
            'items' => trans('FitnessMeal::attributes.items'),
            ...$this->foodAttributes('items.*.'),
        ];
    }
}
