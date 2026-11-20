<?php

namespace Fitness\FoodAnalyzer\Drivers;

/**
 * 分析出的一個食物項目；欄位與 meal_items 相同，數值以整份計，營養素 null 表示不知道。
 */
class FoodItem
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $portion,
        public readonly ?float $grams,
        public readonly float $kcal,
        public readonly ?float $proteinG,
        public readonly ?float $carbsG,
        public readonly ?float $fatG,
        public readonly ?float $fiberG,
        public readonly float $confidence,
    ) {}

    /**
     * 從 snake_case 陣列建立（driver 解析回應、或從 food_analyses.result 讀回時使用），
     * 數值四捨五入到小數一位、負數歸零，信心度限制在 0～1。
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['name'] ?? '')),
            portion: self::text($data['portion'] ?? null),
            grams: self::amount($data['grams'] ?? null),
            kcal: self::amount($data['kcal'] ?? null) ?? 0.0,
            proteinG: self::amount($data['protein_g'] ?? null),
            carbsG: self::amount($data['carbs_g'] ?? null),
            fatG: self::amount($data['fat_g'] ?? null),
            fiberG: self::amount($data['fiber_g'] ?? null),
            confidence: round(min(1, max(0, (float) ($data['confidence'] ?? 0))), 2),
        );
    }

    /**
     * 轉成 snake_case 陣列，欄位名稱與 meal_items 相同。
     *
     * @return array{name: string, portion: ?string, grams: ?float, kcal: float, protein_g: ?float, carbs_g: ?float, fat_g: ?float, fiber_g: ?float, confidence: float}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'portion' => $this->portion,
            'grams' => $this->grams,
            'kcal' => $this->kcal,
            'protein_g' => $this->proteinG,
            'carbs_g' => $this->carbsG,
            'fat_g' => $this->fatG,
            'fiber_g' => $this->fiberG,
            'confidence' => $this->confidence,
        ];
    }

    /**
     * 文字欄位去頭尾空白，空字串視為 null。
     */
    protected static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * 數值欄位：非數字為 null，負數歸零，四捨五入到小數一位。
     */
    protected static function amount(mixed $value): ?float
    {
        return is_numeric($value) ? round(max(0, (float) $value), 1) : null;
    }
}
