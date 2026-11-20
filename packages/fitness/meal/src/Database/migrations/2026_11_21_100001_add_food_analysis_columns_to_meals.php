<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 照片分析建立的餐點：meals 記錄來自哪一筆分析（一筆分析只能建立一餐），meal_items 記錄 AI 信心度。
     * 都是可 null 的新欄位，舊版程式不受影響。
     */
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->foreignId('food_analysis_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::table('meal_items', function (Blueprint $table) {
            $table->decimal('confidence', 3, 2)->nullable();
        });
    }

    /**
     * 移除照片分析相關欄位。
     */
    public function down(): void
    {
        Schema::table('meal_items', function (Blueprint $table) {
            $table->dropColumn('confidence');
        });

        Schema::table('meals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('food_analysis_id');
        });
    }
};
