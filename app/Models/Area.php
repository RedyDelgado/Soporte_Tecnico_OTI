<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sede_id', 'nombre'])]
class Area extends Model
{
    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    /** Personas que hoy trabajan en el área. */
    public function solicitantes()
    {
        return $this->hasMany(Solicitante::class);
    }

    /** Tickets pedidos desde el área (aunque la persona ya se haya ido). */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
