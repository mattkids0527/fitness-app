<?php

namespace Fitness\Profile\Repositories\Contracts;

use App\Models\User;

/**
 * 使用者個人資料（users 表上的 profile 欄位）的存取介面。
 */
interface ProfileRepository
{
    /**
     * 更新個人資料欄位，非 profile 欄位會被忽略。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User;

    /**
     * 標記使用者已完成首次設定。
     */
    public function markOnboarded(User $user): User;
}
