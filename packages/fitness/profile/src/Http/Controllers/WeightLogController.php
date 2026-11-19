<?php

namespace Fitness\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\Profile\Http\Requests\WeightLogStoreRequest;
use Fitness\Profile\Services\UserCalendar;
use Fitness\Profile\Services\WeightLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 體重紀錄：列表、新增、刪除，只能操作自己的紀錄。
 */
class WeightLogController extends Controller
{
    /**
     * 列出最新的體重紀錄；?page=N 表示顯示前 N 批（「載入更多」時 N 加一）。
     */
    public function index(Request $request, WeightLogService $weightLogs): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) config('fitness.profile.weight_logs_per_page');

        return Inertia::render('profile/WeightLogs', [
            'history' => $weightLogs->history($request->user(), $perPage * $page),
            'page' => $page,
            'today' => UserCalendar::today($request->user())->toDateString(),
        ]);
    }

    /**
     * 新增一筆體重紀錄。
     */
    public function store(WeightLogStoreRequest $request, WeightLogService $service): RedirectResponse
    {
        $service->record($request->user(), (float) $request->validated('weight_kg'), $request->validated('measured_on'));

        return back();
    }

    /**
     * 刪除自己的一筆體重紀錄；不是自己的紀錄回 404，最後一筆不能刪。
     */
    public function destroy(Request $request, int $weightLog, WeightLogService $service): RedirectResponse
    {
        $service->delete($request->user(), $weightLog);

        return back();
    }
}
