*** TradeRules ***
```
 Symbol | Actio | Opne P | Now P  | Chg%    | PL%     | Break Out
------- | ----- |------- | ------ | ------- | ------- | ---------
@foreach ($items as $item)
{{ $item->symbol }} | {{ Str::padLeft($item->action, 5) }} | {{ Str::padLeft($item->open_price, 6) }} | {{ Str::padRight($item->close_price, 6) }} | {{ Str::padLeft($item->chg_pr, 5) }} % | {{ Str::padLeft($item->pl_pr, 5) }} % | {{ $item->is_action_updated ? '✅ ' . $item->open_price : '' }}
@endforeach
```