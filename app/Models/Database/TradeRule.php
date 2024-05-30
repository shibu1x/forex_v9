<?php

namespace App\Models\Database;

use App\Models\Concept\AlphaVantageApi;
use App\Models\Concept\Backtest;
use App\Models\Concept\Util;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TradeRule extends Model
{
    use HasFactory;

    protected $fillable = ['symbol'];

    protected $casts = [
        'input' => 'array',
        'candles' => 'array',
        'action_at' => 'datetime',
        'priced_at' => 'datetime',
        'candles_at' => 'datetime',
    ];

    private bool $is_long;
    private int $action_int;

    private array $breakout_log = [];

    protected static function booted(): void
    {
        static::retrieved(function (self $item) {
            $item->init();
        });
    }

    /**
     * 初期設定
     * @return void
     */
    private function init(): void
    {
        $this->is_long = $this->action === 'long';
        if ($this->is_long) {
            $this->action_int = 1;
        } else {
            $this->action_int = -1;
        }
    }

    public function trade(): void
    {
        // 反対方向のポジションがあった場合はclose
        $this->closeTrade();

        // 新規注文
        $this->createOrder();
    }

    public function closeTrade(): void
    {
        // 片方が breackoutしていたらclose
        if (!$this->isChannelBreakOut($this->input['close_length']) && !$this->isChannelBreakOut()) {
            return;
        }

        TradeHistory::closePosition($this);

        Log::info('Close trade.', [$this->symbol, $this->action]);
    }

    public function createOrder(): void
    {
        try {
            // 方向を更新
            $this->updateAction();
        } finally {
            $this->save();
        }
    }

    /**
     * 方向を更新
     *
     * @return void
     */
    public function updateAction(): void
    {
        if (!$this->isChannelBreakOut()) {
            return;
        }

        // 方向を反転
        $this->action = $this->is_long ? 'short' : 'long';
        $this->init();

        $log_data = $this->getBreakoutLog();
        $this->action_at = Util::convertCarbon($log_data['close_time']);
        $this->open_price = $this->round($log_data['close_price'], -1);

        Log::info('Update action.', [$this->symbol, $this->is_long ? 'short -> long' : 'long -> short', $this->open_price]);

        TradeHistory::openPosition($this);
    }

    public function getBreakoutLog(): array
    {
        return $this->breakout_log;
    }

    /**
     * 最新の終値
     *
     * @return float
     */
    private function getLatestClosePrice(): float
    {
        $latest_candle = $this->getCandles()->first();
        return $this->round($latest_candle['mid']['c'], -1);
    }

    /**
     * 現在の利益率
     *
     * @return integer
     */
    private function getPlPercent(): float
    {
        $close_price = $this->getLatestClosePrice();

        $diff_price = ($close_price - $this->open_price) * $this->action_int;

        return round($diff_price / $this->open_price * 100, 2);
    }

    /**
     * 前日比
     *
     * @return float
     */
    private function getChangePercent(): float
    {
        $candles = $this->getCandles();

        $candle1 =  $candles->shift();
        $candle2 =  $candles->shift();

        $diff_price = ($candle1['mid']['c'] - $candle2['mid']['c']);

        return round($diff_price / $candle2['mid']['c'] * 100, 2);
    }

    /**
     * 方向が今日変わったか
     *
     * @return boolean
     */
    public function isActionUpdated(): bool
    {
        $latest_candle = $this->getCandles()->first();
        return intval($this->action_at->format('Ymd')) === $latest_candle['time'];
    }

    /**
     * @param integer $length
     * @return boolean
     */
    public function isChannelBreakOut(int $length = 0): bool
    {
        // チャネル取得
        $channel_bands = $this->getChannelBands($length);

        // チャネル幅 (pips)
        $band_range = $this->toPips($channel_bands[1] - $channel_bands[0]);

        // チャネル幅が規定値以下か
        if ($band_range < $this->input['band_range']['min']) {
            // 変わっていないことにする
            return false;
        }

        $latest_candle = $this->getCandles()->first();
        $close_price = floatval($latest_candle['mid']['c']);

        $threshold = $this->is_long ? $channel_bands[0] : $channel_bands[1];

        $overflow = $this->toPips($close_price - $threshold);

        // ログ用のデータを残す
        $breakout_log = [
            'close_price' => $close_price,
            'close_time' => $latest_candle['time'],
            'overflow' => abs($overflow),
            'band_range' => $this->toPips($channel_bands[1] - $channel_bands[0]),
        ];

        if ($this->is_long && $overflow < $this->input['overflow'] * -1) {
            // Break out
            $this->breakout_log = $breakout_log;
            return true;
        }

        if (!$this->is_long && $overflow > $this->input['overflow']) {
            // Break out
            $this->breakout_log = $breakout_log;
            return true;
        }

        return false;
    }

    /**
     * ChannelBands
     *
     * @param integer $length
     * @return array
     */
    private function getChannelBands(int $length = 0): array
    {
        $this->updateCandles();

        // ローソク足を取得
        $candles = $this->getCandles();

        if ($length === 0) {
            $length = $this->input['length'];
        }

        // 直前のデータ取得のため、最新データを除外
        $candles->shift();
        // 指定の件数を取得する
        $candles = $candles->shift($length - 1);

        return [
            $this->round($candles->min(function (array $item) {
                return $item['mid']['l'];
            }), -1),
            $this->round($candles->max(function (array $item) {
                return $item['mid']['h'];
            }), -1)
        ];
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
            'count' => Backtest::get()->isActive() ? 500 : $this->input['length'] + 2,
        ]);

        $this->candles_at = Util::convertCarbon($this->candles[0]['time']);

        $this->save();

        return true;
    }

    /**
     * 完了しているローソク足を取得
     *
     * @return Collection
     */
    private function getCandles(): Collection
    {
        $candles = collect($this->candles);

        // backtestの場合
        if (Backtest::get()->isActive()) {
            $candles = $candles->filter(function (array $item) {
                return Backtest::get()->filterCandles($item);
            });
        }

        return $candles;
    }

    /**
     * price => pips
     */
    public function toPips(float $price): int
    {
        return intval($price * pow(10, $this->precision));
    }

    /**
     * pips => price
     */
    public function toPrice(int $pips): float
    {
        return $pips / pow(10, $this->precision);
    }

    public function round(float $price, int $point = 0): float
    {
        return round($price, $this->precision + $point);
    }

    public function tradeHistory(): HasMany
    {
        return $this->hasMany(TradeHistory::class);
    }

    public function updateBacktestScore(): void
    {
        $this->backtest_long = $this->tradeHistory->where('action', 'long')->sum('pl') / $this->input['ratio'];
        $this->backtest_short = $this->tradeHistory->where('action', 'short')->sum('pl') / $this->input['ratio'];

        $this->save();
    }

    /**
     * inputを更新する
     *
     * @param array $input
     * @return void
     */
    public function createInputCase(array $input): void
    {
        Backtest::get()->setActive(true);

        // ローソク足を更新する
        $this->updateCandles();

        $copy = $this->replicate();
        $copy->input = array_merge($this->input, $input);
        $copy->save();
    }

    public static function getTextStatus(): string
    {
        // テキスト作成
        return view('discord.traderule_status', [
            'items' => self::lazy()->map(function (self $self) {
                return (object)[
                    'symbol' => $self->symbol,
                    'action' => $self->action,
                    'is_action_updated' => $self->isActionUpdated(),
                    'close_price' => $self->getLatestClosePrice(),
                    'open_price' => $self->open_price,
                    'pl_pr' => $self->getPlPercent(),
                    'chg_pr' => $self->getChangePercent(),
                ];
            }),
        ])->render();
    }

    public static function importInitData(): void
    {
        self::upsert(
            collect([
                ['USD_JPY',  'long', 'D',  9,  9, 15, 0, 60733, 'longのみ。スワップが厳しい'], // 9861
                ['EUR_USD', 'short', 'D',  7,  7,  3, 0, 65793, ''], // 6213
                ['USD_CHF',  'long', 'D',  5,  5,  6, 0, 60733, ''], // 6078
                ['GBP_USD', 'short', 'D',  8,  6, 21, 0, 76730, ''], // 5187
                // ['USD_CAD',  'long', 'D',  6,  6,  0, 0, 60733, ''], // 3483
                // ['NZD_USD', 'short', 'D',  4,  4, 12, 0, 36584, '触らない'], // 2136
                // ['AUD_USD',  'long', 'D',  8,  8,  0, 0, 40022, '触らない'], // -3172
            ])->map(function (array $item, $idx) {
                $i = 0;
                return [
                    'id' => $idx + 1,
                    'symbol' => $item[$i],
                    'precision' => strpos($item[$i++], 'JPY') === false ? 5 : 3,
                    'action' => $item[$i++],
                    'input' => json_encode([
                        'granularity' => $item[$i++],
                        'length' => $item[$i++],
                        'close_length' => $item[$i++],
                        'band_range' => [
                            'min' => $item[$i++] * 100,
                        ],
                        'overflow' => $item[$i++],
                        // 必要証拠金の倍率
                        'ratio' => round($item[$i++] / 36584, 2),
                    ]),
                ];
            })->toArray(),
            ['id'], // レコード識別子
            ['input'] // updateの場合に更新するcolumn
        );

        Log::info('Imported trade rules.');
    }
}
