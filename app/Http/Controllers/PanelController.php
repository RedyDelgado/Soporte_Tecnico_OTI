<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PanelController extends Controller
{
    // ponytail: calcula en PHP sobre los tickets del mes; pasar a SQL agregado si se superan ~10 mil por mes
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $mes = Carbon::parse($request->query('mes', now()->format('Y-m')).'-01');
        $rango = [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()];

        $base = fn () => Ticket::validos()
            ->when(! $user->puedeAsignar(), fn ($q) => $q->where('tecnico_id', $user->id))
            ->when($request->query('sede'), fn ($q, $s) => $q->where('sede_id', $s));

        $abiertos = $base()->abiertos()->get();
        $creados = $base()->whereBetween('created_at', $rango)->with('sede', 'categoria')->get();
        $resueltos = $base()->where('estado', 'resuelto')->whereBetween('resuelto_at', $rango)->get();

        $kpi = [
            'abiertos' => $abiertos->count(),
            'vencidos' => $abiertos->filter(fn ($t) => $t->semaforo() === 'rojo')->count(),
            'resueltos' => $resueltos->count(),
            'sla' => $this->pct($resueltos->filter(fn ($t) => $t->resuelto_at <= $t->vence_at)->count(), $resueltos->count()),
            'respuesta' => $this->minutos($resueltos->whereNotNull('atencion_at'), 'created_at', 'atencion_at'),
            'solucion' => $this->minutos($resueltos, 'created_at', 'resuelto_at'),
            'reabiertos' => $this->pct($resueltos->where('reaperturas', '>', 0)->count(), $resueltos->count()),
            'remota' => $this->pct($resueltos->where('modalidad', 'remota')->count(), $resueltos->count()),
        ];

        $porEstado = collect(Ticket::ABIERTOS)->mapWithKeys(fn ($e) => [Ticket::ESTADOS[$e] => $abiertos->where('estado', $e)->count()]);
        $porSede = $creados->countBy(fn ($t) => $t->sede->nombre)->sortDesc();
        $porCategoria = $creados->countBy(fn ($t) => $t->categoria->nombre)->sortDesc()->take(10);

        $equipo = ! $user->puedeAsignar() ? collect() : User::where('activo', true)->whereIn('rol', ['tecnico', 'jefe'])->orderBy('name')->get()
            ->map(function ($u) use ($abiertos, $resueltos) {
                $r = $resueltos->where('tecnico_id', $u->id);

                return [
                    'nombre' => $u->name,
                    'abiertos' => $abiertos->where('tecnico_id', $u->id)->count(),
                    'resueltos' => $r->count(),
                    'solucion' => $this->minutos($r, 'created_at', 'resuelto_at'),
                    'sla' => $this->pct($r->filter(fn ($t) => $t->resuelto_at <= $t->vence_at)->count(), $r->count()),
                ];
            })->filter(fn ($f) => $f['abiertos'] || $f['resueltos'])->values();

        $semanas = collect(range(7, 0))->map(fn ($i) => now()->startOfWeek()->subWeeks($i));
        $desde = $semanas->first();
        $tc = $base()->where('created_at', '>=', $desde)->pluck('created_at');
        $tr = $base()->where('resuelto_at', '>=', $desde)->pluck('resuelto_at');
        $tendencia = [
            'labels' => $semanas->map(fn ($s) => $s->format('d/m'))->all(),
            'creados' => $semanas->map(fn ($s) => $tc->filter(fn ($d) => $d->between($s, $s->copy()->endOfWeek()))->count())->all(),
            'resueltos' => $semanas->map(fn ($s) => $tr->filter(fn ($d) => $d->between($s, $s->copy()->endOfWeek()))->count())->all(),
        ];

        return view('panel', compact('kpi', 'porEstado', 'porSede', 'porCategoria', 'equipo', 'tendencia', 'mes') + [
            'sedes' => Sede::orderBy('nombre')->get(),
        ]);
    }

    private function pct(int $parte, int $total): ?int
    {
        return $total ? (int) round($parte * 100 / $total) : null;
    }

    private function minutos($tickets, string $desde, string $hasta): ?int
    {
        return $tickets->isEmpty() ? null
            : (int) round($tickets->avg(fn ($t) => $t->$desde->diffInMinutes($t->$hasta)));
    }
}
