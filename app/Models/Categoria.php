<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'especialidad', 'prioridad', 'activa', 'creada_por'])]
class Categoria extends Model
{
    const ESPECIALIDADES = ['soporte' => 'Soporte', 'redes' => 'Redes', 'servidores' => 'Servidores', 'web' => 'Web y publicaciones'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creada_por');
    }
}
