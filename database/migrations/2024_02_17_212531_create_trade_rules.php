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
        Schema::create('trade_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('symbol', ['AUD_USD', 'EUR_USD', 'GBP_USD', 'NZD_USD', 'USD_CAD', 'USD_CHF', 'USD_JPY', 'USD_MXN']);
            $table->unsignedSmallInteger('term')->default(180);
            $table->enum('action', ['long', 'short'])->default('long');
            $table->float('open_price', 8, 4)->default(0);
            $table->json('input')->nullable();
            $table->float('backtest_long')->default(0);
            $table->float('backtest_short')->default(0);
            $table->unsignedTinyInteger('backtest_cnt')->default(0);
            $table->boolean('is_update_action')->default(false);
            $table->boolean('is_open_pos')->default(false);
            $table->boolean('is_close_pos')->default(false);
            $table->tinyInteger('precision')->default(5);
            $table->json('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_rules');
    }
};
