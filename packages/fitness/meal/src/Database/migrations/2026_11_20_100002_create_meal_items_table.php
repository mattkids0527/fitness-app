<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立食物項目表；營養素為 null 表示「不知道」，與 0 區分。
     */
    public function up(): void
    {
        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name', 100);
            $table->string('portion', 50)->nullable();
            $table->decimal('grams', 7, 1)->nullable();
            $table->decimal('kcal', 7, 1);
            $table->decimal('protein_g', 6, 1)->nullable();
            $table->decimal('carbs_g', 6, 1)->nullable();
            $table->decimal('fat_g', 6, 1)->nullable();
            $table->decimal('fiber_g', 6, 1)->nullable();
            $table->timestampsTz();

            $table->index(['meal_id', 'position']);
        });
    }

    /**
     * 刪除食物項目表。
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_items');
    }
};
