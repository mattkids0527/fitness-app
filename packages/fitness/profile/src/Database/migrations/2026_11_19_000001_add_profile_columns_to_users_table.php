<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 在 users 加入首次設定與 BMR 計算所需的個人資料欄位。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->decimal('height_cm', 4, 1)->nullable();
            $table->string('activity_level', 20)->nullable();
            $table->string('goal', 20)->nullable();
            $table->decimal('goal_rate_kg_per_week', 3, 2)->nullable();
            $table->unsignedSmallInteger('bmr_override')->nullable();
            $table->string('timezone', 64)->default('Asia/Taipei');
            $table->timestamp('onboarded_at')->nullable();
        });
    }

    /**
     * 移除個人資料欄位。
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'birth_date', 'height_cm', 'activity_level', 'goal',
                'goal_rate_kg_per_week', 'bmr_override', 'timezone', 'onboarded_at',
            ]);
        });
    }
};
