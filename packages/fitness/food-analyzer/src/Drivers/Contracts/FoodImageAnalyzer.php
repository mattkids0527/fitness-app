<?php

namespace Fitness\FoodAnalyzer\Drivers\Contracts;

use Fitness\FoodAnalyzer\Drivers\FoodAnalysisResult;
use Fitness\FoodAnalyzer\Drivers\FoodImage;
use Fitness\FoodAnalyzer\Exceptions\FoodAnalyzerException;

/**
 * 食物照片分析 driver 的合約；driver 模組實作後綁定到容器 fitness.food_analyzer.<driver>。
 */
interface FoodImageAnalyzer
{
    /**
     * 分析一張餐點照片，回傳食物項目；$hint 為使用者的補充說明。
     * 失敗時只丟本模組的例外（看不出食物、次數限制、逾時、其他），不讓外部服務的例外往外傳。
     *
     * @throws FoodAnalyzerException
     */
    public function analyze(FoodImage $image, ?string $hint = null): FoodAnalysisResult;

    /**
     * 實際使用的模型 ID，寫入 food_analyses.model。
     */
    public function model(): string;

    /**
     * 顯示用名稱，例如「Gemini」。
     */
    public function label(): string;

    /**
     * 必要設定（例如 API 金鑰）是否齊全；不齊全時不呼叫外部服務。
     */
    public function isConfigured(): bool;
}
