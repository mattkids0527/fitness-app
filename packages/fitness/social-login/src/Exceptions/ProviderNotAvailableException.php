<?php

namespace Fitness\SocialLogin\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 指定的 provider 沒有啟用、沒有 driver 或設定不齊全；對外視為不存在的網址（404）。
 */
class ProviderNotAvailableException extends NotFoundHttpException
{
    /**
     * 記下是哪個 provider，方便查 log。
     */
    public function __construct(string $provider)
    {
        parent::__construct("Social login provider [{$provider}] is not available.");
    }
}
