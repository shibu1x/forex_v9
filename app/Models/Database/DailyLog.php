<?php

namespace App\Models\Database;

use App\Models\Concept\Backtest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyLog extends Model
{
    use HasFactory;

    protected $fillable = ['date_at', 'trade_rule_id', 'action', 'profit_rate', 'is_update_action', 'is_open_pos', 'is_close_pos'];

    public static function addLog(TradeRule $rule): void
    {
        if (!Backtest::get()->isDailyLog()) {
            return;
        }

        // 日付を取る
        self::create([
            'date_at' => $rule->getLatestDay(),
            'trade_rule_id' => $rule->id,
            'action' => $rule->action,
            'profit_rate' => $rule->getProfitRate(),
            'is_update_action' => $rule->is_update_action,
            'is_open_pos' => $rule->is_open_pos,
            'is_close_pos' => $rule->is_close_pos,
        ]);
    }

    /**
     * PL percent の最小値と最大値を取得する
     *
     * @param TradeRule $rule
     * @return array
     */
    public static function getProfitRateRange(TradeRule $rule): array
    {
        $items1 = self::where('trade_rule_id', $rule->id)
            ->where('date_at', '<=', Backtest::get()->getDay())->orderBy('id', 'desc')->get();

        // トレンドが変わったindex
        $change_index = $items1->search(function (self $item) use ($rule) {
            return $item->action != $rule->action;
        });

        // トレンドが変わるところまでのデータを取得ï
        $items2 = $items1->slice(0, $change_index);

        return [$items2->min('profit_rate') ?? 0, $items2->max('profit_rate') ?? 0];
    }
}
