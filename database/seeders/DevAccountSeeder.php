<?php

namespace Database\Seeders;

use App\Models\User;
use Fitness\Profile\Services\OnboardingService;
use Illuminate\Database\Seeder;

/**
 * 本地開發用的固定帳號：帳密讀 .env 的 DEV_ACCOUNT_*（不進 git），只在 APP_ENV=local 建立。
 * 重複執行時以 email 找到同一個帳號，名稱與密碼改回 .env 的值；尚未完成首次設定時填入範例資料。
 */
class DevAccountSeeder extends Seeder
{
    /**
     * 首次設定的範例資料，登入後可直接進今日頁。
     *
     * @var array<string, mixed>
     */
    const SAMPLE_PROFILE = [
        'gender' => 'male',
        'birth_date' => '1990-01-01',
        'height_cm' => 175,
        'weight_kg' => 75,
        'activity_level' => 'light',
        'goal' => 'maintain',
        'goal_rate_kg_per_week' => null,
        'bmr_override' => null,
        'timezone' => 'Asia/Taipei',
    ];

    /**
     * 建立或更新開發帳號；不是本地環境或 .env 沒填時略過。
     */
    public function run(OnboardingService $onboarding): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('略過開發帳號：只在 APP_ENV=local 建立。');

            return;
        }

        $account = config('auth.dev_account');

        if (blank($account['email']) || blank($account['password'])) {
            $this->command?->warn('略過開發帳號：.env 的 DEV_ACCOUNT_EMAIL、DEV_ACCOUNT_PASSWORD 沒填。');

            return;
        }

        $user = User::firstOrNew(['email' => strtolower($account['email'])]);
        $user->forceFill([
            'name' => $account['name'] ?: 'Developer',
            'password' => $account['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if (! $user->onboarded_at) {
            $onboarding->complete($user, self::SAMPLE_PROFILE);
        }

        $this->command?->info("開發帳號 {$user->email}（ID {$user->id}）已就緒。");
    }
}
