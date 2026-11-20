<?php

namespace Fitness\SocialLogin\Tests\Unit;

use Fitness\SocialLogin\Exceptions\ProviderNotAvailableException;
use Fitness\SocialLogin\Services\SocialProviderManager;
use Fitness\SocialLogin\Tests\Concerns\FakesSocialProviders;
use Tests\TestCase;

/**
 * driver Manager：依設定與容器綁定決定哪些 provider 可用。
 */
class SocialProviderManagerTest extends TestCase
{
    use FakesSocialProviders;

    protected function setUp(): void
    {
        parent::setUp();

        // 移除 Google driver 模組的綁定，模擬「沒有任何 driver」
        $this->app->offsetUnset('fitness.social_login.google');
    }

    public function test_manager_without_driver(): void
    {
        $manager = new SocialProviderManager;

        $this->assertSame([], $manager->available());
        $this->assertSame([], $manager->options());

        $this->expectException(ProviderNotAvailableException::class);
        $manager->get('google');
    }

    public function test_manager_excludes_unconfigured_or_disabled(): void
    {
        config(['fitness.social_login.providers' => ['google', 'line']]);
        $google = $this->fakeProvider('google');
        $this->fakeProvider('line', 'LINE', configured: false);
        $this->fakeProvider('github', 'GitHub');

        $manager = new SocialProviderManager;

        $this->assertSame(['google' => $google], $manager->available());
        $this->assertSame([['key' => 'google', 'label' => 'Google']], $manager->options());
        $this->assertSame($google, $manager->get('google'));

        foreach (['line', 'github'] as $provider) {
            try {
                $manager->get($provider);
                $this->fail("{$provider} 不應可用");
            } catch (ProviderNotAvailableException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
