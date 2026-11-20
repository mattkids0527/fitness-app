<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立常用食物表（數值為一份）；同一個使用者的名稱不分大小寫不可重複。
     */
    public function up(): void
    {
        Schema::create('saved_foods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('portion', 50)->nullable();
            $table->decimal('grams', 7, 1)->nullable();
            $table->decimal('kcal', 7, 1);
            $table->decimal('protein_g', 6, 1)->nullable();
            $table->decimal('carbs_g', 6, 1)->nullable();
            $table->decimal('fat_g', 6, 1)->nullable();
            $table->decimal('fiber_g', 6, 1)->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
        });

        // Schema Builder 不支援運算式索引，以 SQL 建立 (user_id, lower(name)) 唯一索引
        DB::statement('CREATE UNIQUE INDEX saved_foods_user_id_lower_name_unique ON saved_foods (user_id, lower(name))');
    }

    /**
     * 刪除常用食物表（索引隨表一併刪除）。
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_foods');
    }
};
