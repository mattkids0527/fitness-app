<?php

namespace Fitness\Water\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\Water\Http\Requests\WaterLogStoreRequest;
use Fitness\Water\Services\WaterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 喝水紀錄的新增與刪除；沒有獨立頁面，操作後回到原頁面並以 toast 顯示結果。
 */
class WaterLogController extends Controller
{
    public function __construct(protected WaterService $water) {}

    /**
     * 記錄今天喝的水。
     */
    public function store(WaterLogStoreRequest $request): RedirectResponse
    {
        $amount = (int) $request->validated('amount_ml');
        $this->water->record($request->user(), $amount);

        return back()->with('success', trans('FitnessWater::messages.recorded', ['amount' => number_format($amount)]));
    }

    /**
     * 刪除自己今天的一筆紀錄。
     */
    public function destroy(Request $request, int $waterLog): RedirectResponse
    {
        $this->water->deleteToday($request->user(), $waterLog);

        return back()->with('success', trans('FitnessWater::messages.deleted'));
    }
}
