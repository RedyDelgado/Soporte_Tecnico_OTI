@php
    $yo = $u->is(auth()->user());
    $dur = fn ($m) => $m === null ? '—' : ($m < 60 ? $m.' min' : ($m < 1440 ? round($m / 60, 1).' h' : round($m / 1440, 1).' días'));
@endphp
<x-layout :titulo="$u->name">
    <x-cabecera :titulo="$u->name" :subtitulo="$u->email" :volver="route('usuarios.index')">
        @if ($u->activo && ! $yo)
            <form method="POST" action="{{ route('usuarios.impersonar', $u) }}"
                  data-confirmar="¿Entrar al sistema como {{ $u->name }}?" data-confirmar-boton="Entrar como {{ $u->name }}">
                @csrf
                <button class="btn"><i class="ti ti-eye"></i>Ver como {{ $u->name }}</button>
            </form>
        @endif
    </x-cabecera>

    <div class="grid gap-4 xl:grid-cols-[340px_1fr]">
        {{-- Ficha y rol --}}
        <div class="space-y-4">
            <div class="card p-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-12 place-items-center rounded-full bg-brand-600 font-semibold text-white">{{ collect(explode(' ', $u->name))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}</span>
                    <div>
                        <div class="font-semibold text-neutral-900">{{ $u->name }}</div>
                        <div class="flex gap-1.5"><span class="badge">{{ \App\Models\User::ROLES[$u->rol] }}</span>@if ($u->especialidad)<x-etiqueta :esp="$u->especialidad" />@endif</div>
                    </div>
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="muted">Sede y área</dt><dd class="text-right">{{ $persona ? $persona->area->sede->nombre.' · '.$persona->area->nombre : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="muted">Celular</dt><dd>{{ $persona?->telefono ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="muted">Ingreso con Google</dt><dd>{{ $u->google_id ? 'Sí' : 'Aún no' }}</dd></div>
                    <div class="flex justify-between"><dt class="muted">Último ingreso</dt><dd>{{ $u->ultimo_ingreso?->format('d/m/Y H:i') ?? 'Nunca' }}</dd></div>
                    <div class="flex justify-between"><dt class="muted">Registrado</dt><dd>{{ $u->created_at->format('d/m/Y') }}</dd></div>
                </dl>
            </div>

            <form method="POST" action="{{ route('usuarios.update', $u) }}" class="card"
                  data-confirmar="¿Guardar el rol y los datos de {{ $u->name }}?" data-confirmar-boton="Guardar">
                @csrf @method('PUT')
                <div class="card-header"><i class="ti ti-shield-check text-brand-600"></i><span class="card-title">Rol y acceso</span></div>
                <div class="space-y-3 p-4">
                    <div><label class="label" for="name">Nombre</label><input id="name" name="name" value="{{ $u->name }}" class="input" required></div>
                    <div>
                        <label class="label" for="rol">Rol</label>
                        <select id="rol" name="rol" class="input" @disabled($yo)>
                            @foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected($u->rol === $k)>{{ $v }}</option>@endforeach
                        </select>
                        @if ($yo)<input type="hidden" name="rol" value="{{ $u->rol }}"><p class="hint">No puedes cambiar tu propio rol.</p>@endif
                    </div>
                    <div>
                        <label class="label" for="especialidad">Especialidad</label>
                        <select id="especialidad" name="especialidad" class="input">
                            <option value="">Ninguna</option>
                            @foreach (\App\Models\Categoria::ESPECIALIDADES as $k => $v)<option value="{{ $k }}" @selected($u->especialidad === $k)>{{ $v }}</option>@endforeach
                        </select>
                        <p class="hint">Técnico de soporte, especialista en redes, en servidores o en web.</p>
                    </div>
                    <label class="flex items-center gap-2"><input type="checkbox" name="activo" value="1" @checked($u->activo) @disabled($yo) class="size-4 accent-brand-600">Cuenta activa</label>
                </div>
                <div class="flex justify-end rounded-b-xl border-t border-neutral-100 bg-neutral-50/60 px-4 py-2.5">
                    <button class="btn btn-primary"><i class="ti ti-device-floppy"></i>Guardar</button>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            {{-- Como técnico --}}
            @if ($u->esPersonal())
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    @foreach ([['Abiertos', $trabajo['abiertos']], ['Resueltos', $trabajo['resueltos']], ['En plazo', $trabajo['en_plazo'] === null ? '—' : $trabajo['en_plazo'].'%'], ['Solución prom.', $dur($trabajo['minutos'])], ['Reabiertos', $trabajo['reabiertos']]] as [$k, $v])
                        <div class="card p-3"><div class="text-xs muted">{{ $k }}</div><div class="text-xl font-semibold tabular-nums text-neutral-900">{{ $v }}</div></div>
                    @endforeach
                </div>
                <div class="card overflow-hidden">
                    <div class="card-header"><i class="ti ti-tool text-brand-600"></i><span class="card-title">Últimos tickets atendidos</span></div>
                    <div class="overflow-x-auto">
                        <table class="tabla">
                            <thead><tr><th>Código</th><th>Pedido</th><th>Área</th><th>Estado</th><th>Fecha</th></tr></thead>
                            <tbody>
                                @forelse ($ultimosAtendidos as $t)
                                    <tr>
                                        <td class="whitespace-nowrap"><a href="{{ route('tickets.show', $t) }}" class="link font-mono">{{ $t->codigo }}</a></td>
                                        <td class="max-w-80 truncate">{{ $t->descripcion }}</td>
                                        <td class="muted">{{ $t->area->nombre }}</td>
                                        <td><x-etiqueta :estado="$t->estado" /></td>
                                        <td class="whitespace-nowrap muted">{{ $t->created_at->format('d/m/Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-6 text-center muted">Sin tickets asignados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Como solicitante --}}
            <div class="card overflow-hidden">
                <div class="card-header"><i class="ti ti-message-report text-brand-600"></i><span class="card-title">Pedidos de soporte que hizo</span>
                    @if ($persona)<a href="{{ route('reportes.detalle', ['solicitante' => $persona->id, 'desde' => '2020-01-01']) }}" class="link ml-auto text-xs">Ver en reportes</a>@endif
                </div>
                @if ($pedidos && $pedidos->total())
                    <div class="overflow-x-auto">
                        <table class="tabla">
                            <thead><tr><th>Código</th><th>Pedido</th><th>Área (en ese momento)</th><th>Problema</th><th>Estado</th><th>Fecha</th></tr></thead>
                            <tbody>
                                @foreach ($pedidos as $t)
                                    <tr>
                                        <td class="whitespace-nowrap"><a href="{{ route('tickets.show', $t) }}" class="link font-mono">{{ $t->codigo }}</a></td>
                                        <td class="max-w-72 truncate">{{ $t->descripcion }}</td>
                                        <td>{{ $t->area->nombre }}</td>
                                        <td class="muted">{{ $t->categoria->nombre }}</td>
                                        <td><x-etiqueta :estado="$t->estado" /></td>
                                        <td class="whitespace-nowrap muted">{{ $t->created_at->format('d/m/Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-paginacion :items="$pedidos" />
                @else
                    <p class="px-4 py-6 text-center muted">No ha pedido soporte.</p>
                @endif
            </div>
        </div>
    </div>
</x-layout>
