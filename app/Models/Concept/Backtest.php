<?php

namespace App\Models\Concept;

use Carbon\Carbon;

class Backtest
{
    private static self $instance;

    private Carbon $day;

    private int $day_int;

    private array $context = [];

    public static function get(): self
    {
        return self::$instance = self::$instance ?? new self();
    }

    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    public function isActive(): bool
    {
        return $this->context['is_active'] ?? false;
    }

    public function isDailyLog(): bool
    {
        return $this->context['is_daily_log'] ?? false;
    }

    public function isForceClose(): bool
    {
        return $this->context['is_force_close'] ?? false;
    }

    public function setStartDaysAgo(int $days)
    {
        $this->day = Carbon::today()->subDays($days);
    }

    public function nextDay(): bool
    {
        $this->day = $this->day->addWeekday();
        $this->day_int = intval($this->day->format('Ymd'));
        return $this->day->lessThan(Carbon::today());
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
