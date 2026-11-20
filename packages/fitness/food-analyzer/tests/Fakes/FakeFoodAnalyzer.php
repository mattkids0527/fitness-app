<?php

namespace Fitness\FoodAnalyzer\Tests\Fakes;

use Fitness\FoodAnalyzer\Drivers\FoodAnalysisResult;
use Fitness\FoodAnalyzer\Drivers\FoodImage;
use Fitness\FoodAnalyzer\Drivers\FoodImageAnalyzer;
use Fitness\FoodAnalyzer\Drivers\FoodItem;
use Throwable;

/**
 * 測試用 driver：不連外，回傳預先設定的結果或丟出預先設定的例外，並記錄被呼叫的次數與內容。
 * 沒有設定時回傳一份固定的便當結果。
 */
class FakeFoodAnalyzer extends FoodImageAnalyzer
{
    /**
     * 下一次分析回傳的結果；null 時用 defaultResult()。
     */
    public ?FoodAnalysisResult $nextResult = null;

    /**
     * 下一次分析要丟出的例外；優先於 nextResult。
     */
    public ?Throwable $nextException = null;

    /**
     * 每次呼叫收到的照片與補充說明。
     *
     * @var list<array{image: FoodImage, hint: ?string}>
     */
    public array $calls = [];

    public function __construct(
        protected string $model = 'fake-model',
        protected bool $configured = true,
    ) {}

    /**
     * 記錄呼叫後，丟出預設的例外或回傳預設的結果。
     */
    public function analyze(FoodImage $image, ?string $hint = null): FoodAnalysisResult
    {
        $this->calls[] = ['image' => $image, 'hint' => $hint];

        if ($this->nextException) {
            throw $this->nextException;
        }

        return $this->nextResult ?? self::defaultResult();
    }

    /**
     * 固定的提示詞，有補充說明時接在後面。
     */
    public function prompt(?string $hint = null): string
    {
        return '辨識照片中的食物。'.($hint === null ? '' : "\n補充說明：{$hint}");
    }

    /**
     * 模型 ID。
     */
    public function model(): string
    {
        return $this->model;
    }

    /**
     * 顯示用名稱。
     */
    public function label(): string
    {
        return 'Fake';
    }

    /**
     * 是否視為設定齊全。
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * 固定的分析結果：雞腿便當四個項目，信心度高、中、低都有。
     */
    public static function defaultResult(): FoodAnalysisResult
    {
        $items = [
            ['name' => '滷雞腿', 'portion' => '1 隻', 'grams' => 150, 'kcal' => 330, 'protein_g' => 28, 'carbs_g' => 4, 'fat_g' => 22, 'fiber_g' => 0, 'confidence' => 0.86],
            ['name' => '白飯', 'portion' => '1 碗', 'grams' => 200, 'kcal' => 280, 'protein_g' => 5, 'carbs_g' => 62, 'fat_g' => 0.6, 'fiber_g' => 0.6, 'confidence' => 0.9],
            ['name' => '炒高麗菜', 'portion' => '1 份', 'grams' => 80, 'kcal' => 60, 'protein_g' => 1.5, 'carbs_g' => 5, 'fat_g' => 4, 'fiber_g' => 2, 'confidence' => 0.68],
            ['name' => '滷蛋', 'portion' => '半顆', 'grams' => 25, 'kcal' => 38, 'protein_g' => 3, 'carbs_g' => 0.3, 'fat_g' => 2.6, 'fiber_g' => 0, 'confidence' => 0.42],
        ];

        return new FoodAnalysisResult(
            items: array_map(fn (array $item) => FoodItem::fromArray($item), $items),
            rawResponse: ['items' => $items],
            inputTokens: 1290,
            outputTokens: 320,
        );
    }
}
