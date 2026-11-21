<?php

namespace Fitness\Exercise\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\Exercise\Http\Requests\ExerciseRequest;
use Fitness\Exercise\Models\Exercise;
use Fitness\Exercise\Models\ExerciseCategory;
use Fitness\Exercise\Services\BodyWeightResolver;
use Fitness\Exercise\Services\ExerciseCategoryService;
use Fitness\Exercise\Services\ExercisePresenter;
use Fitness\Exercise\Services\ExerciseService;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 分類與項目管理頁，以及項目的新增、編輯、刪除／封存、還原；只能操作自己的項目。
 */
class ExerciseController extends Controller
{
    public function __construct(
        protected ExerciseService $exercises,
        protected ExerciseCategoryService $categories,
        protected ExercisePresenter $presenter,
    ) {}

    /**
     * 分類與項目管理頁：分類依排序分組列出項目，最下面是已封存的項目。
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('exercise/Manage', [
            'categories' => $this->presenter->groups($this->categories->all($user), $this->exercises->active($user)),
            'archived' => $this->exercises->archived($user)->map(fn (Exercise $exercise) => $this->presenter->archived($exercise))->values(),
            'record_types' => $this->presenter->recordTypes(),
        ]);
    }

    /**
     * 新增項目頁；可由 ?category= 指定分類、?name= 帶入名稱（選擇項目面板搜尋不到時）。
     */
    public function create(Request $request, BodyWeightResolver $weights): Response
    {
        $user = $request->user();
        $categories = $this->categories->all($user);
        $category = $categories->firstWhere('id', (int) $request->query('category')) ?? $categories->first();

        return Inertia::render('exercise/Form', [
            ...$this->formProps($request, $weights, $categories),
            'exercise' => null,
            'defaults' => [
                'name' => mb_substr(trim((string) $request->query('name', '')), 0, 50),
                'category_id' => $category?->id,
            ],
        ]);
    }

    /**
     * 新增項目，回到管理頁。
     */
    public function store(ExerciseRequest $request): RedirectResponse
    {
        $exercise = $this->exercises->create($request->user(), $request->validated());

        return redirect()
            ->route('fitness.exercise.index')
            ->with('success', trans('FitnessExercise::messages.exercise_created', ['name' => $exercise->name]));
    }

    /**
     * 編輯自己未封存的項目；帶出紀錄筆數，決定刪除時是封存還是直接刪除。
     */
    public function edit(Request $request, BodyWeightResolver $weights, int $exercise): Response
    {
        $user = $request->user();
        $model = $this->exercises->findActive($user, $exercise);

        return Inertia::render('exercise/Form', [
            ...$this->formProps($request, $weights, $this->categories->all($user)),
            'exercise' => [...$this->presenter->exercise($model), 'log_count' => (int) $model->logs_count],
            'defaults' => null,
        ]);
    }

    /**
     * 更新自己的項目，回到管理頁。
     */
    public function update(ExerciseRequest $request, int $exercise): RedirectResponse
    {
        $updated = $this->exercises->update($request->user(), $exercise, $request->validated());

        return redirect()
            ->route('fitness.exercise.index')
            ->with('success', trans('FitnessExercise::messages.exercise_updated', ['name' => $updated->name]));
    }

    /**
     * 刪除項目：有紀錄時改成封存，沒有紀錄時直接刪除；回到管理頁。
     */
    public function destroy(Request $request, int $exercise): RedirectResponse
    {
        ['exercise' => $model, 'archived' => $archived] = $this->exercises->remove($request->user(), $exercise);
        $key = $archived ? 'exercise_archived' : 'exercise_deleted';

        return redirect()
            ->route('fitness.exercise.index')
            ->with('success', trans("FitnessExercise::messages.{$key}", ['name' => $model->name]));
    }

    /**
     * 還原封存的項目，回到管理頁。
     */
    public function restore(Request $request, int $exercise): RedirectResponse
    {
        $model = $this->exercises->restore($request->user(), $exercise);

        return back()->with('success', trans('FitnessExercise::messages.exercise_restored', ['name' => $model->name]));
    }

    /**
     * 新增與編輯表單共用的 props：分類、記錄方式、目前體重與估算係數。
     *
     * @param  Collection<int, ExerciseCategory>  $categories
     * @return array<string, mixed>
     */
    protected function formProps(Request $request, BodyWeightResolver $weights, Collection $categories): array
    {
        $user = $request->user();

        return [
            'categories' => $categories->map(fn (ExerciseCategory $category) => $this->presenter->category($category))->values(),
            'record_types' => $this->presenter->recordTypes(),
            'weight' => $this->presenter->weight($weights->on($user->id, UserCalendar::today($user))),
            'calculation' => $this->presenter->calculation(),
        ];
    }
}
