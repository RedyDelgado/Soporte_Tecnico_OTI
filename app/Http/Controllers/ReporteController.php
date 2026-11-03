<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Sede;
use App\Models\Solicitante;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Reportes del personal OTI: quién pide más (áreas y personas), qué se pide más, qué se repite y el avance de cada técnico. */
class ReporteController extends Controller
{
    public function index(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $base = fn () => $this->filtrados($request, $desde, $hasta);
        $minutos = 'TIMESTAMPDIFF(MINUTE, tickets.created_at, tickets.resuelto_at)';

        $resumen = $base()->selectRaw("count(*) total,
            sum(estado = 'resuelto') resueltos,
            sum(estado <> 'resuelto') abiertos,
            sum(estado = 'resuelto' and resuelto_at <= vence_at) en_plazo,
            sum(reaperturas > 0) reabiertos,
            avg(case when estado = 'resuelto' then $minutos end) minutos,
            count(distinct solicitante_id) personas,
            count(distinct tickets.area_id) areas")->first();

        $areas = $base()->join('areas', 'areas.id', '=', 'tickets.area_id')->join('sedes', 'sedes.id', '=', 'tickets.sede_id')
            ->selectRaw("tickets.area_id, areas.nombre area, sedes.nombre sede, count(*) total,
                sum(estado = 'resuelto') resueltos, sum(estado <> 'resuelto') abiertos,
                avg(case when estado = 'resuelto' then $minutos end) minutos,
                count(distinct solicitante_id) personas, max(tickets.created_at) ultimo")
            ->groupBy('tickets.area_id', 'areas.nombre', 'sedes.nombre')->orderByDesc('total')->limit(15)->get();

        $personas = $base()->join('solicitantes', 'solicitantes.id', '=', 'tickets.solicitante_id')
            ->join('areas', 'areas.id', '=', 'solicitantes.area_id')
            ->selectRaw("solicitante_id, solicitantes.nombre, solicitantes.email, areas.nombre area_actual, count(*) total,
                sum(estado <> 'resuelto') abiertos, max(tickets.created_at) ultimo")
            ->groupBy('solicitante_id', 'solicitantes.nombre', 'solicitantes.email', 'areas.nombre')->orderByDesc('total')->limit(15)->get();
        // Problema más frecuente de cada persona del top.
        $frecuente = $base()->whereIn('solicitante_id', $personas->pluck('solicitante_id'))
            ->join('categorias', 'categorias.id', '=', 'tickets.categoria_id')
            ->selectRaw('solicitante_id, categorias.nombre, count(*) n')->groupBy('solicitante_id', 'categorias.nombre')
            ->get()->sortByDesc('n')->unique('solicitante_id')->pluck('nombre', 'solicitante_id');

        $problemas = $base()->join('categorias', 'categorias.id', '=', 'tickets.categoria_id')
            ->selectRaw("categoria_id, categorias.nombre, categorias.especialidad, count(*) total,
                avg(case when estado = 'resuelto' then $minutos end) minutos,
                sum(reaperturas > 0) reabiertos, sum(modalidad = 'remota') remotos, sum(estado = 'resuelto') resueltos")
            ->groupBy('categoria_id', 'categorias.nombre', 'categorias.especialidad')->orderByDesc('total')->limit(10)->get();

        // El mismo problema en la misma área 3 o más veces: suele indicar un equipo para reemplazar.
        $recurrentes = $base()->join('areas', 'areas.id', '=', 'tickets.area_id')->join('sedes', 'sedes.id', '=', 'tickets.sede_id')
            ->join('categorias', 'categorias.id', '=', 'tickets.categoria_id')
            ->selectRaw('tickets.area_id, categoria_id, areas.nombre area, sedes.nombre sede, categorias.nombre problema, count(*) total, max(tickets.created_at) ultimo')
            ->groupBy('tickets.area_id', 'categoria_id', 'areas.nombre', 'sedes.nombre', 'categorias.nombre')
            ->havingRaw('count(*) >= 3')->orderByDesc('total')->limit(15)->get();

        // Avance de cada técnico con los pedidos que tiene como responsable.
        $tecnicos = $base()->join('users', 'users.id', '=', 'tickets.tecnico_id')
            ->selectRaw("tecnico_id, users.name, users.especialidad, count(*) total,
                sum(estado = 'resuelto') resueltos, sum(estado <> 'resuelto') abiertos,
                sum(estado = 'resuelto' and resuelto_at <= vence_at) en_plazo, sum(reaperturas > 0) reabiertos,
                avg(case when estado = 'resuelto' then $minutos end) minutos")
            ->groupBy('tecnico_id', 'users.name', 'users.especialidad')->orderByDesc('resueltos')->orderByDesc('total')->get();

        $porSede = $base()->join('sedes', 'sedes.id', '=', 'tickets.sede_id')->selectRaw('sedes.nombre, count(*) total')->groupBy('sedes.nombre')->orderByDesc('total')->pluck('total', 'nombre');
        $porCanal = $base()->selectRaw('canal, count(*) total')->groupBy('canal')->orderByDesc('total')->pluck('total', 'canal');

        if ($tipo = $request->query('csv')) {
            return $this->csvSeccion($tipo, compact('areas', 'personas', 'frecuente', 'problemas', 'recurrentes', 'tecnicos'), $desde, $hasta);
        }

        return view('reportes.index', compact('resumen', 'areas', 'personas', 'frecuente', 'problemas', 'recurrentes', 'tecnicos', 'porSede', 'porCanal', 'desde', 'hasta') + [
            'sedes' => Sede::orderBy('nombre')->get(),
        ]);
    }

    /** Lista de tickets detrás de cada número del reporte (por área, persona o problema). */
    public function detalle(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);
        $q = $this->filtrados($request, $desde, $hasta)
            ->with('sede', 'area', 'solicitante', 'categoria', 'tecnico')
            ->when($request->query('area'), fn ($w, $v) => $w->where('tickets.area_id', $v))
            ->when($request->query('solicitante'), fn ($w, $v) => $w->where('solicitante_id', $v))
            ->when($request->query('categoria'), fn ($w, $v) => $w->where('categoria_id', $v))
            ->when($request->query('tecnico'), fn ($w, $v) => $w->where('tecnico_id', $v))
            ->latest('tickets.id');

        if ($request->boolean('csv')) {
            return $this->csv('detalle', ['Código', 'Fecha', 'Sede', 'Área', 'Solicitante', 'Categoría', 'Estado', 'Técnico', 'Descripción', 'Diagnóstico', 'Solución'],
                $q->get()->map(fn ($t) => [$t->codigo, $t->created_at->format('d/m/Y H:i'), $t->sede->nombre, $t->area->nombre, $t->solicitante->nombre,
                    $t->categoria->nombre, Ticket::ESTADOS[$t->estado], $t->tecnico?->name, $t->descripcion, $t->diagnostico, $t->solucion]), $desde, $hasta);
        }

        $titulo = match (true) {
            (bool) $request->query('area') => 'Pedidos del área '.optional(Area::find($request->query('area')))->nombre,
            (bool) $request->query('solicitante') => 'Pedidos de '.optional(Solicitante::find($request->query('solicitante')))->nombre,
            (bool) $request->query('tecnico') => 'Pedidos atendidos por '.optional(User::find($request->query('tecnico')))->name,
            (bool) $request->query('categoria') => 'Pedidos de «'.optional(Categoria::find($request->query('categoria')))->nombre.'»',
            default => 'Todos los pedidos',
        };

        return view('reportes.detalle', [
            'titulo' => $titulo,
            'tickets' => $q->paginate($this->porPagina($request))->withQueryString(),
            'desde' => $desde, 'hasta' => $hasta,
        ]);
    }

    private function rango(Request $request): array
    {
        $desde = Carbon::parse($request->query('desde', now()->subDays(29)->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->query('hasta', now()->toDateString()))->endOfDay();

        return [$desde, $hasta];
    }

    private function filtrados(Request $request, Carbon $desde, Carbon $hasta)
    {
        return Ticket::validos()
            ->whereBetween('tickets.created_at', [$desde, $hasta])
            ->when($request->query('sede'), fn ($q, $s) => $q->where('tickets.sede_id', $s));
    }

    private function csvSeccion(string $tipo, array $d, Carbon $desde, Carbon $hasta)
    {
        $h = fn ($m) => $m === null ? '' : round($m / 60, 1);

        return match ($tipo) {
            'areas' => $this->csv('areas', ['Sede', 'Área', 'Pedidos', 'Resueltos', 'Abiertos', 'Personas', 'Horas prom. solución', 'Último pedido'],
                $d['areas']->map(fn ($r) => [$r->sede, $r->area, $r->total, $r->resueltos, $r->abiertos, $r->personas, $h($r->minutos), $r->ultimo]), $desde, $hasta),
            'personas' => $this->csv('personas', ['Persona', 'Correo', 'Área actual', 'Pedidos', 'Abiertos', 'Problema más frecuente', 'Último pedido'],
                $d['personas']->map(fn ($r) => [$r->nombre, $r->email, $r->area_actual, $r->total, $r->abiertos, $d['frecuente'][$r->solicitante_id] ?? '', $r->ultimo]), $desde, $hasta),
            'problemas' => $this->csv('problemas', ['Problema', 'Especialidad', 'Pedidos', 'Resueltos', 'Remotos', 'Reabiertos', 'Horas prom. solución'],
                $d['problemas']->map(fn ($r) => [$r->nombre, Categoria::ESPECIALIDADES[$r->especialidad] ?? $r->especialidad, $r->total, $r->resueltos, $r->remotos, $r->reabiertos, $h($r->minutos)]), $desde, $hasta),
            'recurrentes' => $this->csv('recurrentes', ['Sede', 'Área', 'Problema', 'Veces', 'Último'],
                $d['recurrentes']->map(fn ($r) => [$r->sede, $r->area, $r->problema, $r->total, $r->ultimo]), $desde, $hasta),
            'tecnicos' => $this->csv('tecnicos', ['Técnico', 'Especialidad', 'Asignados', 'Resueltos', 'Abiertos', 'En plazo', 'Reabiertos', 'Horas prom. solución'],
                $d['tecnicos']->map(fn ($r) => [$r->name, Categoria::ESPECIALIDADES[$r->especialidad] ?? $r->especialidad, $r->total, $r->resueltos, $r->abiertos, $r->en_plazo, $r->reabiertos, $h($r->minutos)]), $desde, $hasta),
            default => abort(404),
        };
    }

    /** CSV con separador ";" y BOM para que Excel en español lo abra con tildes y columnas correctas. */
    private function csv(string $nombre, array $cabecera, $filas, Carbon $desde, Carbon $hasta)
    {
        $archivo = "reporte-{$nombre}-{$desde->format('Ymd')}-{$hasta->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($cabecera, $filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $cabecera, ';');
            foreach ($filas as $f) {
                fputcsv($out, $f, ';');
            }
            fclose($out);
        }, $archivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
