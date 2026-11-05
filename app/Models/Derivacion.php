<?php

namespace App\Models;

use Database\Factories\DerivacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['asignado_a', 'asignado_por', 'nro_proveido', 'descripcion'])]
class Derivacion extends Model
{
    /** @use HasFactory<DerivacionFactory> */
    use HasFactory;

    protected $table = 'derivaciones';

    protected $attributes = ['estado' => 'abierta'];

    protected function casts(): array
    {
        return ['cerrada_at' => 'datetime'];
    }

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function asignador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    /** Solo el especialista asignado o el jefe la cierran, y una sola vez. */
    public function puedeCerrar(User $user): bool
    {
        return $this->estado === 'abierta' && ($this->asignado_a === $user->id || $user->esJefe());
    }

    public function cerrar(User $user, string $resolucion): void
    {
        $this->forceFill(['estado' => 'cerrada', 'resolucion' => $resolucion, 'cerrada_por' => $user->id, 'cerrada_at' => now()])->save();
    }
}
