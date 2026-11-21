<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立健身紀錄表：一筆是一個項目的一次運動，分類／項目名稱、記錄方式、MET、秒數、體重與消耗熱量都存快照。
     */
    public function up(): void
    {
        Schema::create('exercise_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('performed_at');
            $table->string('category_name', 30);
            $table->string('exercise_name', 50);
            $table->string('record_type', 16);
            $table->decimal('met', 4, 1);
            $table->unsignedSmallInteger('sec_per_rep')->nullable();
            $table->unsignedSmallInteger('rest_sec')->nullable();
            $table->decimal('body_weight_kg', 5, 1);
            $table->decimal('duration_min', 5, 1);
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->decimal('kcal', 7, 1);
            $table->decimal('volume_kg', 9, 1)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'performed_at']);
            $table->index(['user_id', 'exercise_id', 'performed_at']);
        });
    }

    /**
     * 刪除健身紀錄表。
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_logs');
    }
};
