<?php

use Fitness\Exercise\Services\DefaultExerciseService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 替上線前已完成首次設定、還沒有任何健身分類的使用者補建一次預設分類與項目；之後新使用者由 ProfileCompleted 事件建立。
     */
    public function up(): void
    {
        $service = app(DefaultExerciseService::class);

        DB::table('users')
            ->whereNotNull('onboarded_at')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('exercise_categories')
                ->whereColumn('exercise_categories.user_id', 'users.id'))
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $userId) => $service->createFor($userId));
    }

    /**
     * 不復原：補建的資料屬於使用者，回退資料表時會隨 exercise_categories 一併刪除。
     */
    public function down(): void
    {
        //
    }
};
