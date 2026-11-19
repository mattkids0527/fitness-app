<?php

namespace Fitness\Profile\Http\Requests;

/**
 * 首次設定表單的驗證：個人資料欄位再加上目前體重。
 */
class OnboardingRequest extends ProfileUpdateRequest
{
    /**
     * 個人資料規則加上體重。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'weight_kg' => ['required', 'numeric', 'between:20,300'],
        ];
    }
}
