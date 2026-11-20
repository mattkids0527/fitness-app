<?php

namespace Fitness\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\Meal\Http\Requests\SavedFoodRequest;
use Fitness\Meal\Services\MealPresenter;
use Fitness\Meal\Services\SavedFoodService;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 常用食物管理：列表、新增、修改、刪除，只能操作自己的常用食物。
 */
class SavedFoodController extends Controller
{
    public function __construct(protected SavedFoodService $savedFoods) {}

    /**
     * 常用食物管理頁，最近使用的在前。
     */
    public function index(Request $request, MealPresenter $presenter): Response
    {
        $user = $request->user();
        $timezone = UserCalendar::timezone($user);

        return Inertia::render('meal/SavedFoods', [
            'today' => UserCalendar::today($user)->toDateString(),
            'saved_foods' => $this->savedFoods->all($user)->map(fn ($food) => $presenter->savedFood($food, $timezone))->values(),
        ]);
    }

    /**
     * 新增常用食物（管理頁手動新增，或在餐點表單按 ☆），回到原頁面。
     */
    public function store(SavedFoodRequest $request): RedirectResponse
    {
        $food = $this->savedFoods->create($request->user(), $request->validated());

        return back()->with('success', trans('FitnessMeal::messages.saved_food_created', ['name' => $food->name]));
    }

    /**
     * 修改自己的常用食物。
     */
    public function update(SavedFoodRequest $request, int $savedFood): RedirectResponse
    {
        $food = $this->savedFoods->update($request->user(), $savedFood, $request->validated());

        return back()->with('success', trans('FitnessMeal::messages.saved_food_updated', ['name' => $food->name]));
    }

    /**
     * 刪除自己的常用食物；已記錄的餐點不受影響。
     */
    public function destroy(Request $request, int $savedFood): RedirectResponse
    {
        $food = $this->savedFoods->delete($request->user(), $savedFood);

        return back()->with('success', trans('FitnessMeal::messages.saved_food_deleted', ['name' => $food->name]));
    }
}
