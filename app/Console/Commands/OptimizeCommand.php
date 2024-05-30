<?php

namespace App\Console\Commands;

use App\Models\Database\TradeHistory;
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
        // default
        $symbols = TradeRule::all()->pluck('symbol');

        if ($symbol = $this->argument('symbol')) {
            $symbols = collect([$symbol]);
        }

        $start = Carbon::now();

        Log::info('creating a backtest cases...');

        $symbols->each(function (string $symbol) {
            $rule = TradeRule::where('symbol', $symbol)->first();
            for ($len = 5; $len <= 16; $len += 2) {
                // close_length <= length 
                for ($c_len = $len; $c_len >= 5; $c_len -= 2) {
                    for ($range = 0; $range <= 24; $range += 3) {
                        $rule->createInputCase([
                            'length' => $len,
                            'close_length' => $c_len,
                            'band_range' => [
                                'min' => $range * 100,
                            ],
                        ]);
                    }
                }
            }

            TradeHistory::simulate($symbol);
        });

        Log::info($start->diffInMinutes() . ' s');
    }
}
