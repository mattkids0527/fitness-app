<?php

namespace Fitness\FoodAnalyzer\Drivers;

/**
 * driver 回傳的分析結果：食物項目，以及寫入 food_analyses 的原始回應與 token 用量。
 * 核心模組只認得這個格式，不依賴外部服務的型別。
 */
class FoodAnalysisResult
{
    /**
     * @param  list<FoodItem>  $items
     * @param  array<mixed>|string|null  $rawResponse  外部服務回傳的結果（不含照片），供除錯與調整提示詞
     */
    public function __construct(
        public readonly array $items,
        public readonly array|string|null $rawResponse = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {}

    /**
     * 項目轉成陣列，寫入 food_analyses.result。
     *
     * @return list<array<string, mixed>>
     */
    public function itemsToArray(): array
    {
        return array_map(fn (FoodItem $item) => $item->toArray(), $this->items);
    }
}
