<?php

namespace Fitness\LlmLog;

use Illuminate\Support\ServiceProvider;

/**
 * llm-log 模組的進入點，於 bootstrap/providers.php 註冊後掛載設定、容器綁定與 migration。
 * 其他模組呼叫 LLM API 後，透過 LlmLogRepository 介面寫入紀錄。
 */
class LlmLogServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的設定、容器綁定與 migration。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->bindInstances();
        $this->setupMigrations();
    }

    /**
     * 合併模組設定，讓 config('fitness.llm_log.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/llm-log.php', 'fitness.llm_log');
    }

    /**
     * 依設定檔的 binds 綁定 Repository 介面與實作。
     */
    protected function bindInstances(): void
    {
        foreach (config('fitness.llm_log.binds', []) as $interface => $target) {
            $this->app->bind($interface, $target);
        }
    }

    /**
     * 載入模組自己的 migration。
     */
    protected function setupMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
    }
}
