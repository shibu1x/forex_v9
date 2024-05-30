<?php

namespace App\Console\Commands;

use App\Models\Concept\Backtest;
use App\Models\Database\TradeHistory;
use App\Models\Database\TradeRule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DebugCommand extends Command
{
    protected $signature = 'app:debug';

    protected $description = 'Command description';

    public function handle()
    {
        dd(TradeRule::getTextStatus());
    }
}
