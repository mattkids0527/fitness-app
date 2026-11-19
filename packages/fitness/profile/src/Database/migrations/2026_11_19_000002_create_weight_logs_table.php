<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立體重紀錄表，每次量測都留一筆歷史。
     */
    public function up(): void
    {
        Schema::create('weight_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight_kg', 5, 2);
            $table->date('measured_on');
            $table->timestamps();

            $table->index(['user_id', 'measured_on']);
        });
    }

    /**
     * 刪除體重紀錄表。
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_logs');
    }
};
