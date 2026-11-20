<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 設定頁「版本」分頁：讀取部署寫入的 version.json 顯示目前版本。
 * 測試改用暫存檔，不碰專案根目錄的 version.json。
 */
class VersionTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    /**
     * 每個測試使用獨立的暫存版本檔路徑，並讓容器改讀這個檔案。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'fitness-version-');
        @unlink($this->path);
        $this->app->instance(AppVersion::class, new AppVersion($this->path));
    }

    /**
     * 刪除測試寫入的暫存版本檔。
     */
    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_version_page_requires_login(): void
    {
        $this->get(route('version'))->assertRedirect(route('login'));
    }

    public function test_version_page_shows_deployed_version(): void
    {
        $this->writeVersionFile([
            'version' => 'v1.3.0',
            'commit' => 'D974E73A1B2C3D4E5F60718293A4B5C6D7E8F901',
            'deployed_at' => '2026-11-21T06:30:00Z',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('version'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Version')
                ->where('version', [
                    'version' => 'v1.3.0',
                    'commit' => 'd974e73',
                    'deployed_at' => '2026-11-21 14:30',
                ])
            );
    }

    public function test_version_page_without_version_file(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('version'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Version')
                ->where('version', null)
            );
    }

    public function test_invalid_version_file_is_ignored(): void
    {
        $user = User::factory()->create();

        foreach (['not json', json_encode(['version' => '1.3.0']), json_encode(['commit' => 'd974e73'])] as $content) {
            file_put_contents($this->path, $content);

            $this->actingAs($user)
                ->get(route('version'))
                ->assertInertia(fn (Assert $page) => $page->where('version', null));
        }
    }

    public function test_invalid_commit_and_time_are_omitted(): void
    {
        $this->writeVersionFile([
            'version' => 'v1.3.1',
            'commit' => 'not-a-sha',
            'deployed_at' => 'yesterday-ish',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('version'))
            ->assertInertia(fn (Assert $page) => $page->where('version', [
                'version' => 'v1.3.1',
                'commit' => null,
                'deployed_at' => null,
            ]));
    }

    /**
     * 寫入測試用的版本資訊檔。
     *
     * @param  array<string, string>  $data
     */
    private function writeVersionFile(array $data): void
    {
        file_put_contents($this->path, json_encode($data));
    }
}
