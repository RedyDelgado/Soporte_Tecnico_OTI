<?php

namespace App\Notifications;

use App\Models\Derivacion;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AvisoDerivacion extends Notification
{
    public function __construct(public Derivacion $derivacion) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        $d = $this->derivacion;

        return (new WebPushMessage)
            ->title('Te derivaron un proveído')
            ->body("{$d->nro_proveido}: {$d->descripcion}")
            ->icon('/logo-192.png')
            ->tag('derivacion-'.$d->id)
            ->data(['url' => route('derivaciones.index')]);
    }
}
