<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Sede;
use App\Models\Solicitante;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DirectorioController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->query('q', ''));

        return view('directorio.index', [
            'q' => $q,
            'sedes' => Sede::with(['areas' => fn ($a) => $a->withCount('solicitantes', 'tickets')])->orderBy('nombre')->get(),
            'personas' => Solicitante::with('area.sede', 'tickets.area')
                ->when($q, fn ($w) => $w->where('nombre', 'like', "%$q%")->orWhere('telefono', 'like', "%$q%")
                    ->orWhereHas('area', fn ($a) => $a->where('nombre', 'like', "%$q%")))
                ->when($request->query('area'), fn ($w, $a) => $w->where('area_id', $a))
                ->orderBy('nombre')
                ->paginate($this->porPagina($request))->withQueryString(),
        ]);
    }

    /** Registrar a una persona de una oficina sin que haya pedido nada todavía. */
    public function crearPersona(Request $request)
    {
        $p = Solicitante::create($this->validarPersona($request, null));

        return back()->with('ok', "{$p->nombre} registrado en el directorio");
    }

    /** Editar persona o moverla de área: sus tickets anteriores conservan el área donde se pidieron. */
    public function persona(Request $request, Solicitante $solicitante)
    {
        $solicitante->update($this->validarPersona($request, $solicitante));

        return back()->with('ok', "{$solicitante->nombre} actualizado");
    }

    public function area(Request $request, Area $area)
    {
        $area->update($request->validate([
            'nombre' => ['required', 'max:80', Rule::unique('areas')->where('sede_id', $area->sede_id)->ignore($area)],
        ]));

        return back()->with('ok', 'Área actualizada');
    }

    private function validarPersona(Request $request, ?Solicitante $s): array
    {
        return $request->validate([
            'nombre' => 'required|max:120',
            'telefono' => ['nullable', 'max:20', Rule::unique('solicitantes')->ignore($s)],
            'area_id' => 'required|exists:areas,id',
        ]);
    }
}
