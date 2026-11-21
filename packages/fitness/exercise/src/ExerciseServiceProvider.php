<?php

namespace Fitness\Exercise;

use Fitness\Exercise\Listeners\CreateDefaultExercises;
use Fitness\Profile\Events\ProfileCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * exercise 模組的進入點，於 bootstrap/providers.php 註冊後掛載設定、容器綁定、語系、migration 與事件。
 */
class ExerciseServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的設定、容器綁定、語系、migration 與事件。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->bindInstances();
        $this->registerSingletons();
        $this->setupLang();
        $this->setupMigrations();
        $this->setupEvents();
    }

    /**
     * 合併模組設定，讓 config('fitness.exercise.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/exercise.php', 'fitness.exercise');
    }

    /**
     * 依設定檔的 binds 綁定 Repository 介面與實作。
     */
    protected function bindInstances(): void
    {
        foreach (config('fitness.exercise.binds', []) as $interface => $target) {
            $this->app->bind($interface, $target);
        }
    }

    /**
     * 依設定檔的 singletons 註冊單例服務。
     */
    protected function registerSingletons(): void
    {
        foreach (config('fitness.exercise.singletons', []) as $abstract => $class) {
            $this->app->singleton($abstract, $class);
        }
    }

    /**
     * 載入模組語系，以 FitnessExercise:: 前綴取用。
     */
    protected function setupLang(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'FitnessExercise');
    }

    /**
     * 載入模組自己的 migration。
     */
    protected function setupMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
    }

    /**
     * 註冊此模組監聽的事件：使用者完成首次設定時建立預設分類與項目。
     */
    protected function setupEvents(): void
    {
        Event::listen(ProfileCompleted::class, CreateDefaultExercises::class);
    }
}
