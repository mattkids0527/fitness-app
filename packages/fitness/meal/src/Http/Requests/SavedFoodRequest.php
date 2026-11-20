<?php

namespace Fitness\Meal\Http\Requests;

use Fitness\Meal\Http\Requests\Concerns\HasFoodRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 新增／修改常用食物的驗證（數值為一份）；同名檢查由 SavedFoodService 處理。
 */
class SavedFoodRequest extends FormRequest
{
    use HasFoodRules;

    /**
     * 已登入即可管理自己的常用食物；修改他人的由 Service 以 404 擋下。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 常用食物欄位規則。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->foodRules();
    }

    /**
     * 欄位中文名稱。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->foodAttributes();
    }
}
