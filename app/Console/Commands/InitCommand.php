<?php

namespace App\Console\Commands;

use App\Models\Database\TradeRule;
use Illuminate\Console\Command;

class InitCommand extends Command
{
    protected $signature = 'app:init';

    protected $description = 'Command description';

    public function handle()
    {
        TradeRule::importInitData();
    }
}
