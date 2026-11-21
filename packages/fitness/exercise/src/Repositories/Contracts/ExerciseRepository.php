<?php

namespace Fitness\Exercise\Repositories\Contracts;

use Fitness\Exercise\Models\Exercise;

/**
 * 健身項目的存取介面；所有查詢都限定在指定使用者底下。
 */
interface ExerciseRepository
{
    /**
     * 取得使用者自己的某個項目（含封存），不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findForUserOrFail(int $userId, int $id): Exercise;

    /**
     * 新增項目。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $attributes): Exercise;
}
