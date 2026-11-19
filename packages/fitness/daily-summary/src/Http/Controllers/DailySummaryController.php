<?php

namespace Fitness\DailySummary\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Fitness\DailySummary\Events\DailySummaryCollecting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 今日頁：發出收集型事件取得各模組的當日摘要後顯示。
 */
class DailySummaryController extends Controller
{
    /**
     * 以使用者時區的今天收集摘要並顯示今日頁。
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $date = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->startOfDay();

        $event = new DailySummaryCollecting($user, $date);
        Event::dispatch($event);

        return Inertia::render('daily-summary/Today', [
            'date' => $date->toDateString(),
            'sections' => (object) $event->getSections(),
        ]);
    }
}
