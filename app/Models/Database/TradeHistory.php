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

    protected $fillable = ['open_at', 'trade_rule_id', 'action', 'open_price', 'overflow', 'open_band_range'];

    public static function simulate(string $symbol = null): void
    {
        self::truncate();

        Backtest::get()->setActive(true);

        $builder = TradeRule::on();
        if ($symbol) {
            $builder->where('symbol', $symbol);
        }

        $builder->each(function (TradeRule $trade_rule) {
            for ($days = 360; $days >= 0; $days--) {
                $day = Carbon::today()->subDays($days);
                if ($day->isSaturday() || $day->isSunday()) {
                    continue;
                }

                Backtest::get()->setDay($day);

                $trade_rule->trade();
            }

            $trade_rule->updateBacktestScore();
        });
    }

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
            'open_at' => Backtest::get()->isActive() ? Backtest::get()->getDay() : Carbon::today(),
            'trade_rule_id' => $trade_rule->id,
            'action' => $trade_rule->action,
        ], [
            'open_price' => $log_data['close_price'],
            'overflow' => $log_data['overflow'],
            'open_band_range' => $log_data['band_range'],
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

        if ($trade) {
            $log_data = $trade_rule->getBreakoutLog();

            $trade->updateClosePrice($log_data['close_price']);
        }
    }

    public function updateClosePrice(float $close_price): void
    {
        if ($this->close_price !== 0.0) {
            return;
        }

        $this->close_price = $close_price;
        $this->close_at = Backtest::get()->isActive() ? Backtest::get()->getDay() : Carbon::today();

        if ($this->action === 'long') {
            $diff_price = $this->close_price - $this->open_price;
        } else {
            $diff_price = $this->open_price - $this->close_price;
        }

        $this->pl = $this->tradeRule->toPips($diff_price);

        $this->save();
    }

    public function tradeRule(): BelongsTo
    {
        return $this->belongsTo(TradeRule::class);
    }
}
