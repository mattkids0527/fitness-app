<?php

namespace Fitness\SocialLogin\Services;

use App\Models\User;
use Fitness\SocialLogin\Drivers\Contracts\SocialProvider;
use Fitness\SocialLogin\Drivers\SocialUser;
use Fitness\SocialLogin\Exceptions\ProviderAlreadyLinkedException;
use Fitness\SocialLogin\Exceptions\SocialAccountAlreadyLinkedException;
use Fitness\SocialLogin\Exceptions\SocialAccountNotLinkedException;
use Fitness\SocialLogin\Models\SocialAccount;
use Fitness\SocialLogin\Repositories\Contracts\SocialAccountRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 第三方帳號的綁定、登入與解除綁定規則。
 */
class SocialLoginService
{
    public function __construct(
        protected SocialAccountRepository $accounts,
        protected SocialProviderManager $providers,
    ) {}

    /**
     * 以第三方帳號登入綁定的使用者（一律記住我），並更新綁定紀錄上的 email／名稱／頭像。
     * 沒有綁定時拒絕，不以 email 比對、不建立帳號。
     *
     * @throws SocialAccountNotLinkedException
     */
    public function login(string $provider, SocialUser $socialUser): User
    {
        $account = $this->accounts->findByProviderUser($provider, $socialUser->id);

        if (! $account) {
            throw new SocialAccountNotLinkedException;
        }

        $this->accounts->updateProfile($account, $socialUser);
        Auth::guard('web')->login($account->user, remember: true);

        return $account->user;
    }

    /**
     * 把第三方帳號綁定到使用者；先檢查給出友善訊息，同時綁定的競態由唯一索引擋下後轉成相同的例外。
     *
     * @throws ProviderAlreadyLinkedException
     * @throws SocialAccountAlreadyLinkedException
     */
    public function link(User $user, string $provider, SocialUser $socialUser): SocialAccount
    {
        $this->ensureLinkable($user, $provider, $socialUser);

        try {
            // 包在交易（savepoint）裡，唯一鍵衝突時只回滾這次寫入，後續查詢不受影響
            return DB::transaction(fn () => $this->accounts->create($user->id, $provider, $socialUser));
        } catch (UniqueConstraintViolationException $e) {
            $this->ensureLinkable($user, $provider, $socialUser);

            throw new SocialAccountAlreadyLinkedException(previous: $e);
        }
    }

    /**
     * 解除使用者在某個平台的綁定，回傳是否有解除。
     */
    public function unlink(User $user, string $provider): bool
    {
        return $this->accounts->deleteForUser($user->id, $provider);
    }

    /**
     * 已連結帳號頁的資料：每個可用的 provider 附上使用者的綁定資訊（未綁定為 null）。
     *
     * @return list<array{key: string, label: string, account: array{email: ?string, name: ?string, avatar: ?string, linked_at: string}|null}>
     */
    public function connectedAccounts(User $user): array
    {
        $accounts = $this->accounts->allForUser($user->id);

        return collect($this->providers->available())
            ->map(function (SocialProvider $driver, string $provider) use ($accounts) {
                $account = $accounts->get($provider);

                return [
                    'key' => $provider,
                    'label' => $driver->label(),
                    'account' => $account ? [
                        'email' => $account->email,
                        'name' => $account->name,
                        'avatar' => $account->avatar,
                        'linked_at' => $account->created_at->toIso8601String(),
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 綁定前的檢查：自己已綁定過這個平台，或這組第三方帳號已綁定在別人身上。
     *
     * @throws ProviderAlreadyLinkedException
     * @throws SocialAccountAlreadyLinkedException
     */
    protected function ensureLinkable(User $user, string $provider, SocialUser $socialUser): void
    {
        if ($this->accounts->findForUser($user->id, $provider)) {
            throw new ProviderAlreadyLinkedException;
        }

        if ($this->accounts->findByProviderUser($provider, $socialUser->id)) {
            throw new SocialAccountAlreadyLinkedException;
        }
    }
}
