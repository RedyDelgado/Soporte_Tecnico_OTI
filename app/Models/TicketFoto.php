<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['ticket_id', 'user_id', 'etapa', 'ruta', 'bytes'])]
class TicketFoto extends Model
{
    const ANCHO_MAX = 850;

    /** Hasta 3 fotos por formulario. El navegador ya las comprime; esto es el límite del servidor. */
    const REGLAS = [
        'fotos' => 'nullable|array|max:3',
        'fotos.*' => 'image|mimes:jpg,jpeg,png,webp|max:3072',
    ];

    public function url(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }

    public static function guardarTodas(Ticket $ticket, array $archivos, string $etapa, User $user): void
    {
        foreach (array_slice($archivos, 0, 3) as $archivo) {
            $ruta = "fotos/{$ticket->id}/".Str::random(20).'.webp';
            $contenido = self::comprimir($archivo);
            if ($contenido === null) {
                $ruta = $archivo->store("fotos/{$ticket->id}", 'public');
            } else {
                Storage::disk('public')->put($ruta, $contenido);
            }
            self::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'etapa' => $etapa,
                'ruta' => $ruta, 'bytes' => Storage::disk('public')->size($ruta)]);
        }
    }

    /** Reduce a 850 px de ancho como máximo (alto proporcional) y guarda en WebP. Sin GD, devuelve null y se guarda tal cual. */
    private static function comprimir(UploadedFile $archivo): ?string
    {
        if (! function_exists('imagewebp') || ! ($img = @imagecreatefromstring($archivo->get()))) {
            return null;
        }
        if (imagesx($img) > self::ANCHO_MAX) {
            $img = imagescale($img, self::ANCHO_MAX);
        }
        ob_start();
        imagewebp($img, null, 75);

        return ob_get_clean();
    }
}
