<?php

namespace Fitness\SocialLogin\Drivers;

/**
 * driver 從第三方取回的使用者資料，核心模組只認得這個格式，不依賴第三方套件的型別。
 */
class SocialUser
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $email = null,
        public readonly ?string $name = null,
        public readonly ?string $avatar = null,
    ) {}
}
