<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Sede;
use App\Models\Solicitante;
use App\Models\Ticket;
use App\Models\TicketFoto;
use App\Models\User;
use App\Notifications\AvisoTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->query('f', 'pend');
        $q = Ticket::with('sede', 'area', 'solicitante', 'categoria', 'tecnico', 'apoyos')
            ->when($request->query('sede'), fn ($q, $s) => $q->where('sede_id', $s));

        $tickets = match ($filtro) {
            'cur' => $q->whereIn('estado', ['asignado', 'en_camino', 'en_atencion'])->orderBy('vence_at'),
            'all' => $q->latest('id'),
            default => $q->where('estado', 'pendiente')->orderBy('vence_at'),
        };
        $tickets = $tickets->paginate($this->porPagina($request))->withQueryString();

        $abiertos = Ticket::abiertos()->get(['id', 'estado', 'created_at', 'vence_at']);
        $resumen = [
            'pend' => $abiertos->where('estado', 'pendiente')->count(),
            'cur' => $abiertos->where('estado', '!=', 'pendiente')->count(),
            'rojo' => $abiertos->filter(fn ($t) => $t->semaforo() === 'rojo')->count(),
            'hoy' => Ticket::where('estado', 'resuelto')->whereDate('resuelto_at', today())->count(),
            'max' => Ticket::max('id') ?? 0,
        ];

        $data = compact('tickets', 'resumen', 'filtro') + ['tecnicos' => $this->tecnicos()];

        return $request->boolean('parcial')
            ? view('tickets._bandeja', $data)
            : view('tickets.index', $data);
    }

    public function create()
    {
        return view('tickets.create', [
            'sedes' => Sede::with('areas')->orderBy('nombre')->get(),
            'categorias' => Categoria::where('activa', true)->orderBy('especialidad')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->puedeAsignar(), 403);

        $data = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'area_id' => ['required', Rule::exists('areas', 'id')->where('sede_id', $request->sede_id)],
            'solicitante_id' => 'nullable|exists:solicitantes,id',
            'nombre' => 'required|max:120',
            // Sin persona elegida, un celular ya registrado reutiliza a esa persona; elegida, no puede tomar el celular de otra.
            'telefono' => array_filter(['nullable', 'max:20', $request->filled('solicitante_id') ? Rule::unique('solicitantes')->ignore($request->solicitante_id) : null]),
            'canal' => 'required|in:'.implode(',', array_keys(Ticket::CANALES)),
            'categoria_id' => 'required|exists:categorias,id',
            'prioridad' => 'required|in:'.implode(',', array_keys(Ticket::SLA_HORAS)),
            'descripcion' => 'required|max:2000',
            'tecnico_id' => ['nullable', $this->reglaTecnico()],
        ] + TicketFoto::REGLAS);

        $ticket = DB::transaction(function () use ($data, $request) {
            $persona = Solicitante::registrar($data['solicitante_id'] ?? null, $data['nombre'], $data['telefono'] ?? null, $data['area_id']);

            $ticket = Ticket::create(collect($data)->only('sede_id', 'area_id', 'canal', 'categoria_id', 'prioridad', 'descripcion')->all()
                + ['solicitante_id' => $persona->id, 'creado_por' => $request->user()->id]);
            TicketFoto::guardarTodas($ticket, $request->file('fotos', []), 'pedido', $request->user());

            return $ticket;
        });
        $this->avisar(User::where('activo', true)->where('id', '!=', $request->user()->id)->get(), $ticket, 'Nuevo pedido');

        if ($request->filled('tecnico_id')) {
            $this->asignarA($ticket, User::find($data['tecnico_id']), $request->user());
        }

        return redirect()->route('tickets.show', $ticket)->with('creado', true);
    }

    public function show(Ticket $ticket)
    {
        return view('tickets.show', [
            'ticket' => $ticket->load('sede', 'area', 'solicitante.area', 'categoria', 'tecnico', 'apoyos', 'eventos.user', 'fotos'),
            'tecnicos' => $this->tecnicos(),
            'categorias' => Categoria::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function asignar(Request $request, Ticket $ticket)
    {
        abort_unless($request->user()->puedeAsignar() && $ticket->estado !== 'resuelto', 403);
        $request->validate(['tecnico_id' => ['required', $this->reglaTecnico()]]);

        $this->asignarA($ticket, User::find($request->tecnico_id), $request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Ticket asignado');
    }

    /** Técnicos que van de apoyo con el responsable. Se reemplaza la lista completa. */
    public function apoyos(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->estado !== 'resuelto' && $ticket->tecnico_id, 403);
        $request->validate(['apoyos' => 'nullable|array', 'apoyos.*' => [$this->reglaTecnico()]]);

        $ids = collect($request->input('apoyos', []))->map(fn ($i) => (int) $i)->reject(fn ($i) => $i === $ticket->tecnico_id)->unique();
        $cambios = $ticket->apoyos()->sync($ids);

        if ($cambios['attached'] || $cambios['detached']) {
            $nombres = User::whereIn('id', $ids)->pluck('name')->implode(', ');
            $ticket->eventos()->create(['user_id' => $request->user()->id, 'estado' => $ticket->estado, 'nota' => $nombres ? "Apoyo: $nombres" : 'Sin apoyo']);
            $this->avisar(User::whereIn('id', $cambios['attached'])->get(), $ticket, 'Vas de apoyo en un ticket');
        }

        return back()->with('ok', $ids->isEmpty() ? 'Se quitó el apoyo' : 'Equipo actualizado');
    }

    /** La mesa corrige categoría y prioridad (p. ej. pedidos del portal "Por clasificar"); el plazo se recalcula. */
    public function clasificar(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->estado !== 'resuelto', 403);
        $data = $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'prioridad' => 'required|in:'.implode(',', array_keys(Ticket::SLA_HORAS)),
        ]);

        $ticket->fill($data);
        $ticket->vence_at = $ticket->created_at->copy()->addHours(Ticket::SLA_HORAS[$data['prioridad']]);
        $ticket->save();

        return back()->with('ok', 'Clasificación actualizada');
    }

    public function estado(Request $request, Ticket $ticket)
    {
        abort_unless($this->esResponsable($request->user(), $ticket), 403);
        // Solo hacia adelante: asignado → en camino → en atención.
        $permitidos = ['asignado' => ['en_camino', 'en_atencion'], 'en_camino' => ['en_atencion']][$ticket->estado] ?? [];
        $request->validate(['estado' => ['required', Rule::in($permitidos)]]);

        $ticket->cambiarEstado($request->estado, $request->user());

        return back()->with('ok', Ticket::ESTADOS[$request->estado]);
    }

    public function resolver(Request $request, Ticket $ticket)
    {
        abort_unless($this->esResponsable($request->user(), $ticket), 403);

        $data = $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'diagnostico' => 'required|min:10|max:2000',
            'solucion' => 'required|min:10|max:2000',
            'modalidad' => 'required|in:remota,presencial',
        ] + TicketFoto::REGLAS);

        $ticket->forceFill(collect($data)->except('fotos')->all());
        TicketFoto::guardarTodas($ticket, $request->file('fotos', []), 'cierre', $request->user());
        $ticket->cambiarEstado('resuelto', $request->user(), ucfirst($data['modalidad']));

        $this->avisar(User::whereIn('rol', ['mesa', 'jefe'])->where('activo', true)->get(), $ticket, 'Ticket resuelto por '.$request->user()->name);

        return redirect()->route('tickets.mios')->with('ok', "{$ticket->codigo} resuelto");
    }

    public function reabrir(Request $request, Ticket $ticket)
    {
        abort_unless($request->user()->puedeAsignar() && $ticket->estado === 'resuelto', 403);
        $request->validate(['motivo' => 'required|max:255']);

        $ticket->reaperturas++;
        $ticket->resuelto_at = null;
        $ticket->cambiarEstado('asignado', $request->user(), 'Reabierto: '.$request->motivo);
        $this->avisar(collect([$ticket->tecnico])->filter(), $ticket, 'Ticket reabierto');

        return back()->with('ok', 'Ticket reabierto');
    }

    public function mios(Request $request)
    {
        // Responsable o apoyo. La cola se atiende por prioridad: el primero es el que toca ahora.
        $mios = Ticket::deTecnico($request->user()->id)->with('sede', 'area', 'categoria', 'tecnico', 'apoyos');

        return view('tickets.mios', [
            'abiertos' => (clone $mios)->abiertos()->ordenCola()->get(),
            'resueltos' => $mios->where('estado', 'resuelto')->latest('resuelto_at')->paginate($this->porPagina($request))->withQueryString(),
        ]);
    }

    public function soluciones(Request $request)
    {
        $q = trim($request->query('q', ''));
        $resultados = $q === '' ? null : Ticket::with('categoria', 'tecnico', 'area')
            ->where('estado', 'resuelto')
            ->where(fn ($w) => $w->where('descripcion', 'like', "%$q%")
                ->orWhere('diagnostico', 'like', "%$q%")
                ->orWhere('solucion', 'like', "%$q%"))
            ->latest('resuelto_at')->paginate($this->porPagina($request))->withQueryString(); // ponytail: LIKE, pasar a FULLTEXT si son miles de tickets

        return view('tickets.soluciones', compact('q', 'resultados'));
    }

    /** Autocompletado de personas por nombre o celular. */
    public function buscarPersona(Request $request)
    {
        $q = trim($request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return Solicitante::with('area.sede')
            ->where(fn ($w) => $w->where('nombre', 'like', "%$q%")->orWhere('telefono', 'like', "%$q%"))
            ->orderBy('nombre')->limit(8)->get()
            ->map(fn ($s) => [
                'id' => $s->id, 'nombre' => $s->nombre, 'telefono' => $s->telefono,
                'area_id' => $s->area_id, 'area' => $s->area->nombre, 'sede_id' => $s->area->sede_id, 'sede' => $s->area->sede->nombre,
            ]);
    }

    /** Registrar un área nueva desde un formulario. */
    public function nuevaArea(Request $request)
    {
        $data = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'nombre' => ['required', 'max:80', Rule::unique('areas')->where('sede_id', $request->sede_id)],
        ]);

        $area = Area::create($data);

        return $request->expectsJson()
            ? response()->json($area->only('id', 'sede_id', 'nombre'))
            : back()->with('ok', "Área {$area->nombre} registrada");
    }

    public function seguimiento(string $token)
    {
        $ticket = Ticket::where('token', $token)->with('sede', 'area', 'categoria', 'tecnico', 'apoyos', 'eventos')->firstOrFail();

        return view('tickets.seguimiento', compact('ticket'));
    }

    private function asignarA(Ticket $ticket, User $tecnico, User $por): void
    {
        $ticket->tecnico_id = $tecnico->id;
        $ticket->apoyos()->detach($tecnico->id); // si iba de apoyo, ahora es el responsable
        $ticket->cambiarEstado('asignado', $por, 'Asignado a '.$tecnico->name);
        $this->avisar(collect([$tecnico]), $ticket, 'Te asignaron un ticket');
    }

    private function esResponsable(User $user, Ticket $ticket): bool
    {
        return $ticket->estado !== 'resuelto' && $ticket->tecnico_id && ($ticket->participa($user) || $user->esJefe());
    }

    private function reglaTecnico()
    {
        return Rule::exists('users', 'id')->where('activo', true)->whereIn('rol', ['tecnico', 'jefe']);
    }

    private function tecnicos()
    {
        $enCurso = fn ($q) => $q->whereIn('estado', ['en_camino', 'en_atencion']);

        // abiertos = su cola; ocupado = está en camino o atendiendo algo ahora (como responsable o apoyo).
        return User::where('activo', true)->whereIn('rol', ['tecnico', 'jefe'])
            ->withCount(['tickets as abiertos' => fn ($q) => $q->abiertos(), 'tickets as atendiendo' => $enCurso, 'apoyos as apoyando' => $enCurso])
            ->orderBy('name')->get()
            ->each(fn ($u) => $u->ocupado = $u->atendiendo + $u->apoyando > 0);
    }

    private function avisar($users, Ticket $ticket, string $titulo): void
    {
        try {
            Notification::send($users, new AvisoTicket($ticket, $titulo));
        } catch (\Throwable $e) {
            report($e); // un fallo del push no debe impedir guardar el ticket
        }
    }
}
