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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pedido sin correo institucional: cualquiera lo registra sin cuenta y la mesa de ayuda lo valida
 * antes de que entre a la cola de atención.
 */
class PedidoPublicoController extends Controller
{
    public function create(): View
    {
        return view('pedido.create', ['sedes' => Sede::with('areas')->orderBy('nombre')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Campo trampa invisible: solo un bot lo llena. Se responde como si todo saliera bien.
        if ($request->filled('sitio_web')) {
            return redirect()->route('pedido.publico');
        }

        $data = $request->validate([
            'nombre' => 'required|max:120',
            'telefono' => 'required|regex:/^\+?\d{9,12}$/',
            'email' => 'nullable|email|max:120',
            'sede_id' => 'required|exists:sedes,id',
            'area_id' => ['nullable', 'required_without:area_nueva', Rule::exists('areas', 'id')->where('sede_id', $request->sede_id)],
            'area_nueva' => 'nullable|required_without:area_id|max:80',
            'descripcion' => 'required|max:2000',
        ] + TicketFoto::REGLAS, [
            'telefono.regex' => 'Escribe tu celular solo con números (9 dígitos).',
            'area_id.required_without' => 'Elige tu área o escribe su nombre.',
        ]);

        $categoria = Categoria::firstWhere('nombre', 'Por clasificar');

        $ticket = DB::transaction(function () use ($data, $categoria, $request) {
            $areaId = $data['area_id'] ?? Area::firstOrCreate(['sede_id' => $data['sede_id'], 'nombre' => trim($data['area_nueva'])])->id;

            // Si ya está en el directorio (mismo celular) se reutiliza sin tocar sus datos: este pedido aún no está validado.
            $persona = Solicitante::firstWhere('telefono', $data['telefono'])
                ?? Solicitante::create([
                    'nombre' => $data['nombre'], 'telefono' => $data['telefono'], 'area_id' => $areaId,
                    'email' => filled($data['email'] ?? null) && ! Solicitante::where('email', $data['email'])->exists() ? $data['email'] : null,
                ]);

            $ticket = Ticket::forceCreate([
                'sede_id' => $data['sede_id'], 'area_id' => $areaId, 'solicitante_id' => $persona->id,
                'canal' => 'formulario', 'categoria_id' => $categoria->id, 'prioridad' => $categoria->prioridad,
                'descripcion' => $data['descripcion'], 'estado' => 'por_validar',
            ]);
            TicketFoto::guardarTodas($ticket, $request->file('fotos', []), 'pedido', null);

            return $ticket;
        });

        try {
            Notification::send(User::where('activo', true)->whereIn('rol', ['mesa', 'jefe'])->get(), new AvisoTicket($ticket, 'Pedido por validar (sin correo UNIQ)'));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('seguimiento', $ticket->token)->with('registrado', true);
    }
}
