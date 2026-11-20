<?php

namespace Fitness\SocialLogin\Exceptions;

/**
 * 以第三方帳號登入，但這組第三方帳號沒有綁定任何使用者。
 */
class SocialAccountNotLinkedException extends SocialLoginException
{
    /**
     * 錯誤提示的語系鍵。
     */
    protected function langKey(string $action): string
    {
        return 'not_linked';
    }
}
