<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesPassword;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\text;

/**
 * 建立登入帳號（第一版不開放註冊）：php artisan user:create --name= --email= [--password=|--generate-password]，缺的欄位以互動方式詢問。
 */
class CreateUserCommand extends Command
{
    use ResolvesPassword;

    protected $signature = 'user:create
        {--name= : 姓名}
        {--email= : Email（登入帳號）}
        {--password= : 密碼；不給且未指定 --generate-password 時以互動方式輸入}
        {--generate-password : 產生隨機密碼並只顯示一次}';

    protected $description = '建立登入帳號（Email 視為已驗證，登入後需先完成首次設定）';

    /**
     * 收集並驗證資料後建立帳號。
     */
    public function handle(): int
    {
        $name = $this->option('name') ?? text(label: '姓名', required: true);
        $email = Str::lower(trim((string) ($this->option('email') ?? text(label: 'Email', required: true))));
        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("已建立帳號 {$user->email}（ID {$user->id}）。");
        $this->showGeneratedPassword($password);

        return self::SUCCESS;
    }
}
