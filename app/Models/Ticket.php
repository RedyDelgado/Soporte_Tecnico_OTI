<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['sede_id', 'area_id', 'solicitante_id', 'canal', 'categoria_id', 'prioridad', 'descripcion', 'creado_por'])]
class Ticket extends Model
{
    const ESTADOS = [
        'pendiente' => 'Por asignar',
        'asignado' => 'Asignado',
        'en_camino' => 'En camino',
        'en_atencion' => 'En atención',
        'resuelto' => 'Resuelto',
    ];

    const ABIERTOS = ['pendiente', 'asignado', 'en_camino', 'en_atencion'];

    const SLA_HORAS = ['alta' => 4, 'media' => 24, 'baja' => 72];

    const CANALES = ['whatsapp' => 'WhatsApp', 'correo' => 'Correo', 'llamada' => 'Llamada', 'presencial' => 'Presencial', 'portal' => 'Portal web'];

    protected function casts(): array
    {
        return [
            'vence_at' => 'datetime',
            'asignado_at' => 'datetime',
            'atencion_at' => 'datetime',
            'resuelto_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $t) {
            $t->token = Str::random(32);
            $t->vence_at = now()->addHours(self::SLA_HORAS[$t->prioridad]);
        });

        static::created(function (Ticket $t) {
            $t->forceFill(['codigo' => 'OTI-'.str_pad($t->id, 4, '0', STR_PAD_LEFT)])->saveQuietly();
            $t->eventos()->create(['user_id' => $t->creado_por, 'estado' => 'pendiente', 'nota' => 'Registrado por '.self::CANALES[$t->canal]]);
        });
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function solicitante()
    {
        return $this->belongsTo(Solicitante::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function tecnico()
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    /** Técnicos que acompañan al responsable (pueden ir dos o más a un mismo soporte). */
    public function apoyos()
    {
        return $this->belongsToMany(User::class, 'ticket_apoyos')->withTimestamps()->orderBy('name');
    }

    /** Responsable o apoyo: puede avanzar y cerrar el ticket. */
    public function participa(User $user): bool
    {
        return $this->tecnico_id === $user->id || $this->apoyos->contains($user);
    }

    public function fotos()
    {
        return $this->hasMany(TicketFoto::class);
    }

    /** Tickets donde la persona es responsable o va de apoyo. */
    public function scopeDeTecnico($q, int $userId)
    {
        return $q->where(fn ($w) => $w->where('tecnico_id', $userId)->orWhereHas('apoyos', fn ($a) => $a->where('users.id', $userId)));
    }

    /** Orden de atención: primero la prioridad (alta, media, baja), luego lo que vence antes. */
    public function scopeOrdenCola($q)
    {
        return $q->orderByRaw("FIELD(prioridad, 'alta', 'media', 'baja')")->orderBy('vence_at');
    }

    /**
     * Estado del equipo para avisar al usuario: si todos los técnicos están atendiendo algo ahora
     * (en camino o en atención) y cuántos pedidos esperan técnico.
     */
    public static function disponibilidad(): array
    {
        $tecnicos = User::where('activo', true)->where('rol', 'tecnico')->pluck('id');
        $ocupados = self::whereIn('estado', ['en_camino', 'en_atencion'])->get(['id', 'tecnico_id'])->load('apoyos:id')
            ->flatMap(fn ($t) => $t->apoyos->pluck('id')->push($t->tecnico_id))->unique();

        return [
            'todos_ocupados' => $tecnicos->isNotEmpty() && $tecnicos->diff($ocupados)->isEmpty(),
            'en_espera' => self::where('estado', 'pendiente')->count(),
        ];
    }

    /** Mensaje para quien pidió el soporte: le explica por qué espera y que lo atenderán apenas se pueda. */
    public function mensajeParaSolicitante(): ?array
    {
        return match ($this->estado) {
            'pendiente' => (function () {
                $antes = $this->puestoEnCola();
                $cola = $antes ? " Hay {$antes} ".($antes === 1 ? 'pedido' : 'pedidos').' antes que el tuyo.' : '';

                return self::disponibilidad()['todos_ocupados']
                    ? ['ocupado', 'Nuestros técnicos están atendiendo otros pedidos en este momento.'.$cola.' Apenas se libere uno, te atenderá.']
                    : ['espera', 'Tu pedido está por asignarse a un técnico.'.$cola];
            })(),
            'asignado' => $this->equipoOcupadoEnOtro()
                ? ['ocupado', "{$this->tecnico->name} está atendiendo otro pedido en este momento. Irá a ayudarte apenas termine."]
                : ['espera', "{$this->tecnico->name} atenderá tu pedido."],
            'en_camino' => ['camino', "{$this->tecnico->name} va en camino."],
            default => null,
        };
    }

    /** ¿Alguien del equipo de este ticket está en camino o atendiendo otro ticket ahora? */
    private function equipoOcupadoEnOtro(): bool
    {
        $equipo = $this->apoyos->pluck('id')->push($this->tecnico_id);

        return self::whereIn('estado', ['en_camino', 'en_atencion'])->where('id', '!=', $this->id)
            ->where(fn ($q) => $q->whereIn('tecnico_id', $equipo)->orWhereHas('apoyos', fn ($a) => $a->whereIn('users.id', $equipo)))
            ->exists();
    }

    /** Cuántos pedidos sin técnico se registraron antes que este. */
    public function puestoEnCola(): int
    {
        return self::where('estado', 'pendiente')->where('id', '<', $this->id)->count();
    }

    public function eventos()
    {
        return $this->hasMany(TicketEvento::class)->latest('id');
    }

    public function scopeAbiertos($q)
    {
        return $q->whereIn('estado', self::ABIERTOS);
    }

    /** verde | ambar | rojo (abiertos) — cumplido | fuera (resueltos) */
    public function semaforo(): string
    {
        if ($this->estado === 'resuelto') {
            return $this->resuelto_at <= $this->vence_at ? 'cumplido' : 'fuera';
        }
        $total = $this->created_at->diffInSeconds($this->vence_at);
        $usado = $this->created_at->diffInSeconds(now());

        return match (true) {
            $usado >= $total => 'rojo',
            $usado >= $total * 0.75 => 'ambar',
            default => 'verde',
        };
    }

    public function cambiarEstado(string $estado, User $user, ?string $nota = null): void
    {
        $this->estado = $estado;
        match ($estado) {
            'asignado' => $this->asignado_at ??= now(),
            'en_atencion' => $this->atencion_at ??= now(),
            'resuelto' => $this->resuelto_at = now(),
            default => null,
        };
        $this->save();
        $this->eventos()->create(['user_id' => $user->id, 'estado' => $estado, 'nota' => $nota]);
    }

    public function linkWhatsapp(): ?string
    {
        if (! $this->solicitante->telefono) {
            return null;
        }
        $num = preg_replace('/\D/', '', $this->solicitante->telefono);
        if (strlen($num) === 9) {
            $num = '51'.$num;
        }
        $texto = "Hola {$this->solicitante->nombre}, registramos su pedido con el código {$this->codigo}. "
            .'Puede ver el avance aquí: '.route('seguimiento', $this->token);

        return 'https://wa.me/'.$num.'?text='.rawurlencode($texto);
    }
}
