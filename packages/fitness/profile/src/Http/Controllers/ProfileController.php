<?php

namespace Fitness\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AppVersion;
use Fitness\Profile\Http\Controllers\Traits\HasProfileFormOptions;
use Fitness\Profile\Http\Requests\ProfileUpdateRequest;
use Fitness\Profile\Repositories\Contracts\ProfileRepository;
use Fitness\Profile\Repositories\ProfileRepository as EloquentProfileRepository;
use Fitness\Profile\Services\EnergyTargetCalculator;
use Fitness\Profile\Services\UserCalendar;
use Fitness\Profile\Services\WeightLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 「我的」頁的個人資料：顯示資料與 BMR／熱量目標，並可到編輯頁修改。
 */
class ProfileController extends Controller
{
    use HasProfileFormOptions;

    /**
     * 顯示個人資料、目前體重、BMR 與每日目標，以及設定清單「版本」列要顯示的目前版號。
     */
    public function show(Request $request, EnergyTargetCalculator $calculator, WeightLogService $weightLogs, AppVersion $version): Response
    {
        $user = $request->user();

        return Inertia::render('profile/Show', [
            'profile' => $this->profileOf($user),
            'targets' => $calculator->forUser($user)?->toArray(),
            'currentWeight' => $weightLogs->current($user),
            'options' => $this->profileFormOptions(),
            'appVersion' => $version->current()['version'] ?? null,
        ]);
    }

    /**
     * 顯示編輯個人資料表單；BMR 與目標預覽以目前體重計算。
     */
    public function edit(Request $request, WeightLogService $weightLogs): Response
    {
        $user = $request->user();

        return Inertia::render('profile/Edit', [
            'profile' => $this->profileOf($user),
            'currentWeight' => $weightLogs->current($user),
            'today' => UserCalendar::today($user)->toDateString(),
            'options' => $this->profileFormOptions(),
        ]);
    }

    /**
     * 儲存個人資料修改後回到「我的」。
     */
    public function update(ProfileUpdateRequest $request, ProfileRepository $profiles): RedirectResponse
    {
        $profiles->update($request->user(), $request->profileData());

        return redirect()->route('fitness.profile.show');
    }

    /**
     * 取出前端需要的 profile 欄位。
     *
     * @return array<string, mixed>
     */
    protected function profileOf(User $user): array
    {
        return Arr::only($user->toArray(), EloquentProfileRepository::FIELDS);
    }
}
