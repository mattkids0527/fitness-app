<?php

namespace Fitness\SocialLogin\Repositories\Contracts;

use Fitness\SocialLogin\Drivers\SocialUser;
use Fitness\SocialLogin\Models\SocialAccount;
use Illuminate\Support\Collection;

/**
 * 第三方帳號綁定的存取介面。
 */
interface SocialAccountRepository
{
    /**
     * 以第三方帳號找綁定紀錄（連同綁定的使用者）。
     */
    public function findByProviderUser(string $provider, string $providerUserId): ?SocialAccount;

    /**
     * 取得使用者在某個平台的綁定紀錄。
     */
    public function findForUser(int $userId, string $provider): ?SocialAccount;

    /**
     * 取得使用者所有綁定紀錄，以 provider 為鍵。
     *
     * @return Collection<string, SocialAccount>
     */
    public function allForUser(int $userId): Collection;

    /**
     * 新增一筆綁定；違反唯一索引時丟 UniqueConstraintViolationException。
     */
    public function create(int $userId, string $provider, SocialUser $socialUser): SocialAccount;

    /**
     * 以第三方最新的資料更新 email、名稱與頭像。
     */
    public function updateProfile(SocialAccount $account, SocialUser $socialUser): void;

    /**
     * 刪除使用者在某個平台的綁定，回傳是否有刪到。
     */
    public function deleteForUser(int $userId, string $provider): bool;
}
