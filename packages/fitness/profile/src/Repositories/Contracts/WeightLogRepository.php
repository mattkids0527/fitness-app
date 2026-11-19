<?php

namespace Fitness\Profile\Repositories\Contracts;

use Fitness\Profile\Models\WeightLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 體重紀錄的存取介面；所有查詢都限定在指定使用者底下。
 */
interface WeightLogRepository
{
    /**
     * 新增一筆體重紀錄。
     */
    public function create(int $userId, float $weightKg, string $measuredOn): WeightLog;

    /**
     * 取得使用者最新一筆紀錄（依量測日、再依建立順序）。
     */
    public function latestForUser(int $userId): ?WeightLog;

    /**
     * 分頁列出使用者的紀錄，新的在前。
     */
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;

    /**
     * 取得使用者自己的某筆紀錄，不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findForUserOrFail(int $userId, int $id): WeightLog;

    /**
     * 計算使用者的紀錄筆數。
     */
    public function countForUser(int $userId): int;

    /**
     * 刪除一筆紀錄。
     */
    public function delete(WeightLog $weightLog): void;
}
