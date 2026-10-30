<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AvisoTicket extends Notification
{
    public function __construct(public Ticket $ticket, public string $titulo) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $t = $this->ticket;

        return (new WebPushMessage)
            ->title($this->titulo)
            ->body("{$t->codigo} · {$t->sede->nombre}: {$t->descripcion}")
            ->icon('/icono.svg')
            ->tag($t->codigo)
            ->data(['url' => route('tickets.show', $t)]);
    }
}
