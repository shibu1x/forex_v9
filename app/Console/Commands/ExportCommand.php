<?php

namespace App\Console\Commands;

use App\Models\Database\TradeRule;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'app:export {--clean}';

    protected $description = 'Command description';

    public function handle()
    {
        $is_clean = $this->option('clean');
        if ($is_clean) {
            TradeRule::clean();
        }

        TradeRule::exportInitData();
    }
}
