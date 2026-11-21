<?php

namespace Fitness\Exercise\Services;

use App\Models\User;
use Fitness\Exercise\Enums\RecordType;
use Fitness\Exercise\Exceptions\ExerciseCategoryNameTakenException;
use Fitness\Exercise\Exceptions\ExerciseCategoryNotEmptyException;
use Fitness\Exercise\Models\ExerciseCategory;
use Fitness\Exercise\Repositories\Contracts\ExerciseCategoryRepository;
use Fitness\Exercise\Repositories\Contracts\ExerciseRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * 分類的新增、改名、排序、刪除；名稱不分大小寫不可重複，記錄方式建立後不能改。
 */
class ExerciseCategoryService
{
    public function __construct(
        protected ExerciseCategoryRepository $categories,
        protected ExerciseRepository $exercises,
    ) {}

    /**
     * 使用者所有分類，依排序。
     *
     * @return Collection<int, ExerciseCategory>
     */
    public function all(User $user): Collection
    {
        return $this->categories->allForUser($user->id);
    }

    /**
     * 新增分類（排在最後）；同名時丟 ExerciseCategoryNameTakenException。
     *
     * @throws ExerciseCategoryNameTakenException
     */
    public function create(User $user, string $name, RecordType $type): ExerciseCategory
    {
        $name = trim($name);
        $this->ensureNameAvailable($user, $name);

        return $this->guardUnique(fn () => $this->categories->create($user->id, [
            'name' => $name,
            'record_type' => $type->value,
        ]));
    }

    /**
     * 分類改名；記錄方式不能改。
     *
     * @throws ExerciseCategoryNameTakenException
     */
    public function rename(User $user, int $categoryId, string $name): ExerciseCategory
    {
        $category = $this->categories->findForUserOrFail($user->id, $categoryId);
        $name = trim($name);
        $this->ensureNameAvailable($user, $name, $category->id);

        return $this->guardUnique(fn () => $this->categories->update($category, ['name' => $name]));
    }

    /**
     * 往上（-1）或往下（1）移一格；已在最前／最後時不動作。
     */
    public function move(User $user, int $categoryId, int $step): ExerciseCategory
    {
        $category = $this->categories->findForUserOrFail($user->id, $categoryId);
        $this->categories->move($category, $step);

        return $category;
    }

    /**
     * 刪除分類：還有未封存的項目時丟 ExerciseCategoryNotEmptyException；只剩封存項目時照常刪除，封存項目變成沒有分類。
     *
     * @throws ExerciseCategoryNotEmptyException
     */
    public function delete(User $user, int $categoryId): ExerciseCategory
    {
        $category = $this->categories->findForUserOrFail($user->id, $categoryId);
        $count = $this->exercises->activeCountInCategory($category->id);

        if ($count > 0) {
            throw new ExerciseCategoryNotEmptyException($category, $count);
        }

        $this->categories->delete($category);

        return $category;
    }

    /**
     * 先以程式檢查同名，讓使用者看到友善的錯誤訊息。
     *
     * @throws ExerciseCategoryNameTakenException
     */
    protected function ensureNameAvailable(User $user, string $name, ?int $exceptId = null): void
    {
        if ($this->categories->nameTaken($user->id, $name, $exceptId)) {
            throw new ExerciseCategoryNameTakenException;
        }
    }

    /**
     * 同時送出兩次時由唯一索引擋下，轉成同一個 Exception。
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     *
     * @throws ExerciseCategoryNameTakenException
     */
    protected function guardUnique(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw new ExerciseCategoryNameTakenException;
        }
    }
}
