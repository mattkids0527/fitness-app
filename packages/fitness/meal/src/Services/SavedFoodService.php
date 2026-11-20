<?php

namespace Fitness\Meal\Services;

use App\Models\User;
use Fitness\Meal\Exceptions\SavedFoodNameTakenException;
use Fitness\Meal\Models\SavedFood;
use Fitness\Meal\Repositories\Contracts\SavedFoodRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * 常用食物的新增、修改、刪除；名稱不分大小寫不可重複。
 */
class SavedFoodService
{
    public function __construct(protected SavedFoodRepository $savedFoods) {}

    /**
     * 取得使用者所有常用食物，最近使用的在前。
     *
     * @return Collection<int, SavedFood>
     */
    public function all(User $user): Collection
    {
        return $this->savedFoods->allForUser($user->id);
    }

    /**
     * 新增常用食物；同名時丟 SavedFoodNameTakenException。
     *
     * @param  array<string, mixed>  $data  已驗證的表單資料
     *
     * @throws SavedFoodNameTakenException
     */
    public function create(User $user, array $data): SavedFood
    {
        $this->ensureNameAvailable($user, $data['name']);

        return $this->guardUnique(fn () => $this->savedFoods->create($user->id, $data));
    }

    /**
     * 修改自己的常用食物；改成其他項目已使用的名稱時丟 SavedFoodNameTakenException。
     *
     * @param  array<string, mixed>  $data  已驗證的表單資料
     *
     * @throws SavedFoodNameTakenException
     */
    public function update(User $user, int $savedFoodId, array $data): SavedFood
    {
        $savedFood = $this->savedFoods->findForUserOrFail($user->id, $savedFoodId);
        $this->ensureNameAvailable($user, $data['name'], $savedFood->id);

        return $this->guardUnique(fn () => $this->savedFoods->update($savedFood, $data));
    }

    /**
     * 刪除自己的常用食物；已記錄的餐點不受影響。
     */
    public function delete(User $user, int $savedFoodId): SavedFood
    {
        $savedFood = $this->savedFoods->findForUserOrFail($user->id, $savedFoodId);
        $this->savedFoods->delete($savedFood);

        return $savedFood;
    }

    /**
     * 先以程式檢查同名，讓使用者看到友善的錯誤訊息。
     *
     * @throws SavedFoodNameTakenException
     */
    protected function ensureNameAvailable(User $user, string $name, ?int $exceptId = null): void
    {
        if ($this->savedFoods->nameTaken($user->id, $name, $exceptId)) {
            throw new SavedFoodNameTakenException;
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
     * @throws SavedFoodNameTakenException
     */
    protected function guardUnique(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw new SavedFoodNameTakenException;
        }
    }
}
