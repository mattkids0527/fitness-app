<?php

namespace Fitness\Exercise\Http\Controllers;

use App\Http\Controllers\Controller;
use Fitness\Exercise\Enums\RecordType;
use Fitness\Exercise\Http\Requests\ExerciseCategoryMoveRequest;
use Fitness\Exercise\Http\Requests\ExerciseCategoryRequest;
use Fitness\Exercise\Services\ExerciseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 分類管理：新增、改名、排序、刪除，只能操作自己的分類；都回到管理頁。
 */
class ExerciseCategoryController extends Controller
{
    public function __construct(protected ExerciseCategoryService $categories) {}

    /**
     * 新增分類（名稱＋記錄方式），排在最後。
     */
    public function store(ExerciseCategoryRequest $request): RedirectResponse
    {
        $category = $this->categories->create(
            $request->user(),
            $request->validated('name'),
            RecordType::from($request->validated('record_type')),
        );

        return back()->with('success', trans('FitnessExercise::messages.category_created', ['name' => $category->name]));
    }

    /**
     * 分類改名；記錄方式不能改。
     */
    public function update(ExerciseCategoryRequest $request, int $category): RedirectResponse
    {
        $updated = $this->categories->rename($request->user(), $category, $request->validated('name'));

        return back()->with('success', trans('FitnessExercise::messages.category_renamed', ['name' => $updated->name]));
    }

    /**
     * 往上或往下移一格。
     */
    public function move(ExerciseCategoryMoveRequest $request, int $category): RedirectResponse
    {
        $this->categories->move($request->user(), $category, $request->step());

        return back();
    }

    /**
     * 刪除分類；還有未封存的項目時由 ExerciseCategoryNotEmptyException 擋下。
     */
    public function destroy(Request $request, int $category): RedirectResponse
    {
        $deleted = $this->categories->delete($request->user(), $category);

        return back()->with('success', trans('FitnessExercise::messages.category_deleted', ['name' => $deleted->name]));
    }
}
