<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立健身分類表：使用者自建，每個分類有一種記錄方式；同一使用者的名稱不分大小寫不可重複。
     */
    public function up(): void
    {
        Schema::create('exercise_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('record_type', 16);
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            $table->index(['user_id', 'position']);
        });

        // Schema Builder 不支援運算式索引，以 SQL 建立 (user_id, lower(name)) 唯一索引
        DB::statement('CREATE UNIQUE INDEX exercise_categories_user_id_lower_name_unique ON exercise_categories (user_id, lower(name))');
    }

    /**
     * 刪除健身分類表（索引隨表一併刪除）。
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_categories');
    }
};
