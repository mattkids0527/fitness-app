<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesPassword;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * 重設指定帳號的密碼（目前沒有寄信設定，取代忘記密碼信）：php artisan user:password {email} [--password=|--generate-password]。
 */
class ResetUserPasswordCommand extends Command
{
    use ResolvesPassword;

    protected $signature = 'user:password
        {email : 要重設密碼的帳號 Email}
        {--password= : 新密碼；不給且未指定 --generate-password 時以互動方式輸入}
        {--generate-password : 產生隨機密碼並只顯示一次}';

    protected $description = '重設指定帳號的密碼';

    /**
     * 找到帳號、驗證新密碼後更新。
     */
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("找不到帳號 {$email}。");

            return self::FAILURE;
        }

        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password])->save();

        $this->info("已重設 {$user->email} 的密碼。");
        $this->showGeneratedPassword($password);

        return self::SUCCESS;
    }
}
