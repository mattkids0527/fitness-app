<?php

namespace Fitness\FoodAnalyzerGemini;

use Fitness\FoodAnalyzerGemini\Drivers\Gemini;
use Illuminate\Support\ServiceProvider;

/**
 * food-analyzer-gemini 模組的進入點：合併 Gemini 設定，並把 Gemini driver 綁定到 fitness.food_analyzer.gemini。
 */
class FoodAnalyzerGeminiServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組設定與 driver 綁定。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->registerDriver();
    }

    /**
     * 合併模組設定，讓 config('fitness.food_analyzer_gemini.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/food-analyzer-gemini.php', 'fitness.food_analyzer_gemini');
    }

    /**
     * 綁定 Gemini driver，food-analyzer 的 Manager 以別名 gemini 取得。
     */
    protected function registerDriver(): void
    {
        $this->app->bind('fitness.food_analyzer.gemini', Gemini::class);
    }
}
