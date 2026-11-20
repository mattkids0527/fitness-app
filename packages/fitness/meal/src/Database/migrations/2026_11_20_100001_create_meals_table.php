<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立餐點表：一筆是一餐（時間＋餐別），食物項目另存 meal_items。
     */
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('eaten_at');
            $table->string('meal_type', 16);
            $table->string('source', 16)->default('manual');
            $table->string('status', 16)->default('confirmed');
            $table->string('note', 500)->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'eaten_at']);
        });
    }

    /**
     * 刪除餐點表。
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
