<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_logs', function (Blueprint $table) {
            $default_at = '1000-01-01';
            $table->id();
            $table->date('date_at')->default($default_at);
            $table->unsignedBigInteger('trade_rule_id');
            $table->enum('action', ['long', 'short'])->default('long');
            $table->float('profit_rate')->default(0);
            $table->boolean('is_update_action')->default(false);
            $table->boolean('is_open_pos')->default(false);
            $table->boolean('is_close_pos')->default(false);
            $table->timestamps();
            $table->unique(['date_at', 'trade_rule_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_logs');
    }
};
