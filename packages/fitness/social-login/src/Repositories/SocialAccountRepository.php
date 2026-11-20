<?php

namespace Fitness\SocialLogin\Repositories;

use Fitness\SocialLogin\Drivers\SocialUser;
use Fitness\SocialLogin\Models\SocialAccount;
use Fitness\SocialLogin\Repositories\Contracts\SocialAccountRepository as SocialAccountRepositoryContract;
use Illuminate\Support\Collection;

/**
 * 以 Eloquent 讀寫 social_accounts。
 */
class SocialAccountRepository implements SocialAccountRepositoryContract
{
    /**
     * 以 (provider, provider_user_id) 唯一索引查詢，一併載入使用者。
     */
    public function findByProviderUser(string $provider, string $providerUserId): ?SocialAccount
    {
        return SocialAccount::with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }

    /**
     * 以 (user_id, provider) 唯一索引查詢。
     */
    public function findForUser(int $userId, string $provider): ?SocialAccount
    {
        return SocialAccount::where('user_id', $userId)->where('provider', $provider)->first();
    }

    /**
     * 取得使用者所有綁定紀錄，以 provider 為鍵。
     */
    public function allForUser(int $userId): Collection
    {
        return SocialAccount::where('user_id', $userId)->get()->keyBy('provider')->toBase();
    }

    /**
     * 新增一筆綁定。
     */
    public function create(int $userId, string $provider, SocialUser $socialUser): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $socialUser->id,
            ...$this->profileOf($socialUser),
        ]);
    }

    /**
     * 以第三方最新的資料更新 email、名稱與頭像。
     */
    public function updateProfile(SocialAccount $account, SocialUser $socialUser): void
    {
        $account->update($this->profileOf($socialUser));
    }

    /**
     * 以 user_id 一起刪除，確保只能刪自己的綁定。
     */
    public function deleteForUser(int $userId, string $provider): bool
    {
        return SocialAccount::where('user_id', $userId)->where('provider', $provider)->delete() > 0;
    }

    /**
     * 第三方資料中要保存的顯示欄位。
     *
     * @return array{email: ?string, name: ?string, avatar: ?string}
     */
    protected function profileOf(SocialUser $socialUser): array
    {
        return [
            'email' => $socialUser->email,
            'name' => $socialUser->name,
            'avatar' => $socialUser->avatar,
        ];
    }
}
