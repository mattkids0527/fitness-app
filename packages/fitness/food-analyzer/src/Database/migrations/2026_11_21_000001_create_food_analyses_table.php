<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立照片分析紀錄表：每送出一次分析寫一筆（成功或失敗），用來計算每日上限與成本；不存照片。
     */
    public function up(): void
    {
        Schema::create('food_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->string('status', 16);
            $table->string('error', 32)->nullable();
            $table->string('hint', 200)->nullable();
            $table->jsonb('result')->nullable();
            $table->jsonb('raw_response')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * 刪除照片分析紀錄表。
     */
    public function down(): void
    {
        Schema::dropIfExists('food_analyses');
    }
};
