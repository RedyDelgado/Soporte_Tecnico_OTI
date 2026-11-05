<?php

namespace App\Http\Controllers;

use App\Models\Derivacion;
use App\Models\User;
use App\Notifications\AvisoDerivacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DerivacionController extends Controller
{
    /** El jefe y la mesa ven todas; el especialista, solo las suyas. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $estado = $request->query('estado', 'abierta');

        $derivaciones = Derivacion::with('especialista', 'asignador', 'cerrador')
            ->when(! $user->puedeAsignar(), fn ($q) => $q->where('asignado_a', $user->id))
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado === 'cerrada' ? 'cerrada' : 'abierta'))
            ->latest('id')->paginate($this->porPagina($request))->withQueryString();

        return view('derivaciones.index', [
            'derivaciones' => $derivaciones,
            'estado' => $estado,
            'especialistas' => $user->puedeAsignar() ? $this->especialistas()->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asignado_a' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $this->especialistas($q))],
            'nro_proveido' => 'required|max:50',
            'descripcion' => 'required|max:2000',
        ]);

        $derivacion = Derivacion::create($data + ['asignado_por' => $request->user()->id]);

        try {
            $derivacion->especialista->notify(new AvisoDerivacion($derivacion));
        } catch (\Throwable $e) {
            report($e); // un fallo del push no debe impedir guardar la derivación
        }

        return back()->with('ok', "Proveído {$derivacion->nro_proveido} derivado a {$derivacion->especialista->name}");
    }

    public function cerrar(Request $request, Derivacion $derivacion): RedirectResponse
    {
        abort_unless($derivacion->puedeCerrar($request->user()), 403);
        $request->validate(['resolucion' => 'required|max:2000']);

        $derivacion->cerrar($request->user(), $request->resolucion);

        return back()->with('ok', "Proveído {$derivacion->nro_proveido} cerrado");
    }

    /** Especialistas activos: técnicos con especialidad. Sirve como consulta de User o como filtro de la regla exists. */
    private function especialistas($q = null)
    {
        return ($q ?? User::query())->where('activo', true)->where('rol', 'tecnico')->whereNotNull('especialidad');
    }
}
