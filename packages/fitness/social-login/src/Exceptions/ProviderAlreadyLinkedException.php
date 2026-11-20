<?php

namespace Fitness\SocialLogin\Exceptions;

/**
 * 使用者在這個平台已經綁定過一組帳號。
 */
class ProviderAlreadyLinkedException extends SocialLoginException
{
    /**
     * 錯誤提示的語系鍵。
     */
    protected function langKey(string $action): string
    {
        return 'provider_already_linked';
    }
}
