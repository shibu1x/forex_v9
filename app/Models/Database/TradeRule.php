<?php

namespace App\Models\Database;

use App\Models\Concept\Backtest;
use App\Models\Concept\Util;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TradeRule extends Model
{
    use HasFactory;

    protected $fillable = ['symbol'];

    protected $casts = [
        'input' => 'array',
        'note' => 'array',
        'priced_at' => 'datetime',
        'is_update_action' => 'boolean',
        'is_open_pos' => 'boolean',
        'is_close_pos' => 'boolean',
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
        try {
            $this->is_close_pos = $this->isClosePosition();

            $this->is_update_action = $this->isUpdateAction();

            $this->is_open_pos = $this->isOpenPosition();

            if ($this->is_close_pos && !Backtest::get()->isActive()) {
                $this->updateBacktestScore();
            }
        } finally {
            $this->save();
        }

        // 日々の変化を記録
        DailyLog::addLog($this);
    }

    public function isClosePosition(bool $is_force = false): bool
    {
        if (!$this->isChannelBreakOut($this->input['close_length']) && !$is_force) {
            return false;
        }

        TradeHistory::closePosition($this);

        return true;
    }

    /**
     * 方向の更新
     *
     * @return boolean
     */
    public function isUpdateAction(): bool
    {
        if (!$this->isChannelBreakOut()) {
            return false;
        }

        TradeHistory::closePosition($this);

        // 方向を反転
        $this->action = $this->is_long ? 'short' : 'long';
        $this->open_price = $this->getLatestClosePrice();
        $this->init();

        TradeHistory::openPosition($this);

        return true;
    }

    public function isOpenPosition(): bool
    {
        return $this->input['profit_rate_trigger'] > $this->getProfitRate();
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
    public function getLatestClosePrice(): float
    {
        $latest_candle = $this->candle->getCandles()->first();
        return $this->round($latest_candle['mid']['c'], -1);
    }

    public function getLatestDay(): Carbon
    {
        $latest_candle = $this->candle->getCandles()->first();
        return Util::convertCarbon($latest_candle['time']);
    }

    /**
     * 現在の利益率
     *
     * @return integer
     */
    public function getProfitRate(): float
    {
        $close_price = $this->getLatestClosePrice();

        $diff_price = ($close_price - $this->open_price) * $this->action_int;

        if ($this->open_price === 0.0) {
            return 0.0;
        }

        return round($diff_price / $this->open_price * 100, 2);
    }

    /**
     * 前日比
     *
     * @return float
     */
    private function getChangeRate(): float
    {
        $candles = $this->candle->getCandles();

        $candle1 =  $candles->shift();
        $candle2 =  $candles->shift();

        $diff_price = ($candle1['mid']['c'] - $candle2['mid']['c']);

        return round($diff_price / $candle2['mid']['c'] * 100, 2);
    }

    /**
     * @param integer $length
     * @return boolean
     */
    public function isChannelBreakOut(int $length = 0): bool
    {
        // チャネル取得
        $channel_bands = $this->getChannelBands($length);

        // チャンネル幅の%
        $band_range_rate = intval((1 - $channel_bands[0] / $channel_bands[1]) * 1000);

        // チャネル幅が規定値以下か
        if ($band_range_rate < $this->input['band_range_rate']['min']) {
            // 変わっていないことにする
            return false;
        }

        $threshold = $this->is_long ? $channel_bands[0] : $channel_bands[1];

        $overflow = $this->toPips($this->getLatestClosePrice() - $threshold);

        // ログ用のデータを残す
        $breakout_log = [
            'overflow' => abs($overflow),
            'band_range_rate' => $band_range_rate,
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
        // ローソク足を取得
        $candles = $this->candle->getCandles();

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

    public function candle(): HasOne
    {
        return $this->hasOne(Candle::class, 'symbol', 'symbol');
    }

    public function tradeHistory(): HasMany
    {
        return $this->hasMany(TradeHistory::class);
    }

    public static function optimize(string $symbol): void
    {
        Log::info('Backtest running.', [$symbol]);

        // deleteを実行 id最小のものをselect。それ以外を削除
        $origin = self::where('symbol', $symbol)->first();
        self::where('symbol', $symbol)
            ->where('id', '!=', $origin->id)
            ->delete();

        foreach ([180, 270, 360] as $term) {
            for ($len = 3; $len <= 13; $len += 2) {
                // close_length <= length 
                for ($range = 7; $range <= 23; $range += 2) {
                    $origin->createInputCase($term, [
                        'length' => $len,
                        'close_length' => $len,
                        'band_range_rate' => [
                            'min' => $range,
                        ],
                    ]);
                }
            }
        }

        self::runBacktests($symbol);
    }

    public static function runBacktests(string $symbol = null): void
    {
        $builder = self::on();
        if ($symbol) {
            $builder->where('symbol', $symbol);
        }

        $builder->chunkById(5, function (Collection $selfs) {
            foreach ($selfs as $self) {
                $self->runBacktest();
            }
        });
    }

    private function runBacktest(): void
    {
        Log::info('Backtesting.', [$this->symbol, $this->id]);

        $this->tradeHistory()->delete();

        $backtest = Backtest::get();

        // 700日前まで。計算用の期間 -30 で 670日
        // 短期間で利益を上げたい場合は期間を短めに設定する
        $backtest->setStartDaysAgo($this->term);
        while ($backtest->nextDay()) {
            $this->trade();
        }

        if ($backtest->isForceClose()) {
            // 最後の現ポジションのclose処理を入れる
            $this->isClosePosition(true);
        }

        $this->updateBacktestScore();
        $this->save();
    }

    private function updateBacktestScore(): void
    {
        $this->load('tradeHistory');
        $this->backtest_long = $this->tradeHistory->where('action', 'long')->sum('profit_rate');
        $this->backtest_short = $this->tradeHistory->where('action', 'short')->sum('profit_rate');
        $this->backtest_cnt = $this->tradeHistory->count();
    }

    /**
     * inputを更新する
     *
     * @param array $input
     * @return void
     */
    private function createInputCase($term, array $input): void
    {
        $copy = $this->replicate();
        $copy->term = $term;
        $copy->input = array_merge($this->input, $input);
        $copy->save();
    }

    /**
     * 最適なデータを探し出し、それ以外を削除する
     *
     * @return void
     */
    public static function clean(): void
    {
        self::select('symbol', 'term')
            ->groupBy('symbol', 'term')
            ->get()
            ->each(function (self $self) {
                Log::info('Cleaning...', [$self->symbol, $self->term]);
                $self->chooseOne();
            });
    }

    private function chooseOne(): void
    {
        $first = self::where('symbol', $this->symbol)
            ->where('term', $this->term)
            ->select('*')
            ->selectRaw('backtest_long + backtest_short AS score')
            ->orderBy('score', 'desc')
            ->orderByRaw('input->"$.length"')
            ->orderByRaw('input->"$.band_range_rate.min"')
            ->orderByRaw('input->"$.close_length" desc')
            ->first();

        if ($first) {
            self::where('symbol', $this->symbol)
                ->where('term', $this->term)
                ->where('id', '!=', $first->id)
                ->delete();
        }
    }

    /**
     * ポジション日数
     *
     * @return integer
     */
    private function getOpenedPositionDays(): int
    {
        $latest = $this->tradeHistory->last();
        if (!$latest) {
            return -1;
        }
        return $latest->getOpenedPositionDays();
    }

    public static function getTextStatus(): string
    {
        // テキスト作成
        return view('discord.traderule_status', [
            'items' => self::orderBy('symbol')->orderBy('term')->lazy()->map(function (self $self) {
                return (object)[
                    'symbol' => $self->symbol,
                    'term' => $self->term,
                    'is_head' => $self->term === 180,
                    'action' => $self->action,
                    'close_price' => $self->getLatestClosePrice(),
                    'profit_rate' => $self->getProfitRate(),
                    'change_rate' => $self->getChangeRate(),
                    'is_update_action' => $self->is_update_action,
                    'is_open_pos' => $self->is_open_pos,
                    'is_close_pos' => $self->is_close_pos,
                    'opened_pos_days' => $self->getOpenedPositionDays(),
                    'backtest_long' => round($self->backtest_long, 1),
                    'backtest_short' => round($self->backtest_short, 1),
                ];
            }),
        ])->render();
    }

    public static function exportInitData(): void
    {
        self::orderBy('symbol')->lazy()->each(function (self $self) {
            $output = [
                "'{$self->symbol}'", $self->term, "'{$self->action}'", $self->input['length'],
                $self->input['close_length'], $self->input['band_range_rate']['min'], $self->input['overflow'],
                $self->input['profit_rate_trigger'], $self->note['margin'], "'{$self->note['memo']}'"
            ];
            echo "[" . implode(",", $output) . "], // {$self->backtest_long}, {$self->backtest_short} \n";
        });
    }

    public static function importInitData(): void
    {
        self::upsert(
            collect([
                ['EUR_USD', 180, 'long',  7,  7,  9, 0, -0.21, 65793, ''], // 1.26, 2.54 
                ['EUR_USD', 270, 'long',  7,  7,  9, 0, -0.21, 65793, ''], // 3.7, 1.71 
                ['EUR_USD', 360, 'long',  7,  7,  9, 0, -0.21, 65793, ''], // 3.7, 5.97 
                ['GBP_USD', 180, 'long',  9,  9, 13, 0, -0.37, 76730, 'USD shortならこれ'], // 1.66, 1.15 
                ['GBP_USD', 270, 'long',  9,  9, 13, 0, -0.37, 76730, 'USD shortならこれ'], // 4.71, 2.17 
                ['GBP_USD', 360, 'long',  9,  9, 13, 0, -0.37, 76730, 'USD shortならこれ'], // 4.71, 5.16 
                ['NZD_USD', 180, 'long',  9,  9, 18, 0, -0.54, 36584, ''], // 2.07, 4.34 
                ['NZD_USD', 270, 'long', 11, 11, 19, 0, -0.54, 36584, ''], // 4.42, 0.23 
                ['NZD_USD', 360, 'long',  3,  3, 13, 0, -0.54, 36584, ''], // 4.56, 3.77 
                ['USD_CHF', 180, 'short', 3,  3, 11, 0, -0.39, 60733, 'long only'], // 5.53, 0.12 
                ['USD_CHF', 270, 'short', 3,  3, 11, 0, -0.39, 60733, 'long only'], // 4.27, 6.65 
                ['USD_CHF', 360, 'short', 3,  3, 11, 0, -0.39, 60733, 'long only'], // 10.15, 6.65 
                ['USD_JPY', 180, 'long',  7,  7, 21, 0,  0.01, 60733, 'long only'], // 7.02, -3.23 
                ['USD_JPY', 270, 'short', 5,  5, 11, 0,  0.01, 60733, 'long only'], // 5.75, 0.82 
                ['USD_JPY', 360, 'long',  3,  3, 13, 0,  0.01, 60733, 'long only'], // 11.02, 0.36 
                // ['USD_CAD', 180, 'long', 11, 11, 13, 0, -0.19, 60733, ''], // 2, 0 
                // ['USD_CAD', 270, 'short', 5,  5, 11, 0, -0.19, 60733, ''], // 2.22, 1.49 
                // ['USD_CAD', 360, 'long', 13, 13, 17, 0, -0.19, 60733, ''], // 3.21, 0.58
                // ['AUD_USD', 180, 'long',  5,  5, 19, 0, -0.72, 40022, '触らない'], // 1.32, 0.85 
                // ['AUD_USD', 270, 'long',  3,  3, 23, 0, -0.72, 40022, '触らない'], // 0, 0 
                // ['AUD_USD', 360, 'short', 9,  9,  7, 0, -0.72, 40022, '触らない'], // 0.49, 1.65 
            ])->map(function (array $item, $idx) {
                $i = 0;
                return [
                    'id' => $idx + 1,
                    'symbol' => $item[$i],
                    'precision' => strpos($item[$i++], 'JPY') === false ? 5 : 3,
                    'term' => $item[$i++],
                    'action' => $item[$i++],
                    'input' => json_encode([
                        'length' => $item[$i++],
                        'close_length' => $item[$i++],
                        'band_range_rate' => [
                            'min' => $item[$i++],
                        ],
                        'overflow' => $item[$i++],
                        // ポジションを開く/閉じるチャンス
                        'profit_rate_trigger' => $item[$i++],
                        // 必要証拠金の倍率
                        'ratio' => round($item[$i] / 36584, 2),
                    ]),
                    'note' => json_encode([
                        'margin' => $item[$i++],
                        'memo' => $item[$i++],
                    ]),

                ];
            })->toArray(),
            ['id'], // レコード識別子
            ['input'] // updateの場合に更新するcolumn
        );

        Candle::initData();

        Log::info('Imported trade rules.');
    }
}
