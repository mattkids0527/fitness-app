<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立 LLM API 呼叫紀錄表：每呼叫一次寫一筆，記錄使用者、LLM 種類、送出的 prompt 與回應；不存圖片。
     */
    public function up(): void
    {
        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->string('feature', 32);
            $table->text('content');
            $table->jsonb('response')->nullable();
            $table->string('status', 16);
            $table->string('error', 32)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });
    }

    /**
     * 刪除 LLM API 呼叫紀錄表。
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
    }
};
