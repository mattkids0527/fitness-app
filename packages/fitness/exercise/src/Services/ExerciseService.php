<?php

namespace Fitness\Exercise\Services;

use App\Models\User;
use Fitness\Exercise\Exceptions\ExerciseCategoryTypeMismatchException;
use Fitness\Exercise\Exceptions\ExerciseNameTakenException;
use Fitness\Exercise\Exceptions\ExerciseRestoreNameTakenException;
use Fitness\Exercise\Exceptions\ExerciseRestoreNoCategoryException;
use Fitness\Exercise\Models\Exercise;
use Fitness\Exercise\Models\ExerciseCategory;
use Fitness\Exercise\Repositories\Contracts\ExerciseCategoryRepository;
use Fitness\Exercise\Repositories\Contracts\ExerciseRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 項目的新增、編輯、刪除／封存、還原。未封存的項目名稱不分大小寫不可重複；項目只能移到同記錄方式的分類。
 * 逐組項目一定有每下秒數與組間休息，填時長項目一定沒有（Exercise::recordType() 依此判斷沒有分類的封存項目）。
 */
class ExerciseService
{
    public function __construct(
        protected ExerciseRepository $exercises,
        protected ExerciseCategoryRepository $categories,
    ) {}

    /**
     * 使用者未封存的項目（含分類）。
     *
     * @return Collection<int, Exercise>
     */
    public function active(User $user): Collection
    {
        return $this->exercises->activeForUser($user->id);
    }

    /**
     * 使用者已封存的項目（含分類與紀錄筆數）。
     *
     * @return Collection<int, Exercise>
     */
    public function archived(User $user): Collection
    {
        return $this->exercises->archivedForUser($user->id);
    }

    /**
     * 取得自己未封存的項目（編輯用）。
     */
    public function findActive(User $user, int $exerciseId): Exercise
    {
        return $this->exercises->findActiveForUserOrFail($user->id, $exerciseId);
    }

    /**
     * 新增項目到自己的分類；同名時丟 ExerciseNameTakenException。
     *
     * @param  array<string, mixed>  $data  已驗證的表單資料（category_id 已確認屬於使用者）
     *
     * @throws ExerciseNameTakenException
     */
    public function create(User $user, array $data): Exercise
    {
        $category = $this->categories->findForUserOrFail($user->id, (int) $data['category_id']);
        $attributes = $this->attributes($category, $data);
        $this->ensureNameAvailable($user, $attributes['name']);

        return $this->guardUnique(fn () => $this->exercises->create($user->id, $attributes));
    }

    /**
     * 編輯自己未封存的項目；移到不同記錄方式的分類時丟 ExerciseCategoryTypeMismatchException。
     *
     * @param  array<string, mixed>  $data  已驗證的表單資料
     *
     * @throws ExerciseNameTakenException
     * @throws ExerciseCategoryTypeMismatchException
     */
    public function update(User $user, int $exerciseId, array $data): Exercise
    {
        $exercise = $this->exercises->findActiveForUserOrFail($user->id, $exerciseId);
        $category = $this->categories->findForUserOrFail($user->id, (int) $data['category_id']);
        $type = $exercise->recordType();

        if ($category->record_type !== $type) {
            throw new ExerciseCategoryTypeMismatchException($type);
        }

        $attributes = $this->attributes($category, $data);
        $this->ensureNameAvailable($user, $attributes['name'], $exercise->id);

        return $this->guardUnique(fn () => $this->exercises->update($exercise, $attributes));
    }

    /**
     * 刪除自己未封存的項目：已有紀錄時改成封存（歷史紀錄照常顯示），沒有紀錄時直接刪除。
     *
     * @return array{exercise: Exercise, archived: bool}
     */
    public function remove(User $user, int $exerciseId): array
    {
        $exercise = $this->exercises->findActiveForUserOrFail($user->id, $exerciseId);

        if ($this->exercises->hasLogs($exercise)) {
            $this->exercises->update($exercise, ['archived_at' => Carbon::now()]);

            return ['exercise' => $exercise, 'archived' => true];
        }

        $this->exercises->delete($exercise);

        return ['exercise' => $exercise, 'archived' => false];
    }

    /**
     * 還原自己的封存項目；原分類已刪除時放回同記錄方式的第一個分類。
     *
     * @throws ExerciseRestoreNameTakenException 已有同名的未封存項目
     * @throws ExerciseRestoreNoCategoryException 沒有同記錄方式的分類可以放
     */
    public function restore(User $user, int $exerciseId): Exercise
    {
        $exercise = $this->exercises->findArchivedForUserOrFail($user->id, $exerciseId);

        if ($this->exercises->nameTaken($user->id, $exercise->name, $exercise->id)) {
            throw new ExerciseRestoreNameTakenException($exercise);
        }

        $category = $exercise->category;

        if ($category === null) {
            $type = $exercise->recordType();
            $category = $this->categories->firstOfType($user->id, $type)
                ?? throw new ExerciseRestoreNoCategoryException($exercise, $type);
        }

        try {
            return $this->exercises->update($exercise, ['archived_at' => null, 'category_id' => $category->id]);
        } catch (UniqueConstraintViolationException) {
            throw new ExerciseRestoreNameTakenException($exercise);
        }
    }

    /**
     * 依分類的記錄方式整理要寫入的欄位：填時長項目不存每下秒數與組間休息。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(ExerciseCategory $category, array $data): array
    {
        $isSets = $category->record_type->isSets();
        $description = trim((string) ($data['description'] ?? ''));

        return [
            'category_id' => $category->id,
            'name' => trim($data['name']),
            'met' => round((float) $data['met'], 1),
            'sec_per_rep' => $isSets ? (int) $data['sec_per_rep'] : null,
            'rest_sec' => $isSets ? (int) $data['rest_sec'] : null,
            'description' => $description === '' ? null : $description,
        ];
    }

    /**
     * 先以程式檢查同名，讓使用者看到友善的錯誤訊息。
     *
     * @throws ExerciseNameTakenException
     */
    protected function ensureNameAvailable(User $user, string $name, ?int $exceptId = null): void
    {
        if ($this->exercises->nameTaken($user->id, $name, $exceptId)) {
            throw new ExerciseNameTakenException;
        }
    }

    /**
     * 同時送出兩次時由部分唯一索引擋下，轉成同一個 Exception。
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     *
     * @throws ExerciseNameTakenException
     */
    protected function guardUnique(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw new ExerciseNameTakenException;
        }
    }
}
