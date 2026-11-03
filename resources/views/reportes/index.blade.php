@php
    $dur = fn ($m) => $m === null ? '—' : ($m < 60 ? round($m).' min' : ($m < 1440 ? round($m / 60, 1).' h' : round($m / 1440, 1).' días'));
    $filtros = ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'sede' => request('sede')];
    $csv = fn ($tipo) => route('reportes', $filtros + ['csv' => $tipo]);
    $detalle = fn ($extra) => route('reportes.detalle', $filtros + $extra);
    $pct = fn ($a, $b) => $b ? round($a * 100 / $b) : 0;
    $maxArea = max(1, $areas->max('total') ?? 1);
    $maxProb = max(1, $problemas->max('total') ?? 1);
@endphp
<x-layout titulo="Reportes">
    <x-cabecera titulo="Reportes" subtitulo="El avance del equipo, quién pide más soporte, qué se pide más y qué se repite.">
        <form class="flex flex-wrap items-end gap-2">
            <div><label class="label !text-xs" for="desde">Desde</label><input id="desde" type="date" name="desde" value="{{ $desde->toDateString() }}" class="input !py-1"></div>
            <div><label class="label !text-xs" for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="input !py-1"></div>
            <div>
                <label class="label !text-xs" for="sede">Sede</label>
                <select id="sede" name="sede" class="input !py-1">
                    <option value="">Todas</option>
                    @foreach ($sedes as $s)<option value="{{ $s->id }}" @selected(request('sede') == $s->id)>{{ $s->nombre }}</option>@endforeach
                </select>
            </div>
            <button class="btn"><i class="ti ti-filter"></i>Aplicar</button>
            <button type="button" class="btn" onclick="window.print()"><i class="ti ti-printer"></i>Imprimir</button>
        </form>
    </x-cabecera>

    {{-- Resumen --}}
    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        @foreach ([
            ['Pedidos', $resumen->total, 'ti-inbox'],
            ['Resueltos', $resumen->resueltos ?? 0, 'ti-circle-check'],
            ['Abiertos', $resumen->abiertos ?? 0, 'ti-progress'],
            ['En plazo', $pct($resumen->en_plazo, $resumen->resueltos).'%', 'ti-target'],
            ['Solución prom.', $dur($resumen->minutos), 'ti-clock'],
            ['Reabiertos', $resumen->reabiertos ?? 0, 'ti-refresh'],
            ['Personas', $resumen->personas, 'ti-users'],
            ['Áreas', $resumen->areas, 'ti-building'],
        ] as [$k, $v, $i])
            <div class="card p-3">
                <div class="flex items-center justify-between text-xs muted">{{ $k }}<i class="ti {{ $i }} text-brand-500"></i></div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-neutral-900">{{ $v }}</div>
            </div>
        @endforeach
    </div>

    {{-- Avance del equipo --}}
    <div class="card mb-4 overflow-hidden">
        <div class="card-header"><i class="ti ti-users-group text-brand-600"></i><span class="card-title">Avance del equipo técnico</span>
            <a href="{{ $csv('tecnicos') }}" class="btn btn-sm ml-auto"><i class="ti ti-file-spreadsheet"></i>Excel</a></div>
        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>#</th><th>Técnico</th><th>Resueltos</th><th class="text-right">Asignados</th><th class="text-right">Abiertos</th><th class="text-right">En plazo</th><th class="text-right">Reabiertos</th><th class="text-right">Solución</th></tr></thead>
                <tbody>
                    @forelse ($tecnicos as $r)
                        <tr @class(['bg-brand-50' => $r->tecnico_id == auth()->id()])>
                            <td class="muted">{{ $loop->iteration }}</td>
                            <td>
                                <a href="{{ $detalle(['tecnico' => $r->tecnico_id]) }}" class="link">{{ $r->name }}</a>
                                @if ($r->tecnico_id == auth()->id())<span class="ml-1 text-xs font-semibold text-brand-700">(tú)</span>@endif
                                @if ($r->especialidad)<div class="mt-0.5"><x-etiqueta :esp="$r->especialidad" /></div>@endif
                            </td>
                            <td class="w-40">
                                <div class="flex items-center gap-2"><span class="w-6 text-right font-semibold tabular-nums">{{ $r->resueltos }}</span>
                                    <div class="h-1.5 flex-1 rounded-full bg-neutral-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $pct($r->resueltos, $r->total) }}%"></div></div></div>
                            </td>
                            <td class="text-right tabular-nums">{{ $r->total }}</td>
                            <td class="text-right tabular-nums">{{ $r->abiertos }}</td>
                            <td class="text-right tabular-nums">{{ $pct($r->en_plazo, $r->resueltos) }}%</td>
                            <td @class(['text-right tabular-nums', 'font-semibold text-danger-600' => $r->reabiertos > 0])>{{ $r->reabiertos }}</td>
                            <td class="text-right whitespace-nowrap">{{ $dur($r->minutos) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center muted">Ningún pedido asignado a técnicos en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 2xl:grid-cols-2">
        {{-- Por área --}}
        <div class="card overflow-hidden">
            <div class="card-header"><i class="ti ti-building text-brand-600"></i><span class="card-title">Áreas que más piden soporte</span>
                <a href="{{ $csv('areas') }}" class="btn btn-sm ml-auto"><i class="ti ti-file-spreadsheet"></i>Excel</a></div>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>#</th><th>Área</th><th>Pedidos</th><th class="text-right">Abiertos</th><th class="text-right">Personas</th><th class="text-right">Solución</th><th>Último</th></tr></thead>
                    <tbody>
                        @forelse ($areas as $r)
                            <tr>
                                <td class="muted">{{ $loop->iteration }}</td>
                                <td><a href="{{ $detalle(['area' => $r->area_id]) }}" class="link">{{ $r->area }}</a><div class="text-xs muted">{{ $r->sede }}</div></td>
                                <td class="w-40">
                                    <div class="flex items-center gap-2"><span class="w-6 text-right font-semibold tabular-nums">{{ $r->total }}</span>
                                        <div class="h-1.5 flex-1 rounded-full bg-neutral-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $pct($r->total, $maxArea) }}%"></div></div></div>
                                </td>
                                <td class="text-right tabular-nums">{{ $r->abiertos }}</td>
                                <td class="text-right tabular-nums">{{ $r->personas }}</td>
                                <td class="text-right whitespace-nowrap">{{ $dur($r->minutos) }}</td>
                                <td class="whitespace-nowrap text-xs muted">{{ \Illuminate\Support\Carbon::parse($r->ultimo)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center muted">Sin pedidos en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Por persona --}}
        <div class="card overflow-hidden">
            <div class="card-header"><i class="ti ti-user text-brand-600"></i><span class="card-title">Personas que más piden soporte</span>
                <a href="{{ $csv('personas') }}" class="btn btn-sm ml-auto"><i class="ti ti-file-spreadsheet"></i>Excel</a></div>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>#</th><th>Persona</th><th class="text-right">Pedidos</th><th class="text-right">Abiertos</th><th>Problema más frecuente</th><th>Último</th></tr></thead>
                    <tbody>
                        @forelse ($personas as $r)
                            <tr>
                                <td class="muted">{{ $loop->iteration }}</td>
                                <td><a href="{{ $detalle(['solicitante' => $r->solicitante_id]) }}" class="link">{{ $r->nombre }}</a><div class="text-xs muted">{{ $r->area_actual }}{{ $r->email ? ' · '.$r->email : '' }}</div></td>
                                <td class="text-right font-semibold tabular-nums">{{ $r->total }}</td>
                                <td class="text-right tabular-nums">{{ $r->abiertos }}</td>
                                <td class="text-xs">{{ $frecuente[$r->solicitante_id] ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs muted">{{ \Illuminate\Support\Carbon::parse($r->ultimo)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center muted">Sin pedidos en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Top problemas --}}
        <div class="card overflow-hidden">
            <div class="card-header"><i class="ti ti-trophy text-brand-600"></i><span class="card-title">Top 10 de problemas</span>
                <a href="{{ $csv('problemas') }}" class="btn btn-sm ml-auto"><i class="ti ti-file-spreadsheet"></i>Excel</a></div>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>#</th><th>Problema</th><th>Pedidos</th><th class="text-right">% del total</th><th class="text-right">Remotos</th><th class="text-right">Reabiertos</th><th class="text-right">Solución</th></tr></thead>
                    <tbody>
                        @forelse ($problemas as $r)
                            <tr>
                                <td class="muted">{{ $loop->iteration }}</td>
                                <td><a href="{{ $detalle(['categoria' => $r->categoria_id]) }}" class="link">{{ $r->nombre }}</a><div class="mt-0.5"><x-etiqueta :esp="$r->especialidad" /></div></td>
                                <td class="w-40">
                                    <div class="flex items-center gap-2"><span class="w-6 text-right font-semibold tabular-nums">{{ $r->total }}</span>
                                        <div class="h-1.5 flex-1 rounded-full bg-neutral-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $pct($r->total, $maxProb) }}%"></div></div></div>
                                </td>
                                <td class="text-right tabular-nums">{{ $pct($r->total, $resumen->total) }}%</td>
                                <td class="text-right tabular-nums">{{ $r->remotos }}</td>
                                <td @class(['text-right tabular-nums', 'font-semibold text-danger-600' => $r->reabiertos > 0])>{{ $r->reabiertos }}</td>
                                <td class="text-right whitespace-nowrap">{{ $dur($r->minutos) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center muted">Sin pedidos en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recurrentes --}}
        <div class="card overflow-hidden">
            <div class="card-header"><i class="ti ti-repeat text-warn-500"></i><span class="card-title">Problemas que se repiten en la misma área</span>
                <a href="{{ $csv('recurrentes') }}" class="btn btn-sm ml-auto"><i class="ti ti-file-spreadsheet"></i>Excel</a></div>
            <p class="border-b border-neutral-100 px-4 py-2 text-xs muted">3 o más veces en el periodo: suele indicar un equipo que conviene reemplazar o capacitación pendiente.</p>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Área</th><th>Problema</th><th class="text-right">Veces</th><th>Último</th></tr></thead>
                    <tbody>
                        @forelse ($recurrentes as $r)
                            <tr>
                                <td><a href="{{ $detalle(['area' => $r->area_id, 'categoria' => $r->categoria_id]) }}" class="link">{{ $r->area }}</a><div class="text-xs muted">{{ $r->sede }}</div></td>
                                <td>{{ $r->problema }}</td>
                                <td class="text-right font-semibold tabular-nums text-warn-700">{{ $r->total }}</td>
                                <td class="whitespace-nowrap text-xs muted">{{ \Illuminate\Support\Carbon::parse($r->ultimo)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center muted">Ningún problema se repitió 3 veces en la misma área.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Sede y canal --}}
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @foreach (['Pedidos por sede' => $porSede, 'Por dónde llegan' => $porCanal->mapWithKeys(fn ($v, $k) => [\App\Models\Ticket::CANALES[$k] ?? $k => $v])] as $titulo => $serie)
            <div class="card p-4">
                <div class="card-title mb-3">{{ $titulo }}</div>
                @forelse ($serie as $nombre => $n)
                    <div class="mb-2">
                        <div class="mb-1 flex justify-between text-sm"><span>{{ $nombre }}</span><span class="tabular-nums"><b>{{ $n }}</b> <span class="muted">· {{ $pct($n, $resumen->total) }}%</span></span></div>
                        <div class="h-1.5 rounded-full bg-neutral-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $pct($n, $serie->max()) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm muted">Sin datos.</p>
                @endforelse
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-xs muted"><a href="{{ $detalle([]) }}" class="link">Ver la lista completa de pedidos del periodo</a> · Periodo: {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>
</x-layout>
