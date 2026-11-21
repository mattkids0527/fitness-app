<?php

namespace Fitness\Exercise\Services;

use Fitness\Exercise\Enums\RecordType;
use Fitness\Exercise\Models\Exercise;
use Fitness\Exercise\Models\ExerciseCategory;
use Fitness\Profile\Models\WeightLog;
use Illuminate\Support\Collection;

/**
 * 把分類、項目轉成前端用的陣列。
 */
class ExercisePresenter
{
    /**
     * 分類摘要。
     *
     * @return array{id: int, name: string, record_type: string}
     */
    public function category(ExerciseCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'record_type' => $category->record_type->value,
        ];
    }

    /**
     * 項目的參數（列表與表單共用）。
     *
     * @return array{id: int, name: string, category_id: int|null, met: float, sec_per_rep: int|null, rest_sec: int|null, description: string|null}
     */
    public function exercise(Exercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'category_id' => $exercise->category_id,
            'met' => $exercise->met,
            'sec_per_rep' => $exercise->sec_per_rep,
            'rest_sec' => $exercise->rest_sec,
            'description' => $exercise->description,
        ];
    }

    /**
     * 管理頁的分類分組：每個分類帶出底下未封存的項目（依建立順序）。
     *
     * @param  Collection<int, ExerciseCategory>  $categories
     * @param  Collection<int, Exercise>  $exercises
     * @return list<array<string, mixed>>
     */
    public function groups(Collection $categories, Collection $exercises): array
    {
        $byCategory = $exercises->groupBy('category_id');

        return $categories->map(fn (ExerciseCategory $category) => [
            ...$this->category($category),
            'exercises' => $byCategory->get($category->id, collect())->map(fn (Exercise $exercise) => $this->exercise($exercise))->values()->all(),
        ])->values()->all();
    }

    /**
     * 管理頁「已封存」列：原分類名稱（已刪除時為 null）與紀錄筆數。
     *
     * @return array{id: int, name: string, category_id: int|null, category_name: string|null, record_type: string, log_count: int}
     */
    public function archived(Exercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'category_id' => $exercise->category_id,
            'category_name' => $exercise->category?->name,
            'record_type' => $exercise->recordType()->value,
            'log_count' => (int) $exercise->logs_count,
        ];
    }

    /**
     * 記錄方式選項（值、名稱、說明）。
     *
     * @return list<array{value: string, label: string, hint: string}>
     */
    public function recordTypes(): array
    {
        return RecordType::options();
    }

    /**
     * 即時估算用的體重：數值與記錄日期；沒有任何體重紀錄時為 null。
     *
     * @return array{kg: float, date: string}|null
     */
    public function weight(?WeightLog $log): ?array
    {
        return $log ? ['kg' => $log->weight_kg, 'date' => $log->measured_on->toDateString()] : null;
    }

    /**
     * 前端即時估算的係數與基準（公式與 ExerciseCalculator 一致）。
     *
     * @return array{sets_defaults: array{met: float, sec_per_rep: int, rest_sec: int}, estimate: array{duration_min: int, sets: int, reps: int}}
     */
    public function calculation(): array
    {
        $defaults = config('fitness.exercise.sets_defaults');
        $estimate = config('fitness.exercise.estimate');

        return [
            'sets_defaults' => [
                'met' => (float) $defaults['met'],
                'sec_per_rep' => (int) $defaults['sec_per_rep'],
                'rest_sec' => (int) $defaults['rest_sec'],
            ],
            'estimate' => [
                'duration_min' => (int) $estimate['duration_min'],
                'sets' => (int) $estimate['sets'],
                'reps' => (int) $estimate['reps'],
            ],
        ];
    }
}
