<?php

namespace Fitness\FoodAnalyzer\Exceptions;

use Exception;

/**
 * 照片分析中「可以告訴使用者原因」的失敗；每種原因一個子類別。
 * driver 已經拿到回應才失敗時（例如看不出食物），以 withResponse() 附上原始回應與 token 用量，一併寫入紀錄。
 */
abstract class FoodAnalyzerException extends Exception
{
    /**
     * 外部服務回傳的結果（不含照片）。
     *
     * @var array<mixed>|string|null
     */
    public array|string|null $rawResponse = null;

    public ?int $inputTokens = null;

    public ?int $outputTokens = null;

    /**
     * driver 的顯示名稱（例如 Gemini），由分析服務在往外丟之前填入，文案以 :provider 取用。
     */
    public string $providerLabel = '';

    /**
     * 失敗種類，寫入 food_analyses.error，也是 FitnessFoodAnalyzer::errors 的語系鍵。
     */
    abstract public function errorCode(): string;

    /**
     * 附上外部服務的原始回應與 token 用量。
     *
     * @param  array<mixed>|string|null  $rawResponse
     */
    public function withResponse(array|string|null $rawResponse, ?int $inputTokens = null, ?int $outputTokens = null): static
    {
        $this->rawResponse = $rawResponse;
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;

        return $this;
    }

    /**
     * 組成前端錯誤提示的失敗種類、標題與說明。
     *
     * @return array{code: string, title: string, message: string}
     */
    public function toFlash(): array
    {
        $key = 'FitnessFoodAnalyzer::errors.'.$this->errorCode();
        $replace = ['provider' => $this->providerLabel, ...$this->langReplace()];

        return [
            'code' => $this->errorCode(),
            'title' => trans($key.'.title', $replace),
            'message' => trans($key.'.message', $replace),
        ];
    }

    /**
     * 語系文字的額外替換值。
     *
     * @return array<string, mixed>
     */
    protected function langReplace(): array
    {
        return [];
    }
}
