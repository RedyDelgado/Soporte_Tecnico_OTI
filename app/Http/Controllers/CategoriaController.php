<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        return view('categorias.index', [
            'categorias' => Categoria::with('creador')->withCount('tickets')
                ->when($request->query('q'), fn ($q, $t) => $q->where('nombre', 'like', "%$t%"))
                ->orderBy('especialidad')->orderBy('nombre')
                ->paginate($this->porPagina($request))->withQueryString(),
        ]);
    }

    /** Cualquier usuario puede crear una categoría al vuelo desde un formulario. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|max:80|unique:categorias,nombre',
            'especialidad' => 'required|in:'.implode(',', array_keys(Categoria::ESPECIALIDADES)),
            'prioridad' => 'nullable|in:alta,media,baja',
        ]);

        $cat = Categoria::create(array_filter($data) + ['prioridad' => 'media', 'creada_por' => $request->user()->id]);

        return $request->expectsJson()
            ? response()->json($cat->only('id', 'nombre', 'especialidad', 'prioridad'))
            : back()->with('ok', "Categoría {$cat->nombre} creada");
    }

    public function update(Request $request, Categoria $categoria)
    {
        $categoria->update($request->validate([
            'nombre' => 'required|max:80|unique:categorias,nombre,'.$categoria->id,
            'especialidad' => 'required|in:'.implode(',', array_keys(Categoria::ESPECIALIDADES)),
            'prioridad' => 'required|in:alta,media,baja',
            'activa' => 'boolean',
        ]) + ['activa' => false]);

        return back()->with('ok', 'Categoría actualizada');
    }
}
