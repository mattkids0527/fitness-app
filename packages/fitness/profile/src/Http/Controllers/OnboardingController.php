<?php

namespace Fitness\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\Profile\Http\Controllers\Traits\HasProfileFormOptions;
use Fitness\Profile\Http\Requests\OnboardingRequest;
use Fitness\Profile\Services\OnboardingService;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 首次設定頁：填寫個人資料與目前體重，完成後才能使用其他頁面。
 */
class OnboardingController extends Controller
{
    use HasProfileFormOptions;

    /**
     * 顯示首次設定表單；已完成的使用者直接回今日頁。
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->onboarded_at !== null) {
            return redirect()->route('fitness.daily_summary.show');
        }

        return Inertia::render('profile/Onboarding', [
            'options' => $this->profileFormOptions(),
            'today' => UserCalendar::today($request->user())->toDateString(),
        ]);
    }

    /**
     * 儲存首次設定並進入今日頁。
     */
    public function store(OnboardingRequest $request, OnboardingService $onboarding): RedirectResponse
    {
        $onboarding->complete($request->user(), $request->profileData());

        return redirect()->route('fitness.daily_summary.show');
    }
}
