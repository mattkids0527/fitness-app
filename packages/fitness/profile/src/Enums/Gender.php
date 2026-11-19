<?php

namespace Fitness\Profile\Enums;

use Fitness\Profile\Enums\Concerns\HasOptions;

/**
 * 生理性別，決定 BMR 公式的常數項。
 */
enum Gender: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';

    /**
     * 取得中文名稱。
     */
    public function label(): string
    {
        return trans('FitnessProfile::options.gender.'.$this->value);
    }
}
