@php $puedeAsignar = auth()->user()->puedeAsignar(); @endphp
<div data-max="{{ $resumen['max'] }}">
    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Por asignar', $resumen['pend'], 'ti-inbox', 'bg-brand-50 text-brand-600'],
            ['Vencidos', $resumen['rojo'], 'ti-alarm', 'bg-danger-50 text-danger-600'],
            ['En curso', $resumen['cur'], 'ti-progress', 'bg-info-50 text-info-600'],
            ['Resueltos hoy', $resumen['hoy'], 'ti-circle-check', 'bg-ok-50 text-ok-600'],
        ] as [$label, $valor, $icono, $color])
            <div class="card flex items-center gap-4 p-4">
                <div class="grid size-11 place-items-center rounded-lg {{ $color }}"><i class="ti {{ $icono }} text-xl"></i></div>
                <div>
                    <div class="text-xs font-medium muted">{{ $label }}</div>
                    <div class="text-2xl font-semibold tabular-nums text-neutral-900">{{ $valor }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-neutral-100 px-4 py-3">
            <div class="flex rounded-lg bg-neutral-100 p-0.5 text-sm">
                @foreach (array_filter(['val' => $puedeAsignar || $resumen['val'] ? 'Por validar' : null, 'pend' => 'Por asignar', 'cur' => 'En curso', 'all' => 'Todos']) as $k => $txt)
                    <a href="?{{ http_build_query(['f' => $k, 'sede' => request('sede'), 'por_pagina' => request('por_pagina')]) }}" @class([
                        'rounded-md px-3 py-1.5 font-medium transition',
                        'bg-white text-neutral-900 shadow-sm' => $filtro === $k,
                        'text-neutral-500 hover:text-neutral-800' => $filtro !== $k,
                    ])>{{ $txt }}@if ($k === 'val' && $resumen['val'])<span class="ml-1.5 rounded-full bg-warn-500 px-1.5 text-[11px] font-semibold text-white">{{ $resumen['val'] }}</span>@endif</a>
                @endforeach
            </div>
            <form class="ml-auto">
                <input type="hidden" name="f" value="{{ $filtro }}">
                <select name="sede" class="input !w-auto !py-1.5" onchange="this.form.requestSubmit()" aria-label="Filtrar por sede">
                    <option value="">Todas las sedes</option>
                    @foreach (\App\Models\Sede::orderBy('nombre')->get() as $s)
                        <option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="tabla min-w-[960px]">
                <thead>
                    <tr>
                        <th class="w-8"></th><th>Código</th><th>Pedido</th><th>Sede y área</th><th>Solicitante</th><th>Fechas</th>
                        <th class="w-60">{{ $filtro === 'val' ? 'Validación' : ($filtro === 'pend' && $puedeAsignar ? 'Asignar a' : 'Técnico') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $t)
                        <tr data-id="{{ $t->id }}" @class(['!bg-brand-50/60' => $t->created_at->gt(now()->subMinute())])>
                            <td><x-semaforo :ticket="$t" /></td>
                            <td class="whitespace-nowrap"><a href="{{ route('tickets.show', $t) }}" class="link font-mono">{{ $t->codigo }}</a></td>
                            <td class="max-w-80">
                                <a href="{{ route('tickets.show', $t) }}" class="block truncate font-medium text-neutral-900 hover:text-brand-700">{{ $t->descripcion }}</a>
                                <div class="mt-1 flex items-center gap-2 text-xs muted">
                                    <x-etiqueta :esp="$t->categoria->especialidad" />
                                    <span class="truncate">{{ $t->categoria->nombre }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="font-medium text-neutral-800">{{ $t->sede->nombre }}</div>
                                <div class="text-xs muted">{{ $t->area->nombre }}</div>
                            </td>
                            <td class="max-w-44">
                                <div class="truncate">{{ $t->solicitante->nombre }}</div>
                                @if ($t->estado === 'por_validar')<div class="text-xs muted">{{ $t->solicitante->telefono }}</div>@endif
                            </td>
                            <td class="text-xs whitespace-nowrap tabular-nums">
                                <div title="Registrado {{ $t->created_at->diffForHumans() }}"><span class="muted">Registro</span> {{ $t->created_at->format('d/m H:i') }}</div>
                                @if ($t->asignado_at)
                                    <div class="mt-0.5" title="Asignado {{ $t->asignado_at->diffForHumans() }}"><span class="muted">Asignado</span> {{ $t->asignado_at->format('d/m H:i') }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($t->estado === 'por_validar' && $puedeAsignar)
                                    <div class="flex gap-1.5">
                                        <form method="POST" action="{{ route('tickets.validar', $t) }}" data-confirmar="¿Validar el pedido {{ $t->codigo }} de {{ $t->solicitante->nombre }}? Pasará a la cola por asignar." data-confirmar-boton="Validar">
                                            @csrf
                                            <button class="btn btn-sm !border-ok-600 !bg-ok-600 !text-white hover:!bg-ok-700"><i class="ti ti-check"></i>Validar</button>
                                        </form>
                                        <button type="button" class="btn btn-sm" onclick="rechazarPedido('{{ route('tickets.rechazar', $t) }}', '{{ $t->codigo }}')"><i class="ti ti-x"></i>Rechazar</button>
                                    </div>
                                @elseif (in_array($t->estado, \App\Models\Ticket::ABIERTOS) && $puedeAsignar)
                                    @php $accion = $t->tecnico ? 'Reasignar' : 'Asignar'; @endphp
                                    <form method="POST" action="{{ route('tickets.asignar', $t) }}" data-confirmar="¿{{ $accion }} {{ $t->codigo }} a {opcion}?" data-confirmar-boton="{{ $accion }}" data-resetear>
                                        @csrf
                                        <select name="tecnico_id" class="input !py-1.5" onchange="this.form.requestSubmit()" aria-label="{{ $accion }} {{ $t->codigo }}">
                                            <option value="">Elegir técnico…</option>
                                            @foreach ($tecnicos->sortBy(fn ($u) => $u->especialidad === $t->categoria->especialidad ? 0 : 1) as $u)
                                                <option value="{{ $u->id }}" @selected($t->tecnico_id === $u->id)>{{ $u->name }}{{ $u->especialidad ? ' ('.$u->especialidad.')' : '' }} · {{ $u->abiertos }} en cola{{ $u->ocupado ? ' · atendiendo ahora' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    @if ($t->tecnico)
                                        <div class="mt-1 flex items-center gap-1.5">
                                            <x-etiqueta :estado="$t->estado" />
                                            @if ($t->apoyos->isNotEmpty())<span class="badge !px-1.5" title="Apoyo: {{ $t->apoyos->pluck('name')->implode(', ') }}">+{{ $t->apoyos->count() }} apoyo</span>@endif
                                        </div>
                                    @endif
                                @elseif ($t->tecnico)
                                    <div class="flex items-center gap-2">
                                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ mb_substr($t->tecnico->name, 0, 1) }}</span>
                                        <span class="truncate">{{ $t->tecnico->name }}</span>@if ($t->apoyos->isNotEmpty())<span class="badge !px-1.5" title="Apoyo: {{ $t->apoyos->pluck('name')->implode(', ') }}">+{{ $t->apoyos->count() }}</span>@endif
                                        <x-etiqueta :estado="$t->estado" class="ml-auto" />
                                    </div>
                                @else
                                    <x-etiqueta :estado="$t->estado" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <div class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-ok-50"><i class="ti ti-mood-happy text-2xl text-ok-600"></i></div>
                                <p class="font-medium text-neutral-900">{{ $filtro === 'pend' ? 'Todo asignado' : 'Sin tickets' }}</p>
                                <p class="text-sm muted">{{ $filtro === 'pend' ? 'Los pedidos nuevos aparecerán aquí solos.' : 'No hay tickets en esta vista.' }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :items="$tickets" />
    </div>
</div>
