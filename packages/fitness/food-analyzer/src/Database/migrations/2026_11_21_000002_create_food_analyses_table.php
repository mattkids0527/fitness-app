<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立照片分析紀錄表：每送出一次分析寫一筆（成功或失敗），用來計算每日上限與帶入確認頁；不存照片。
     * 呼叫 LLM 的細節（provider、model、prompt、回應、token、耗時）記在 llm_logs。
     */
    public function up(): void
    {
        Schema::create('food_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('llm_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16);
            $table->string('error', 32)->nullable();
            $table->string('hint', 200)->nullable();
            $table->jsonb('result')->nullable();
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
