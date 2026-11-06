<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'rol', 'especialidad', 'activo', 'google_id', 'ultimo_ingreso'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    // "usuario" pide soporte desde el portal; el resto es personal de la OTI. Solo el administrador (jefe) y la mesa cambian roles.
    const ROLES = ['usuario' => 'Usuario', 'mesa' => 'Mesa de ayuda', 'tecnico' => 'Técnico / especialista', 'jefe' => 'Administrador'];

    // Iguales a los defaults de la tabla, para que un usuario recién creado ya los tenga en memoria.
    protected $attributes = ['rol' => 'usuario', 'activo' => true];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_ingreso' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function esPersonal(): bool
    {
        return $this->rol !== 'usuario';
    }

    public function puedeAsignar(): bool
    {
        return in_array($this->rol, ['mesa', 'jefe']);
    }

    /** Privilegios de administrador. La mesa de ayuda tiene los mismos que el administrador. */
    public function esJefe(): bool
    {
        return $this->puedeAsignar();
    }

    /** Ruta de inicio según el rol. */
    public function inicio(): string
    {
        return route(match ($this->rol) {
            'usuario' => 'portal',
            'tecnico' => 'tickets.mios',
            'jefe' => 'panel',
            default => 'tickets.index',
        });
    }

    /** Tickets que atiende como técnico. */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'tecnico_id');
    }

    /** Tickets a los que va de apoyo. */
    public function apoyos()
    {
        return $this->belongsToMany(Ticket::class, 'ticket_apoyos')->withTimestamps();
    }

    /** Su ficha en el directorio (mismo correo), si ya pidió algo o la mesa lo registró. */
    public function solicitante()
    {
        return $this->hasOne(Solicitante::class, 'email', 'email');
    }
}
