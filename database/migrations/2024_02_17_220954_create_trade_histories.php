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
        Schema::create('trade_histories', function (Blueprint $table) {
            $default_at = '1000-01-01';
            $table->id();
            $table->date('open_at')->default($default_at);
            $table->date('close_at')->default($default_at);
            $table->unsignedBigInteger('trade_rule_id');
            $table->enum('action', ['long', 'short'])->default('long');
            $table->float('open_price', 8, 5)->default(0);
            $table->float('close_price', 8, 5)->default(0);
            $table->float('profit_rate')->default(0);
            $table->float('max_profit_rate')->default(0);
            $table->float('min_profit_rate')->default(0);
            $table->unsignedSmallInteger('days')->default(0);
            $table->unsignedSmallInteger('overflow')->default(0);
            $table->unsignedTinyInteger('open_band_range_rate')->default(0);
            $table->timestamps();
            $table->unique(['open_at', 'trade_rule_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_histories');
    }
};
