<?php

namespace Fitness\Exercise\Repositories\Contracts;

use Fitness\Exercise\Models\Exercise;
use Illuminate\Support\Collection;

/**
 * 健身項目的存取介面；所有查詢都限定在指定使用者底下。
 */
interface ExerciseRepository
{
    /**
     * 使用者未封存的項目（含分類），依分類排序、再依建立順序。
     *
     * @return Collection<int, Exercise>
     */
    public function activeForUser(int $userId): Collection;

    /**
     * 使用者已封存的項目（含分類與紀錄筆數 logs_count），最近封存的在前。
     *
     * @return Collection<int, Exercise>
     */
    public function archivedForUser(int $userId): Collection;

    /**
     * 取得使用者自己的某個項目（含封存），不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findForUserOrFail(int $userId, int $id): Exercise;

    /**
     * 取得使用者自己未封存的某個項目（含分類與紀錄筆數 logs_count），封存或不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findActiveForUserOrFail(int $userId, int $id): Exercise;

    /**
     * 取得使用者自己已封存的某個項目，未封存或不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findArchivedForUserOrFail(int $userId, int $id): Exercise;

    /**
     * 名稱是否已被使用者其他未封存的項目使用（不分大小寫）。
     */
    public function nameTaken(int $userId, string $name, ?int $exceptId = null): bool;

    /**
     * 分類底下未封存的項目數。
     */
    public function activeCountInCategory(int $categoryId): int;

    /**
     * 項目是否已有健身紀錄。
     */
    public function hasLogs(Exercise $exercise): bool;

    /**
     * 新增項目。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $attributes): Exercise;

    /**
     * 更新項目（含封存、還原）。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Exercise $exercise, array $attributes): Exercise;

    /**
     * 刪除項目（只用在沒有紀錄的項目）。
     */
    public function delete(Exercise $exercise): void;
}
