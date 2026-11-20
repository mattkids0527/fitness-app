<?php

namespace Fitness\SocialLogin\Exceptions;

use Exception;

/**
 * 綁定／登入流程中「可以告訴使用者原因」的失敗；callback 捕捉後以 flash error 帶回頁面。
 */
abstract class SocialLoginException extends Exception
{
    /**
     * 對應 FitnessSocialLogin::errors 的語系鍵；$action 為 login 或 link。
     */
    abstract protected function langKey(string $action): string;

    /**
     * 組成前端錯誤提示框的標題與說明。
     *
     * @return array{title: string, message: string}
     */
    public function toFlash(string $action, string $providerLabel): array
    {
        $key = 'FitnessSocialLogin::errors.'.$this->langKey($action);

        return [
            'title' => trans($key.'.title', ['provider' => $providerLabel]),
            'message' => trans($key.'.message', ['provider' => $providerLabel]),
        ];
    }
}
