<?php

namespace Fitness\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\FoodAnalyzer\Drivers\FoodImage;
use Fitness\FoodAnalyzer\Exceptions\FoodAnalyzerException;
use Fitness\FoodAnalyzer\Services\FoodAnalysisService;
use Fitness\Meal\Http\Requests\MealAnalyzeRequest;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 照片分析：分析頁與送出分析。照片只在這次請求的記憶體中轉交給分析服務，不寫入 Storage。
 */
class MealAnalysisController extends Controller
{
    public function __construct(protected FoodAnalysisService $analyzer) {}

    /**
     * 分析頁：?mode=camera（拍照）或 upload（上傳，預設），帶今天剩餘次數。
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('meal/Analyze', [
            'mode' => $request->query('mode') === 'camera' ? 'camera' : 'upload',
            'today' => UserCalendar::today($user)->toDateString(),
            'quota' => $this->quota($request),
            'hint_max_length' => (int) config('fitness.food_analyzer.hint_max_length'),
        ]);
    }

    /**
     * 送出照片分析：成功導向確認頁（新增餐點表單帶入分析結果，拍攝時間一併帶過去）；
     * 失敗回到分析頁，以 flash error 帶回失敗種類、說明與今天剩餘次數。
     */
    public function store(MealAnalyzeRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $analysis = $this->analyzer->analyze($user, FoodImage::fromUploadedFile($request->file('photo')), $request->validated('hint'));
        } catch (FoodAnalyzerException $e) {
            return back()->with('error', [...$e->toFlash(), 'remaining' => $this->analyzer->remainingToday($user)]);
        }

        return redirect()->route('fitness.meal.create', array_filter([
            'analysis' => $analysis->id,
            'taken_at' => $request->validated('taken_at'),
        ]));
    }

    /**
     * 今天剩餘次數與每日上限。
     *
     * @return array{remaining: int, limit: int}
     */
    protected function quota(Request $request): array
    {
        return [
            'remaining' => $this->analyzer->remainingToday($request->user()),
            'limit' => $this->analyzer->dailyLimit(),
        ];
    }
}
