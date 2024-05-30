<?php

namespace App\Console\Commands;

use App\Models\Concept\AdminUser;
use App\Models\Database\TradeRule;
use App\Notifications\DailyReport;
use Exception;
use Illuminate\Console\Command;

class DailyReportCommand extends Command
{
    protected $signature = 'app:report';

    protected $description = 'Command description';

    public function handle()
    {
        try {
            // 方向を更新 (金曜日は実行されないため)
            TradeRule::lazy()->each(function (TradeRule $trade_rule) {
                $trade_rule->trade();
            });

            $admin_user = new AdminUser();
            $admin_user->notify(new DailyReport());
        } catch (Exception $e) {
            // ExceptionLog::addLog($e);
            throw $e;
        }
    }
}
