@php
    $iniciales = fn ($n) => collect(explode(' ', $n))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
    $colorRol = ['jefe' => 'border-brand-200 bg-brand-50 text-brand-700', 'mesa' => 'border-info-100 bg-info-50 text-info-700', 'tecnico' => 'border-ok-200 bg-ok-50 text-ok-700', 'usuario' => ''];
@endphp
<x-layout titulo="Usuarios">
    <x-cabecera titulo="Usuarios" subtitulo="Todos los que entraron con su correo UNIQ. Por defecto son usuarios que piden soporte; aquí les asignas otro rol.">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-usuario').showModal()"><i class="ti ti-user-plus"></i>Registrar de antemano</button>
    </x-cabecera>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-neutral-100 px-4 py-2.5">
            <div class="flex flex-wrap rounded-lg bg-neutral-100 p-0.5 text-sm">
                @foreach (['' => 'Todos'] + \App\Models\User::ROLES as $k => $v)
                    <a href="?{{ http_build_query(['rol' => $k, 'q' => request('q')]) }}" @class([
                        'rounded-md px-3 py-1 font-medium transition',
                        'bg-white text-neutral-900 shadow-sm' => request('rol', '') === $k,
                        'text-neutral-500 hover:text-neutral-800' => request('rol', '') !== $k,
                    ])>{{ $v }} <span class="text-xs muted">{{ $k === '' ? $roles->sum() : ($roles[$k] ?? 0) }}</span></a>
                @endforeach
            </div>
            <form class="ml-auto flex gap-2">
                <input type="hidden" name="rol" value="{{ request('rol') }}">
                <div class="relative">
                    <i class="ti ti-search absolute top-1/2 left-3 -translate-y-1/2 muted"></i>
                    <input name="q" value="{{ request('q') }}" class="input !w-64 !pl-9" placeholder="Nombre o correo" aria-label="Buscar usuario">
                </div>
                <button class="btn">Buscar</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="tabla min-w-[900px]">
                <thead><tr><th>Usuario</th><th>Rol</th><th>Especialidad</th><th>Sede y área</th><th class="text-right">Atendidos</th><th>Último ingreso</th><th class="w-28"></th></tr></thead>
                <tbody>
                    @forelse ($usuarios as $u)
                        <tr @class(['opacity-55' => ! $u->activo])>
                            <td>
                                <a href="{{ route('usuarios.show', $u) }}" class="flex items-center gap-2.5">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ $iniciales($u->name) }}</span>
                                    <span><span class="block font-medium text-neutral-900 hover:text-brand-700">{{ $u->name }}</span><span class="block text-xs muted">{{ $u->email }}</span></span>
                                </a>
                            </td>
                            <td><span class="badge {{ $colorRol[$u->rol] }}">{{ \App\Models\User::ROLES[$u->rol] }}</span>@unless ($u->activo)<span class="badge ml-1 border-danger-200 bg-danger-50 text-danger-700">Inactivo</span>@endunless</td>
                            <td>@if ($u->especialidad)<x-etiqueta :esp="$u->especialidad" />@else<span class="muted">—</span>@endif</td>
                            <td class="text-xs">@if ($u->solicitante){{ $u->solicitante->area->sede->nombre }} · {{ $u->solicitante->area->nombre }}@else<span class="muted">—</span>@endif</td>
                            <td class="text-right tabular-nums">{{ $u->esPersonal() ? $u->atendidos : '—' }}</td>
                            <td class="whitespace-nowrap text-xs muted">{{ $u->ultimo_ingreso?->diffForHumans() ?? 'Nunca' }}</td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('usuarios.show', $u) }}" class="btn btn-sm"><i class="ti ti-id-badge-2"></i>Perfil</a>
                                    @if ($u->activo && ! $u->is(auth()->user()))
                                        <form method="POST" action="{{ route('usuarios.impersonar', $u) }}"
                                              data-confirmar="¿Entrar al sistema como {{ $u->name }}? Verás lo mismo que ve esta persona hasta que pulses «Volver a mi cuenta»." data-confirmar-boton="Entrar como {{ $u->name }}">
                                            @csrf
                                            <button class="btn btn-sm" title="Entrar como {{ $u->name }}" aria-label="Entrar como {{ $u->name }}"><i class="ti ti-eye"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center muted">Sin usuarios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :items="$usuarios" />
    </div>

    <x-modal id="modal-usuario" titulo="Registrar a alguien de antemano">
        <form method="POST" action="{{ route('usuarios.store') }}" class="space-y-4">
            @csrf
            <p class="text-sm muted">Útil para dar el rol de técnico o mesa antes de que la persona entre por primera vez. No necesita contraseña: ingresará con Google.</p>
            <div><label class="label" for="n-name">Nombre</label><input id="n-name" name="name" class="input" required></div>
            <div><label class="label" for="n-email">Correo institucional</label><input id="n-email" name="email" type="email" class="input" required placeholder="nombre{{ '@'.config('oti.dominio') }}"></div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="n-rol">Rol</label>
                    <select id="n-rol" name="rol" class="input">
                        @foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected($k === 'tecnico')>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="n-esp">Especialidad</label>
                    <select id="n-esp" name="especialidad" class="input">
                        <option value="">Ninguna</option>
                        @foreach (\App\Models\Categoria::ESPECIALIDADES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
            </div>
            <input type="hidden" name="activo" value="1">
            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancelar</button>
                <button class="btn btn-primary"><i class="ti ti-check"></i>Registrar</button>
            </div>
        </form>
    </x-modal>
</x-layout>
