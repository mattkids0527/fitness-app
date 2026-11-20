<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立喝水紀錄表，每按一次記一筆。
     */
    public function up(): void
    {
        Schema::create('water_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_ml');
            $table->timestampTz('drank_at');
            $table->timestampsTz();

            $table->index(['user_id', 'drank_at']);
        });
    }

    /**
     * 刪除喝水紀錄表。
     */
    public function down(): void
    {
        Schema::dropIfExists('water_logs');
    }
};
