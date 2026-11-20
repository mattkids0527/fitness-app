<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * 讀取部署時寫入的版本資訊檔（version.json），提供設定頁顯示目前版本。
 * 由容器以 app(AppVersion::class) 取得，檔案路徑在 AppServiceProvider 綁定。
 */
class AppVersion
{
    /**
     * 指定版本資訊檔的路徑。
     */
    public function __construct(private readonly string $path) {}

    /**
     * 回傳目前版本；檔案不存在、內容不是 JSON 或版號格式錯誤時回傳 null，不讓設定頁因此出錯。
     *
     * @return array{version: string, commit: ?string, deployed_at: ?string}|null
     */
    public function current(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->path), true);
        if (! is_array($data) || ! is_string($data['version'] ?? null) || ! preg_match('/^v\d+\.\d+\.\d+$/', $data['version'])) {
            return null;
        }

        return [
            'version' => $data['version'],
            'commit' => $this->shortCommit($data['commit'] ?? null),
            'deployed_at' => $this->localTime($data['deployed_at'] ?? null),
        ];
    }

    /**
     * 把完整 commit sha 縮成 7 碼；不是 sha 格式時回傳 null。
     */
    private function shortCommit(mixed $commit): ?string
    {
        return is_string($commit) && preg_match('/^[0-9a-f]{7,40}$/i', $commit) ? strtolower(substr($commit, 0, 7)) : null;
    }

    /**
     * 把部署時間轉成應用程式時區的「年-月-日 時:分」；無法解析時回傳 null。
     */
    private function localTime(mixed $time): ?string
    {
        if (! is_string($time) || $time === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($time)->setTimezone(config('app.timezone'))->format('Y-m-d H:i');
        } catch (Throwable) {
            return null;
        }
    }
}
