<?php

namespace Fitness\Exercise\Services;

use Fitness\Exercise\Enums\RecordType;
use Fitness\Exercise\Repositories\Contracts\ExerciseCategoryRepository;
use Fitness\Exercise\Repositories\Contracts\ExerciseRepository;
use Illuminate\Support\Facades\DB;

/**
 * 把設定檔的預設分類與項目複製一份給使用者，成為使用者自己的資料。
 * 只在完成首次設定（ProfileCompleted）與上線補建時呼叫；使用者已有任何分類時不動作，之後全部刪掉也不會再長回來。
 */
class DefaultExerciseService
{
    public function __construct(
        protected ExerciseCategoryRepository $categories,
        protected ExerciseRepository $exercises,
    ) {}

    /**
     * 替使用者建立預設資料；已有分類時略過並回傳 false。
     */
    public function createFor(int $userId): bool
    {
        if ($this->categories->existsForUser($userId)) {
            return false;
        }

        DB::transaction(function () use ($userId) {
            foreach (config('fitness.exercise.defaults', []) as $default) {
                $type = RecordType::from($default['record_type']);
                $category = $this->categories->create($userId, [
                    'name' => $default['name'],
                    'record_type' => $type->value,
                ]);

                foreach ($default['exercises'] as $exercise) {
                    $this->exercises->create($userId, [
                        'category_id' => $category->id,
                        'name' => $exercise['name'],
                        'met' => $exercise['met'],
                        'sec_per_rep' => $type->isSets() ? ($exercise['sec_per_rep'] ?? (int) config('fitness.exercise.sets_defaults.sec_per_rep')) : null,
                        'rest_sec' => $type->isSets() ? ($exercise['rest_sec'] ?? (int) config('fitness.exercise.sets_defaults.rest_sec')) : null,
                    ]);
                }
            }
        });

        return true;
    }
}
