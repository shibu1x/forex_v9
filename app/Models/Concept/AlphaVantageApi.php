<?php

namespace App\Models\Concept;

use ErrorException;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlphaVantageApi
{
    private string $api_key;
    private string $endpoint = "https://www.alphavantage.co/query";

    /**
     * api
     * @var int
     */
    private int $request_count = 0;

    private static self $instance;

    public static function get(): self
    {
        return self::$instance = self::$instance ?? new self();
    }

    public function __construct()
    {
        $this->api_key = config('services.alpha_vantage.api_key');
    }

    public function getCandles(string $symbol, array $data): array
    {
        $symbols = explode('_', $symbol);

        $count = $data['count'];
        unset($data['count']);

        $candles = $this->send(array_merge([
            'from_symbol' => $symbols[0],
            'to_symbol' => $symbols[1],
            'outputsize' => $count > 100 ? 'full' : 'compact',
        ], $data));

        try {
            return $this->convertCandles($candles)->shift($count)->toArray();
        } catch (ErrorException $e) {
            // API呼び出し制限を超えた等のエラー
            Log::warning($candles['Information']);
            throw $e;
        }
    }

    public function convertCandles(array $data): Collection
    {
        $candles = collect();
        foreach ($data['Time Series FX (Daily)'] as $time => $row) {

            if ($time === date('Y-m-d')) {
                // 今日のデータはまだ確定していないため除外
                continue;
            }

            $candles->push([
                'mid' => [
                    'o' => floatval($row['1. open']),
                    'h' => floatval($row['2. high']),
                    'l' => floatval($row['3. low']),
                    'c' => floatval($row['4. close']),
                ],
                'time' => intval(str_replace('-', '', $time)),
            ]);
        }

        return $candles;
    }

    private function send(array $data = null, string $method = 'get'): array
    {
        if (++$this->request_count >= 100) {
            // 100 request / min limit
            sleep(1);
            $this->request_count = 1;
        }

        $request = Http::acceptJson()
            ->timeout(10)
            ->retry(2, 3000);

        $data = array_merge($data, [
            'apikey' => $this->api_key
        ]);

        $response = null;
        if ($method === 'get') {
            $response = $request->get($this->endpoint, $data);
        } elseif ($method === 'post') {
            $response = $request->post($this->endpoint, $data);
        } elseif ($method === 'put') {
            $response = $request->put($this->endpoint, $data);
        }

        if ($response && $response->successful()) {
            return $response->json();
        }

        Log::error('Alpha Vantage API error.', $response->json());
        throw new Exception('Alpha Vantage API error.');

        return [];
    }
}
