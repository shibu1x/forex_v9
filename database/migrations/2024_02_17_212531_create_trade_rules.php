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
            $default_at = '1000-01-01';
            $table->id();
            $table->enum('symbol', ['AUD_USD', 'EUR_USD', 'GBP_USD', 'NZD_USD', 'USD_CAD', 'USD_CHF', 'USD_JPY', 'USD_MXN']);
            $table->enum('action', ['long', 'short'])->default('long');
            $table->float('open_price', 8, 4)->default(0);
            $table->json('input')->nullable();
            $table->integer('backtest_long')->default(0);
            $table->integer('backtest_short')->default(0);
            $table->tinyInteger('precision')->default(5);
            $table->date('action_at')->default($default_at);
            $table->date('candles_at')->default($default_at);
            $table->json('candles')->nullable();
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
