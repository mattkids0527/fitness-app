<?php

namespace Fitness\Water\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 記錄喝水的驗證：整數 ml，上下限依設定檔。
 */
class WaterLogStoreRequest extends FormRequest
{
    /**
     * 已登入即可記錄自己的喝水。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 喝水量規則。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_ml' => ['required', 'integer', 'between:'.config('fitness.water.min_ml').','.config('fitness.water.max_ml')],
        ];
    }

    /**
     * 欄位中文名稱。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('FitnessWater::attributes');
    }
}
