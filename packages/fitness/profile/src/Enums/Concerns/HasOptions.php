<?php

namespace Fitness\Profile\Enums\Concerns;

/**
 * 讓 enum 輸出前端選項用的 [{value, label, description?}] 陣列；enum 有 description() 時一併輸出說明。
 */
trait HasOptions
{
    /**
     * 取得所有選項（值、中文名稱與說明），供 Inertia 頁面使用。
     *
     * @return list<array{value: string, label: string, description?: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => array_filter([
            'value' => $case->value,
            'label' => $case->label(),
            'description' => method_exists($case, 'description') ? $case->description() : null,
        ], fn ($value) => $value !== null), self::cases());
    }
}
