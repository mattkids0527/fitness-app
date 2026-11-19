<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Str;

use function Laravel\Prompts\password;

/**
 * 帳號指令共用的密碼取得方式：--generate-password 產生隨機密碼、--password 直接指定，否則互動輸入兩次確認。
 */
trait ResolvesPassword
{
    /**
     * 是否由指令產生密碼（產生的密碼只顯示一次）。
     */
    protected bool $passwordGenerated = false;

    /**
     * 依選項取得密碼；互動輸入兩次不一致時回傳 null。
     */
    protected function resolvePassword(): ?string
    {
        if ($this->option('generate-password')) {
            $this->passwordGenerated = true;

            return Str::password(16, symbols: false);
        }

        if ($this->option('password') !== null) {
            return (string) $this->option('password');
        }

        $password = password(label: '密碼', required: true);
        $confirmation = password(label: '再輸入一次密碼', required: true);

        if ($password !== $confirmation) {
            $this->error('兩次輸入的密碼不一致。');

            return null;
        }

        return $password;
    }

    /**
     * 產生的密碼只在這裡顯示一次，提醒使用者登入後到設定頁修改。
     */
    protected function showGeneratedPassword(string $password): void
    {
        if ($this->passwordGenerated) {
            $this->components->twoColumnDetail('密碼（只顯示這一次）', $password);
            $this->warn('請把密碼交給使用者，並請他登入後到「設定 › 密碼」修改。');
        }
    }
}
