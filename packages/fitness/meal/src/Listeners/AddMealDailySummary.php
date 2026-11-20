<?php

namespace Fitness\Meal\Listeners;

use Fitness\DailySummary\Events\DailySummaryCollecting;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Services\MealPresenter;
use Fitness\Meal\Services\MealService;
use Fitness\Meal\Services\NutritionCalculator;
use Fitness\Profile\Services\UserCalendar;

/**
 * 把當日攝取的熱量與營養素加總、今日餐點簡表加入今日頁摘要。
 */
class AddMealDailySummary
{
    public function __construct(
        protected MealService $meals,
        protected NutritionCalculator $nutrition,
        protected MealPresenter $presenter,
    ) {}

    /**
     * 加入 meal 區塊：totals、has_missing_nutrients、meals（依時間由早到晚）。
     */
    public function handle(DailySummaryCollecting $event): void
    {
        $timezone = UserCalendar::timezone($event->user);
        $meals = $this->meals->mealsOn($event->user, $event->date);
        $summary = $this->nutrition->summarize($meals->flatMap(fn (Meal $meal) => $meal->items));

        $event->addSection('meal', [
            'totals' => $summary['totals'],
            'has_missing_nutrients' => $summary['has_missing_nutrients'],
            'meals' => $meals->map(fn (Meal $meal) => $this->presenter->summary($meal, $timezone))->values()->all(),
        ]);
    }
}
