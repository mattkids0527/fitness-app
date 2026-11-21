<?php

namespace Fitness\Exercise\Repositories\Contracts;

use Fitness\Exercise\Enums\RecordType;
use Fitness\Exercise\Models\ExerciseCategory;
use Illuminate\Support\Collection;

/**
 * 健身分類的存取介面；所有查詢都限定在指定使用者底下。
 */
interface ExerciseCategoryRepository
{
    /**
     * 使用者是否已有任何分類。
     */
    public function existsForUser(int $userId): bool;

    /**
     * 使用者所有分類，依排序。
     *
     * @return Collection<int, ExerciseCategory>
     */
    public function allForUser(int $userId): Collection;

    /**
     * 取得使用者自己的某個分類，不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findForUserOrFail(int $userId, int $id): ExerciseCategory;

    /**
     * 取得使用者自己的某個分類，不存在或不屬於該使用者時為 null。
     */
    public function findForUser(int $userId, int $id): ?ExerciseCategory;

    /**
     * 使用者某種記錄方式排在最前面的分類；沒有時為 null。
     */
    public function firstOfType(int $userId, RecordType $type): ?ExerciseCategory;

    /**
     * 名稱是否已被使用者的其他分類使用（不分大小寫）。
     */
    public function nameTaken(int $userId, string $name, ?int $exceptId = null): bool;

    /**
     * 新增分類，排在使用者現有分類的最後。
     *
     * @param  array{name: string, record_type: string}  $attributes
     */
    public function create(int $userId, array $attributes): ExerciseCategory;

    /**
     * 更新分類。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(ExerciseCategory $category, array $attributes): ExerciseCategory;

    /**
     * 與上一個（$step = -1）或下一個（$step = 1）分類交換排序；已在最前／最後時不動作並回傳 false。
     */
    public function move(ExerciseCategory $category, int $step): bool;

    /**
     * 刪除分類；底下的（封存）項目由 FK 把 category_id 設為 null。
     */
    public function delete(ExerciseCategory $category): void;
}
