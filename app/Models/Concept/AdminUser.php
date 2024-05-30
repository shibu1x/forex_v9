<?php

namespace App\Models\Concept;

use Illuminate\Notifications\Notifiable;

class AdminUser
{
    use Notifiable;

    public function routeNotificationForDiscord(): int
    {
        return config('services.discord.channel');
    }
}
