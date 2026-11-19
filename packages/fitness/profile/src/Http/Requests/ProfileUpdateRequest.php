<?php

namespace Fitness\Profile\Http\Requests;

use Fitness\Profile\Enums\ActivityLevel;
use Fitness\Profile\Enums\Gender;
use Fitness\Profile\Enums\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 修改個人資料的驗證；首次設定表單沿用這組規則再加上體重。
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * 已登入即可修改自己的資料。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 個人資料欄位規則；每週目標速度只在減脂／增肌時必填，範圍依目標取自設定檔。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minAge = config('fitness.profile.min_age');
        $rateRange = config('fitness.profile.goal_rate.'.$this->input('goal'));

        return [
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:'.now()->subYears($minAge)->toDateString()],
            'height_cm' => ['required', 'numeric', 'between:100,250'],
            'activity_level' => ['required', Rule::enum(ActivityLevel::class)],
            'goal' => ['required', Rule::enum(Goal::class)],
            'goal_rate_kg_per_week' => array_filter([
                'exclude_if:goal,'.Goal::Maintain->value,
                'required',
                'numeric',
                $rateRange ? 'between:'.$rateRange['min'].','.$rateRange['max'] : null,
            ]),
            'bmr_override' => ['nullable', 'integer', 'between:500,5000'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
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

    /**
     * 取得要寫入的個人資料；維持目標時把每週目標速度清空。
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        $data = $this->validated();

        if ($data['goal'] === Goal::Maintain->value) {
            $data['goal_rate_kg_per_week'] = null;
        }

        return $data;
    }
}
