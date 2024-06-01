<?php

namespace App\Console\Commands;

use App\Models\Concept\Backtest;
use App\Models\Database\TradeRule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OptimizeCommand extends Command
{
    protected $signature = 'app:optimize {symbol?}';

    protected $description = 'Command description';

    public function handle()
    {
        Backtest::get()->setContext([
            'is_active' => true,
            'is_force_close' => true,
        ]);

        // default
        $symbols = TradeRule::all()->pluck('symbol')->unique();

        if ($symbol = $this->argument('symbol')) {
            $symbols = collect([$symbol]);
        }

        $start = Carbon::now();

        Log::info('Creating a backtest cases...');

        $symbols->each(function (string $symbol) {
            TradeRule::optimize($symbol);
        });

        Log::info($start->diffInMinutes() . ' m');
    }
}
