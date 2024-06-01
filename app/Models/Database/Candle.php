<?php

namespace App\Models\Database;

use App\Models\Concept\AlphaVantageApi;
use App\Models\Concept\Backtest;
use App\Models\Concept\Util;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Candle extends Model
{
    use HasFactory;

    protected $casts = [
        'candles' => 'array',
        'candles_at' => 'datetime',
    ];

    public static function initData(): void
    {
        self::upsert(collect(['AUD_USD', 'EUR_USD', 'GBP_USD', 'NZD_USD', 'USD_CAD', 'USD_CHF', 'USD_JPY'])->map(function ($symbol) {
            return [
                'symbol' => $symbol,
            ];
        })->toArray(), ['symbol']);
    }

    /**
     * 完了しているローソク足を取得
     *
     * @return Collection
     */
    public function getCandles(): Collection
    {
        $this->updateCandles();

        $candles = collect($this->candles);

        // backtestの場合
        if (Backtest::get()->isActive()) {
            $candles = $candles->filter(function (array $item) {
                return Backtest::get()->filterCandles($item);
            });
        }

        return $candles;
    }

    private function updateCandles(): bool
    {
        if ($this->candles_at->diffInDays() <= 1) {
            // 1日経過していない
            return false;
        }

        if (
            $this->candles_at->isFriday()
            && isset($this->candles)
            && $this->candles_at->diffInDays() <= 3
        ) {
            // 土日は更新しない
            return false;
        }

        Log::info('Update candles', [$this->symbol]);

        $this->candles = AlphaVantageApi::get()->getCandles($this->symbol, [
            'function' => 'FX_DAILY',
            'count' => 500, // Backtest::get()->isActive() ? 500 : 100,
        ]);

        $this->candles_at = Util::convertCarbon($this->candles[0]['time']);

        $this->save();

        return true;
    }
}
