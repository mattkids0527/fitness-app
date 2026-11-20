<?php

namespace Fitness\SocialLogin\Exceptions;

/**
 * 第三方授權沒有完成：使用者按取消、state 不符、session 中沒有發起紀錄，或第三方回應錯誤。
 */
class SocialAuthFailedException extends SocialLoginException
{
    /**
     * 登入與綁定各有一組文案。
     */
    protected function langKey(string $action): string
    {
        return 'auth_failed.'.$action;
    }
}
