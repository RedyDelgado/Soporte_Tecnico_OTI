<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $roles = User::selectRaw('rol, count(*) as total')->groupBy('rol')->pluck('total', 'rol');

        return view('usuarios.index', [
            'roles' => $roles,
            'usuarios' => User::with('solicitante.area.sede')
                ->withCount(['tickets as atendidos' => fn ($q) => $q->where('estado', 'resuelto')])
                ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")))
                ->when($request->query('rol'), fn ($q, $r) => $q->where('rol', $r))
                ->orderByDesc('activo')->orderByRaw("FIELD(rol, 'jefe', 'mesa', 'tecnico', 'usuario')")->orderBy('name')
                ->paginate($this->porPagina($request))->withQueryString(),
        ]);
    }

    /** Perfil completo: datos, sus pedidos (como solicitante) y su trabajo (como técnico). */
    public function show(Request $request, User $usuario)
    {
        $persona = $usuario->solicitante?->load('area.sede');
        $atendidos = $usuario->tickets()->where('estado', 'resuelto')->get(['created_at', 'resuelto_at', 'vence_at', 'reaperturas']);

        return view('usuarios.show', [
            'u' => $usuario,
            'persona' => $persona,
            'pedidos' => $persona
                ? $persona->tickets()->with('categoria', 'tecnico', 'area')->latest('id')->paginate($this->porPagina($request), ['*'], 'pedidos')->withQueryString()
                : null,
            'trabajo' => [
                'abiertos' => $usuario->tickets()->abiertos()->count(),
                'resueltos' => $atendidos->count(),
                'en_plazo' => $atendidos->count() ? (int) round($atendidos->filter(fn ($t) => $t->resuelto_at <= $t->vence_at)->count() * 100 / $atendidos->count()) : null,
                'minutos' => $atendidos->count() ? (int) round($atendidos->avg(fn ($t) => $t->created_at->diffInMinutes($t->resuelto_at))) : null,
                'reabiertos' => $atendidos->where('reaperturas', '>', 0)->count(),
            ],
            'ultimosAtendidos' => $usuario->tickets()->with('area', 'categoria')->latest('id')->limit(10)->get(),
        ]);
    }

    /** Registrar de antemano a alguien con su correo UNIQ y su rol; al entrar con Google ya tendrá ese rol. */
    public function store(Request $request)
    {
        $data = $this->validar($request, null);
        User::create($data + ['password' => Str::random(40)]);

        return back()->with('ok', "{$data['name']} registrado como ".User::ROLES[$data['rol']]);
    }

    public function update(Request $request, User $usuario)
    {
        $data = $this->validar($request, $usuario) + ['activo' => false];
        // El jefe no puede quitarse su propio rol ni desactivarse: quedaría fuera del sistema.
        if ($usuario->is($request->user())) {
            unset($data['rol'], $data['activo']);
        }
        $usuario->update($data);

        return back()->with('ok', 'Usuario actualizado');
    }

    /** El jefe entra como otro usuario para ver lo que ve; la sesión recuerda quién es en realidad. */
    public function impersonar(Request $request, User $usuario)
    {
        abort_if($request->session()->has('impersonador') || $usuario->is($request->user()) || ! $usuario->activo, 403);

        $request->session()->put('impersonador', $request->user()->id);
        Auth::login($usuario);

        return redirect($usuario->inicio());
    }

    public function dejarDeImpersonar(Request $request)
    {
        $id = $request->session()->pull('impersonador');
        abort_unless($id, 403);
        Auth::loginUsingId($id);

        return redirect()->route('usuarios.index');
    }

    private function validar(Request $request, ?User $usuario): array
    {
        return $request->validate([
            'name' => 'required|max:80',
            // Correo institucional; el de un usuario existente no se cambia (es su identidad en Google).
            'email' => $usuario ? 'prohibited' : ['required', 'email', 'ends_with:@'.config('oti.dominio'), Rule::unique('users')],
            'rol' => 'required|in:'.implode(',', array_keys(User::ROLES)),
            'especialidad' => 'nullable|in:'.implode(',', array_keys(Categoria::ESPECIALIDADES)),
            'activo' => 'boolean',
        ]);
    }
}
