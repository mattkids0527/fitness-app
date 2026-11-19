<?php

namespace Fitness\Profile\Http\Requests;

use Fitness\Profile\Services\UserCalendar;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 新增體重紀錄的驗證；量測日不能晚於使用者時區的今天。
 */
class WeightLogStoreRequest extends FormRequest
{
    /**
     * 已登入即可新增自己的紀錄。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 體重與量測日規則。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weight_kg' => ['required', 'numeric', 'between:20,300'],
            'measured_on' => ['nullable', 'date', 'before_or_equal:'.UserCalendar::today($this->user())->toDateString()],
        ];
    }

    /**
     * 欄位中文名稱。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('FitnessProfile::attributes');
    }
}
