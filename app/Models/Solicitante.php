<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'telefono', 'email', 'area_id'])]
class Solicitante extends Model
{
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Busca a la persona para no duplicarla: primero por id, luego por celular, luego por nombre exacto.
     * Si cambió de área, se mueve; sus tickets anteriores conservan el área donde se pidieron.
     */
    public static function registrar(?int $id, string $nombre, ?string $telefono, int $areaId): self
    {
        $s = match (true) {
            (bool) $id => self::findOrFail($id),
            (bool) $telefono => self::firstOrNew(['telefono' => $telefono]),
            default => self::firstOrNew(['nombre' => $nombre]), // ponytail: homónimos sin celular se fusionan; pedir celular lo evita
        };
        $s->fill(['nombre' => $nombre, 'telefono' => $telefono ?: $s->telefono, 'area_id' => $areaId])->save();

        return $s;
    }
}
