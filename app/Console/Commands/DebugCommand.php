<?php

namespace App\Console\Commands;

use App\Models\Database\Candle;
use App\Models\Database\TradeHistory;
use App\Models\Database\TradeRule;
use Illuminate\Console\Command;

class DebugCommand extends Command
{
    protected $signature = 'app:debug';

    protected $description = 'Command description';

    public function handle()
    {
        dd(TradeRule::getTextStatus());
    }
}
