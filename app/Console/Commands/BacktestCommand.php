<?php

namespace App\Console\Commands;

use App\Models\Concept\Backtest;
use App\Models\Database\DailyLog;
use App\Models\Database\TradeHistory;
use App\Models\Database\TradeRule;
use Illuminate\Console\Command;

class BacktestCommand extends Command
{
    protected $signature = 'app:backtest {symbol?}';

    protected $description = 'Command description';

    public function handle()
    {
        Backtest::get()->setContext([
            'is_active' => true,
            'is_daily_log' => true,
        ]);

        DailyLog::truncate();

        TradeRule::runBacktests($this->argument('symbol'));
    }
}
