<?php

namespace Fitness\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\Profile\Http\Controllers\Traits\HasProfileFormOptions;
use Fitness\Profile\Http\Requests\ProfileUpdateRequest;
use Fitness\Profile\Repositories\Contracts\ProfileRepository;
use Fitness\Profile\Repositories\ProfileRepository as EloquentProfileRepository;
use Fitness\Profile\Services\EnergyTargetCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 「我的」頁的個人資料：顯示資料與 BMR／熱量目標，並可修改。
 */
class ProfileController extends Controller
{
    use HasProfileFormOptions;

    /**
     * 顯示個人資料、BMR 與每日目標。
     */
    public function show(Request $request, EnergyTargetCalculator $calculator): Response
    {
        $user = $request->user();

        return Inertia::render('profile/Show', [
            'profile' => Arr::only($user->toArray(), EloquentProfileRepository::FIELDS),
            'targets' => $calculator->forUser($user)?->toArray(),
            'options' => $this->profileFormOptions(),
        ]);
    }

    /**
     * 儲存個人資料修改。
     */
    public function update(ProfileUpdateRequest $request, ProfileRepository $profiles): RedirectResponse
    {
        $profiles->update($request->user(), $request->profileData());

        return redirect()->route('fitness.profile.show');
    }
}
