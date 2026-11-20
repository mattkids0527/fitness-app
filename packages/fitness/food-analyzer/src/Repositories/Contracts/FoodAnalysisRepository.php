<?php

namespace Fitness\FoodAnalyzer\Repositories\Contracts;

use Carbon\CarbonInterface;
use Fitness\FoodAnalyzer\Models\FoodAnalysis;

/**
 * 照片分析紀錄的存取介面。
 */
interface FoodAnalysisRepository
{
    /**
     * 使用者在 [$from, $to) 之間送出的分析次數（含失敗）。
     */
    public function countBetween(int $userId, CarbonInterface $from, CarbonInterface $to): int;

    /**
     * 寫入一筆分析紀錄。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $attributes): FoodAnalysis;
}
