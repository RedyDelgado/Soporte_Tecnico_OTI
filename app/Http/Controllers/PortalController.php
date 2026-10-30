<?php

namespace App\Http\Controllers;

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

/** Lo que ve cualquier persona de la UNIQ: sus pedidos y el formulario para registrar uno. */
class PortalController extends Controller
{
    public function index(Request $request)
    {
        $persona = $request->user()->solicitante;

        return view('portal.index', [
            'pedidos' => $persona
                ? $persona->tickets()->with('categoria', 'tecnico', 'apoyos', 'area')->latest('id')->paginate($this->porPagina($request))->withQueryString()
                : null,
        ]);
    }

    public function create(Request $request)
    {
        return view('portal.create', [
            'persona' => $request->user()->solicitante?->load('area'),
            'sedes' => Sede::with('areas')->orderBy('nombre')->get(),
            'categorias' => Categoria::where('activa', true)->where('nombre', '!=', 'Por clasificar')->orderBy('especialidad')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $persona = $user->solicitante;

        $data = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'area_id' => ['required', Rule::exists('areas', 'id')->where('sede_id', $request->sede_id)],
            // Choca solo con el celular de otra persona que ya tiene cuenta; si la mesa la registró sin correo, se vincula abajo.
            'telefono' => ['nullable', 'max:20', $persona ? Rule::unique('solicitantes')->ignore($persona) : Rule::unique('solicitantes')->whereNotNull('email')],
            'categoria_id' => 'nullable|exists:categorias,id',
            'descripcion' => 'required|max:2000',
        ] + TicketFoto::REGLAS);

        $categoria = isset($data['categoria_id']) ? Categoria::find($data['categoria_id']) : Categoria::firstWhere('nombre', 'Por clasificar');

        $ticket = DB::transaction(function () use ($data, $user, $persona, $categoria, $request) {
            // Su ficha en el directorio: por correo; si la mesa ya lo había registrado por celular, se vincula.
            $persona ??= filled($data['telefono'] ?? null)
                ? Solicitante::whereNull('email')->where('telefono', $data['telefono'])->first()
                : null;
            $persona ??= new Solicitante;
            $persona->fill(['nombre' => $user->name, 'email' => $user->email, 'area_id' => $data['area_id'],
                'telefono' => ($data['telefono'] ?? null) ?: $persona->telefono])->save();

            $ticket = Ticket::create([
                'sede_id' => $data['sede_id'], 'area_id' => $data['area_id'], 'solicitante_id' => $persona->id,
                'canal' => 'portal', 'categoria_id' => $categoria->id, 'prioridad' => $categoria->prioridad,
                'descripcion' => $data['descripcion'], 'creado_por' => $user->id,
            ]);
            TicketFoto::guardarTodas($ticket, $request->file('fotos', []), 'pedido', $user);

            return $ticket;
        });

        try {
            Notification::send(User::where('activo', true)->where('rol', '!=', 'usuario')->get(), new AvisoTicket($ticket, 'Nuevo pedido desde el portal'));
        } catch (\Throwable $e) {
            report($e);
        }

        $aviso = Ticket::disponibilidad()['todos_ocupados']
            ? ' Nuestros técnicos están ocupados en este momento; apenas se libere uno, te atenderá.'
            : ' Te avisaremos el avance.';

        return redirect()->route('portal')->with('ok', "Tu pedido {$ticket->codigo} fue registrado.".$aviso);
    }
}
