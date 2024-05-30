<?php

namespace App\Notifications;

use App\Models\Database\TradeRule;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;

class DailyReport extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord($notifiable): DiscordMessage
    {
        return DiscordMessage::create($this->buildText());
    }

    private function buildText(): string
    {
        $text = collect();

        $text->push(TradeRule::getTextStatus());

        return $text->filter()->implode("\n");
    }
}
