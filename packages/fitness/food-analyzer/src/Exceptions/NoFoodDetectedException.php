<?php

namespace Fitness\FoodAnalyzer\Exceptions;

/**
 * 照片裡看不出食物；重試同一張沒有意義，畫面引導重拍或換一張。
 */
class NoFoodDetectedException extends FoodAnalyzerException
{
    /**
     * 失敗種類。
     */
    public function errorCode(): string
    {
        return 'no_food';
    }
}
