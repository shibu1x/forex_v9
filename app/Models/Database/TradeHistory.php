<?php

namespace App\Models\Database;

use App\Models\Concept\Backtest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeHistory extends Model
{
    use HasFactory;

    protected $fillable = ['open_at', 'trade_rule_id', 'action', 'open_price', 'overflow', 'open_band_range_rate'];

    protected $casts = [
        'open_at' => 'datetime',
    ];

    /**
     * open
     *
     * @param TradeRule $trade_rule
     * @return void
     */
    public static function openPosition(TradeRule $trade_rule): void
    {
        $log_data = $trade_rule->getBreakoutLog();

        self::firstOrCreate([
            'open_at' => $trade_rule->getLatestDay(),
            'trade_rule_id' => $trade_rule->id,
            'action' => $trade_rule->action,
        ], [
            'open_price' => $trade_rule->open_price,
            'overflow' => $log_data['overflow'],
            'open_band_range_rate' => $log_data['band_range_rate'],
        ]);
    }

    /**
     * close
     *
     * @param TradeRule $trade_rule
     * @return void
     */
    public static function closePosition(TradeRule $trade_rule): void
    {
        $trade = self::where('trade_rule_id', $trade_rule->id)
            ->where('action', $trade_rule->action)
            ->orderBy('id', 'desc')
            ->first();

        if (!$trade || $trade->isClosed()) {
            return;
        }

        $trade->updateClosePrice($trade_rule);
    }

    private function isClosed(): bool
    {
        return $this->close_price !== 0.0;
    }

    private function updateClosePrice(TradeRule $trade_rule): void
    {
        $this->close_price = $trade_rule->getLatestClosePrice();
        $this->close_at = $trade_rule->getLatestDay();
        $this->profit_rate = $this->tradeRule->getProfitRate();

        $profit_rate_range = DailyLog::getProfitRateRange($this->tradeRule);
        $this->min_profit_rate = $profit_rate_range[0];
        $this->max_profit_rate = $profit_rate_range[1];
        $this->days = $this->close_at->diffInDays($this->open_at);

        $this->save();
    }

    /**
     * ポジションを開いてからの経過日数
     *
     * @return integer
     */
    public function getOpenedPositionDays(): int
    {
        if ($this->isClosed()) {
            return -1;
        }
        return Carbon::today()->diffInDays($this->open_at);
    }

    public function tradeRule(): BelongsTo
    {
        return $this->belongsTo(TradeRule::class);
    }
}
