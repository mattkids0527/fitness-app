<?php

namespace Fitness\Meal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 送出照片分析的驗證：一張 JPEG／PNG／WebP 照片（5MB 以內）、選填的補充說明，以及前端讀到的拍攝時間。
 */
class MealAnalyzeRequest extends FormRequest
{
    /**
     * 已登入即可分析；次數上限由分析服務檢查。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 照片、補充說明與拍攝時間（使用者時區 Y-m-d H:i）的規則。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimetypes:'.implode(',', config('fitness.meal.photo_mimetypes')), 'max:'.config('fitness.meal.photo_max_kb')],
            'hint' => ['nullable', 'string', 'max:'.config('fitness.food_analyzer.hint_max_length')],
            'taken_at' => ['nullable', 'date_format:Y-m-d H:i'],
        ];
    }

    /**
     * 照片類型與大小的說明。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.mimetypes' => trans('FitnessMeal::errors.photo_type'),
            'photo.max' => trans('FitnessMeal::errors.photo_size', ['max' => (int) (config('fitness.meal.photo_max_kb') / 1024)]),
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
            'photo' => trans('FitnessMeal::attributes.photo'),
            'hint' => trans('FitnessMeal::attributes.hint'),
            'taken_at' => trans('FitnessMeal::attributes.taken_at'),
        ];
    }
}
