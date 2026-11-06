<?php

namespace App\Http\Controllers;

use App\Models\Derivacion;
use App\Models\User;
use App\Notifications\AvisoDerivacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'documento' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);
        if ($request->hasFile('documento')) {
            $data['documento'] = $request->file('documento')->store('derivaciones', 'local');
        }

        $derivacion = Derivacion::create($data + ['asignado_por' => $request->user()->id]);

        try {
            $derivacion->especialista->notify(new AvisoDerivacion($derivacion));
        } catch (\Throwable $e) {
            report($e); // un fallo del push no debe impedir guardar la derivación
        }

        return back()->with('ok', "Proveído {$derivacion->nro_proveido} derivado a {$derivacion->especialista->name}");
    }

    /** La mesa o el administrador corrigen la derivación en cualquier estado; si ya está cerrada, también su resolución. */
    public function update(Request $request, Derivacion $derivacion): RedirectResponse
    {
        $data = $request->validate([
            'asignado_a' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $this->especialistas($q))],
            'nro_proveido' => 'required|max:50',
            'descripcion' => 'required|max:2000',
            'documento' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ] + ($derivacion->estado === 'cerrada' ? ['resolucion' => 'required|max:2000'] : []));

        if ($request->hasFile('documento')) {
            $anterior = $derivacion->documento;
            $data['documento'] = $request->file('documento')->store('derivaciones', 'local');
        } else {
            unset($data['documento']);
        }

        $especialistaAnterior = $derivacion->asignado_a;
        $derivacion->forceFill($data)->save();
        if (isset($anterior)) {
            Storage::disk('local')->delete($anterior);
        }

        if ($derivacion->estado === 'abierta' && $derivacion->asignado_a !== $especialistaAnterior) {
            try {
                $derivacion->load('especialista')->especialista->notify(new AvisoDerivacion($derivacion));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('ok', "Proveído {$derivacion->nro_proveido} actualizado");
    }

    public function cerrar(Request $request, Derivacion $derivacion): RedirectResponse
    {
        abort_unless($derivacion->puedeCerrar($request->user()), 403);
        $request->validate(['resolucion' => 'required|max:2000']);

        $derivacion->cerrar($request->user(), $request->resolucion);

        return back()->with('ok', "Proveído {$derivacion->nro_proveido} cerrado");
    }

    /** El escaneo se sirve desde el disco privado solo a quien puede ver la derivación. */
    public function documento(Request $request, Derivacion $derivacion): StreamedResponse
    {
        abort_unless($derivacion->puedeVer($request->user()), 403);
        abort_unless($derivacion->documento && Storage::disk('local')->exists($derivacion->documento), 404);

        $extension = pathinfo($derivacion->documento, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($derivacion->documento, "Proveido-{$derivacion->nro_proveido}.{$extension}");
    }

    /** Especialistas activos: técnicos con especialidad. Sirve como consulta de User o como filtro de la regla exists. */
    private function especialistas($q = null)
    {
        return ($q ?? User::query())->where('activo', true)->where('rol', 'tecnico')->whereNotNull('especialidad');
    }
}
