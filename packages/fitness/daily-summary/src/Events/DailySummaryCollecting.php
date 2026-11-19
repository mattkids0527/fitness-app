<?php

namespace Fitness\DailySummary\Events;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * 收集型事件：今日頁發出後，各模組以 addSection() 加入自己的當日摘要，再由今日頁以 getSections() 取回。
 */
class DailySummaryCollecting
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sections = [];

    public function __construct(
        public readonly User $user,
        public readonly CarbonImmutable $date,
    ) {}

    /**
     * 讓各模組加入自己的摘要區塊，key 建議用模組名稱。
     *
     * @param  array<string, mixed>  $data
     */
    public function addSection(string $key, array $data): self
    {
        $this->sections[$key] = $data;

        return $this;
    }

    /**
     * 取回所有模組加入的摘要區塊。
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}
