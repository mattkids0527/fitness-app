<?php

namespace Fitness\FoodAnalyzer\Repositories;

use Carbon\CarbonInterface;
use Fitness\FoodAnalyzer\Models\FoodAnalysis;
use Fitness\FoodAnalyzer\Repositories\Contracts\FoodAnalysisRepository as FoodAnalysisRepositoryContract;

/**
 * 以 Eloquent 讀寫 food_analyses。
 */
class FoodAnalysisRepository implements FoodAnalysisRepositoryContract
{
    /**
     * 時間以帶時區位移的字串綁定，避免以連線時區解讀。
     */
    public function countBetween(int $userId, CarbonInterface $from, CarbonInterface $to): int
    {
        return FoodAnalysis::where('user_id', $userId)
            ->where('created_at', '>=', $from->format('Y-m-d H:i:sP'))
            ->where('created_at', '<', $to->format('Y-m-d H:i:sP'))
            ->count();
    }

    /**
     * 以 user_id 與狀態一起查詢，確保只能取得自己成功的分析。
     */
    public function findSucceededForUser(int $userId, int $id): ?FoodAnalysis
    {
        return FoodAnalysis::where('user_id', $userId)
            ->where('status', FoodAnalysis::STATUS_SUCCEEDED)
            ->find($id);
    }

    /**
     * 寫入一筆分析紀錄。
     */
    public function create(int $userId, array $attributes): FoodAnalysis
    {
        return FoodAnalysis::create(['user_id' => $userId, ...$attributes]);
    }
}
