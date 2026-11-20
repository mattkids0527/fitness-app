<?php

namespace Fitness\SocialLogin\Services;

use Fitness\SocialLogin\Drivers\Contracts\SocialProvider;
use Fitness\SocialLogin\Exceptions\ProviderNotAvailableException;

/**
 * 以別名取得第三方登入 driver（容器 fitness.social_login.<provider>），並列出目前可用的 provider。
 * 核心模組不知道有哪些 driver：啟用哪些由設定 providers 決定，driver 由各自的模組綁定。
 */
class SocialProviderManager
{
    /**
     * driver 在容器中的綁定前綴。
     */
    const BINDING_PREFIX = 'fitness.social_login.';

    /**
     * 已建立的 driver 實例，以別名為鍵。
     *
     * @var array<string, SocialProvider>
     */
    protected array $drivers = [];

    /**
     * 取得可用的 driver；未啟用、沒有 driver 或設定不齊全時丟 404 例外。
     *
     * @throws ProviderNotAvailableException
     */
    public function get(string $provider): SocialProvider
    {
        $driver = $this->resolve($provider);

        if (! $driver?->isConfigured()) {
            throw new ProviderNotAvailableException($provider);
        }

        return $driver;
    }

    /**
     * 列出已啟用、有 driver 且設定齊全的 provider，依設定順序，以別名為鍵。
     *
     * @return array<string, SocialProvider>
     */
    public function available(): array
    {
        $available = [];

        foreach ($this->enabled() as $provider) {
            $driver = $this->resolve($provider);

            if ($driver?->isConfigured()) {
                $available[$provider] = $driver;
            }
        }

        return $available;
    }

    /**
     * 給前端顯示按鈕用的清單。
     *
     * @return list<array{key: string, label: string}>
     */
    public function options(): array
    {
        return collect($this->available())
            ->map(fn (SocialProvider $driver, string $provider) => ['key' => $provider, 'label' => $driver->label()])
            ->values()
            ->all();
    }

    /**
     * 設定中啟用的 provider 別名。
     *
     * @return list<string>
     */
    protected function enabled(): array
    {
        return config('fitness.social_login.providers', []);
    }

    /**
     * 建立（並快取）已啟用且容器中有綁定的 driver，否則回傳 null。
     */
    protected function resolve(string $provider): ?SocialProvider
    {
        if (! in_array($provider, $this->enabled(), true) || ! app()->bound(self::BINDING_PREFIX.$provider)) {
            return null;
        }

        return $this->drivers[$provider] ??= app(self::BINDING_PREFIX.$provider);
    }
}
