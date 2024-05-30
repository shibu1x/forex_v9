<?php

namespace App\Models\Concept;

use Carbon\Carbon;

class Backtest
{
    private static self $instance;

    private bool $is_active = false;

    private Carbon $day;

    private int $day_int;

    public static function get(): self
    {
        return self::$instance = self::$instance ?? new self();
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function setActive(bool $is_active): void
    {
        $this->is_active = $is_active;
    }

    public function setDay(Carbon $day): void
    {
        $this->day = $day;
        $this->day_int = intval($day->format('Ymd'));
    }

    public function getDay(): Carbon
    {
        return $this->day;
    }

    public function filterCandles(array $item): bool
    {
        return $this->day_int >= $item['time'];
    }
}
