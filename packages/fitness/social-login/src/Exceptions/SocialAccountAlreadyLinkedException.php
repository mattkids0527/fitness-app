<?php

namespace Fitness\SocialLogin\Exceptions;

/**
 * 要綁定的第三方帳號已經綁定在其他使用者。
 */
class SocialAccountAlreadyLinkedException extends SocialLoginException
{
    /**
     * 錯誤提示的語系鍵。
     */
    protected function langKey(string $action): string
    {
        return 'account_taken';
    }
}
