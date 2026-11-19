<?php

namespace Fitness\Profile\Services;

use App\Models\User;
use Fitness\Profile\Repositories\Contracts\ProfileRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * 完成首次設定：寫入個人資料、記下第一筆體重、標記已完成，三者在同一個交易內。
 */
class OnboardingService
{
    public function __construct(
        protected ProfileRepository $profiles,
        protected WeightLogService $weightLogs,
    ) {}

    /**
     * 儲存首次設定表單；$data 需含 weight_kg 與 profile 欄位。
     *
     * @param  array<string, mixed>  $data
     */
    public function complete(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $this->profiles->update($user, Arr::except($data, ['weight_kg']));
            $this->weightLogs->record($user, (float) $data['weight_kg']);

            return $this->profiles->markOnboarded($user);
        });
    }
}
