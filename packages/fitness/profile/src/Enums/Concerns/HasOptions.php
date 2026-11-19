<?php

namespace Fitness\Profile\Enums\Concerns;

/**
 * 讓 enum 輸出前端下拉選單用的 [{value, label}] 陣列。
 */
trait HasOptions
{
    /**
     * 取得所有選項（值與中文名稱），供 Inertia 頁面使用。
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
