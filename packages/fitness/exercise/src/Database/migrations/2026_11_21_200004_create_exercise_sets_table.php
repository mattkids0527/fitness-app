<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立逐組明細表：一筆逐組紀錄的每一組次數與重量（徒手時重量為 null）。
     */
    public function up(): void
    {
        Schema::create('exercise_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_log_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('set_no');
            $table->unsignedSmallInteger('reps');
            $table->decimal('weight_kg', 5, 1)->nullable();

            $table->index(['exercise_log_id', 'set_no']);
        });
    }

    /**
     * 刪除逐組明細表。
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_sets');
    }
};
