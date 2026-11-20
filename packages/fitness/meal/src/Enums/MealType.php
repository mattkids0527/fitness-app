<?php

namespace Fitness\Meal\Enums;

/**
 * 餐別：早餐、午餐、晚餐、點心。
 */
enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Snack = 'snack';

    /**
     * 取得中文名稱。
     */
    public function label(): string
    {
        return trans('FitnessMeal::options.meal_type.'.$this->value);
    }

    /**
     * 記錄今天以外的日期時，這個餐別預設的時間（H:i）。
     */
    public function defaultTime(): string
    {
        return config('fitness.meal.meal_type_default_times.'.$this->value);
    }

    /**
     * 依設定檔的時段推測餐別（時間格式 H:i），都不符合時為點心。
     */
    public static function forTime(string $time): self
    {
        $hour = (int) substr($time, 0, 2);

        foreach (config('fitness.meal.meal_type_hours') as $type => [$from, $to]) {
            if ($hour >= $from && $hour < $to) {
                return self::from($type);
            }
        }

        return self::Snack;
    }

    /**
     * 前端選項：值、中文名稱、預設時間，以及自動選擇用的時段（點心為 null）。
     *
     * @return list<array{value: string, label: string, default_time: string, hours: array{0: int, 1: int}|null}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'default_time' => $type->defaultTime(),
            'hours' => config('fitness.meal.meal_type_hours.'.$type->value),
        ], self::cases());
    }
}
