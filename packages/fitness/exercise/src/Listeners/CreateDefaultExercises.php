<?php

namespace Fitness\Exercise\Listeners;

use Fitness\Exercise\Services\DefaultExerciseService;
use Fitness\Profile\Events\ProfileCompleted;

/**
 * 監聽 profile 的 ProfileCompleted：使用者完成首次設定時建立預設分類與項目。
 */
class CreateDefaultExercises
{
    public function __construct(
        protected DefaultExerciseService $defaults,
    ) {}

    /**
     * 替剛完成首次設定的使用者建立預設資料。
     */
    public function handle(ProfileCompleted $event): void
    {
        $this->defaults->createFor($event->user->id);
    }
}
