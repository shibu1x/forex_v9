<?php

namespace App\Console\Commands;

use App\Models\Database\TradeHistory;
use Illuminate\Console\Command;

class BacktestCommand extends Command
{
    protected $signature = 'app:backtest';

    protected $description = 'Command description';

    public function handle()
    {
        TradeHistory::simulate();
    }
}
