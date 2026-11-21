<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立健身項目表：記錄方式沿用所屬分類；有紀錄的項目刪除時改為封存，分類被刪時 category_id 設為 null。
     */
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('exercise_categories')->nullOnDelete();
            $table->string('name', 50);
            $table->decimal('met', 4, 1);
            $table->unsignedSmallInteger('sec_per_rep')->nullable();
            $table->unsignedSmallInteger('rest_sec')->nullable();
            $table->string('description', 300)->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'category_id']);
        });

        // 只有未封存的項目名稱不可重複（不分大小寫），封存後可再建立同名項目
        DB::statement('CREATE UNIQUE INDEX exercises_user_id_lower_name_active_unique ON exercises (user_id, lower(name)) WHERE archived_at IS NULL');
    }

    /**
     * 刪除健身項目表（索引隨表一併刪除）。
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
