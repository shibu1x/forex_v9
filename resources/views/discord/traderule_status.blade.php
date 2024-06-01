*** TradeRules ***
```
 Symbol | Term | Actio | Now P  | Change  | Profit  | Days | Backtest  | Message
------- | ---- | ----- | ------ | ------- | ------- | ---- | --------- | ---------
@foreach ($items as $item)
{{  Str::padLeft($item->is_head ? $item->symbol : '', 7) }} |   {{ Str::padLeft($item->term / 30, 2) }} | {{ Str::padLeft($item->action, 5) }} | {{ Str::padRight($item->is_head ? $item->close_price : '', 6) }} | {{ Str::padLeft($item->is_head ? $item->change_rate : '', 5) }} % | {{ Str::padLeft($item->profit_rate, 5) }} % |  {{ Str::padLeft($item->opened_pos_days, 3) }} | {{ Str::padLeft($item->backtest_long, 4) }} {{ Str::padLeft($item->backtest_short, 4) }} | {{ $item->is_update_action ? '🚨 Break Out! ' : '' }}{{ $item->is_open_pos ? '🔥 Open Pos! ' : '' }}{{ $item->is_close_pos ? '✅ Close Pos! ' : '' }}
@endforeach
```