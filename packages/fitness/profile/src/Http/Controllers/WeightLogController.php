<?php

namespace Fitness\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\Profile\Http\Requests\WeightLogStoreRequest;
use Fitness\Profile\Repositories\Contracts\WeightLogRepository;
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
     * 分頁列出體重紀錄，新的在前。
     */
    public function index(Request $request, WeightLogRepository $weightLogs): Response
    {
        return Inertia::render('profile/WeightLogs', [
            'weightLogs' => $weightLogs->paginateForUser($request->user()->id, config('fitness.profile.weight_logs_per_page')),
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
