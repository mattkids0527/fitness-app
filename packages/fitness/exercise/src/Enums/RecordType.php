<?php

namespace Fitness\Exercise\Enums;

/**
 * 分類的記錄方式：填時長（duration）輸入分鐘數；逐組（sets）逐組輸入次數 × 重量。分類建立後不能改。
 */
enum RecordType: string
{
    case Duration = 'duration';
    case Sets = 'sets';

    /**
     * 取得中文名稱。
     */
    public function label(): string
    {
        return trans('FitnessExercise::options.record_type.'.$this->value);
    }

    /**
     * 選擇記錄方式時的說明。
     */
    public function hint(): string
    {
        return trans('FitnessExercise::options.record_type_hint.'.$this->value);
    }

    /**
     * 是否為逐組記錄（需要每下秒數與組間休息）。
     */
    public function isSets(): bool
    {
        return $this === self::Sets;
    }

    /**
     * 前端選項：值、中文名稱與說明。
     *
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'hint' => $type->hint(),
        ], self::cases());
    }
}
